<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AuthenticationException;
use App\Models\Actor;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Throwable;

final class TokenService
{
    public function __construct(private readonly array $config)
    {
        if (strlen($this->config['key']) < 32) {
            throw new \RuntimeException('APP_KEY must contain at least 32 characters.');
        }
    }

    public function accessToken(Actor $actor): string
    {
        $now = time();
        return JWT::encode([
            'iss' => $this->config['jwt_issuer'],
            'sub' => $actor->identifier(),
            'iat' => $now,
            'exp' => $now + $this->config['jwt_access_ttl'],
            'actor' => [
                'id' => $actor->id,
                'type' => $actor->type,
                'email' => $actor->email,
                'display_name' => $actor->displayName,
                'client_id' => $actor->clientId,
                'roles' => $actor->roles,
                'permissions' => $actor->permissions,
            ],
        ], $this->config['key'], 'HS256');
    }

    public function decode(string $token): Actor
    {
        try {
            $payload = JWT::decode($token, new Key($this->config['key'], 'HS256'));
            if ($payload->iss !== $this->config['jwt_issuer']) {
                throw new AuthenticationException('The access token issuer is invalid.');
            }
            $actor = (array) $payload->actor;
            return new Actor(
                (int) $actor['id'],
                (string) $actor['type'],
                (string) $actor['email'],
                isset($actor['client_id']) ? (int) $actor['client_id'] : null,
                array_values((array) $actor['roles']),
                array_values((array) $actor['permissions']),
                isset($actor['display_name']) ? (string) $actor['display_name'] : null,
            );
        } catch (AuthenticationException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new AuthenticationException('The access token is invalid or expired.');
        }
    }

    public function opaqueToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function hashOpaqueToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
