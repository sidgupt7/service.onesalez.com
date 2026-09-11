<?php

declare(strict_types=1);

namespace App\Exceptions;

final class AuthorizationException extends HttpException
{
    public function __construct(string $message = 'You are not permitted to perform this action.')
    {
        parent::__construct($message, 403, 'FORBIDDEN');
    }
}
