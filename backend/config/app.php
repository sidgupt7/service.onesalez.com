<?php

declare(strict_types=1);

return [
    'env' => getenv('APP_ENV') ?: 'production',
    'debug' => filter_var(getenv('APP_DEBUG') ?: false, FILTER_VALIDATE_BOOL),
    'url' => rtrim(getenv('APP_URL') ?: 'http://localhost:8080', '/'),
    'timezone' => getenv('APP_TIMEZONE') ?: 'Asia/Kolkata',
    'key' => getenv('APP_KEY') ?: '',
    'jwt_issuer' => getenv('JWT_ISSUER') ?: 'onesalez-service-crm',
    'jwt_access_ttl' => (int) (getenv('JWT_ACCESS_TTL') ?: 900),
    'jwt_refresh_ttl' => (int) (getenv('JWT_REFRESH_TTL') ?: 2592000),
    'trusted_device_ttl' => (int) (getenv('TRUSTED_DEVICE_TTL') ?: 15552000),
    'refresh_cookie_name' => getenv('REFRESH_COOKIE_NAME') ?: 'onesalez_refresh',
    'refresh_cookie_secure' => filter_var(getenv('REFRESH_COOKIE_SECURE') ?: true, FILTER_VALIDATE_BOOL),
    'refresh_cookie_samesite' => getenv('REFRESH_COOKIE_SAMESITE') ?: 'Strict',
    'rate_limit_requests' => (int) (getenv('RATE_LIMIT_REQUESTS') ?: 120),
    'rate_limit_window' => (int) (getenv('RATE_LIMIT_WINDOW') ?: 60),
    'cors_allowed_origins' => array_filter(
        array_map('trim', explode(',', getenv('CORS_ALLOWED_ORIGINS') ?: ''))
    ),
];
