<?php

declare(strict_types=1);

namespace App\Repositories;

final class RateLimitRepository extends BaseRepository
{
    public function hit(string $key, int $windowSeconds): int
    {
        $windowSeconds = max(1, $windowSeconds);
        $this->execute(
            "INSERT INTO api_rate_limits (rate_limit_key, window_started_at, request_count, expires_at)
             VALUES (:key, NOW(6), 1, DATE_ADD(NOW(6), INTERVAL {$windowSeconds} SECOND))
             ON DUPLICATE KEY UPDATE
                request_count=IF(expires_at<=NOW(6), 1, request_count+1),
                window_started_at=IF(expires_at<=NOW(6), NOW(6), window_started_at),
                expires_at=IF(expires_at<=NOW(6), DATE_ADD(NOW(6), INTERVAL {$windowSeconds} SECOND), expires_at)",
            ['key' => $key],
        );
        $row = $this->fetchOne('SELECT request_count FROM api_rate_limits WHERE rate_limit_key=:key', ['key' => $key]);
        return (int) ($row['request_count'] ?? 0);
    }
}
