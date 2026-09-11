<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Exceptions\AuthenticationException;
use App\Http\Request;
use App\Http\Response;
use App\Services\TokenService;

final class AuthMiddleware implements Middleware
{
    public function __construct(private readonly TokenService $tokens)
    {
    }

    public function process(Request $request, callable $next): Response
    {
        $authorization = $request->header('authorization') ?? '';
        if (preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches) !== 1) {
            throw new AuthenticationException();
        }
        $request->setAttribute('actor', $this->tokens->decode($matches[1]));
        return $next($request);
    }
}
