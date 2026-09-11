<?php

declare(strict_types=1);

use App\Http\Request;

try {
    $application = require dirname(__DIR__) . '/app/bootstrap.php';
    $application->run(Request::fromGlobals())->send();
} catch (Throwable $exception) {
    error_log(sprintf('[%s] %s: %s', gmdate(DATE_ATOM), $exception::class, $exception->getMessage()));
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'data' => null,
        'error' => ['code' => 'BOOTSTRAP_ERROR', 'message' => 'The service is temporarily unavailable.'],
        'timestamp' => gmdate(DATE_ATOM),
    ], JSON_UNESCAPED_SLASHES);
}
