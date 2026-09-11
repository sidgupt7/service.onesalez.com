<?php

declare(strict_types=1);

namespace App\Database;

use App\Exceptions\DatabaseException;
use PDO;
use PDOException;
use Throwable;

final class Database extends AbstractDatabase
{
    private ?PDO $connection = null;

    public function __construct(private readonly array $config)
    {
    }

    public function connection(): PDO
    {
        if ($this->connection instanceof PDO) {
            return $this->connection;
        }

        return $this->connection = $this->connectWithRetry();
    }

    public function transaction(callable $operation): mixed
    {
        $pdo = $this->connection();
        $pdo->beginTransaction();

        try {
            $result = $operation($pdo);
            $pdo->commit();
            return $result;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    private function connectWithRetry(): PDO
    {
        $attempts = max(1, (int) $this->config['retries']);
        $lastException = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                return new PDO($this->dsn(), $this->config['username'], $this->config['password'], $this->options());
            } catch (PDOException $exception) {
                $lastException = $exception;
                if ($attempt < $attempts) {
                    usleep(100_000 * $attempt);
                }
            }
        }

        throw new DatabaseException('Unable to connect to the database.', $lastException);
    }

    private function dsn(): string
    {
        return sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $this->config['host'],
            $this->config['port'],
            $this->config['database'],
        );
    }

    private function options(): array
    {
        return [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => (bool) $this->config['persistent'],
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+05:30'",
        ];
    }
}
