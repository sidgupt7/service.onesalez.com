-- ONESALEZ Service Tracker
-- Employee master, roles, role assignments, and authentication
-- Target: MariaDB 11.8+

SET NAMES utf8mb4;
SET time_zone = '+05:30';

CREATE TABLE IF NOT EXISTS employee_roles (
    employee_role_id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    role_code VARCHAR(30) NOT NULL,
    role_name VARCHAR(100) NOT NULL,
    description VARCHAR(500) NULL,
    created_by VARCHAR(100) NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_by VARCHAR(100) NULL,
    updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
    is_deleted BOOLEAN NOT NULL DEFAULT FALSE,
    deleted_by VARCHAR(100) NULL,
    deleted_at DATETIME(6) NULL,
    PRIMARY KEY (employee_role_id),
    UNIQUE KEY uq_employee_roles_code (role_code),
    CONSTRAINT chk_employee_roles_soft_delete CHECK (
        (is_deleted = FALSE AND deleted_by IS NULL AND deleted_at IS NULL)
        OR
        (is_deleted = TRUE AND deleted_by IS NOT NULL AND deleted_at IS NOT NULL)
    )
) ENGINE = InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS employees (
    employee_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    employee_code VARCHAR(30) NOT NULL,
    full_name VARCHAR(200) NOT NULL,
    official_email VARCHAR(254) NOT NULL,
    mobile_number VARCHAR(20) NULL,
    designation VARCHAR(150) NULL,
    department VARCHAR(150) NULL,
    joining_date DATE NULL,
    employment_status ENUM('ACTIVE', 'SUSPENDED', 'LEFT') NOT NULL DEFAULT 'ACTIVE',
    created_by VARCHAR(100) NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_by VARCHAR(100) NULL,
    updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
    is_deleted BOOLEAN NOT NULL DEFAULT FALSE,
    deleted_by VARCHAR(100) NULL,
    deleted_at DATETIME(6) NULL,
    PRIMARY KEY (employee_id),
    UNIQUE KEY uq_employees_code (employee_code),
    UNIQUE KEY uq_employees_email (official_email),
    KEY ix_employees_status (employment_status, is_deleted),
    CONSTRAINT chk_employees_soft_delete CHECK (
        (is_deleted = FALSE AND deleted_by IS NULL AND deleted_at IS NULL)
        OR
        (is_deleted = TRUE AND deleted_by IS NOT NULL AND deleted_at IS NOT NULL)
    )
) ENGINE = InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS employee_role_assignments (
    employee_role_assignment_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    employee_id BIGINT UNSIGNED NOT NULL,
    employee_role_id SMALLINT UNSIGNED NOT NULL,
    created_by VARCHAR(100) NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_by VARCHAR(100) NULL,
    updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
    is_deleted BOOLEAN NOT NULL DEFAULT FALSE,
    deleted_by VARCHAR(100) NULL,
    deleted_at DATETIME(6) NULL,
    PRIMARY KEY (employee_role_assignment_id),
    UNIQUE KEY uq_employee_role_assignment (employee_id, employee_role_id),
    CONSTRAINT fk_employee_role_assignment_employee FOREIGN KEY (employee_id)
        REFERENCES employees (employee_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_employee_role_assignment_role FOREIGN KEY (employee_role_id)
        REFERENCES employee_roles (employee_role_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_employee_role_assignment_soft_delete CHECK (
        (is_deleted = FALSE AND deleted_by IS NULL AND deleted_at IS NULL)
        OR
        (is_deleted = TRUE AND deleted_by IS NOT NULL AND deleted_at IS NOT NULL)
    )
) ENGINE = InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS employee_user_accounts (
    account_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    employee_id BIGINT UNSIGNED NOT NULL,
    password_hash VARCHAR(255) NULL,
    account_status ENUM('INVITED', 'ACTIVE', 'SUSPENDED') NOT NULL DEFAULT 'INVITED',
    invitation_token_hash CHAR(64) NULL,
    invitation_expires_at DATETIME(6) NULL,
    password_reset_token_hash CHAR(64) NULL,
    password_reset_expires_at DATETIME(6) NULL,
    failed_login_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until DATETIME(6) NULL,
    last_login_at DATETIME(6) NULL,
    password_changed_at DATETIME(6) NULL,
    suspended_by VARCHAR(100) NULL,
    suspended_at DATETIME(6) NULL,
    suspension_reason VARCHAR(500) NULL,
    created_by VARCHAR(100) NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_by VARCHAR(100) NULL,
    updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
    is_deleted BOOLEAN NOT NULL DEFAULT FALSE,
    deleted_by VARCHAR(100) NULL,
    deleted_at DATETIME(6) NULL,
    PRIMARY KEY (account_id),
    UNIQUE KEY uq_employee_user_account_employee (employee_id),
    KEY ix_employee_user_account_status (account_status, is_deleted),
    CONSTRAINT fk_employee_user_account_employee FOREIGN KEY (employee_id)
        REFERENCES employees (employee_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_employee_user_account_soft_delete CHECK (
        (is_deleted = FALSE AND deleted_by IS NULL AND deleted_at IS NULL)
        OR
        (is_deleted = TRUE AND deleted_by IS NOT NULL AND deleted_at IS NOT NULL)
    )
) ENGINE = InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

INSERT INTO employee_roles (
    role_code,
    role_name,
    description,
    created_by
)
VALUES
    ('SERVICE_EMPLOYEE', 'Service Employee', 'Can accept, work on, release, and complete service tickets.', 'SYSTEM'),
    ('SERVICE_ADMIN', 'Service Admin', 'Can manage service operations, decline tickets, view reports, and access the executive dashboard.', 'SYSTEM'),
    ('SYSTEM_ADMIN', 'System Admin', 'Can manage employees, roles, accounts, and system configuration.', 'SYSTEM')
ON DUPLICATE KEY UPDATE
    role_name = VALUES(role_name),
    description = VALUES(description);

