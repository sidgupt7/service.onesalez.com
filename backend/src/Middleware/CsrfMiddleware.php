<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Exceptions\AuthorizationException;
use App\Http\Request;
use App\Http\Response;

final class CsrfMiddleware implements Middleware
{
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function process(Request $request, callable $next): Response
    {
        if (in_array($request->method, self::SAFE_METHODS, true)) {
            return $next($request);
        }

        $cookieToken = $_COOKIE['XSRF-TOKEN'] ?? null;
        $headerToken = $request->header('x-xsrf-token');
        if (!is_string($cookieToken) || !is_string($headerToken) || !hash_equals($cookieToken, $headerToken)) {
            throw new AuthorizationException('CSRF validation failed.');
        }
        return $next($request);
    }
}
