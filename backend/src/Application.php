<?php

declare(strict_types=1);

namespace App;

use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use App\Middleware\Middleware;
use App\Support\ExceptionHandler;
use Throwable;

final class Application
{
    /** @param list<Middleware> $middleware */
    public function __construct(
        private readonly Router $router,
        private readonly ExceptionHandler $exceptions,
        private readonly array $middleware,
    ) {
    }

    public function run(Request $request): Response
    {
        try {
            $core = fn (Request $request): Response => $this->router->dispatch($request);
            $pipeline = array_reduce(
                array_reverse($this->middleware),
                static fn (callable $next, Middleware $middleware): callable =>
                    static fn (Request $request): Response => $middleware->process($request, $next),
                $core,
            );
            return $pipeline($request);
        } catch (Throwable $exception) {
            return $this->exceptions->render($exception, $request);
        }
    }
}
