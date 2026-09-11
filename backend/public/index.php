<?php

declare(strict_types=1);

use App\Http\Request;

try {
    $application = require dirname(__DIR__) . '/bootstrap.php';
    $application->run(Request::fromGlobals())->send();
} catch (Throwable $exception) {
    $logEntry = json_encode([
        'timestamp' => gmdate(DATE_ATOM),
        'level' => 'critical',
        'message' => $exception->getMessage(),
        'exception' => $exception::class,
        'trace' => $exception->getTraceAsString(),
    ], JSON_UNESCAPED_SLASHES);
    error_log($logEntry === false ? 'Unable to encode bootstrap exception.' : $logEntry);
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'data' => null,
        'error' => ['code' => 'BOOTSTRAP_ERROR', 'message' => 'The service is temporarily unavailable.'],
        'timestamp' => gmdate(DATE_ATOM),
    ], JSON_UNESCAPED_SLASHES);
}
