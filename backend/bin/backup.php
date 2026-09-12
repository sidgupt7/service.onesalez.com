<?php

declare(strict_types=1);

use Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';
$arguments = $_SERVER['argv'] ?? [];
$directory = realpath($arguments[1] ?? '');
$environment = realpath($arguments[2] ?? dirname(__DIR__));
if ($directory === false || !is_dir($directory) || str_contains(str_replace('\\', '/', $directory), '/public_html/')) {
    throw new RuntimeException('Provide an existing private backup directory outside public_html.');
}
if ($environment === false) {
    throw new RuntimeException('Environment directory not found.');
}
Dotenv::createUnsafeImmutable($environment)->safeLoad();
$credentialFile = tempnam($directory, '.database-');
if ($credentialFile === false) {
    throw new RuntimeException('Cannot create private backup configuration.');
}
chmod($credentialFile, 0600);
$quote = static fn (string $value): string => '"' . str_replace(['\\', '"', "\r", "\n"], ['\\\\', '\\"', '\\r', '\\n'], $value) . '"';
$configuration = "[client]\n";
foreach (['host' => 'DB_HOST', 'port' => 'DB_PORT', 'user' => 'DB_USER', 'password' => 'DB_PASSWORD'] as $key => $env) {
    $configuration .= $key . '=' . $quote((string) getenv($env)) . "\n";
}
file_put_contents($credentialFile, $configuration);
$target = $directory . '/database-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.sql';
$binary = getenv('MARIADB_DUMP_BINARY') ?: 'mariadb-dump';
try {
    $pipes = [];
    $process = proc_open([$binary, '--defaults-extra-file=' . $credentialFile, '--single-transaction', '--quick', '--hex-blob', '--routines', '--triggers', '--events', (string) getenv('DB_NAME')], [0 => ['pipe', 'r'], 1 => ['file', $target, 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Unable to start database backup.');
    }
    fclose($pipes[0]);
    stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    if (proc_close($process) !== 0 || filesize($target) === 0) {
        @unlink($target);
        throw new RuntimeException('Database backup failed; deployment must stop.');
    }
    chmod($target, 0600);
    $source = fopen($target, 'rb');
    $compressed = gzopen($target . '.gz', 'wb6');
    if ($source === false || $compressed === false) {
        throw new RuntimeException('Cannot compress database backup.');
    }
    while (!feof($source)) {
        $chunk = fread($source, 1024 * 1024);
        if ($chunk === false || gzwrite($compressed, $chunk) === false) {
            throw new RuntimeException('Database backup compression failed.');
        }
    }
    fclose($source);
    gzclose($compressed);
    chmod($target . '.gz', 0600);
    unlink($target);
    fwrite(STDOUT, $target . ".gz\n");
} finally {
    unlink($credentialFile);
}
