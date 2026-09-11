<?php

declare(strict_types=1);

namespace App\Database;

use App\Exceptions\BadRequestException;

final class QueryBuilder
{
    private array $where = [];
    private array $bindings = [];
    private string $orderBy = '';
    private string $limit = '';

    public function where(string $column, string $operator, mixed $value): self
    {
        if (!in_array($operator, ['=', '<>', '>', '>=', '<', '<=', 'LIKE'], true)) {
            throw new BadRequestException('Unsupported query operator.');
        }

        $parameter = ':q' . count($this->bindings);
        $this->where[] = sprintf('%s %s %s', $column, $operator, $parameter);
        $this->bindings[$parameter] = $value;
        return $this;
    }

    public function orderBy(string $column, string $direction): self
    {
        $this->orderBy = sprintf(' ORDER BY %s %s', $column, strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC');
        return $this;
    }

    public function paginate(int $page, int $limit): self
    {
        $offset = (max(1, $page) - 1) * $limit;
        $this->limit = sprintf(' LIMIT %d OFFSET %d', $limit, $offset);
        return $this;
    }

    public function compile(string $baseSql): array
    {
        $sql = $baseSql;
        if ($this->where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $this->where);
        }

        return [$sql . $this->orderBy . $this->limit, $this->bindings];
    }
}
