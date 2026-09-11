<?php

declare(strict_types=1);

namespace App\Http;

final class Response
{
    public function __construct(
        public readonly array $payload,
        public readonly int $status = 200,
        public readonly array $headers = [],
    ) {
    }

    public static function success(mixed $data = null, int $status = 200, array $meta = []): self
    {
        $payload = [
            'success' => true,
            'data' => $data,
            'error' => null,
            'timestamp' => gmdate(DATE_ATOM),
        ];
        if ($meta !== []) {
            $payload['meta'] = $meta;
        }
        return new self($payload, $status);
    }

    public static function error(string $code, string $message, int $status, array $details = []): self
    {
        return new self([
            'success' => false,
            'data' => null,
            'error' => array_filter([
                'code' => $code,
                'message' => $message,
                'details' => $details,
            ], static fn (mixed $value): bool => $value !== []),
            'timestamp' => gmdate(DATE_ATOM),
        ], $status);
    }

    public function send(): never
    {
        http_response_code($this->status);
        header('Content-Type: application/json; charset=utf-8');
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }
        echo json_encode($this->payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public function withHeader(string $name, string $value): self
    {
        return new self($this->payload, $this->status, array_merge($this->headers, [$name => $value]));
    }
}
