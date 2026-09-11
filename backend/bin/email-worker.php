<?php

declare(strict_types=1);

use App\Database\Database;
use App\Services\SmtpMailer;
use Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';
Dotenv::createUnsafeImmutable(dirname(__DIR__))->safeLoad();
$pdo = (new Database(require dirname(__DIR__) . '/config/database.php'))->connection();
$mailer = new SmtpMailer(require dirname(__DIR__) . '/config/mail.php');
// Hold a connection-scoped lock through SMTP transmission, not just queue selection.
$lockName = 'onesalez-email-' . substr(hash('sha256', (string) getenv('DB_NAME')), 0, 32);
$lock = $pdo->prepare('SELECT GET_LOCK(:name, 0)');
$lock->execute(['name' => $lockName]);
if ((int) $lock->fetchColumn() !== 1) {
    exit(0);
}
$failed = false;
try {
    $deadline = time() + 45;
    for ($count = 0; $count < 20 && time() < $deadline; $count++) {
        $job = $pdo->query("SELECT * FROM email_jobs WHERE job_status='PENDING' AND available_at<=NOW(6) ORDER BY email_job_id LIMIT 1")->fetch();
        if ($job === false) {
            break;
        }
        // Never deliver an expired or superseded password-reset link.
        if (str_contains($job['body'], '/reset-password?')) {
            preg_match('~https?://[^\s]+~', $job['body'], $matches);
            parse_str((string) parse_url($matches[0] ?? '', PHP_URL_QUERY), $parameters);
            $valid = false;
            if (isset($parameters['token'], $parameters['realm']) && is_string($parameters['token']) && in_array($parameters['realm'], ['employee', 'client'], true)) {
                $table = $parameters['realm'] === 'employee' ? 'employee_user_accounts' : 'client_user_accounts';
                $check = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE password_reset_token_hash=:hash AND password_reset_expires_at>NOW(6) AND is_deleted=FALSE");
                $check->execute(['hash' => hash('sha256', $parameters['token'])]);
                $valid = (int) $check->fetchColumn() > 0;
            }
            if (!$valid) {
                $pdo->prepare("UPDATE email_jobs SET job_status='FAILED', processed_at=NOW(6), last_error='Reset link expired or superseded; request a new link' WHERE email_job_id=:id")->execute(['id' => $job['email_job_id']]);
                continue;
            }
        }
        $pdo->prepare('UPDATE email_jobs SET attempts=attempts+1 WHERE email_job_id=:id')->execute(['id' => $job['email_job_id']]);
        try {
            $mailer->send($job['recipient_email'], $job['subject'], $job['body']);
            $pdo->prepare("UPDATE email_jobs SET job_status='SENT', processed_at=NOW(6), last_error=NULL WHERE email_job_id=:id")->execute(['id' => $job['email_job_id']]);
            echo 'SMTP accepted email job ' . $job['email_job_id'] . PHP_EOL;
        } catch (Throwable $exception) {
            // Do not record credentials, reset tokens, or SMTP conversation in errors.
            $pdo->prepare("UPDATE email_jobs SET job_status=IF(attempts>=3,'FAILED','PENDING'), available_at=DATE_ADD(NOW(6),INTERVAL 2 MINUTE), last_error='SMTP delivery failed; check mailbox credentials and server connectivity' WHERE email_job_id=:id")->execute(['id' => $job['email_job_id']]);
            fwrite(STDERR, 'SMTP delivery failed for email job ' . $job['email_job_id'] . PHP_EOL);
            $failed = true;
            break;
        }
    }
} finally {
    $pdo->prepare('SELECT RELEASE_LOCK(:name)')->execute(['name' => $lockName]);
}
exit($failed ? 1 : 0);
