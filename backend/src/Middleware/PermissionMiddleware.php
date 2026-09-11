<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Exceptions\AuthorizationException;
use App\Http\Request;
use App\Http\Response;
use App\Models\Actor;

final class PermissionMiddleware implements Middleware
{
    public function __construct(private readonly string $permission)
    {
    }

    public function process(Request $request, callable $next): Response
    {
        $actor = $request->attribute('actor');
        if (!$actor instanceof Actor || !$actor->can($this->permission)) {
            throw new AuthorizationException();
        }
        return $next($request);
    }
}
