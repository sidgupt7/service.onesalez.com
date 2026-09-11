<?php

declare(strict_types=1);

namespace App\Exceptions;

final class DatabaseException extends HttpException
{
    public function __construct(string $message = 'A database operation failed.', ?\Throwable $previous = null)
    {
        parent::__construct($message, 500, 'DATABASE_ERROR', [], $previous);
    }
}
