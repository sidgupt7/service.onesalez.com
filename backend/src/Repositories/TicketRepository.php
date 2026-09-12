<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Utils\Input;
use App\Models\Actor;

final class TicketRepository extends BaseRepository
{
    public function contactCanUseLocation(int $clientId, int $contactId, int $locationId): bool
    {
        $row = $this->fetchOne(
            "SELECT 1 FROM client_contacts c
             JOIN clients client ON client.client_id=c.client_id AND client.is_active=TRUE AND client.is_deleted=FALSE
             JOIN client_locations l ON l.client_id=c.client_id AND l.location_id=:location AND l.is_active=TRUE AND l.is_deleted=FALSE
             WHERE c.contact_id=:contact AND c.client_id=:client AND c.is_active=TRUE AND c.is_deleted=FALSE
             AND (c.has_all_locations=TRUE OR EXISTS (
                 SELECT 1 FROM client_contact_locations cl
                 WHERE cl.contact_id=c.contact_id AND cl.location_id=l.location_id AND cl.is_deleted=FALSE
             ))",
            ['location' => $locationId, 'contact' => $contactId, 'client' => $clientId],
        );
        return $row !== null;
    }

    public function paginate(array $filters, Actor $actor): array
    {
        $page = Input::positiveInt($filters['page'] ?? null, 1);
        $limit = min(100, Input::positiveInt($filters['limit'] ?? null, 20));
        $where = ['t.is_deleted=FALSE'];
        $params = [];
        foreach (['ticket_status' => 'status', 'priority' => 'priority'] as $column => $input) {
            if (!empty($filters[$input])) {
                $where[] = "t.{$column}=:{$input}";
                $params[$input] = $filters[$input];
            }
        }
        if (!$actor->can('tickets.decline')) {
            $where[] = "t.ticket_status<>'DECLINED'";
        }
        if ($actor->type === 'CLIENT_CONTACT') {
            $where[] = 't.client_id=:client_id AND EXISTS (
                SELECT 1 FROM client_contacts contact
                JOIN clients client ON client.client_id=contact.client_id AND client.is_active=TRUE AND client.is_deleted=FALSE
                JOIN client_locations location ON location.location_id=t.location_id AND location.client_id=client.client_id
                    AND location.is_active=TRUE AND location.is_deleted=FALSE
                WHERE contact.contact_id=:contact_id AND contact.client_id=t.client_id
                    AND contact.is_active=TRUE AND contact.is_deleted=FALSE
                    AND (contact.has_all_locations=TRUE OR EXISTS (
                        SELECT 1 FROM client_contact_locations access
                        WHERE access.contact_id=contact.contact_id AND access.location_id=t.location_id AND access.is_deleted=FALSE
                    )))';
            $params['client_id'] = $actor->clientId ?? 0;
            $params['contact_id'] = $actor->id;
        }
        if (!empty($filters['search'])) {
            $where[] = '(t.service_request_number LIKE :search OR t.subject LIKE :search_subject
                OR EXISTS (SELECT 1 FROM clients sc WHERE sc.client_id=t.client_id AND sc.legal_name LIKE :search_client)
                OR EXISTS (SELECT 1 FROM client_locations sl WHERE sl.location_id=t.location_id AND sl.location_name LIKE :search_location))';
            $params['search'] = '%' . $filters['search'] . '%';
            $params['search_subject'] = $params['search'];
            $params['search_client'] = $params['search'];
            $params['search_location'] = $params['search'];
        }
        if (!empty($filters['client_id']) && $actor->type === 'EMPLOYEE') {
            $where[] = 't.client_id=:filter_client';
            $params['filter_client'] = Input::positiveInt($filters['client_id'], 0);
        }
        $countWhere = array_values(array_filter($where, static fn (string $clause): bool => $clause !== 't.ticket_status=:status'));
        $countParams = array_diff_key($params, ['status' => true]);
        $counts = array_fill_keys(['OPEN', 'ACCEPTED', 'COMPLETED', 'DECLINED'], 0);
        foreach (
            $this->fetchAll('SELECT t.ticket_status, COUNT(*) total FROM service_tickets t WHERE '
            . implode(' AND ', $countWhere) . ' GROUP BY t.ticket_status', $countParams) as $row
        ) {
            $counts[$row['ticket_status']] = (int) $row['total'];
        }
        $condition = implode(' AND ', $where);
        $total = $this->fetchOne("SELECT COUNT(*) total FROM service_tickets t WHERE {$condition}", $params);
        $offset = ($page - 1) * $limit;
        $items = $this->fetchAll(
            "SELECT t.*, c.legal_name client_name, l.location_name, e.full_name current_employee_name
             FROM service_tickets t JOIN clients c ON c.client_id=t.client_id
             JOIN client_locations l ON l.location_id=t.location_id
             LEFT JOIN employees e ON e.employee_id=t.current_employee_id
             WHERE {$condition} ORDER BY t.created_at DESC, t.ticket_id DESC LIMIT {$limit} OFFSET {$offset}",
            $params,
        );
        return ['items' => $items, 'total' => (int) ($total['total'] ?? 0), 'page' => $page, 'limit' => $limit, 'counts' => $counts];
    }

    public function find(int $id): ?array
    {
        $ticket = $this->fetchOne(
            "SELECT t.*, c.legal_name client_name, l.location_name, ct.full_name reported_by_name
             FROM service_tickets t JOIN clients c ON c.client_id=t.client_id
             JOIN client_locations l ON l.location_id=t.location_id
             JOIN client_contacts ct ON ct.contact_id=t.reported_by_contact_id
             WHERE t.ticket_id=:id AND t.is_deleted=FALSE",
            ['id' => $id],
        );
        if ($ticket === null) {
            return null;
        }
        $ticket['attempts'] = $this->fetchAll(
            "SELECT a.*, e.full_name employee_name FROM service_attempts a
             JOIN employees e ON e.employee_id=a.employee_id
             WHERE a.ticket_id=:id AND a.is_deleted=FALSE ORDER BY a.attempt_number",
            ['id' => $id],
        );
        $ticket['messages'] = $this->fetchAll(
            'SELECT * FROM ticket_messages WHERE ticket_id=:id AND is_deleted=FALSE ORDER BY created_at',
            ['id' => $id],
        );
        $ticket['description_history'] = $this->fetchAll(
            "SELECT h.*, c.full_name changed_by_name
             FROM service_ticket_description_history h
             JOIN client_contacts c ON c.contact_id=h.changed_by_contact_id
             WHERE h.ticket_id=:id ORDER BY h.created_at DESC",
            ['id' => $id],
        );
        return $ticket;
    }

    public function create(array $data, string $actor): int
    {
        return $this->database->transaction(function () use ($data, $actor): int {
            $id = $this->insert('service_tickets', $data + ['created_by' => $actor]);
            $this->history($id, null, 'OPEN', 'CLIENT_CONTACT', (int) $data['reported_by_contact_id'], $actor);
            return $id;
        });
    }

    public function history(int $ticketId, ?string $from, string $to, string $actorType, int $actorId, string $actor): void
    {
        $this->insert('service_status_history', [
            'ticket_id' => $ticketId,
            'from_status' => $from,
            'to_status' => $to,
            'actor_type' => $actorType,
            'contact_id' => $actorType === 'CLIENT_CONTACT' ? $actorId : null,
            'employee_id' => $actorType === 'EMPLOYEE' ? $actorId : null,
            'created_by' => $actor,
        ]);
    }

    public function currentAttempt(int $ticketId): ?array
    {
        return $this->fetchOne(
            "SELECT * FROM service_attempts WHERE ticket_id=:id AND attempt_status='IN_PROGRESS' AND is_deleted=FALSE FOR UPDATE",
            ['id' => $ticketId],
        );
    }

    public function messages(int $ticketId, bool $includeInternal): array
    {
        $filter = $includeInternal ? '' : ' AND is_internal=FALSE';
        return $this->fetchAll(
            "SELECT * FROM ticket_messages WHERE ticket_id=:id AND is_deleted=FALSE{$filter} ORDER BY created_at",
            ['id' => $ticketId],
        );
    }

    public function addMessage(array $data): int
    {
        return $this->insert('ticket_messages', $data);
    }

    public function accept(int $ticketId, int $employeeId, string $actor): bool
    {
        return $this->database->transaction(function () use ($ticketId, $employeeId, $actor): bool {
            $updated = $this->execute(
                "UPDATE service_tickets SET ticket_status='ACCEPTED', current_employee_id=:employee,
                 accepted_at=NOW(6), updated_by=:actor
                 WHERE ticket_id=:ticket AND ticket_status='OPEN' AND is_deleted=FALSE",
                ['employee' => $employeeId, 'actor' => $actor, 'ticket' => $ticketId],
            );
            if ($updated !== 1) {
                return false;
            }
            $row = $this->fetchOne(
                'SELECT COALESCE(MAX(attempt_number),0)+1 next_attempt FROM service_attempts WHERE ticket_id=:ticket',
                ['ticket' => $ticketId],
            );
            $this->insert('service_attempts', [
                'ticket_id' => $ticketId,
                'attempt_number' => (int) ($row['next_attempt'] ?? 1),
                'employee_id' => $employeeId,
                'created_by' => $actor,
            ]);
            $this->history($ticketId, 'OPEN', 'ACCEPTED', 'EMPLOYEE', $employeeId, $actor);
            return true;
        });
    }

    public function release(int $ticketId, int $employeeId, string $note, string $actor): bool
    {
        return $this->finishAttempt($ticketId, $employeeId, 'RELEASED', 'OPEN', $note, $actor);
    }

    public function complete(int $ticketId, int $employeeId, string $note, string $actor): bool
    {
        return $this->finishAttempt($ticketId, $employeeId, 'COMPLETED', 'COMPLETED', $note, $actor);
    }

    public function decline(int $ticketId, int $employeeId, string $reason, string $actor): bool
    {
        return $this->database->transaction(function () use ($ticketId, $employeeId, $reason, $actor): bool {
            $ticket = $this->fetchOne(
                "SELECT ticket_status FROM service_tickets WHERE ticket_id=:id AND ticket_status<>'DECLINED' AND is_deleted=FALSE FOR UPDATE",
                ['id' => $ticketId],
            );
            if ($ticket === null) {
                return false;
            }
            $this->execute(
                "UPDATE service_attempts SET attempt_status='CANCELLED_BY_DECLINE', ended_at=NOW(6),
                 service_note=:reason, updated_by=:actor
                 WHERE ticket_id=:id AND attempt_status='IN_PROGRESS' AND is_deleted=FALSE",
                ['reason' => $reason, 'actor' => $actor, 'id' => $ticketId],
            );
            $this->execute(
                "UPDATE service_tickets SET ticket_status='DECLINED', declined_by_employee_id=:employee,
                 declined_at=NOW(6), decline_reason=:reason, current_employee_id=NULL, updated_by=:actor
                 WHERE ticket_id=:id",
                ['employee' => $employeeId, 'reason' => $reason, 'actor' => $actor, 'id' => $ticketId],
            );
            $this->history($ticketId, $ticket['ticket_status'], 'DECLINED', 'EMPLOYEE', $employeeId, $actor);
            return true;
        });
    }

    public function updatePriority(int $ticketId, string $priority, string $actor): bool
    {
        return $this->execute(
            'UPDATE service_tickets SET priority=:priority, updated_by=:actor WHERE ticket_id=:id AND is_deleted=FALSE',
            ['priority' => $priority, 'actor' => $actor, 'id' => $ticketId],
        ) > 0;
    }

    public function updateDescription(
        int $ticketId,
        int $clientId,
        int $contactId,
        string $description,
        string $actor,
    ): bool {
        return $this->database->transaction(function () use ($ticketId, $clientId, $contactId, $description, $actor): bool {
            $ticket = $this->fetchOne(
                "SELECT issue_description FROM service_tickets
                 WHERE ticket_id=:ticket AND client_id=:client AND ticket_status IN ('OPEN','ACCEPTED')
                   AND is_deleted=FALSE FOR UPDATE",
                ['ticket' => $ticketId, 'client' => $clientId],
            );
            if ($ticket === null) {
                return false;
            }
            $previous = (string) $ticket['issue_description'];
            if ($previous === $description) {
                return true;
            }
            $this->execute(
                'UPDATE service_tickets SET issue_description=:description, updated_by=:actor WHERE ticket_id=:ticket',
                ['description' => $description, 'actor' => $actor, 'ticket' => $ticketId],
            );
            $this->insert('service_ticket_description_history', [
                'ticket_id' => $ticketId,
                'previous_description' => $previous,
                'new_description' => $description,
                'changed_by_contact_id' => $contactId,
                'created_by' => $actor,
            ]);
            return true;
        });
    }

    public function softDelete(int $ticketId, string $actor): bool
    {
        return $this->execute(
            "UPDATE service_tickets SET is_deleted=TRUE, deleted_by=:actor, deleted_at=NOW(6)
             WHERE ticket_id=:id AND ticket_status IN ('COMPLETED','DECLINED') AND is_deleted=FALSE",
            ['actor' => $actor, 'id' => $ticketId],
        ) > 0;
    }

    private function finishAttempt(
        int $ticketId,
        int $employeeId,
        string $attemptStatus,
        string $ticketStatus,
        string $note,
        string $actor,
    ): bool {
        return $this->database->transaction(function () use (
            $ticketId,
            $employeeId,
            $attemptStatus,
            $ticketStatus,
            $note,
            $actor,
        ): bool {
            // All state transitions lock the ticket before touching its attempts.
            $ticket = $this->fetchOne(
                "SELECT ticket_id FROM service_tickets WHERE ticket_id=:id AND ticket_status='ACCEPTED' AND is_deleted=FALSE FOR UPDATE",
                ['id' => $ticketId],
            );
            if ($ticket === null) {
                return false;
            }
            $attempt = $this->currentAttempt($ticketId);
            if ($attempt === null || (int) $attempt['employee_id'] !== $employeeId) {
                return false;
            }
            $this->execute(
                'UPDATE service_attempts SET attempt_status=:status, ended_at=NOW(6), service_note=:note, updated_by=:actor WHERE attempt_id=:id',
                ['status' => $attemptStatus, 'note' => $note, 'actor' => $actor, 'id' => $attempt['attempt_id']],
            );
            $completion = $ticketStatus === 'COMPLETED';
            $this->execute(
                "UPDATE service_tickets SET ticket_status=:status, current_employee_id=:current_employee,
                 accepted_at=:accepted_at, completed_by_employee_id=:completed_by,
                 completed_at=" . ($completion ? 'NOW(6)' : 'NULL') . ", final_resolution=:resolution, updated_by=:actor
                 WHERE ticket_id=:id AND ticket_status='ACCEPTED'",
                [
                    'status' => $ticketStatus,
                    'current_employee' => $completion ? $employeeId : null,
                    'accepted_at' => $completion ? $attempt['accepted_at'] : null,
                    'completed_by' => $completion ? $employeeId : null,
                    'resolution' => $completion ? $note : null,
                    'actor' => $actor,
                    'id' => $ticketId,
                ],
            );
            $this->history($ticketId, 'ACCEPTED', $ticketStatus, 'EMPLOYEE', $employeeId, $actor);
            return true;
        });
    }
}
