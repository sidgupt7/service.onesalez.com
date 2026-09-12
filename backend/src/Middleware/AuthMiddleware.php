<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Exceptions\AuthenticationException;
use App\Http\Request;
use App\Http\Response;
use App\Services\TokenService;
use App\Repositories\AuthRepository;

final class AuthMiddleware implements Middleware
{
    public function __construct(private readonly TokenService $tokens, private readonly AuthRepository $repository)
    {
    }

    public function process(Request $request, callable $next): Response
    {
        $authorization = $request->header('authorization') ?? '';
        if (preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches) !== 1) {
            throw new AuthenticationException();
        }
        $decoded = $this->tokens->decode($matches[1]);
        if (!$this->repository->sessionActive($decoded)) {
            throw new AuthenticationException('Your session has ended. Please sign in again.');
        }
        $actor = $this->repository->actor($decoded->type, $decoded->id, $decoded->sessionId);
        if ($actor === null) {
            throw new AuthenticationException('This account is no longer active.');
        }
        $request->setAttribute('actor', $actor);
        return $next($request);
    }
}
