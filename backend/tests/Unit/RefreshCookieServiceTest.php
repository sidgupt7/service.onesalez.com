<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Request;
use App\Services\RefreshCookieService;
use PHPUnit\Framework\TestCase;

final class RefreshCookieServiceTest extends TestCase
{
    private RefreshCookieService $cookies;

    protected function setUp(): void
    {
        $this->cookies = new RefreshCookieService([
            'refresh_cookie_name' => 'onesalez_refresh',
            'refresh_cookie_secure' => true,
            'refresh_cookie_samesite' => 'Strict',
            'jwt_refresh_ttl' => 3600,
        ]);
    }

    public function testIssueHeaderUsesSecureHttpOnlyCookie(): void
    {
        $header = $this->cookies->issue('secret-refresh-token');

        self::assertStringContainsString('onesalez_refresh=secret-refresh-token', $header);
        self::assertStringContainsString('HttpOnly', $header);
        self::assertStringContainsString('Secure', $header);
        self::assertStringContainsString('SameSite=Strict', $header);
        self::assertStringContainsString('Path=/api/v1/auth', $header);
    }

    public function testReadsCookieWithoutUsingRequestBody(): void
    {
        $request = new Request('POST', '/api/v1/auth/refresh', [], [], [], '127.0.0.1', [
            'onesalez_refresh' => 'cookie-token',
        ]);

        self::assertSame('cookie-token', $this->cookies->read($request));
    }

    public function testClearHeaderExpiresCookieImmediately(): void
    {
        $header = $this->cookies->clear();

        self::assertStringContainsString('Max-Age=0', $header);
        self::assertStringContainsString('Expires=Thu, 01 Jan 1970 00:00:00 GMT', $header);
    }
}
