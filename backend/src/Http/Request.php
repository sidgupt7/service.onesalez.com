<?php

declare(strict_types=1);

namespace App\Http;

use App\Exceptions\BadRequestException;

final class Request
{
    private array $attributes = [];

    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query,
        public readonly array $body,
        public readonly array $headers,
        public readonly string $ipAddress,
        public readonly array $cookies = [],
    ) {
    }

    public static function fromGlobals(): self
    {
        $rawBody = file_get_contents('php://input') ?: '';
        $body = $rawBody === '' ? [] : json_decode($rawBody, true);
        if (!is_array($body)) {
            throw new BadRequestException('The request body must be valid JSON.');
        }

        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        return new self(
            strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
            rawurldecode((string) parse_url($uri, PHP_URL_PATH)),
            $_GET,
            $body,
            self::headers(),
            (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'),
            $_COOKIE,
        );
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function cookie(string $name): ?string
    {
        $value = $this->cookies[$name] ?? null;
        return is_string($value) && $value !== '' ? $value : null;
    }

    public function setAttribute(string $name, mixed $value): void
    {
        $this->attributes[$name] = $value;
    }

    public function attribute(string $name, mixed $default = null): mixed
    {
        return $this->attributes[$name] ?? $default;
    }

    private static function headers(): array
    {
        $headers = [];
        foreach (getallheaders() ?: [] as $name => $value) {
            $headers[strtolower($name)] = trim((string) $value);
        }
        return $headers;
    }
}
