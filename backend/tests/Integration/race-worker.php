<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';
$name = (string) getenv('TEST_DB_NAME');
if (!preg_match('/^onesalez_(audit|test)(_[a-z0-9]+)?$/', $name)) {
    throw new RuntimeException('Disposable test database required.');
}
date_default_timezone_set('Asia/Kolkata');
$input = json_decode((string) stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$db = new App\Database\Database(['host' => getenv('TEST_DB_HOST') ?: '127.0.0.1', 'port' => (int) (getenv('TEST_DB_PORT') ?: 3306), 'database' => $name, 'username' => getenv('TEST_DB_USER') ?: 'audit', 'password' => getenv('TEST_DB_PASSWORD') ?: '', 'retries' => 1, 'persistent' => false]);
$db->connection();
while (microtime(true) < $input['start']) {
    usleep(10_000);
}
try {
    $success = false;
    if ($input['operation'] === 'accept') {
        $success = (new App\Repositories\TicketRepository($db))->accept($input['ticket'], $input['employee'], 'EMPLOYEE:' . $input['employee']);
    } elseif ($input['operation'] === 'suspend') {
        $success = (new App\Repositories\EmployeeRepository($db))->suspend($input['employee'], 'TEST', 'TEST');
    } elseif ($input['operation'] === 'refresh') {
        $config = ['key' => str_repeat('integration-key-', 4), 'jwt_issuer' => 'audit', 'jwt_access_ttl' => 900, 'jwt_refresh_ttl' => 86400];
        (new App\Services\AuthService(new App\Repositories\AuthRepository($db), new App\Services\TokenService($config), $config))->refresh($input['token'], '127.0.0.1', 'race-test');
        $success = true;
    }
    echo json_encode(['success' => $success], JSON_THROW_ON_ERROR);
} catch (App\Exceptions\HttpException $error) {
    echo json_encode(['success' => false, 'error' => $error->errorCode], JSON_THROW_ON_ERROR);
}
