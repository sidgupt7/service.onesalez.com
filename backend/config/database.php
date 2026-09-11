<?php

declare(strict_types=1);

return [
    'host' => getenv('DB_HOST') ?: '127.0.0.1',
    'port' => (int) (getenv('DB_PORT') ?: 3306),
    'database' => getenv('DB_NAME') ?: '',
    'username' => getenv('DB_USER') ?: '',
    'password' => getenv('DB_PASSWORD') ?: '',
    'retries' => (int) (getenv('DB_CONNECT_RETRIES') ?: 3),
    'persistent' => filter_var(getenv('DB_PERSISTENT') ?: true, FILTER_VALIDATE_BOOL),
];

