-- ONESALEZ Service Tracker
-- Service tickets, employee attempts, and permanent status history
-- Target: MariaDB 11.8+

SET NAMES utf8mb4;
SET time_zone = '+05:30';

CREATE TABLE IF NOT EXISTS service_tickets (
    ticket_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    service_request_number VARCHAR(40) NOT NULL,
    client_id BIGINT UNSIGNED NOT NULL,
    location_id BIGINT UNSIGNED NOT NULL,
    reported_by_contact_id BIGINT UNSIGNED NOT NULL,
    subject VARCHAR(250) NOT NULL,
    issue_description TEXT NOT NULL,
    priority ENUM('NORMAL', 'HIGH', 'URGENT') NOT NULL DEFAULT 'NORMAL',
    ticket_status ENUM('OPEN', 'ACCEPTED', 'COMPLETED', 'DECLINED') NOT NULL DEFAULT 'OPEN',
    current_employee_id BIGINT UNSIGNED NULL,
    accepted_at DATETIME(6) NULL,
    completed_by_employee_id BIGINT UNSIGNED NULL,
    completed_at DATETIME(6) NULL,
    final_resolution TEXT NULL,
    declined_by_employee_id BIGINT UNSIGNED NULL,
    declined_at DATETIME(6) NULL,
    decline_reason VARCHAR(1000) NULL,
    created_by VARCHAR(100) NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_by VARCHAR(100) NULL,
    updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
    is_deleted BOOLEAN NOT NULL DEFAULT FALSE,
    deleted_by VARCHAR(100) NULL,
    deleted_at DATETIME(6) NULL,
    PRIMARY KEY (ticket_id),
    UNIQUE KEY uq_service_request_number (service_request_number),
    KEY ix_service_tickets_console (ticket_status, is_deleted, created_at),
    KEY ix_service_tickets_client (client_id, ticket_status, created_at),
    KEY ix_service_tickets_employee (current_employee_id, ticket_status),
    KEY ix_service_tickets_location (location_id, created_at),
    CONSTRAINT fk_service_ticket_client FOREIGN KEY (client_id)
        REFERENCES clients (client_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_service_ticket_location FOREIGN KEY (location_id)
        REFERENCES client_locations (location_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_service_ticket_contact FOREIGN KEY (reported_by_contact_id)
        REFERENCES client_contacts (contact_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_service_ticket_current_employee FOREIGN KEY (current_employee_id)
        REFERENCES employees (employee_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_service_ticket_completed_employee FOREIGN KEY (completed_by_employee_id)
        REFERENCES employees (employee_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_service_ticket_declined_employee FOREIGN KEY (declined_by_employee_id)
        REFERENCES employees (employee_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_service_ticket_soft_delete CHECK (
        (is_deleted = FALSE AND deleted_by IS NULL AND deleted_at IS NULL)
        OR
        (is_deleted = TRUE AND deleted_by IS NOT NULL AND deleted_at IS NOT NULL)
    ),
    CONSTRAINT chk_service_ticket_completion CHECK (
        ticket_status <> 'COMPLETED'
        OR (
            completed_by_employee_id IS NOT NULL
            AND completed_at IS NOT NULL
            AND final_resolution IS NOT NULL
        )
    ),
    CONSTRAINT chk_service_ticket_decline CHECK (
        ticket_status <> 'DECLINED'
        OR (
            declined_by_employee_id IS NOT NULL
            AND declined_at IS NOT NULL
            AND decline_reason IS NOT NULL
        )
    )
) ENGINE = InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS service_attempts (
    attempt_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ticket_id BIGINT UNSIGNED NOT NULL,
    attempt_number SMALLINT UNSIGNED NOT NULL,
    employee_id BIGINT UNSIGNED NOT NULL,
    attempt_status ENUM('IN_PROGRESS', 'RELEASED', 'COMPLETED', 'CANCELLED_BY_DECLINE')
        NOT NULL DEFAULT 'IN_PROGRESS',
    accepted_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    ended_at DATETIME(6) NULL,
    service_note TEXT NULL,
    created_by VARCHAR(100) NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_by VARCHAR(100) NULL,
    updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
    is_deleted BOOLEAN NOT NULL DEFAULT FALSE,
    deleted_by VARCHAR(100) NULL,
    deleted_at DATETIME(6) NULL,
    PRIMARY KEY (attempt_id),
    UNIQUE KEY uq_service_attempt_number (ticket_id, attempt_number),
    KEY ix_service_attempt_employee (employee_id, accepted_at),
    KEY ix_service_attempt_status (attempt_status, accepted_at),
    CONSTRAINT fk_service_attempt_ticket FOREIGN KEY (ticket_id)
        REFERENCES service_tickets (ticket_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_service_attempt_employee FOREIGN KEY (employee_id)
        REFERENCES employees (employee_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_service_attempt_outcome CHECK (
        attempt_status = 'IN_PROGRESS'
        OR (ended_at IS NOT NULL AND service_note IS NOT NULL)
    ),
    CONSTRAINT chk_service_attempt_soft_delete CHECK (
        (is_deleted = FALSE AND deleted_by IS NULL AND deleted_at IS NULL)
        OR
        (is_deleted = TRUE AND deleted_by IS NOT NULL AND deleted_at IS NOT NULL)
    )
) ENGINE = InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS service_status_history (
    status_history_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ticket_id BIGINT UNSIGNED NOT NULL,
    from_status ENUM('OPEN', 'ACCEPTED', 'COMPLETED', 'DECLINED') NULL,
    to_status ENUM('OPEN', 'ACCEPTED', 'COMPLETED', 'DECLINED') NOT NULL,
    actor_type ENUM('CLIENT_CONTACT', 'EMPLOYEE', 'SYSTEM') NOT NULL,
    contact_id BIGINT UNSIGNED NULL,
    employee_id BIGINT UNSIGNED NULL,
    change_note TEXT NULL,
    created_by VARCHAR(100) NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    is_deleted BOOLEAN NOT NULL DEFAULT FALSE,
    deleted_by VARCHAR(100) NULL,
    deleted_at DATETIME(6) NULL,
    PRIMARY KEY (status_history_id),
    KEY ix_service_status_history_ticket (ticket_id, created_at),
    CONSTRAINT fk_service_status_history_ticket FOREIGN KEY (ticket_id)
        REFERENCES service_tickets (ticket_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_service_status_history_contact FOREIGN KEY (contact_id)
        REFERENCES client_contacts (contact_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_service_status_history_employee FOREIGN KEY (employee_id)
        REFERENCES employees (employee_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_service_status_actor CHECK (
        (actor_type = 'CLIENT_CONTACT' AND contact_id IS NOT NULL AND employee_id IS NULL)
        OR
        (actor_type = 'EMPLOYEE' AND employee_id IS NOT NULL AND contact_id IS NULL)
        OR
        (actor_type = 'SYSTEM' AND employee_id IS NULL AND contact_id IS NULL)
    ),
    CONSTRAINT chk_service_status_history_soft_delete CHECK (
        (is_deleted = FALSE AND deleted_by IS NULL AND deleted_at IS NULL)
        OR
        (is_deleted = TRUE AND deleted_by IS NOT NULL AND deleted_at IS NOT NULL)
    )
) ENGINE = InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

