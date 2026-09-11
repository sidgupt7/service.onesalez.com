-- Application-owned support tables for the PHP API.
SET NAMES utf8mb4;
SET time_zone = '+05:30';

CREATE TABLE IF NOT EXISTS leads (
    lead_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    business_name VARCHAR(200) NOT NULL,
    contact_name VARCHAR(200) NOT NULL,
    email VARCHAR(254) NOT NULL,
    phone VARCHAR(20) NULL,
    source VARCHAR(100) NULL,
    status ENUM('NEW', 'CONTACTED', 'QUALIFIED', 'WON', 'LOST') NOT NULL DEFAULT 'NEW',
    assigned_employee_id BIGINT UNSIGNED NULL,
    notes TEXT NULL,
    created_by VARCHAR(100) NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_by VARCHAR(100) NULL,
    updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
    is_deleted BOOLEAN NOT NULL DEFAULT FALSE,
    deleted_by VARCHAR(100) NULL,
    deleted_at DATETIME(6) NULL,
    PRIMARY KEY (lead_id),
    KEY ix_leads_search (status, is_deleted, business_name),
    KEY ix_leads_employee (assigned_employee_id, status),
    CONSTRAINT fk_leads_employee FOREIGN KEY (assigned_employee_id)
        REFERENCES employees (employee_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_leads_soft_delete CHECK (
        (is_deleted = FALSE AND deleted_by IS NULL AND deleted_at IS NULL)
        OR (is_deleted = TRUE AND deleted_by IS NOT NULL AND deleted_at IS NOT NULL)
    )
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auth_refresh_tokens (
    refresh_token_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    actor_type ENUM('CLIENT_CONTACT', 'EMPLOYEE') NOT NULL,
    actor_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME(6) NOT NULL,
    revoked_at DATETIME(6) NULL,
    replaced_by_token_id BIGINT UNSIGNED NULL,
    user_agent VARCHAR(500) NULL,
    ip_address VARCHAR(45) NOT NULL,
    created_by VARCHAR(100) NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (refresh_token_id),
    UNIQUE KEY uq_auth_refresh_token_hash (token_hash),
    KEY ix_auth_refresh_actor (actor_type, actor_id, revoked_at),
    KEY ix_auth_refresh_expiry (expires_at),
    CONSTRAINT fk_auth_refresh_replacement FOREIGN KEY (replaced_by_token_id)
        REFERENCES auth_refresh_tokens (refresh_token_id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ticket_messages (
    message_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ticket_id BIGINT UNSIGNED NOT NULL,
    author_type ENUM('CLIENT_CONTACT', 'EMPLOYEE') NOT NULL,
    contact_id BIGINT UNSIGNED NULL,
    employee_id BIGINT UNSIGNED NULL,
    message TEXT NOT NULL,
    is_internal BOOLEAN NOT NULL DEFAULT FALSE,
    created_by VARCHAR(100) NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_by VARCHAR(100) NULL,
    updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
    is_deleted BOOLEAN NOT NULL DEFAULT FALSE,
    deleted_by VARCHAR(100) NULL,
    deleted_at DATETIME(6) NULL,
    PRIMARY KEY (message_id),
    KEY ix_ticket_messages_ticket (ticket_id, is_deleted, created_at),
    CONSTRAINT fk_ticket_messages_ticket FOREIGN KEY (ticket_id)
        REFERENCES service_tickets (ticket_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_ticket_messages_contact FOREIGN KEY (contact_id)
        REFERENCES client_contacts (contact_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_ticket_messages_employee FOREIGN KEY (employee_id)
        REFERENCES employees (employee_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_ticket_message_author CHECK (
        (author_type='CLIENT_CONTACT' AND contact_id IS NOT NULL AND employee_id IS NULL AND is_internal=FALSE)
        OR (author_type='EMPLOYEE' AND employee_id IS NOT NULL AND contact_id IS NULL)
    ),
    CONSTRAINT chk_ticket_messages_soft_delete CHECK (
        (is_deleted=FALSE AND deleted_by IS NULL AND deleted_at IS NULL)
        OR (is_deleted=TRUE AND deleted_by IS NOT NULL AND deleted_at IS NOT NULL)
    )
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS api_rate_limits (
    rate_limit_key CHAR(64) NOT NULL,
    window_started_at DATETIME(6) NOT NULL,
    request_count INT UNSIGNED NOT NULL DEFAULT 1,
    expires_at DATETIME(6) NOT NULL,
    PRIMARY KEY (rate_limit_key),
    KEY ix_rate_limits_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS teams (
    team_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    team_name VARCHAR(150) NOT NULL,
    description VARCHAR(500) NULL,
    created_by VARCHAR(100) NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_by VARCHAR(100) NULL,
    updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
    is_deleted BOOLEAN NOT NULL DEFAULT FALSE,
    deleted_by VARCHAR(100) NULL,
    deleted_at DATETIME(6) NULL,
    PRIMARY KEY (team_id),
    UNIQUE KEY uq_teams_name (team_name),
    CONSTRAINT chk_teams_soft_delete CHECK (
        (is_deleted=FALSE AND deleted_by IS NULL AND deleted_at IS NULL)
        OR (is_deleted=TRUE AND deleted_by IS NOT NULL AND deleted_at IS NOT NULL)
    )
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS employee_team_members (
    team_member_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    team_id BIGINT UNSIGNED NOT NULL,
    employee_id BIGINT UNSIGNED NOT NULL,
    is_team_lead BOOLEAN NOT NULL DEFAULT FALSE,
    created_by VARCHAR(100) NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    is_deleted BOOLEAN NOT NULL DEFAULT FALSE,
    deleted_by VARCHAR(100) NULL,
    deleted_at DATETIME(6) NULL,
    PRIMARY KEY (team_member_id),
    UNIQUE KEY uq_employee_team (team_id, employee_id),
    CONSTRAINT fk_team_member_team FOREIGN KEY (team_id)
        REFERENCES teams (team_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_team_member_employee FOREIGN KEY (employee_id)
        REFERENCES employees (employee_id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS permissions (
    permission_id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    permission_code VARCHAR(80) NOT NULL,
    description VARCHAR(500) NULL,
    created_by VARCHAR(100) NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (permission_id),
    UNIQUE KEY uq_permissions_code (permission_code)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS employee_role_permissions (
    employee_role_permission_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    employee_role_id SMALLINT UNSIGNED NOT NULL,
    permission_id SMALLINT UNSIGNED NOT NULL,
    created_by VARCHAR(100) NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (employee_role_permission_id),
    UNIQUE KEY uq_employee_role_permission (employee_role_id, permission_id),
    CONSTRAINT fk_role_permission_role FOREIGN KEY (employee_role_id)
        REFERENCES employee_roles (employee_role_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_role_permission_permission FOREIGN KEY (permission_id)
        REFERENCES permissions (permission_id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_jobs (
    email_job_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    recipient_email VARCHAR(254) NOT NULL,
    subject VARCHAR(250) NOT NULL,
    body TEXT NOT NULL,
    job_status ENUM('PENDING', 'SENT', 'FAILED') NOT NULL DEFAULT 'PENDING',
    attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    available_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    processed_at DATETIME(6) NULL,
    last_error VARCHAR(1000) NULL,
    created_by VARCHAR(100) NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (email_job_id),
    KEY ix_email_jobs_queue (job_status, available_at)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

INSERT INTO permissions (permission_code, description, created_by) VALUES
('tickets.manage', 'Accept, release, complete, and update service tickets.', 'SYSTEM'),
('tickets.decline', 'Decline service tickets.', 'SYSTEM'),
('clients.manage', 'Manage clients and client contacts.', 'SYSTEM'),
('employees.manage', 'Manage employees, roles, and teams.', 'SYSTEM'),
('leads.manage', 'Manage sales leads.', 'SYSTEM'),
('analytics.view', 'View service analytics and employee performance.', 'SYSTEM')
ON DUPLICATE KEY UPDATE description=VALUES(description);

INSERT IGNORE INTO employee_role_permissions (employee_role_id, permission_id, created_by)
SELECT er.employee_role_id, p.permission_id, 'SYSTEM'
FROM employee_roles er
JOIN permissions p ON
    er.role_code='SYSTEM_ADMIN'
    OR (er.role_code='SERVICE_ADMIN' AND p.permission_code IN ('tickets.manage','tickets.decline','clients.manage','leads.manage','analytics.view'))
    OR (er.role_code='SERVICE_EMPLOYEE' AND p.permission_code IN ('tickets.manage'));

