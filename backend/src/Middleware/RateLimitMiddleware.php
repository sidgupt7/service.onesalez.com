<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Exceptions\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Models\Actor;
use App\Repositories\RateLimitRepository;

final class RateLimitMiddleware implements Middleware
{
    public function __construct(
        private readonly RateLimitRepository $repository,
        private readonly int $limit,
        private readonly int $windowSeconds,
    ) {
    }

    public function process(Request $request, callable $next): Response
    {
        $actor = $request->attribute('actor');
        $identity = $actor instanceof Actor ? $actor->identifier() : $request->ipAddress;
        $key = hash('sha256', $identity . '|' . $request->path);
        if ($this->repository->hit($key, $this->windowSeconds) > $this->limit) {
            throw new HttpException('Too many requests. Please try again later.', 429, 'RATE_LIMITED');
        }
        return $next($request);
    }
}
