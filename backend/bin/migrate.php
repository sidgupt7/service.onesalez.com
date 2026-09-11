<?php

declare(strict_types=1);

use App\Database\Database;
use Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';
Dotenv::createUnsafeImmutable(dirname(__DIR__))->safeLoad();

$database = new Database(require dirname(__DIR__) . '/config/database.php');
$pdo = $database->connection();
$pdo->exec(
    'CREATE TABLE IF NOT EXISTS schema_migrations ('
    . 'migration VARCHAR(255) NOT NULL PRIMARY KEY, '
    . 'applied_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)'
    . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

$directory = dirname(__DIR__) . '/database/migrations';
$files = glob($directory . '/*.sql') ?: [];
$priority = [
    '001_create_client_tables.sql' => 10,
    '002_apply_client_authentication.sql' => 20,
    'employee.sql' => 30,
    'service.sql' => 40,
    '003_application_features.sql' => 50,
    '004_trusted_device_pin_login.sql' => 60,
    '005_uppercase_client_master.sql' => 70,
    '006_ticket_description_history.sql' => 80,
];
usort($files, static fn (string $left, string $right): int =>
    ($priority[basename($left)] ?? 100) <=> ($priority[basename($right)] ?? 100));

$check = $pdo->prepare('SELECT 1 FROM schema_migrations WHERE migration=:migration');
$record = $pdo->prepare('INSERT INTO schema_migrations (migration) VALUES (:migration)');
foreach ($files as $file) {
    $name = basename($file);
    $check->execute(['migration' => $name]);
    if ($check->fetchColumn() !== false) {
        continue;
    }
    $sql = file_get_contents($file);
    if ($sql === false) {
        throw new RuntimeException('Unable to read migration: ' . $name);
    }
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
        $pdo->exec($statement);
    }
    $record->execute(['migration' => $name]);
    fwrite(STDOUT, "Applied {$name}\n");
}
