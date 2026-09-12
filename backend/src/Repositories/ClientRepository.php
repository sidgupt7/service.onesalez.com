<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\DatabaseException;
use App\Utils\Input;

final class ClientRepository extends BaseRepository
{
    public function removeLocation(int $clientId, int $locationId, string $actor): void
    {
        $this->database->transaction(function () use ($clientId, $locationId, $actor): void {
            $this->execute('UPDATE client_locations SET is_active=FALSE,is_deleted=TRUE,deleted_by=:actor,deleted_at=NOW(6),updated_by=:actor WHERE client_id=:client AND location_id=:location AND is_deleted=FALSE', ['actor' => $actor, 'client' => $clientId, 'location' => $locationId]);
            $this->execute('UPDATE client_contact_locations SET is_deleted=TRUE,deleted_by=:actor,deleted_at=NOW(6) WHERE location_id=:location AND is_deleted=FALSE', ['actor' => $actor, 'location' => $locationId]);
        });
    }

    public function removeContact(int $clientId, int $contactId, string $actor): void
    {
        $this->database->transaction(function () use ($clientId, $contactId, $actor): void {
            $this->execute('UPDATE client_contacts SET is_active=FALSE,is_deleted=TRUE,deleted_by=:actor,deleted_at=NOW(6),updated_by=:actor WHERE client_id=:client AND contact_id=:contact AND is_deleted=FALSE', ['actor' => $actor, 'client' => $clientId, 'contact' => $contactId]);
            $this->execute("UPDATE client_user_accounts SET account_status='SUSPENDED',is_deleted=TRUE,deleted_by=:actor,deleted_at=NOW(6),updated_by=:actor WHERE contact_id=:contact AND is_deleted=FALSE", ['actor' => $actor, 'contact' => $contactId]);
            $this->execute('UPDATE client_contact_locations SET is_deleted=TRUE,deleted_by=:actor,deleted_at=NOW(6) WHERE contact_id=:contact AND is_deleted=FALSE', ['actor' => $actor, 'contact' => $contactId]);
            $this->revokeContactSessions($contactId);
        });
    }

    public function paginate(array $filters): array
    {
        $page = Input::positiveInt($filters['page'] ?? null, 1);
        $limit = min(100, Input::positiveInt($filters['limit'] ?? null, 20));
        $search = trim((string) ($filters['search'] ?? ''));
        $where = 'c.is_deleted=FALSE';
        $params = [];
        if ($search !== '') {
            $where .= ' AND (c.legal_name LIKE :search OR c.client_code LIKE :search_code OR c.gstin LIKE :search_gstin)';
            $params['search'] = '%' . $search . '%';
            $params['search_code'] = $params['search'];
            $params['search_gstin'] = $params['search'];
        }
        $total = $this->fetchOne("SELECT COUNT(*) total FROM clients c WHERE {$where}", $params);
        $offset = ($page - 1) * $limit;
        $rows = $this->fetchAll(
            "SELECT c.*,
                (SELECT COUNT(*) FROM client_locations l WHERE l.client_id=c.client_id AND l.is_deleted=FALSE) location_count,
                (SELECT COUNT(*) FROM client_contacts ct WHERE ct.client_id=c.client_id AND ct.is_deleted=FALSE) contact_count
             FROM clients c
             WHERE {$where} ORDER BY c.legal_name, c.client_id LIMIT {$limit} OFFSET {$offset}",
            $params,
        );
        return ['items' => $rows, 'total' => (int) ($total['total'] ?? 0), 'page' => $page, 'limit' => $limit];
    }

    public function find(int $id): ?array
    {
        $client = $this->fetchOne('SELECT * FROM clients WHERE client_id=:id AND is_deleted=FALSE', ['id' => $id]);
        if ($client === null) {
            return null;
        }
        $client['locations'] = $this->fetchAll(
            'SELECT * FROM client_locations WHERE client_id=:id AND is_deleted=FALSE ORDER BY is_primary DESC, location_name',
            ['id' => $id],
        );
        $client['contacts'] = $this->fetchAll(
            "SELECT c.*, r.role_code, r.role_name, a.account_status,
                    IF(a.account_id IS NOT NULL AND (a.suspension_reason IS NULL OR a.suspension_reason<>'PORTAL ACCESS DISABLED'), TRUE, FALSE) portal_enabled
             FROM client_contacts c
             JOIN client_contact_roles r ON r.contact_role_id=c.contact_role_id
             LEFT JOIN client_user_accounts a ON a.contact_id=c.contact_id AND a.is_deleted=FALSE
             WHERE c.client_id=:id AND c.is_deleted=FALSE ORDER BY c.full_name",
            ['id' => $id],
        );
        $assignments = $this->fetchAll(
            'SELECT access.contact_id,access.location_id FROM client_contact_locations access
             JOIN client_contacts contact ON contact.contact_id=access.contact_id
             WHERE contact.client_id=:id AND access.is_deleted=FALSE',
            ['id' => $id],
        );
        $byContact = [];
        foreach ($assignments as $assignment) {
            $byContact[$assignment['contact_id']][] = (int) $assignment['location_id'];
        }
        foreach ($client['contacts'] as &$contact) {
            $contact['location_ids'] = $byContact[$contact['contact_id']] ?? [];
        }
        unset($contact);
        return $client;
    }

    public function locationsBelongToClient(int $clientId, array $locationIds): bool
    {
        $ids = array_values(array_unique(array_map('intval', $locationIds)));
        if ($ids === []) {
            return true;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $statement = $this->pdo->prepare(
            "SELECT COUNT(*) FROM client_locations WHERE client_id=? AND location_id IN ({$placeholders}) AND is_deleted=FALSE",
        );
        $statement->execute([$clientId, ...$ids]);
        return (int) $statement->fetchColumn() === count($ids);
    }

    public function create(array $data, string $actor): int
    {
        return $this->insert('clients', $data + ['created_by' => $actor]);
    }

    public function onboardingConflict(string $clientCode, ?string $gstin, string $email): ?string
    {
        if ($this->fetchOne('SELECT client_id FROM clients WHERE client_code=:value LIMIT 1', ['value' => $clientCode])) {
            return 'client.client_code';
        }
        if ($gstin !== null && $gstin !== '' && $this->fetchOne('SELECT client_id FROM clients WHERE gstin=:value LIMIT 1', ['value' => $gstin])) {
            return 'client.gstin';
        }
        if ($this->fetchOne('SELECT contact_id FROM client_contacts WHERE email=:value LIMIT 1', ['value' => $email])) {
            return 'administrator.email';
        }
        return null;
    }

    public function contactEmailInUse(string $email, ?int $excludeContactId = null): bool
    {
        $sql = 'SELECT contact_id FROM client_contacts WHERE LOWER(email)=LOWER(:email) AND is_deleted=FALSE';
        $parameters = ['email' => $email];
        if ($excludeContactId !== null) {
            $sql .= ' AND contact_id<>:exclude_id';
            $parameters['exclude_id'] = $excludeContactId;
        }
        return $this->fetchOne($sql . ' LIMIT 1', $parameters) !== null;
    }

    public function onboard(array $client, array $location, array $administrator, string $passwordHash, string $actor): int
    {
        return $this->database->transaction(function () use ($client, $location, $administrator, $passwordHash, $actor): int {
            $role = $this->fetchOne(
                "SELECT contact_role_id FROM client_contact_roles WHERE role_code='CLIENT_ADMIN' AND is_deleted=FALSE",
            );
            if ($role === null) {
                throw new DatabaseException('The client administrator role is not configured.');
            }

            $clientId = $this->insert('clients', $client + ['created_by' => $actor]);
            $this->insert('client_locations', $location + [
                'client_id' => $clientId,
                'is_primary' => true,
                'created_by' => $actor,
            ]);
            $contactId = $this->insert('client_contacts', $administrator + [
                'client_id' => $clientId,
                'contact_role_id' => (int) $role['contact_role_id'],
                'has_all_locations' => true,
                'is_primary_contact' => true,
                'created_by' => $actor,
            ]);
            $this->insert('client_user_accounts', [
                'contact_id' => $contactId,
                'password_hash' => $passwordHash,
                'account_status' => 'ACTIVE',
                'password_changed_at' => date('Y-m-d H:i:s.u'),
                'created_by' => $actor,
            ]);
            return $clientId;
        });
    }

    public function update(int $id, array $data, string $actor): bool
    {
        return $this->updateById('clients', 'client_id', $id, $data + ['updated_by' => $actor]);
    }

    public function setClientActive(int $id, bool $active, string $actor): bool
    {
        return $this->database->transaction(function () use ($id, $active, $actor): bool {
            $updated = $this->execute(
                'UPDATE clients SET is_active=:active, updated_by=:actor WHERE client_id=:id AND is_deleted=FALSE',
                ['active' => $active, 'actor' => $actor, 'id' => $id],
            ) > 0;
            if (!$active) {
                $this->execute(
                    "UPDATE client_user_accounts a JOIN client_contacts c ON c.contact_id=a.contact_id
                     SET a.account_status='SUSPENDED', a.suspended_by=:actor, a.suspended_at=NOW(6),
                         a.suspension_reason='CLIENT SUSPENDED', a.updated_by=:actor
                     WHERE c.client_id=:id AND a.is_deleted=FALSE AND a.account_status<>'SUSPENDED'",
                    ['actor' => $actor, 'id' => $id],
                );
                $this->execute(
                    "UPDATE auth_refresh_tokens SET revoked_at=NOW(6)
                     WHERE actor_type='CLIENT_CONTACT' AND actor_id IN
                         (SELECT contact_id FROM client_contacts WHERE client_id=:id) AND revoked_at IS NULL",
                    ['id' => $id],
                );
                $this->execute(
                    "UPDATE auth_trusted_devices SET revoked_at=NOW(6)
                     WHERE actor_type='CLIENT_CONTACT' AND actor_id IN
                         (SELECT contact_id FROM client_contacts WHERE client_id=:id) AND revoked_at IS NULL",
                    ['id' => $id],
                );
            } else {
                $this->execute(
                    "UPDATE client_user_accounts a JOIN client_contacts c ON c.contact_id=a.contact_id
                     SET a.account_status=IF(a.password_hash IS NULL, 'INVITED', 'ACTIVE'),
                         a.suspended_by=NULL, a.suspended_at=NULL, a.suspension_reason=NULL, a.updated_by=:actor
                     WHERE c.client_id=:id AND c.is_active=TRUE AND a.is_deleted=FALSE
                       AND a.suspension_reason='CLIENT SUSPENDED'",
                    ['actor' => $actor, 'id' => $id],
                );
            }
            return $updated;
        });
    }

    public function createLocation(int $clientId, array $data, string $actor): int
    {
        return $this->insert('client_locations', $data + ['client_id' => $clientId, 'created_by' => $actor]);
    }

    public function updateLocation(int $clientId, int $locationId, array $data, string $actor): bool
    {
        $sets = array_map(static fn (string $field): string => $field . '=:' . $field, array_keys($data));
        $data += ['actor' => $actor, 'client_id' => $clientId, 'location_id' => $locationId];
        return $this->execute(
            'UPDATE client_locations SET ' . implode(', ', $sets) . ', updated_by=:actor
             WHERE location_id=:location_id AND client_id=:client_id AND is_deleted=FALSE',
            $data,
        ) > 0;
    }

    public function setLocationActive(int $clientId, int $locationId, bool $active, string $actor): bool
    {
        return $this->execute(
            'UPDATE client_locations SET is_active=:active, updated_by=:actor
             WHERE location_id=:location_id AND client_id=:client_id AND is_deleted=FALSE',
            ['active' => $active, 'actor' => $actor, 'location_id' => $locationId, 'client_id' => $clientId],
        ) > 0;
    }

    public function createContact(
        int $clientId,
        array $data,
        array $locationIds,
        ?string $passwordHash,
        string $actor,
    ): int {
        return $this->database->transaction(function () use ($clientId, $data, $locationIds, $passwordHash, $actor): int {
            $role = $this->fetchOne(
                'SELECT contact_role_id FROM client_contact_roles WHERE role_code=:code AND is_deleted=FALSE',
                ['code' => $data['role_code']],
            );
            if ($role === null) {
                throw new DatabaseException('The selected client contact role is unavailable.');
            }
            unset($data['role_code']);
            $contactId = $this->insert('client_contacts', $data + [
                'client_id' => $clientId,
                'contact_role_id' => (int) $role['contact_role_id'],
                'created_by' => $actor,
            ]);
            $this->replaceContactLocations($contactId, $locationIds, $actor);
            if ($passwordHash !== null) {
                $client = $this->fetchOne('SELECT is_active FROM clients WHERE client_id=:id', ['id' => $clientId]);
                $active = (bool) ($client['is_active'] ?? false) && (bool) ($data['is_active'] ?? true);
                $this->insert('client_user_accounts', [
                    'contact_id' => $contactId,
                    'password_hash' => $passwordHash,
                    'account_status' => $active ? 'ACTIVE' : 'SUSPENDED',
                    'password_changed_at' => date('Y-m-d H:i:s.u'),
                    'suspension_reason' => $active ? null : 'CLIENT SUSPENDED',
                    'suspended_by' => $active ? null : $actor,
                    'suspended_at' => $active ? null : date('Y-m-d H:i:s.u'),
                    'created_by' => $actor,
                ]);
            }
            return $contactId;
        });
    }

    public function updateContact(
        int $clientId,
        int $contactId,
        array $data,
        array $locationIds,
        bool $portalEnabled,
        ?string $passwordHash,
        string $actor,
    ): bool {
        return $this->database->transaction(function () use ($clientId, $contactId, $data, $locationIds, $portalEnabled, $passwordHash, $actor): bool {
            if (isset($data['role_code'])) {
                $role = $this->fetchOne(
                    'SELECT contact_role_id FROM client_contact_roles WHERE role_code=:code AND is_deleted=FALSE',
                    ['code' => $data['role_code']],
                );
                if ($role === null) {
                    throw new DatabaseException('The selected client contact role is unavailable.');
                }
                $data['contact_role_id'] = (int) $role['contact_role_id'];
                unset($data['role_code']);
            }
            $sets = array_map(static fn (string $field): string => $field . '=:' . $field, array_keys($data));
            $data += ['actor' => $actor, 'client_id' => $clientId, 'contact_id' => $contactId];
            $updated = $this->execute(
                'UPDATE client_contacts SET ' . implode(', ', $sets) . ', updated_by=:actor
                 WHERE contact_id=:contact_id AND client_id=:client_id AND is_deleted=FALSE',
                $data,
            ) > 0;
            if ($updated) {
                $this->replaceContactLocations($contactId, $locationIds, $actor);
                $this->configurePortalAccess($clientId, $contactId, $portalEnabled, $passwordHash, $actor);
            }
            return $updated;
        });
    }

    private function configurePortalAccess(
        int $clientId,
        int $contactId,
        bool $enabled,
        ?string $passwordHash,
        string $actor,
    ): void {
        $account = $this->fetchOne(
            'SELECT * FROM client_user_accounts WHERE contact_id=:id AND is_deleted=FALSE',
            ['id' => $contactId],
        );
        if (!$enabled) {
            if ($account !== null) {
                $this->execute(
                    "UPDATE client_user_accounts SET account_status='SUSPENDED', suspension_reason='PORTAL ACCESS DISABLED',
                     suspended_by=:actor, suspended_at=NOW(6), updated_by=:actor WHERE contact_id=:id AND is_deleted=FALSE",
                    ['actor' => $actor, 'id' => $contactId],
                );
                $this->revokeContactSessions($contactId);
            }
            return;
        }

        $state = $this->fetchOne(
            'SELECT client.is_active client_active, contact.is_active contact_active
             FROM clients client JOIN client_contacts contact ON contact.client_id=client.client_id
             WHERE client.client_id=:client AND contact.contact_id=:contact',
            ['client' => $clientId, 'contact' => $contactId],
        );
        $clientActive = (bool) ($state['client_active'] ?? false);
        $contactActive = (bool) ($state['contact_active'] ?? false);
        $active = $clientActive && $contactActive;
        $suspensionReason = !$clientActive ? 'CLIENT SUSPENDED' : (!$contactActive ? 'CONTACT SUSPENDED' : null);
        if ($account === null) {
            $this->insert('client_user_accounts', [
                'contact_id' => $contactId,
                'password_hash' => $passwordHash,
                'account_status' => $active ? 'ACTIVE' : 'SUSPENDED',
                'password_changed_at' => date('Y-m-d H:i:s.u'),
                'suspension_reason' => $suspensionReason,
                'suspended_by' => $active ? null : $actor,
                'suspended_at' => $active ? null : date('Y-m-d H:i:s.u'),
                'created_by' => $actor,
            ]);
            return;
        }

        $changes = ['updated_by=:actor'];
        $parameters = ['actor' => $actor, 'id' => $contactId];
        if ($passwordHash !== null) {
            $changes[] = 'password_hash=:password';
            $changes[] = 'password_changed_at=NOW(6)';
            $changes[] = 'password_reset_token_hash=NULL';
            $changes[] = 'password_reset_expires_at=NULL';
            $changes[] = 'failed_login_attempts=0';
            $changes[] = 'locked_until=NULL';
            $parameters['password'] = $passwordHash;
        }
        if ($account['suspension_reason'] === 'PORTAL ACCESS DISABLED') {
            $changes[] = "account_status='" . ($active ? 'ACTIVE' : 'SUSPENDED') . "'";
            $changes[] = $active ? 'suspension_reason=NULL' : 'suspension_reason=:suspension_reason';
            $changes[] = $active ? 'suspended_by=NULL' : 'suspended_by=:actor';
            $changes[] = $active ? 'suspended_at=NULL' : 'suspended_at=NOW(6)';
            if (!$active) {
                $parameters['suspension_reason'] = $suspensionReason;
            }
        }
        $this->execute(
            'UPDATE client_user_accounts SET ' . implode(', ', $changes) . ' WHERE contact_id=:id AND is_deleted=FALSE',
            $parameters,
        );
        if ($passwordHash !== null) {
            $this->revokeContactSessions($contactId);
        }
    }

    private function revokeContactSessions(int $contactId): void
    {
        $this->execute(
            "UPDATE auth_refresh_tokens SET revoked_at=NOW(6)
             WHERE actor_type='CLIENT_CONTACT' AND actor_id=:id AND revoked_at IS NULL",
            ['id' => $contactId],
        );
        $this->execute(
            "UPDATE auth_trusted_devices SET revoked_at=NOW(6)
             WHERE actor_type='CLIENT_CONTACT' AND actor_id=:id AND revoked_at IS NULL",
            ['id' => $contactId],
        );
    }

    public function setContactActive(int $clientId, int $contactId, bool $active, string $actor): bool
    {
        return $this->database->transaction(function () use ($clientId, $contactId, $active, $actor): bool {
            $updated = $this->execute(
                'UPDATE client_contacts SET is_active=:active, updated_by=:actor
                 WHERE contact_id=:contact_id AND client_id=:client_id AND is_deleted=FALSE',
                ['active' => $active, 'actor' => $actor, 'contact_id' => $contactId, 'client_id' => $clientId],
            ) > 0;
            if (!$active) {
                $this->execute(
                    "UPDATE client_user_accounts SET account_status='SUSPENDED', suspended_by=:actor,
                     suspended_at=NOW(6), suspension_reason='CONTACT SUSPENDED', updated_by=:actor
                     WHERE contact_id=:contact_id AND is_deleted=FALSE
                       AND suspension_reason IS NULL",
                    ['actor' => $actor, 'contact_id' => $contactId],
                );
                $this->execute(
                    "UPDATE auth_refresh_tokens SET revoked_at=NOW(6)
                     WHERE actor_type='CLIENT_CONTACT' AND actor_id=:contact_id AND revoked_at IS NULL",
                    ['contact_id' => $contactId],
                );
                $this->execute(
                    "UPDATE auth_trusted_devices SET revoked_at=NOW(6)
                     WHERE actor_type='CLIENT_CONTACT' AND actor_id=:contact_id AND revoked_at IS NULL",
                    ['contact_id' => $contactId],
                );
            } else {
                $this->execute(
                    "UPDATE client_user_accounts a JOIN client_contacts c ON c.contact_id=a.contact_id
                     JOIN clients client ON client.client_id=c.client_id AND client.is_active=TRUE
                     SET a.account_status=IF(a.password_hash IS NULL, 'INVITED', 'ACTIVE'),
                         a.suspended_by=NULL, a.suspended_at=NULL, a.suspension_reason=NULL, a.updated_by=:actor
                     WHERE a.contact_id=:contact_id AND a.is_deleted=FALSE AND a.suspension_reason='CONTACT SUSPENDED'",
                    ['actor' => $actor, 'contact_id' => $contactId],
                );
            }
            return $updated;
        });
    }

    private function replaceContactLocations(int $contactId, array $locationIds, string $actor): void
    {
        $this->execute(
            'UPDATE client_contact_locations SET is_deleted=TRUE, deleted_by=:actor, deleted_at=NOW(6)
             WHERE contact_id=:id AND is_deleted=FALSE',
            ['actor' => $actor, 'id' => $contactId],
        );
        foreach (array_unique(array_map('intval', $locationIds)) as $locationId) {
            $existing = $this->fetchOne(
                'SELECT contact_location_id FROM client_contact_locations WHERE contact_id=:contact AND location_id=:location',
                ['contact' => $contactId, 'location' => $locationId],
            );
            if ($existing === null) {
                $this->insert('client_contact_locations', [
                    'contact_id' => $contactId,
                    'location_id' => $locationId,
                    'created_by' => $actor,
                ]);
            } else {
                $this->execute(
                    'UPDATE client_contact_locations SET is_deleted=FALSE, deleted_by=NULL, deleted_at=NULL, updated_by=:actor
                     WHERE contact_location_id=:id',
                    ['actor' => $actor, 'id' => $existing['contact_location_id']],
                );
            }
        }
    }

    public function softDelete(int $id, string $actor): bool
    {
        return $this->execute(
            'UPDATE clients SET is_deleted=TRUE, deleted_by=:actor, deleted_at=NOW(6) WHERE client_id=:id AND is_deleted=FALSE',
            ['actor' => $actor, 'id' => $id],
        ) > 0;
    }

    public function serviceHistory(int $id, int $page, int $limit): array
    {
        $offset = ($page - 1) * $limit;
        $total = $this->fetchOne(
            'SELECT COUNT(*) total FROM service_tickets WHERE client_id=:id AND is_deleted=FALSE',
            ['id' => $id],
        );
        $items = $this->fetchAll(
            "SELECT ticket_id, service_request_number, subject, priority, ticket_status, created_at, completed_at
             FROM service_tickets WHERE client_id=:id AND is_deleted=FALSE ORDER BY created_at DESC
             LIMIT {$limit} OFFSET {$offset}",
            ['id' => $id],
        );
        return ['items' => $items, 'total' => (int) ($total['total'] ?? 0), 'page' => $page, 'limit' => $limit];
    }
}
