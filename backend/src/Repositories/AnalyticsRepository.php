<?php

declare(strict_types=1);

namespace App\Repositories;

final class AnalyticsRepository extends BaseRepository
{
    public function serviceLedger(array $filters): array
    {
        [$condition, $parameters] = $this->ledgerCondition($filters);
        $page = (int) $filters['page'];
        $limit = (int) $filters['limit'];
        $offset = ($page - 1) * $limit;
        $total = $this->fetchOne(
            "SELECT COUNT(*) total FROM service_tickets t WHERE {$condition}",
            $parameters,
        );
        $summary = $this->fetchOne(
            "SELECT COUNT(*) total,
                    SUM(t.ticket_status='OPEN') open_count,
                    SUM(t.ticket_status='ACCEPTED') accepted_count,
                    SUM(t.ticket_status='COMPLETED') completed_count,
                    SUM(t.ticket_status='DECLINED') declined_count,
                    ROUND(AVG(CASE WHEN t.completed_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE,t.created_at,t.completed_at) END),2) avg_resolution_minutes
             FROM service_tickets t WHERE {$condition}",
            $parameters,
        ) ?? [];
        $items = $this->fetchAll(
            "SELECT t.ticket_id, t.service_request_number, t.subject, t.issue_description, t.priority,
                    t.ticket_status, t.created_at, t.accepted_at, t.completed_at, t.final_resolution,
                    t.declined_at, t.decline_reason,
                    c.client_id, c.client_code, c.legal_name client_name,
                    l.location_id, l.location_code, l.location_name, l.city, l.state_name,
                    contact.full_name reported_by_name,
                    current_employee.full_name current_employee_name,
                    completed_employee.full_name completed_by_name,
                    declined_employee.full_name declined_by_name,
                    COUNT(DISTINCT a.attempt_id) attempt_count,
                    GROUP_CONCAT(DISTINCT attempt_employee.full_name ORDER BY attempt_employee.full_name SEPARATOR ', ') service_employees
             FROM service_tickets t
             JOIN clients c ON c.client_id=t.client_id
             JOIN client_locations l ON l.location_id=t.location_id
             JOIN client_contacts contact ON contact.contact_id=t.reported_by_contact_id
             LEFT JOIN employees current_employee ON current_employee.employee_id=t.current_employee_id
             LEFT JOIN employees completed_employee ON completed_employee.employee_id=t.completed_by_employee_id
             LEFT JOIN employees declined_employee ON declined_employee.employee_id=t.declined_by_employee_id
             LEFT JOIN service_attempts a ON a.ticket_id=t.ticket_id AND a.is_deleted=FALSE
             LEFT JOIN employees attempt_employee ON attempt_employee.employee_id=a.employee_id
             WHERE {$condition}
             GROUP BY t.ticket_id
             ORDER BY t.created_at DESC LIMIT {$limit} OFFSET {$offset}",
            $parameters,
        );
        return [
            'items' => $items,
            'summary' => $summary,
            'filters' => $this->ledgerOptions(),
            'page' => $page,
            'limit' => $limit,
            'total' => (int) ($total['total'] ?? 0),
        ];
    }

    private function ledgerCondition(array $filters): array
    {
        $where = [
            't.is_deleted=FALSE',
            't.created_at>=:from_date',
            't.created_at<DATE_ADD(:to_date, INTERVAL 1 DAY)',
        ];
        $parameters = ['from_date' => $filters['from'], 'to_date' => $filters['to']];
        if ($filters['status'] === 'PENDING') {
            $where[] = "t.ticket_status IN ('OPEN','ACCEPTED')";
        } elseif ($filters['status'] !== '') {
            $where[] = 't.ticket_status=:status';
            $parameters['status'] = $filters['status'];
        }
        foreach (['client_id' => 'client', 'location_id' => 'location'] as $field => $parameter) {
            if ($filters[$field] !== null) {
                $where[] = "t.{$field}=:{$parameter}";
                $parameters[$parameter] = $filters[$field];
            }
        }
        if ($filters['employee_id'] !== null) {
            $where[] = 'EXISTS (SELECT 1 FROM service_attempts filter_attempt
                WHERE filter_attempt.ticket_id=t.ticket_id AND filter_attempt.employee_id=:employee
                  AND filter_attempt.is_deleted=FALSE)';
            $parameters['employee'] = $filters['employee_id'];
        }
        if ($filters['search'] !== '') {
            $where[] = '(t.service_request_number LIKE :search OR t.subject LIKE :search OR t.issue_description LIKE :search)';
            $parameters['search'] = '%' . $filters['search'] . '%';
        }
        return [implode(' AND ', $where), $parameters];
    }

    private function ledgerOptions(): array
    {
        return [
            'clients' => $this->fetchAll(
                'SELECT client_id, client_code, legal_name FROM clients WHERE is_deleted=FALSE ORDER BY legal_name',
            ),
            'employees' => $this->fetchAll(
                'SELECT employee_id, employee_code, full_name FROM employees WHERE is_deleted=FALSE ORDER BY full_name',
            ),
            'locations' => $this->fetchAll(
                'SELECT location_id, client_id, location_code, location_name FROM client_locations WHERE is_deleted=FALSE ORDER BY location_name',
            ),
        ];
    }

    public function summary(string $from, string $to): array
    {
        return $this->fetchOne(
            "SELECT COUNT(*) total,
             SUM(ticket_status='OPEN') open_count,
             SUM(ticket_status='ACCEPTED') accepted_count,
             SUM(ticket_status='COMPLETED') completed_count,
             SUM(ticket_status='DECLINED') declined_count,
             ROUND(AVG(CASE WHEN completed_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, created_at, completed_at) END),2) avg_resolution_minutes
             FROM service_tickets WHERE is_deleted=FALSE AND created_at>=:from_date AND created_at<DATE_ADD(:to_date, INTERVAL 1 DAY)",
            ['from_date' => $from, 'to_date' => $to],
        ) ?? [];
    }

    public function teamPerformance(string $from, string $to): array
    {
        return $this->fetchAll(
            "SELECT e.employee_id, e.full_name, COUNT(a.attempt_id) attempts,
             SUM(a.attempt_status='COMPLETED') completed,
             SUM(a.attempt_status='RELEASED') released,
             ROUND(AVG(CASE WHEN a.ended_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE,a.accepted_at,a.ended_at) END),2) avg_attempt_minutes
             FROM employees e LEFT JOIN service_attempts a ON a.employee_id=e.employee_id
               AND a.accepted_at>=:from_date AND a.accepted_at<DATE_ADD(:to_date, INTERVAL 1 DAY) AND a.is_deleted=FALSE
             WHERE e.is_deleted=FALSE GROUP BY e.employee_id ORDER BY completed DESC, attempts DESC",
            ['from_date' => $from, 'to_date' => $to],
        );
    }
}
