<?php

declare(strict_types=1);

use App\Database\Database;
use Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

Dotenv::createUnsafeImmutable(dirname(__DIR__))->safeLoad();

$email = strtolower(trim((string) getenv('ADMIN_EMAIL')));
$password = (string) getenv('ADMIN_PASSWORD');
$name = trim((string) (getenv('ADMIN_NAME') ?: 'Siddharth Gupta'));
$code = trim((string) (getenv('ADMIN_CODE') ?: 'EMP001'));

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    throw new RuntimeException('ADMIN_EMAIL must be a valid email address.');
}

if (strlen($password) < 12) {
    throw new RuntimeException('ADMIN_PASSWORD must be at least 12 characters.');
}

$database = new Database(require dirname(__DIR__) . '/config/database.php');

$employeeId = $database->transaction(function (PDO $pdo) use ($email, $password, $name, $code): int {
    $findEmployee = $pdo->prepare(
        'SELECT employee_id FROM employees WHERE LOWER(official_email)=LOWER(:email) LIMIT 1',
    );
    $findEmployee->execute(['email' => $email]);
    $employeeId = $findEmployee->fetchColumn();

    if ($employeeId === false) {
        $insertEmployee = $pdo->prepare(
            "INSERT INTO employees (employee_code, full_name, official_email, employment_status, created_by)
             VALUES (:code, :name, :email, 'ACTIVE', 'SYSTEM')",
        );
        $insertEmployee->execute([
            'code' => $code,
            'name' => $name,
            'email' => $email,
        ]);
        $employeeId = (int) $pdo->lastInsertId();
    } else {
        $employeeId = (int) $employeeId;
        $activateEmployee = $pdo->prepare(
            "UPDATE employees
             SET full_name=:name, employment_status='ACTIVE', is_deleted=FALSE,
                 deleted_by=NULL, deleted_at=NULL, updated_by='SYSTEM'
             WHERE employee_id=:employee",
        );
        $activateEmployee->execute([
            'name' => $name,
            'employee' => $employeeId,
        ]);
    }

    $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    $upsertAccount = $pdo->prepare(
        "INSERT INTO employee_user_accounts
            (employee_id, password_hash, account_status, password_changed_at, created_by)
         VALUES
            (:employee, :password, 'ACTIVE', NOW(6), 'SYSTEM')
         ON DUPLICATE KEY UPDATE
            password_hash=VALUES(password_hash),
            account_status='ACTIVE',
            password_changed_at=NOW(6),
            failed_login_attempts=0,
            locked_until=NULL,
            suspended_by=NULL,
            suspended_at=NULL,
            suspension_reason=NULL,
            is_deleted=FALSE,
            deleted_by=NULL,
            deleted_at=NULL,
            updated_by='SYSTEM'",
    );
    $upsertAccount->execute([
        'employee' => $employeeId,
        'password' => $passwordHash,
    ]);

    $assignRole = $pdo->prepare(
        "INSERT INTO employee_role_assignments (employee_id, employee_role_id, created_by)
         SELECT :employee, employee_role_id, 'SYSTEM'
         FROM employee_roles
         WHERE role_code='SYSTEM_ADMIN' AND is_deleted=FALSE
         ON DUPLICATE KEY UPDATE is_deleted=FALSE, deleted_by=NULL, deleted_at=NULL, updated_by='SYSTEM'",
    );
    $assignRole->execute(['employee' => $employeeId]);

    $revokeSessions = $pdo->prepare(
        'UPDATE auth_refresh_tokens
         SET revoked_at=NOW(6)
         WHERE actor_type=:actor_type AND actor_id=:actor_id AND revoked_at IS NULL',
    );
    $revokeSessions->execute([
        'actor_type' => 'EMPLOYEE',
        'actor_id' => $employeeId,
    ]);

    $revokeTrustedDevices = $pdo->prepare(
        'UPDATE auth_trusted_devices
         SET revoked_at=NOW(6)
         WHERE actor_type=:actor_type AND actor_id=:actor_id AND revoked_at IS NULL',
    );
    $revokeTrustedDevices->execute([
        'actor_type' => 'EMPLOYEE',
        'actor_id' => $employeeId,
    ]);

    return $employeeId;
});

fwrite(STDOUT, "System administrator credential updated for employee_id={$employeeId}.\n");
