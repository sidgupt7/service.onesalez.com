<?php

declare(strict_types=1);

use App\Database\Database;
use Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';
Dotenv::createUnsafeImmutable(dirname(__DIR__))->safeLoad();
$pdo = (new Database(require dirname(__DIR__) . '/config/database.php'))->connection();

$pdo->beginTransaction();
$query = $pdo->query(
    "SELECT * FROM email_jobs WHERE job_status='PENDING' AND available_at<=NOW(6) "
    . 'ORDER BY email_job_id LIMIT 1 FOR UPDATE SKIP LOCKED'
);
if ($query === false) {
    $pdo->rollBack();
    throw new RuntimeException('Unable to read the email queue.');
}
$job = $query->fetch();
if ($job === false) {
    $pdo->commit();
    exit(0);
}
$pdo->prepare('UPDATE email_jobs SET attempts=attempts+1 WHERE email_job_id=:id')
    ->execute(['id' => $job['email_job_id']]);
$pdo->commit();

$sent = mail(
    $job['recipient_email'],
    $job['subject'],
    $job['body'],
    ['From' => getenv('MAIL_FROM') ?: 'noreply@onesalez.com'],
);
$statement = $pdo->prepare(
    $sent
        ? "UPDATE email_jobs SET job_status='SENT', processed_at=NOW(6) WHERE email_job_id=:id"
        : "UPDATE email_jobs SET job_status=IF(attempts>=3,'FAILED','PENDING'), "
            . "available_at=DATE_ADD(NOW(6),INTERVAL 5 MINUTE), "
            . "last_error='mail() returned false' WHERE email_job_id=:id"
);
$statement->execute(['id' => $job['email_job_id']]);
