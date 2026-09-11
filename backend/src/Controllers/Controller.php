<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\AuthenticationException;
use App\Exceptions\BadRequestException;
use App\Http\Request;
use App\Models\Actor;

abstract class Controller
{
    protected function actor(Request $request): Actor
    {
        $actor = $request->attribute('actor');
        return $actor instanceof Actor ? $actor : throw new AuthenticationException();
    }

    protected function id(array $parameters, string $name = 'id'): int
    {
        $id = filter_var($parameters[$name] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $id === false ? throw new BadRequestException('Invalid resource identifier.') : $id;
    }
}
