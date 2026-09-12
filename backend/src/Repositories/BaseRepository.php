<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\AbstractDatabase;
use App\Exceptions\DatabaseException;
use PDO;
use PDOException;
use PDOStatement;

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
            $statement = $this->prepare($sql, $parameters);
            $row = $statement->fetch();
            return $row === false ? null : $row;
        } catch (PDOException $exception) {
            throw new DatabaseException(previous: $exception);
        }
    }

    protected function fetchAll(string $sql, array $parameters = []): array
    {
        try {
            $statement = $this->prepare($sql, $parameters);
            return $statement->fetchAll();
        } catch (PDOException $exception) {
            throw new DatabaseException(previous: $exception);
        }
    }

    protected function execute(string $sql, array $parameters = []): int
    {
        try {
            $statement = $this->prepare($sql, $parameters);
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

    private function prepare(string $sql, array $parameters): PDOStatement
    {
        // Native PDO requires a distinct binding for each occurrence. Skip SQL
        // string literals and quoted identifiers while expanding named bindings.
        if ($parameters !== [] && !array_is_list($parameters)) {
            $expanded = [];
            $sql = preg_replace_callback(
                '/\x27(?:\x27\x27|[^\x27])*\x27|"(?:""|[^"])*"|`[^`]*`|:([a-zA-Z_][a-zA-Z0-9_]*)/',
                static function (array $match) use ($parameters, &$expanded): string {
                    if (!isset($match[1]) || !array_key_exists($match[1], $parameters)) {
                        return $match[0];
                    }
                    $key = 'bound_' . count($expanded);
                    $expanded[$key] = $parameters[$match[1]];
                    return ':' . $key;
                },
                $sql,
            ) ?? throw new DatabaseException('Unable to prepare query bindings.');
            $parameters = $expanded;
        }
        $statement = $this->pdo->prepare($sql);
        foreach ($parameters as $key => $value) {
            $type = match (true) {
                is_bool($value) => PDO::PARAM_BOOL,
                is_int($value) => PDO::PARAM_INT,
                $value === null => PDO::PARAM_NULL,
                default => PDO::PARAM_STR,
            };
            $statement->bindValue(is_int($key) ? $key + 1 : ':' . $key, $value, $type);
        }
        $statement->execute();
        return $statement;
    }

    protected function updateById(string $table, string $idColumn, int $id, array $data): bool
    {
        $sets = array_map(static fn (string $column): string => $column . ' = :' . $column, array_keys($data));
        $data['entity_id'] = $id;
        $sql = sprintf('UPDATE %s SET %s WHERE %s = :entity_id', $table, implode(', ', $sets), $idColumn);
        return $this->execute($sql, $data) > 0;
    }
}
