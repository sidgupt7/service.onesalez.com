<?php

declare(strict_types=1);

use App\Database\Database;
use Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';
Dotenv::createUnsafeImmutable(dirname(__DIR__))->safeLoad();
$pdo = (new Database(require dirname(__DIR__) . '/config/database.php'))->connection();
$lockName = 'onesalez-migrate-' . substr(hash('sha256', (string) getenv('DB_NAME')), 0, 32);
$lock = $pdo->prepare('SELECT GET_LOCK(:name, 10)');
$lock->execute(['name' => $lockName]);
if ((int) $lock->fetchColumn() !== 1) {
    throw new RuntimeException('Another migration process is running.');
}
try {
    $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
        migration VARCHAR(255) NOT NULL PRIMARY KEY,
        applied_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    $pdo->exec('ALTER TABLE schema_migrations ADD COLUMN IF NOT EXISTS checksum CHAR(64) NULL');
    $pdo->exec("CREATE TABLE IF NOT EXISTS schema_migration_attempts (
        migration VARCHAR(255) NOT NULL PRIMARY KEY, checksum CHAR(64) NOT NULL,
        status ENUM('APPLYING','FAILED','APPLIED') NOT NULL,
        started_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
        finished_at DATETIME(6) NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $files = glob(dirname(__DIR__) . '/database/migrations/*.sql') ?: [];
    $priority = ['001_create_client_tables.sql' => 10, '002_apply_client_authentication.sql' => 20,
        'employee.sql' => 30, 'service.sql' => 40, '003_application_features.sql' => 50,
        '004_trusted_device_pin_login.sql' => 60, '005_uppercase_client_master.sql' => 70,
        '006_ticket_description_history.sql' => 80];
    usort($files, static fn (string $left, string $right): int =>
        (($priority[basename($left)] ?? 100) <=> ($priority[basename($right)] ?? 100)) ?: strcmp(basename($left), basename($right)));
    foreach ($files as $file) {
        $name = basename($file);
        $sql = file_get_contents($file);
        if ($sql === false) {
            throw new RuntimeException('Unable to read migration: ' . $name);
        }
        $checksum = hash('sha256', str_replace("\r\n", "\n", $sql));
        $check = $pdo->prepare('SELECT checksum FROM schema_migrations WHERE migration=:migration');
        $check->execute(['migration' => $name]);
        $applied = $check->fetch();
        if ($applied !== false) {
            if ($applied['checksum'] !== null && !hash_equals($applied['checksum'], $checksum)) {
                throw new RuntimeException('An applied migration changed: ' . $name . '. Add a new migration instead.');
            }
            if ($applied['checksum'] === null) {
                $pdo->prepare('UPDATE schema_migrations SET checksum=:checksum WHERE migration=:migration')->execute(['checksum' => $checksum, 'migration' => $name]);
            }
            continue;
        }
        $check = $pdo->prepare('SELECT status FROM schema_migration_attempts WHERE migration=:migration');
        $check->execute(['migration' => $name]);
        if ($check->fetchColumn() !== false && !in_array('--retry-reviewed', $_SERVER['argv'] ?? [], true)) {
            throw new RuntimeException('Unfinished migration: ' . $name . '. Review partial DDL and backup before using --retry-reviewed.');
        }
        $pdo->prepare("INSERT INTO schema_migration_attempts (migration,checksum,status) VALUES (:migration,:checksum,'APPLYING')
            ON DUPLICATE KEY UPDATE checksum=VALUES(checksum),status='APPLYING',started_at=NOW(6),finished_at=NULL")
            ->execute(['migration' => $name, 'checksum' => $checksum]);
        try {
            // Execute the trusted SQL file as a whole; semicolons in strings or
            // comments must not be split. MariaDB DDL commits implicitly.
            $pdo->exec($sql);
            $pdo->prepare('INSERT INTO schema_migrations (migration,checksum) VALUES (:migration,:checksum)')
                ->execute(['migration' => $name, 'checksum' => $checksum]);
            $pdo->prepare("UPDATE schema_migration_attempts SET status='APPLIED',finished_at=NOW(6) WHERE migration=:migration")
                ->execute(['migration' => $name]);
            fwrite(STDOUT, "Applied {$name}\n");
        } catch (Throwable $error) {
            $pdo->prepare("UPDATE schema_migration_attempts SET status='FAILED',finished_at=NOW(6) WHERE migration=:migration")
                ->execute(['migration' => $name]);
            throw $error;
        }
    }
} finally {
    $pdo->prepare('SELECT RELEASE_LOCK(:name)')->execute(['name' => $lockName]);
}
