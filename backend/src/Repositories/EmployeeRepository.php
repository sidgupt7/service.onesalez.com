<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Actor;
use App\Utils\Input;

final class EmployeeRepository extends BaseRepository
{
    public function paginate(array $query): array
    {
        $page = Input::positiveInt($query['page'] ?? null, 1);
        $limit = min(100, Input::positiveInt($query['limit'] ?? null, 20));
        $offset = ($page - 1) * $limit;
        $total = $this->fetchOne('SELECT COUNT(*) total FROM employees WHERE is_deleted=FALSE');
        $items = $this->fetchAll(
            "SELECT e.*, a.account_status, GROUP_CONCAT(er.role_code ORDER BY er.role_code) roles
             FROM employees e LEFT JOIN employee_user_accounts a
               ON a.employee_id=e.employee_id AND a.is_deleted=FALSE
             LEFT JOIN employee_role_assignments era
               ON era.employee_id=e.employee_id AND era.is_deleted=FALSE
             LEFT JOIN employee_roles er ON er.employee_role_id=era.employee_role_id AND er.is_deleted=FALSE
             WHERE e.is_deleted=FALSE GROUP BY e.employee_id ORDER BY e.full_name LIMIT {$limit} OFFSET {$offset}",
        );
        return ['items' => $items, 'total' => (int) ($total['total'] ?? 0), 'page' => $page, 'limit' => $limit];
    }

    public function find(int $id): ?array
    {
        return $this->fetchOne(
            "SELECT e.*, a.account_status, GROUP_CONCAT(er.role_code ORDER BY er.role_code) roles
             FROM employees e
             LEFT JOIN employee_user_accounts a ON a.employee_id=e.employee_id AND a.is_deleted=FALSE
             LEFT JOIN employee_role_assignments era ON era.employee_id=e.employee_id AND era.is_deleted=FALSE
             LEFT JOIN employee_roles er ON er.employee_role_id=era.employee_role_id AND er.is_deleted=FALSE
             WHERE e.employee_id=:id AND e.is_deleted=FALSE GROUP BY e.employee_id",
            ['id' => $id],
        );
    }

    public function emailInUse(string $email, ?int $excludeEmployeeId = null): bool
    {
        $sql = 'SELECT employee_id FROM employees WHERE LOWER(official_email)=LOWER(:email) AND is_deleted=FALSE';
        $parameters = ['email' => $email];
        if ($excludeEmployeeId !== null) {
            $sql .= ' AND employee_id<>:exclude_id';
            $parameters['exclude_id'] = $excludeEmployeeId;
        }
        return $this->fetchOne($sql . ' LIMIT 1', $parameters) !== null;
    }

    public function createEmployee(array $data, array $roles, string $password, Actor $actor): int
    {
        return $this->database->transaction(function () use ($data, $roles, $password, $actor): int {
            $employeeId = $this->insert('employees', $data + ['created_by' => $actor->identifier()]);
            $this->insert('employee_user_accounts', [
                'employee_id' => $employeeId,
                'password_hash' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
                'account_status' => 'ACTIVE',
                'password_changed_at' => gmdate('Y-m-d H:i:s'),
                'created_by' => $actor->identifier(),
            ]);
            foreach ($roles as $role) {
                $this->execute(
                    "INSERT INTO employee_role_assignments (employee_id, employee_role_id, created_by)
                     SELECT :employee, employee_role_id, :actor FROM employee_roles
                     WHERE role_code=:role AND is_deleted=FALSE",
                    ['employee' => $employeeId, 'actor' => $actor->identifier(), 'role' => $role],
                );
            }
            return $employeeId;
        });
    }

    public function createClientContact(array $data, string $password, Actor $actor): int
    {
        return $this->database->transaction(function () use ($data, $password, $actor): int {
            $contactId = $this->insert('client_contacts', $data + [
                'client_id' => $actor->clientId,
                'created_by' => $actor->identifier(),
            ]);
            $this->insert('client_user_accounts', [
                'contact_id' => $contactId,
                'password_hash' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
                'account_status' => 'ACTIVE',
                'password_changed_at' => gmdate('Y-m-d H:i:s'),
                'created_by' => $actor->identifier(),
            ]);
            return $contactId;
        });
    }

    public function update(int $id, array $data, string $actor): bool
    {
        return $this->updateById('employees', 'employee_id', $id, $data + ['updated_by' => $actor]);
    }

    public function updateEmployee(int $id, array $data, array $roles, string $actor): void
    {
        $this->database->transaction(function () use ($id, $data, $roles, $actor): void {
            $this->updateById('employees', 'employee_id', $id, $data + ['updated_by' => $actor]);
            $this->execute(
                'UPDATE employee_role_assignments SET is_deleted=TRUE, deleted_by=:actor, deleted_at=NOW(6), updated_by=:actor
                 WHERE employee_id=:id AND is_deleted=FALSE',
                ['actor' => $actor, 'id' => $id],
            );
            foreach ($roles as $role) {
                $existing = $this->fetchOne(
                    'SELECT era.employee_role_assignment_id FROM employee_role_assignments era
                     JOIN employee_roles er ON er.employee_role_id=era.employee_role_id
                     WHERE era.employee_id=:employee AND er.role_code=:role',
                    ['employee' => $id, 'role' => $role],
                );
                if ($existing === null) {
                    $this->execute(
                        "INSERT INTO employee_role_assignments (employee_id, employee_role_id, created_by)
                         SELECT :employee, employee_role_id, :actor FROM employee_roles
                         WHERE role_code=:role AND is_deleted=FALSE",
                        ['employee' => $id, 'actor' => $actor, 'role' => $role],
                    );
                } else {
                    $this->execute(
                        'UPDATE employee_role_assignments SET is_deleted=FALSE, deleted_by=NULL, deleted_at=NULL, updated_by=:actor
                         WHERE employee_role_assignment_id=:id',
                        ['actor' => $actor, 'id' => $existing['employee_role_assignment_id']],
                    );
                }
            }
        });
    }

    public function suspend(int $id, string $reason, string $actor): bool
    {
        return $this->database->transaction(function () use ($id, $reason, $actor): bool {
            $changed = $this->execute(
                "UPDATE employees SET employment_status='SUSPENDED', updated_by=:actor WHERE employee_id=:id AND is_deleted=FALSE",
                ['actor' => $actor, 'id' => $id],
            );
            $this->execute(
                "UPDATE employee_user_accounts SET account_status='SUSPENDED', suspended_by=:actor,
                 suspended_at=NOW(6), suspension_reason=:reason, updated_by=:actor
                 WHERE employee_id=:id AND is_deleted=FALSE",
                ['actor' => $actor, 'reason' => $reason, 'id' => $id],
            );
            $this->revokeSessions($id);
            return $changed === 1;
        });
    }

    public function reactivate(int $id, string $actor): bool
    {
        return $this->database->transaction(function () use ($id, $actor): bool {
            $changed = $this->execute(
                "UPDATE employees SET employment_status='ACTIVE', updated_by=:actor
                 WHERE employee_id=:id AND is_deleted=FALSE",
                ['actor' => $actor, 'id' => $id],
            );
            $this->execute(
                "UPDATE employee_user_accounts SET account_status='ACTIVE', suspended_by=NULL, suspended_at=NULL,
                 suspension_reason=NULL, failed_login_attempts=0, locked_until=NULL, updated_by=:actor
                 WHERE employee_id=:id AND is_deleted=FALSE",
                ['actor' => $actor, 'id' => $id],
            );
            return $changed === 1;
        });
    }

    public function setPassword(int $id, string $passwordHash, string $actor): bool
    {
        return $this->database->transaction(function () use ($id, $passwordHash, $actor): bool {
            $updated = $this->execute(
                'UPDATE employee_user_accounts SET password_hash=:password, password_changed_at=NOW(6),
                 failed_login_attempts=0, locked_until=NULL, updated_by=:actor
                 WHERE employee_id=:id AND is_deleted=FALSE',
                ['password' => $passwordHash, 'actor' => $actor, 'id' => $id],
            ) === 1;
            if ($updated) {
                $this->revokeSessions($id);
            }
            return $updated;
        });
    }

    public function softDelete(int $id, string $actor): bool
    {
        return $this->database->transaction(function () use ($id, $actor): bool {
            $updated = $this->execute(
                "UPDATE employees SET employment_status='LEFT', is_deleted=TRUE, deleted_by=:actor,
                 deleted_at=NOW(6), updated_by=:actor WHERE employee_id=:id AND is_deleted=FALSE",
                ['actor' => $actor, 'id' => $id],
            ) === 1;
            if (!$updated) {
                return false;
            }
            $this->execute(
                "UPDATE employee_user_accounts SET account_status='SUSPENDED', is_deleted=TRUE,
                 deleted_by=:actor, deleted_at=NOW(6), suspended_by=:actor, suspended_at=NOW(6),
                 suspension_reason='EMPLOYEE DELETED', updated_by=:actor
                 WHERE employee_id=:id AND is_deleted=FALSE",
                ['actor' => $actor, 'id' => $id],
            );
            $this->execute(
                'UPDATE employee_role_assignments SET is_deleted=TRUE, deleted_by=:actor, deleted_at=NOW(6), updated_by=:actor
                 WHERE employee_id=:id AND is_deleted=FALSE',
                ['actor' => $actor, 'id' => $id],
            );
            $this->revokeSessions($id);
            return true;
        });
    }

    public function activeSystemAdministratorCount(): int
    {
        $result = $this->fetchOne(
            "SELECT COUNT(DISTINCT e.employee_id) total FROM employees e
             JOIN employee_user_accounts a ON a.employee_id=e.employee_id AND a.is_deleted=FALSE AND a.account_status='ACTIVE'
             JOIN employee_role_assignments era ON era.employee_id=e.employee_id AND era.is_deleted=FALSE
             JOIN employee_roles er ON er.employee_role_id=era.employee_role_id AND er.role_code='SYSTEM_ADMIN'
             WHERE e.is_deleted=FALSE AND e.employment_status='ACTIVE'",
        );
        return (int) ($result['total'] ?? 0);
    }

    public function hasRole(int $id, string $role): bool
    {
        return $this->fetchOne(
            'SELECT era.employee_role_assignment_id FROM employee_role_assignments era
             JOIN employee_roles er ON er.employee_role_id=era.employee_role_id
             WHERE era.employee_id=:id AND er.role_code=:role AND era.is_deleted=FALSE',
            ['id' => $id, 'role' => $role],
        ) !== null;
    }

    private function revokeSessions(int $employeeId): void
    {
        $this->execute(
            "UPDATE auth_refresh_tokens SET revoked_at=NOW(6)
             WHERE actor_type='EMPLOYEE' AND actor_id=:id AND revoked_at IS NULL",
            ['id' => $employeeId],
        );
        $this->execute(
            "UPDATE auth_trusted_devices SET revoked_at=NOW(6)
             WHERE actor_type='EMPLOYEE' AND actor_id=:id AND revoked_at IS NULL",
            ['id' => $employeeId],
        );
    }

    public function createTeam(string $name, ?string $description, string $actor): int
    {
        return $this->insert('teams', ['team_name' => $name, 'description' => $description, 'created_by' => $actor]);
    }

    public function addTeamMember(int $teamId, int $employeeId, bool $lead, string $actor): int
    {
        return $this->insert('employee_team_members', [
            'team_id' => $teamId,
            'employee_id' => $employeeId,
            'is_team_lead' => $lead,
            'created_by' => $actor,
        ]);
    }
}
