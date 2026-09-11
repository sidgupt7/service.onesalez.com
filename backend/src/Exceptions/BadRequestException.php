<?php

declare(strict_types=1);

namespace App\Exceptions;

final class BadRequestException extends HttpException
{
    public function __construct(string $message = 'The request is invalid.', array $details = [])
    {
        parent::__construct($message, 400, 'BAD_REQUEST', $details);
    }
}
