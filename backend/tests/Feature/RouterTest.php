<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\NotFoundException;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    public function testDispatchesParameterizedRoute(): void
    {
        $router = new Router();
        $router->add('GET', '/api/v1/items/{id}', static fn (Request $request, array $params): Response =>
            Response::success(['id' => $params['id']]));
        $response = $router->dispatch(new Request('GET', '/api/v1/items/42', [], [], [], '127.0.0.1'));
        self::assertSame(42, $response->payload['data']['id'] * 1);
    }

    public function testUnknownRouteReturnsNotFoundException(): void
    {
        $this->expectException(NotFoundException::class);
        (new Router())->dispatch(new Request('GET', '/missing', [], [], [], '127.0.0.1'));
    }
}
