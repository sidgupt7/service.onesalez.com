<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AuthenticationException;
use App\Exceptions\ValidationException;
use App\Models\Actor;
use App\Repositories\AuthRepository;

final class AuthService
{
    public function __construct(
        private readonly AuthRepository $repository,
        private readonly TokenService $tokens,
        private readonly array $config,
    ) {
    }

    public function login(string $email, string $password, string $realm, string $ip, ?string $agent): array
    {
        $account = $realm === 'client'
            ? $this->repository->findClientAccount($email)
            : $this->repository->findEmployeeAccount($email);

        if ($account === null) {
            throw new AuthenticationException('Email or password is incorrect.');
        }
        if ($account['account_status'] !== 'ACTIVE' || $this->isLocked($account['locked_until'])) {
            throw new AuthenticationException('This account is unavailable or temporarily locked.');
        }
        if (!password_verify($password, (string) $account['password_hash'])) {
            $this->repository->recordFailedLogin($realm, (int) $account['account_id']);
            throw new AuthenticationException('Email or password is incorrect.');
        }

        return $this->repository->transaction(function () use ($account, $realm, $ip, $agent): array {
            $type = $realm === 'client' ? 'CLIENT_CONTACT' : 'EMPLOYEE';
            $this->repository->lockActor($type, (int) $account['actor_id']);
            $actor = $this->repository->actor($type, (int) $account['actor_id']);
            if ($actor === null || $this->repository->passwordHash($actor) !== $account['password_hash']) {
                throw new AuthenticationException('The account changed while signing in. Please try again.');
            }
            $this->repository->recordLogin($realm, (int) $account['account_id']);
            return $this->issueTokens($actor, $ip, $agent);
        });
    }

    public function enrollTrustedDevice(Actor $actor, string $pin, string $deviceName, string $ip, ?string $agent): array
    {
        return $this->repository->transaction(function () use ($actor, $pin, $deviceName, $ip, $agent): array {
            $this->repository->lockActor($actor->type, $actor->id);
            if (!$this->repository->sessionActive($actor) || $this->repository->actor($actor->type, $actor->id) === null) {
                throw new AuthenticationException('Sign in again before enabling PIN login.');
            }
            $deviceId = bin2hex(random_bytes(16));
            $deviceToken = $this->tokens->opaqueToken();
            $expiresAtTimestamp = time() + $this->config['trusted_device_ttl'];
            $expiresAt = (new \DateTimeImmutable('@' . $expiresAtTimestamp))->setTimezone(new \DateTimeZone('Asia/Kolkata'))->format('Y-m-d H:i:s.u');
            $this->repository->createTrustedDevice(
                $actor,
                $deviceId,
                $deviceName,
                $this->tokens->hashOpaqueToken($deviceToken),
                password_hash($pin, PASSWORD_BCRYPT, ['cost' => 12]),
                $expiresAt,
                $ip,
                $agent,
            );

            return [
            'device_id' => $deviceId,
            'device_token' => $deviceToken,
            'device_name' => $deviceName,
            'expires_at' => gmdate(DATE_ATOM, $expiresAtTimestamp),
            'actor' => $actor,
            ];
        });
    }

    public function pinLogin(string $deviceId, string $deviceToken, string $pin, string $ip, ?string $agent): array
    {
        $device = $this->repository->trustedDevice(
            $deviceId,
            $this->tokens->hashOpaqueToken($deviceToken),
        );
        if ($device === null) {
            throw new AuthenticationException('Quick login is unavailable for this device. Sign in with your password.');
        }
        if ($this->isLocked($device['locked_until'])) {
            throw new AuthenticationException('Quick login is temporarily locked. Try again in 15 minutes or use your password.');
        }
        if (!password_verify($pin, (string) $device['pin_hash'])) {
            $this->repository->recordTrustedDeviceFailure((int) $device['trusted_device_id']);
            throw new AuthenticationException('The PIN is incorrect.');
        }

        return $this->repository->transaction(function () use ($device, $deviceId, $deviceToken, $ip, $agent): array {
            $this->repository->lockActor((string) $device['actor_type'], (int) $device['actor_id']);
            $fresh = $this->repository->trustedDevice($deviceId, $this->tokens->hashOpaqueToken($deviceToken), true);
            $actor = $this->repository->actor((string) $device['actor_type'], (int) $device['actor_id']);
            if ($fresh === null || $actor === null || $this->isLocked($fresh['locked_until'])) {
                throw new AuthenticationException('Quick login is no longer available. Sign in with your password.');
            }
            $this->repository->recordTrustedDeviceLogin((int) $device['trusted_device_id'], $ip, $agent);
            return $this->issueTokens($actor, $ip, $agent);
        });
    }

    public function revokeTrustedDevice(Actor $actor, string $deviceId): void
    {
        $this->repository->revokeTrustedDevice($actor, $deviceId);
    }

    public function refresh(string $refreshToken, string $ip, ?string $agent): array
    {
        return $this->repository->transaction(function () use ($refreshToken, $ip, $agent): array {
            $stored = $this->repository->consumeRefreshToken($this->tokens->hashOpaqueToken($refreshToken));
            if ($stored === null) {
                throw new AuthenticationException('The refresh token is invalid or expired.');
            }
            $this->repository->lockActor((string) $stored['actor_type'], (int) $stored['actor_id']);
            $stored = $this->repository->consumeRefreshToken($this->tokens->hashOpaqueToken($refreshToken), true);
            if ($stored === null) {
                throw new AuthenticationException('The refresh token has already been used.');
            }
            $actor = $this->repository->actor((string) $stored['actor_type'], (int) $stored['actor_id']);
            if ($actor === null) {
                throw new AuthenticationException('The account is no longer active.');
            }

            $new = $this->issueTokens($actor, $ip, $agent);
            $newStored = $this->repository->consumeRefreshToken($this->tokens->hashOpaqueToken($new['refresh_token']));
            $this->repository->revokeRefreshToken(
                (int) $stored['refresh_token_id'],
                $newStored === null ? null : (int) $newStored['refresh_token_id'],
            );
            return $new;
        });
    }

    public function logout(string $refreshToken): void
    {
        $stored = $this->repository->consumeRefreshToken($this->tokens->hashOpaqueToken($refreshToken));
        if ($stored !== null) {
            $this->repository->revokeRefreshToken((int) $stored['refresh_token_id']);
        }
    }

    public function requestPasswordReset(string $email, string $realm): void
    {
        $token = $this->tokens->opaqueToken();
        $url = $this->config['url'] . '/reset-password?realm=' . rawurlencode($realm) . '&token=' . rawurlencode($token);
        $this->repository->createPasswordReset(
            $email,
            $realm,
            $this->tokens->hashOpaqueToken($token),
            $url,
        );
    }

    public function resetPassword(string $token, string $password, string $realm): void
    {
        if (
            !$this->repository->resetPassword(
                $realm,
                $this->tokens->hashOpaqueToken($token),
                password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
            )
        ) {
            throw new AuthenticationException('The reset token is invalid or expired.');
        }
    }

    public function changePassword(Actor $actor, string $currentPassword, string $newPassword): void
    {
        $currentHash = $this->repository->passwordHash($actor);
        if ($currentHash === null || !password_verify($currentPassword, $currentHash)) {
            throw new AuthenticationException('The current password is incorrect.');
        }
        if (password_verify($newPassword, $currentHash)) {
            throw new ValidationException(['new_password' => ['The new password must be different from the current password.']]);
        }
        if (
            !$this->repository->changePassword(
                $actor,
                password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]),
                $currentHash,
            )
        ) {
            throw new AuthenticationException('The account is no longer available.');
        }
    }

    private function issueTokens(Actor $actor, string $ip, ?string $agent): array
    {
        $refresh = $this->tokens->opaqueToken();
        $sessionId = $this->repository->createRefreshToken(
            $actor,
            $this->tokens->hashOpaqueToken($refresh),
            (new \DateTimeImmutable('@' . (time() + $this->config['jwt_refresh_ttl'])))->setTimezone(new \DateTimeZone('Asia/Kolkata'))->format('Y-m-d H:i:s.u'),
            $ip,
            $agent,
        );
        return [
            'access_token' => $this->tokens->accessToken($actor, $sessionId),
            'refresh_token' => $refresh,
            'token_type' => 'Bearer',
            'expires_in' => $this->config['jwt_access_ttl'],
            'actor' => $actor,
        ];
    }

    private function isLocked(mixed $lockedUntil): bool
    {
        return $lockedUntil !== null && strtotime((string) $lockedUntil) > time();
    }
}
