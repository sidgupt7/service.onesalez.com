<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Utils\Input;

final class LeadRepository extends BaseRepository
{
    public function convert(int $id, callable $createClient, string $actor): array
    {
        return $this->database->transaction(function () use ($id, $createClient, $actor): array {
            $lead = $this->fetchOne('SELECT * FROM leads WHERE lead_id=:id AND is_deleted=FALSE FOR UPDATE', ['id' => $id]);
            if ($lead === null) {
                throw new \App\Exceptions\NotFoundException('Lead not found.');
            }
            if ($lead['converted_client_id'] !== null) {
                throw new \App\Exceptions\BadRequestException('This lead has already been converted.');
            }
            $client = $createClient();
            $this->updateById('leads', 'lead_id', $id, ['status' => 'WON', 'converted_client_id' => $client['client_id'], 'updated_by' => $actor]);
            return $client;
        });
    }

    private const SORTS = ['business_name', 'status', 'created_at'];

    public function paginate(array $filters): array
    {
        $page = Input::positiveInt($filters['page'] ?? null, 1);
        $limit = min(100, Input::positiveInt($filters['limit'] ?? null, 20));
        $where = ['is_deleted=FALSE'];
        $params = [];
        if (!empty($filters['status'])) {
            $where[] = 'status=:status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['search'])) {
            $where[] = '(business_name LIKE :search OR contact_name LIKE :search_contact OR email LIKE :search_email)';
            $params['search'] = '%' . $filters['search'] . '%';
            $params['search_contact'] = $params['search'];
            $params['search_email'] = $params['search'];
        }
        $sort = in_array($filters['sort'] ?? '', self::SORTS, true) ? $filters['sort'] : 'created_at';
        $order = strtoupper((string) ($filters['order'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
        $condition = implode(' AND ', $where);
        $total = $this->fetchOne("SELECT COUNT(*) AS total FROM leads WHERE {$condition}", $params);
        $offset = ($page - 1) * $limit;
        $rows = $this->fetchAll(
            "SELECT * FROM leads WHERE {$condition} ORDER BY {$sort} {$order} LIMIT {$limit} OFFSET {$offset}",
            $params,
        );
        return ['items' => $rows, 'total' => (int) ($total['total'] ?? 0), 'page' => $page, 'limit' => $limit];
    }

    public function find(int $id): ?array
    {
        return $this->fetchOne('SELECT * FROM leads WHERE lead_id=:id AND is_deleted=FALSE', ['id' => $id]);
    }

    public function create(array $data, string $actor): int
    {
        return $this->insert('leads', $data + ['created_by' => $actor]);
    }

    public function update(int $id, array $data, string $actor): bool
    {
        return $this->updateById('leads', 'lead_id', $id, $data + ['updated_by' => $actor]);
    }

    public function softDelete(int $id, string $actor): bool
    {
        return $this->execute(
            'UPDATE leads SET is_deleted=TRUE, deleted_by=:actor, deleted_at=NOW(6) WHERE lead_id=:id AND is_deleted=FALSE',
            ['actor' => $actor, 'id' => $id],
        ) > 0;
    }

    public function bulkStatus(array $ids, string $status, string $actor): int
    {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        return $this->execute(
            "UPDATE leads SET status=?, updated_by=? WHERE lead_id IN ({$placeholders}) AND is_deleted=FALSE",
            array_merge([$status, $actor], $ids),
        );
    }
}
