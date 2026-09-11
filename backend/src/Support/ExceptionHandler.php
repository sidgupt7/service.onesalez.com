<?php

declare(strict_types=1);

namespace App\Support;

use App\Exceptions\HttpException;
use App\Http\Request;
use App\Http\Response;
use Psr\Log\LoggerInterface;
use Throwable;

final class ExceptionHandler
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly bool $debug,
    ) {
    }

    public function render(Throwable $exception, ?Request $request = null): Response
    {
        $context = [
            'exception' => $exception,
            'request' => $request === null ? null : [
                'method' => $request->method,
                'path' => $request->path,
                'ip' => $request->ipAddress,
                'actor' => $request->attribute('actor'),
            ],
        ];
        $this->logger->error($exception->getMessage(), $context);

        if ($exception instanceof HttpException) {
            return Response::error(
                $exception->errorCode,
                $exception->getMessage(),
                $exception->statusCode,
                $exception->details,
            );
        }

        $message = $this->debug ? $exception->getMessage() : 'An unexpected error occurred.';
        return Response::error('INTERNAL_ERROR', $message, 500);
    }
}
