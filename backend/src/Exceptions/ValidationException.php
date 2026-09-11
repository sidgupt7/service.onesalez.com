<?php

declare(strict_types=1);

namespace App\Exceptions;

final class ValidationException extends HttpException
{
    public function __construct(array $errors)
    {
        parent::__construct('Validation failed.', 422, 'VALIDATION_ERROR', $errors);
    }
}
