<?php

declare(strict_types=1);

namespace App\Utils;

final class Input
{
    public static function sanitize(array $input): array
    {
        $clean = [];
        foreach ($input as $key => $value) {
            // Secrets are opaque: trimming or stripping markup changes the password.
            if (in_array($key, ['password', 'current_password', 'new_password', 'confirm_password'], true)) {
                $clean[$key] = $value;
                continue;
            }
            $clean[$key] = is_array($value)
                ? self::sanitize($value)
                : (is_string($value) ? trim(strip_tags($value)) : $value);
        }
        return $clean;
    }

    public static function positiveInt(mixed $value, int $default): int
    {
        $filtered = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $filtered === false ? $default : $filtered;
    }
}
