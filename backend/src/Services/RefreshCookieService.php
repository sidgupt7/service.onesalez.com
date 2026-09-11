<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Request;

final class RefreshCookieService
{
    public function __construct(private readonly array $config)
    {
    }

    public function read(Request $request): ?string
    {
        return $request->cookie($this->config['refresh_cookie_name']);
    }

    public function issue(string $token): string
    {
        return $this->header($token, $this->config['jwt_refresh_ttl'], gmdate(DATE_COOKIE, time() + $this->config['jwt_refresh_ttl']));
    }

    public function clear(): string
    {
        return $this->header('', 0, 'Thu, 01 Jan 1970 00:00:00 GMT');
    }

    private function header(string $value, int $maxAge, string $expires): string
    {
        $parts = [
            rawurlencode($this->config['refresh_cookie_name']) . '=' . rawurlencode($value),
            'Path=/api/v1/auth',
            'Max-Age=' . $maxAge,
            'Expires=' . $expires,
            'HttpOnly',
            'SameSite=' . $this->sameSite(),
        ];
        if ($this->config['refresh_cookie_secure']) {
            $parts[] = 'Secure';
        }
        return implode('; ', $parts);
    }

    private function sameSite(): string
    {
        $value = ucfirst(strtolower((string) $this->config['refresh_cookie_samesite']));
        return in_array($value, ['Strict', 'Lax', 'None'], true) ? $value : 'Strict';
    }
}
