<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Http\Request;
use App\Http\Response;

final class CorsMiddleware implements Middleware
{
    public function __construct(private readonly array $allowedOrigins)
    {
    }

    public function process(Request $request, callable $next): Response
    {
        $response = $request->method === 'OPTIONS' ? Response::success() : $next($request);
        $origin = $request->header('origin');
        if ($origin === null || !in_array($origin, $this->allowedOrigins, true)) {
            return $response;
        }
        return new Response($response->payload, $response->status, array_merge($response->headers, [
            'Access-Control-Allow-Origin' => $origin,
            'Access-Control-Allow-Credentials' => 'true',
            'Vary' => 'Origin',
            'Access-Control-Allow-Headers' => 'Authorization, Content-Type, X-XSRF-TOKEN',
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
            'Access-Control-Max-Age' => '600',
        ]));
    }
}
