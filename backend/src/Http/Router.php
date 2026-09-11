<?php

declare(strict_types=1);

namespace App\Http;

use App\Exceptions\NotFoundException;
use App\Middleware\Middleware;

final class Router
{
    /** @var list<array{string, string, callable, list<Middleware>}> */
    private array $routes = [];

    /** @param list<Middleware> $middleware */
    public function add(string $method, string $path, callable $handler, array $middleware = []): void
    {
        $pattern = preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', '(?P<$1>[^/]+)', $path);
        $this->routes[] = [$method, '#^' . $pattern . '$#', $handler, $middleware];
    }

    public function dispatch(Request $request): Response
    {
        foreach ($this->routes as [$method, $pattern, $handler, $middleware]) {
            if ($method !== $request->method || preg_match($pattern, $request->path, $matches) !== 1) {
                continue;
            }

            $parameters = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            $core = static fn (Request $request): Response => $handler($request, $parameters);
            $pipeline = array_reduce(
                array_reverse($middleware),
                static fn (callable $next, Middleware $item): callable =>
                    static fn (Request $request): Response => $item->process($request, $next),
                $core,
            );
            return $pipeline($request);
        }

        throw new NotFoundException('API endpoint not found.');
    }
}
