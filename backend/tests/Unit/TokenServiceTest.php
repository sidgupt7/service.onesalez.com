<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Actor;
use App\Services\TokenService;
use PHPUnit\Framework\TestCase;

final class TokenServiceTest extends TestCase
{
    public function testAccessTokenRoundTripPreservesActor(): void
    {
        $service = new TokenService([
            'key' => str_repeat('a', 32),
            'jwt_issuer' => 'test-suite',
            'jwt_access_ttl' => 900,
        ]);
        $actor = new Actor(7, 'EMPLOYEE', 'admin@example.com', null, ['SYSTEM_ADMIN'], ['tickets.manage']);
        $decoded = $service->decode($service->accessToken($actor));
        self::assertSame($actor->id, $decoded->id);
        self::assertSame($actor->roles, $decoded->roles);
        self::assertTrue($decoded->can('anything'));
    }

    public function testOpaqueTokenIsRandomAndHashable(): void
    {
        $service = new TokenService(['key' => str_repeat('b', 32)]);
        $first = $service->opaqueToken();
        self::assertNotSame($first, $service->opaqueToken());
        self::assertSame(64, strlen($service->hashOpaqueToken($first)));
    }
}
