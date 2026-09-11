<?php

declare(strict_types=1);

namespace App\Support;

use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

final class LoggerFactory
{
    public static function create(string $directory): LoggerInterface
    {
        if (!is_dir($directory)) {
            mkdir($directory, 0770, true);
        }
        $configuredLevel = strtolower(getenv('LOG_LEVEL') ?: 'info');
        $levels = [
            'debug' => Level::Debug,
            'info' => Level::Info,
            'notice' => Level::Notice,
            'warning' => Level::Warning,
            'error' => Level::Error,
            'critical' => Level::Critical,
            'alert' => Level::Alert,
            'emergency' => Level::Emergency,
        ];
        $handler = new RotatingFileHandler(
            $directory . '/application.log',
            (int) (getenv('LOG_MAX_FILES') ?: 14),
            $levels[$configuredLevel] ?? Level::Info,
        );
        $handler->setFormatter(new JsonFormatter(JsonFormatter::BATCH_MODE_JSON, true, true));
        return new Logger('onesalez-service', [$handler]);
    }
}
