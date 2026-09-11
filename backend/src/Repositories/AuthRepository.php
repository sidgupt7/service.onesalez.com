<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Actor;

final class AuthRepository extends BaseRepository
{
    public function passwordHash(Actor $actor): ?string
    {
        $table = $actor->type === 'CLIENT_CONTACT' ? 'client_user_accounts' : 'employee_user_accounts';
        $foreignKey = $actor->type === 'CLIENT_CONTACT' ? 'contact_id' : 'employee_id';
        $account = $this->fetchOne(
            "SELECT password_hash FROM {$table} WHERE {$foreignKey}=:id AND is_deleted=FALSE",
            ['id' => $actor->id],
        );
        return isset($account['password_hash']) ? (string) $account['password_hash'] : null;
    }

    public function changePassword(Actor $actor, string $passwordHash): bool
    {
        $table = $actor->type === 'CLIENT_CONTACT' ? 'client_user_accounts' : 'employee_user_accounts';
        $foreignKey = $actor->type === 'CLIENT_CONTACT' ? 'contact_id' : 'employee_id';
        return $this->database->transaction(function () use ($actor, $passwordHash, $table, $foreignKey): bool {
            $updated = $this->execute(
                "UPDATE {$table} SET password_hash=:password, password_changed_at=NOW(6),
                 failed_login_attempts=0, locked_until=NULL, updated_by=:updated_by
                 WHERE {$foreignKey}=:id AND is_deleted=FALSE",
                ['password' => $passwordHash, 'updated_by' => $actor->identifier(), 'id' => $actor->id],
            ) === 1;
            if ($updated) {
                $this->execute(
                    'UPDATE auth_refresh_tokens SET revoked_at=NOW(6)
                     WHERE actor_type=:type AND actor_id=:id AND revoked_at IS NULL',
                    ['type' => $actor->type, 'id' => $actor->id],
                );
                $this->execute(
                    'UPDATE auth_trusted_devices SET revoked_at=NOW(6)
                     WHERE actor_type=:type AND actor_id=:id AND revoked_at IS NULL',
                    ['type' => $actor->type, 'id' => $actor->id],
                );
            }
            return $updated;
        });
    }

    public function findClientAccount(string $email): ?array
    {
        return $this->fetchOne(
            "SELECT a.account_id, a.password_hash, a.account_status, a.locked_until, c.contact_id AS actor_id,
                    c.client_id, c.email, c.full_name, r.role_code
             FROM client_user_accounts a
             JOIN client_contacts c ON c.contact_id=a.contact_id AND c.is_deleted=FALSE
             JOIN clients cl ON cl.client_id=c.client_id AND cl.is_deleted=FALSE AND cl.is_active=TRUE
             JOIN client_contact_roles r ON r.contact_role_id=c.contact_role_id AND r.is_deleted=FALSE
             WHERE LOWER(c.email)=LOWER(:email) AND c.is_active=TRUE AND a.is_deleted=FALSE",
            ['email' => $email],
        );
    }

    public function findEmployeeAccount(string $email): ?array
    {
        $account = $this->fetchOne(
            "SELECT a.account_id, a.password_hash, a.account_status, a.locked_until, e.employee_id AS actor_id,
                    e.official_email AS email, e.full_name
             FROM employee_user_accounts a
             JOIN employees e ON e.employee_id=a.employee_id AND e.is_deleted=FALSE
             WHERE LOWER(e.official_email)=LOWER(:email) AND a.is_deleted=FALSE",
            ['email' => $email],
        );
        if ($account === null) {
            return null;
        }
        $access = $this->employeeAccess((int) $account['actor_id']);
        $account['roles'] = $access['roles'];
        $account['permissions'] = $access['permissions'];
        return $account;
    }

    public function employeeAccess(int $employeeId): array
    {
        $rows = $this->fetchAll(
            "SELECT DISTINCT er.role_code, p.permission_code
             FROM employee_role_assignments era
             JOIN employee_roles er ON er.employee_role_id=era.employee_role_id AND er.is_deleted=FALSE
             LEFT JOIN employee_role_permissions erp ON erp.employee_role_id=er.employee_role_id
             LEFT JOIN permissions p ON p.permission_id=erp.permission_id
             WHERE era.employee_id=:id AND era.is_deleted=FALSE",
            ['id' => $employeeId],
        );
        return [
            'roles' => array_values(array_unique(array_column($rows, 'role_code'))),
            'permissions' => array_values(array_filter(array_unique(array_column($rows, 'permission_code')))),
        ];
    }

    public function createRefreshToken(Actor $actor, string $hash, string $expiresAt, string $ip, ?string $agent): int
    {
        return $this->insert('auth_refresh_tokens', [
            'actor_type' => $actor->type,
            'actor_id' => $actor->id,
            'token_hash' => $hash,
            'expires_at' => $expiresAt,
            'user_agent' => $agent,
            'ip_address' => $ip,
            'created_by' => $actor->identifier(),
        ]);
    }

    public function consumeRefreshToken(string $hash): ?array
    {
        return $this->fetchOne(
            'SELECT * FROM auth_refresh_tokens WHERE token_hash=:hash AND revoked_at IS NULL AND expires_at>NOW(6)',
            ['hash' => $hash],
        );
    }

    public function revokeRefreshToken(int $id, ?int $replacementId = null): void
    {
        $this->execute(
            'UPDATE auth_refresh_tokens SET revoked_at=NOW(6), replaced_by_token_id=:replacement WHERE refresh_token_id=:id AND revoked_at IS NULL',
            ['replacement' => $replacementId, 'id' => $id],
        );
    }

    public function createTrustedDevice(
        Actor $actor,
        string $deviceId,
        string $deviceName,
        string $tokenHash,
        string $pinHash,
        string $expiresAt,
        string $ip,
        ?string $agent,
    ): void {
        $this->insert('auth_trusted_devices', [
            'device_id' => $deviceId,
            'actor_type' => $actor->type,
            'actor_id' => $actor->id,
            'device_name' => $deviceName,
            'token_hash' => $tokenHash,
            'pin_hash' => $pinHash,
            'expires_at' => $expiresAt,
            'last_ip_address' => $ip,
            'user_agent' => $agent,
            'created_by' => $actor->identifier(),
        ]);
    }

    public function trustedDevice(string $deviceId, string $tokenHash): ?array
    {
        return $this->fetchOne(
            'SELECT * FROM auth_trusted_devices
             WHERE device_id=:device_id AND token_hash=:token_hash
               AND revoked_at IS NULL AND expires_at>NOW(6)',
            ['device_id' => $deviceId, 'token_hash' => $tokenHash],
        );
    }

    public function recordTrustedDeviceFailure(int $id): void
    {
        $this->execute(
            'UPDATE auth_trusted_devices SET failed_attempts=failed_attempts+1,
             locked_until=IF(failed_attempts+1>=5, DATE_ADD(NOW(6), INTERVAL 15 MINUTE), locked_until)
             WHERE trusted_device_id=:id AND revoked_at IS NULL',
            ['id' => $id],
        );
    }

    public function recordTrustedDeviceLogin(int $id, string $ip, ?string $agent): void
    {
        $this->execute(
            'UPDATE auth_trusted_devices SET failed_attempts=0, locked_until=NULL,
             last_used_at=NOW(6), last_ip_address=:ip, user_agent=:agent
             WHERE trusted_device_id=:id AND revoked_at IS NULL',
            ['id' => $id, 'ip' => $ip, 'agent' => $agent],
        );
    }

    public function revokeTrustedDevice(Actor $actor, string $deviceId): bool
    {
        return $this->execute(
            'UPDATE auth_trusted_devices SET revoked_at=NOW(6)
             WHERE device_id=:device_id AND actor_type=:actor_type AND actor_id=:actor_id AND revoked_at IS NULL',
            ['device_id' => $deviceId, 'actor_type' => $actor->type, 'actor_id' => $actor->id],
        ) === 1;
    }

    public function actor(string $type, int $id): ?Actor
    {
        if ($type === 'CLIENT_CONTACT') {
            $row = $this->fetchOne(
                "SELECT c.contact_id, c.client_id, c.email, c.full_name, r.role_code
                 FROM client_contacts c JOIN client_contact_roles r ON r.contact_role_id=c.contact_role_id
                 JOIN client_user_accounts a ON a.contact_id=c.contact_id
                 JOIN clients cl ON cl.client_id=c.client_id
                 WHERE c.contact_id=:id AND c.is_deleted=FALSE AND c.is_active=TRUE
                   AND cl.is_deleted=FALSE AND cl.is_active=TRUE AND a.account_status='ACTIVE' AND a.is_deleted=FALSE",
                ['id' => $id],
            );
            return $row === null ? null : new Actor(
                $id,
                $type,
                $row['email'],
                (int) $row['client_id'],
                [$row['role_code']],
                [],
                $row['full_name'],
            );
        }
        $row = $this->fetchOne(
            "SELECT e.official_email, e.full_name FROM employees e JOIN employee_user_accounts a ON a.employee_id=e.employee_id
             WHERE e.employee_id=:id AND e.is_deleted=FALSE AND a.account_status='ACTIVE' AND a.is_deleted=FALSE",
            ['id' => $id],
        );
        if ($row === null) {
            return null;
        }
        $access = $this->employeeAccess($id);
        return new Actor($id, $type, $row['official_email'], null, $access['roles'], $access['permissions'], $row['full_name']);
    }

    public function recordLogin(string $realm, int $accountId): void
    {
        $table = $realm === 'client' ? 'client_user_accounts' : 'employee_user_accounts';
        $this->execute("UPDATE {$table} SET last_login_at=NOW(6), failed_login_attempts=0, locked_until=NULL WHERE account_id=:id", ['id' => $accountId]);
    }

    public function recordFailedLogin(string $realm, int $accountId): void
    {
        $table = $realm === 'client' ? 'client_user_accounts' : 'employee_user_accounts';
        $this->execute(
            "UPDATE {$table} SET failed_login_attempts=failed_login_attempts+1,
             locked_until=IF(failed_login_attempts+1>=5, DATE_ADD(NOW(6), INTERVAL 15 MINUTE), locked_until)
             WHERE account_id=:id",
            ['id' => $accountId],
        );
    }

    public function createPasswordReset(string $email, string $realm, string $tokenHash, string $resetUrl): void
    {
        $table = $realm === 'client' ? 'client_user_accounts' : 'employee_user_accounts';
        $master = $realm === 'client' ? 'client_contacts' : 'employees';
        $foreignKey = $realm === 'client' ? 'contact_id' : 'employee_id';
        $emailColumn = $realm === 'client' ? 'email' : 'official_email';
        $account = $this->fetchOne(
            "SELECT a.account_id FROM {$table} a JOIN {$master} m ON m.{$foreignKey}=a.{$foreignKey}
             WHERE LOWER(m.{$emailColumn})=LOWER(:email) AND m.is_deleted=FALSE AND a.is_deleted=FALSE",
            ['email' => $email],
        );
        if ($account === null) {
            return;
        }
        $this->execute(
            "UPDATE {$table} SET password_reset_token_hash=:hash,
             password_reset_expires_at=DATE_ADD(NOW(6), INTERVAL 30 MINUTE) WHERE account_id=:id",
            ['hash' => $tokenHash, 'id' => $account['account_id']],
        );
        $this->insert('email_jobs', [
            'recipient_email' => $email,
            'subject' => 'Reset your ONESALEZ Service Tracker password',
            'body' => 'Use this secure link within 30 minutes: ' . $resetUrl,
            'created_by' => 'SYSTEM',
        ]);
    }

    public function resetPassword(string $realm, string $tokenHash, string $passwordHash): bool
    {
        $table = $realm === 'client' ? 'client_user_accounts' : 'employee_user_accounts';
        $foreignKey = $realm === 'client' ? 'contact_id' : 'employee_id';
        $account = $this->fetchOne(
            "SELECT {$foreignKey} AS actor_id FROM {$table}
             WHERE password_reset_token_hash=:token AND password_reset_expires_at>NOW(6) AND is_deleted=FALSE",
            ['token' => $tokenHash],
        );
        $updated = $this->execute(
            "UPDATE {$table} SET password_hash=:password, password_reset_token_hash=NULL,
             password_reset_expires_at=NULL, password_changed_at=NOW(6), failed_login_attempts=0,
             locked_until=NULL, account_status=IF(account_status='INVITED','ACTIVE',account_status)
             WHERE password_reset_token_hash=:token AND password_reset_expires_at>NOW(6) AND is_deleted=FALSE",
            ['password' => $passwordHash, 'token' => $tokenHash],
        ) === 1;
        if ($updated && $account !== null) {
            $this->execute(
                'UPDATE auth_trusted_devices SET revoked_at=NOW(6) WHERE actor_type=:type AND actor_id=:id AND revoked_at IS NULL',
                ['type' => $realm === 'client' ? 'CLIENT_CONTACT' : 'EMPLOYEE', 'id' => $account['actor_id']],
            );
        }
        return $updated;
    }
}
