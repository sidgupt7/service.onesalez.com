<?php

declare(strict_types=1);

use App\Database\Database;
use Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';
Dotenv::createUnsafeImmutable(dirname(__DIR__))->safeLoad();
$required = ['ADMIN_CODE', 'ADMIN_NAME', 'ADMIN_EMAIL', 'ADMIN_PASSWORD'];
foreach ($required as $name) {
    if (!is_string(getenv($name)) || getenv($name) === '') {
        throw new RuntimeException("{$name} is required.");
    }
}
$database = new Database(require dirname(__DIR__) . '/config/database.php');
$database->transaction(function (PDO $pdo): void {
    $employee = $pdo->prepare(
        "INSERT INTO employees (employee_code, full_name, official_email, employment_status, created_by)
         VALUES (:code, :name, :email, 'ACTIVE', 'SYSTEM')"
    );
    $employee->execute([
        'code' => getenv('ADMIN_CODE'),
        'name' => getenv('ADMIN_NAME'),
        'email' => strtolower((string) getenv('ADMIN_EMAIL')),
    ]);
    $employeeId = (int) $pdo->lastInsertId();
    $account = $pdo->prepare(
        "INSERT INTO employee_user_accounts
         (employee_id, password_hash, account_status, password_changed_at, created_by)
         VALUES (:employee, :password, 'ACTIVE', NOW(6), 'SYSTEM')"
    );
    $account->execute([
        'employee' => $employeeId,
        'password' => password_hash((string) getenv('ADMIN_PASSWORD'), PASSWORD_BCRYPT, ['cost' => 12]),
    ]);
    $role = $pdo->prepare(
        "INSERT INTO employee_role_assignments (employee_id, employee_role_id, created_by)
         SELECT :employee, employee_role_id, 'SYSTEM' FROM employee_roles WHERE role_code='SYSTEM_ADMIN'"
    );
    $role->execute(['employee' => $employeeId]);
});
fwrite(STDOUT, "Initial system administrator created.\n");
