<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\AbstractDatabase;
use App\Exceptions\DatabaseException;
use PDO;
use PDOException;

abstract class BaseRepository
{
    protected PDO $pdo;

    public function __construct(protected readonly AbstractDatabase $database)
    {
        $this->pdo = $database->connection();
    }

    protected function fetchOne(string $sql, array $parameters = []): ?array
    {
        try {
            $statement = $this->pdo->prepare($sql);
            $statement->execute($parameters);
            $row = $statement->fetch();
            return $row === false ? null : $row;
        } catch (PDOException $exception) {
            throw new DatabaseException(previous: $exception);
        }
    }

    protected function fetchAll(string $sql, array $parameters = []): array
    {
        try {
            $statement = $this->pdo->prepare($sql);
            $statement->execute($parameters);
            return $statement->fetchAll();
        } catch (PDOException $exception) {
            throw new DatabaseException(previous: $exception);
        }
    }

    protected function execute(string $sql, array $parameters = []): int
    {
        try {
            $statement = $this->pdo->prepare($sql);
            $statement->execute($parameters);
            return $statement->rowCount();
        } catch (PDOException $exception) {
            throw new DatabaseException(previous: $exception);
        }
    }

    protected function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $parameters = array_map(static fn (string $column): string => ':' . $column, $columns);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $columns),
            implode(', ', $parameters),
        );
        $this->execute($sql, $data);
        return (int) $this->pdo->lastInsertId();
    }

    protected function updateById(string $table, string $idColumn, int $id, array $data): bool
    {
        $sets = array_map(static fn (string $column): string => $column . ' = :' . $column, array_keys($data));
        $data['entity_id'] = $id;
        $sql = sprintf('UPDATE %s SET %s WHERE %s = :entity_id', $table, implode(', ', $sets), $idColumn);
        return $this->execute($sql, $data) > 0;
    }
}
