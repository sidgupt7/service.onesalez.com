<?php
declare(strict_types=1);
return [
    'host' => getenv('SMTP_HOST') ?: 'smtp.hostinger.com',
    'port' => (int) (getenv('SMTP_PORT') ?: 465),
    'encryption' => getenv('SMTP_ENCRYPTION') ?: 'ssl',
    'username' => getenv('SMTP_USERNAME') ?: '',
    'password' => getenv('SMTP_PASSWORD') ?: '',
    'from' => getenv('MAIL_FROM') ?: 'serviceadmin@onesalez.com',
    'from_name' => getenv('MAIL_FROM_NAME') ?: 'ONESALEZ Service CRM',
];
