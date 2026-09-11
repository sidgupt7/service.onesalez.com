-- ONESALEZ Service Tracker
-- Migration 001: Client master and associated tables
-- Target: MariaDB 11.8+

SET NAMES utf8mb4;
SET time_zone = '+05:30';

CREATE TABLE IF NOT EXISTS clients (
    client_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    client_code VARCHAR(30) NOT NULL,
    legal_name VARCHAR(200) NOT NULL,
    display_name VARCHAR(200) NULL,
    gstin VARCHAR(15) NULL,
    pan VARCHAR(10) NULL,
    primary_email VARCHAR(254) NULL,
    primary_phone VARCHAR(20) NULL,
    website_url VARCHAR(500) NULL,
    notes TEXT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_by VARCHAR(100) NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_by VARCHAR(100) NULL,
    updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
    is_deleted BOOLEAN NOT NULL DEFAULT FALSE,
    deleted_by VARCHAR(100) NULL,
    deleted_at DATETIME(6) NULL,
    PRIMARY KEY (client_id),
    UNIQUE KEY uq_clients_client_code (client_code),
    UNIQUE KEY uq_clients_gstin (gstin),
    KEY ix_clients_active (is_deleted, is_active, legal_name),
    CONSTRAINT chk_clients_soft_delete CHECK (
        (is_deleted = FALSE AND deleted_by IS NULL AND deleted_at IS NULL)
        OR
        (is_deleted = TRUE AND deleted_by IS NOT NULL AND deleted_at IS NOT NULL)
    )
) ENGINE = InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS client_locations (
    location_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    client_id BIGINT UNSIGNED NOT NULL,
    location_code VARCHAR(30) NOT NULL,
    location_name VARCHAR(200) NOT NULL,
    location_type ENUM('HEAD_OFFICE', 'BRANCH', 'WAREHOUSE', 'OTHER') NOT NULL DEFAULT 'BRANCH',
    address_line_1 VARCHAR(250) NOT NULL,
    address_line_2 VARCHAR(250) NULL,
    landmark VARCHAR(200) NULL,
    city VARCHAR(100) NOT NULL,
    district VARCHAR(100) NULL,
    state_name VARCHAR(100) NOT NULL,
    postal_code VARCHAR(10) NOT NULL,
    gstin VARCHAR(15) NULL,
    email VARCHAR(254) NULL,
    phone VARCHAR(20) NULL,
    is_primary BOOLEAN NOT NULL DEFAULT FALSE,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_by VARCHAR(100) NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_by VARCHAR(100) NULL,
    updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
    is_deleted BOOLEAN NOT NULL DEFAULT FALSE,
    deleted_by VARCHAR(100) NULL,
    deleted_at DATETIME(6) NULL,
    PRIMARY KEY (location_id),
    UNIQUE KEY uq_client_locations_code (client_id, location_code),
    KEY ix_client_locations_client (client_id, is_deleted, is_active),
    KEY ix_client_locations_state (state_name, city),
    CONSTRAINT fk_client_locations_client FOREIGN KEY (client_id)
        REFERENCES clients (client_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_client_locations_soft_delete CHECK (
        (is_deleted = FALSE AND deleted_by IS NULL AND deleted_at IS NULL)
        OR
        (is_deleted = TRUE AND deleted_by IS NOT NULL AND deleted_at IS NOT NULL)
    )
) ENGINE = InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS client_contact_roles (
    contact_role_id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
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
    PRIMARY KEY (contact_role_id),
    UNIQUE KEY uq_client_contact_roles_code (role_code),
    CONSTRAINT chk_client_contact_roles_soft_delete CHECK (
        (is_deleted = FALSE AND deleted_by IS NULL AND deleted_at IS NULL)
        OR
        (is_deleted = TRUE AND deleted_by IS NOT NULL AND deleted_at IS NOT NULL)
    )
) ENGINE = InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS client_contacts (
    contact_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    client_id BIGINT UNSIGNED NOT NULL,
    contact_role_id SMALLINT UNSIGNED NOT NULL,
    full_name VARCHAR(200) NOT NULL,
    designation VARCHAR(150) NULL,
    email VARCHAR(254) NOT NULL,
    mobile_number VARCHAR(20) NOT NULL,
    alternate_number VARCHAR(20) NULL,
    has_all_locations BOOLEAN NOT NULL DEFAULT FALSE,
    is_primary_contact BOOLEAN NOT NULL DEFAULT FALSE,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_by VARCHAR(100) NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_by VARCHAR(100) NULL,
    updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
    is_deleted BOOLEAN NOT NULL DEFAULT FALSE,
    deleted_by VARCHAR(100) NULL,
    deleted_at DATETIME(6) NULL,
    PRIMARY KEY (contact_id),
    KEY ix_client_contacts_client (client_id, is_deleted, is_active),
    KEY ix_client_contacts_mobile (mobile_number),
    UNIQUE KEY uq_client_contacts_email (email),
    CONSTRAINT fk_client_contacts_client FOREIGN KEY (client_id)
        REFERENCES clients (client_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_client_contacts_role FOREIGN KEY (contact_role_id)
        REFERENCES client_contact_roles (contact_role_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_client_contacts_soft_delete CHECK (
        (is_deleted = FALSE AND deleted_by IS NULL AND deleted_at IS NULL)
        OR
        (is_deleted = TRUE AND deleted_by IS NOT NULL AND deleted_at IS NOT NULL)
    )
) ENGINE = InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS client_user_accounts (
    account_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    contact_id BIGINT UNSIGNED NOT NULL,
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
    UNIQUE KEY uq_client_user_accounts_contact (contact_id),
    KEY ix_client_user_accounts_status (account_status, is_deleted),
    CONSTRAINT fk_client_user_accounts_contact FOREIGN KEY (contact_id)
        REFERENCES client_contacts (contact_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_client_user_accounts_soft_delete CHECK (
        (is_deleted = FALSE AND deleted_by IS NULL AND deleted_at IS NULL)
        OR
        (is_deleted = TRUE AND deleted_by IS NOT NULL AND deleted_at IS NOT NULL)
    )
) ENGINE = InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS client_contact_locations (
    contact_location_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    contact_id BIGINT UNSIGNED NOT NULL,
    location_id BIGINT UNSIGNED NOT NULL,
    created_by VARCHAR(100) NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_by VARCHAR(100) NULL,
    updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
    is_deleted BOOLEAN NOT NULL DEFAULT FALSE,
    deleted_by VARCHAR(100) NULL,
    deleted_at DATETIME(6) NULL,
    PRIMARY KEY (contact_location_id),
    UNIQUE KEY uq_client_contact_locations (contact_id, location_id),
    KEY ix_client_contact_locations_location (location_id, is_deleted),
    CONSTRAINT fk_client_contact_locations_contact FOREIGN KEY (contact_id)
        REFERENCES client_contacts (contact_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_client_contact_locations_location FOREIGN KEY (location_id)
        REFERENCES client_locations (location_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_client_contact_locations_soft_delete CHECK (
        (is_deleted = FALSE AND deleted_by IS NULL AND deleted_at IS NULL)
        OR
        (is_deleted = TRUE AND deleted_by IS NOT NULL AND deleted_at IS NOT NULL)
    )
) ENGINE = InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

INSERT INTO client_contact_roles (
    role_code,
    role_name,
    description,
    created_by
)
VALUES
    ('SYSTEM_OPERATOR', 'System Operator', 'Client contact who operates the software system.', 'SYSTEM'),
    ('END_USER', 'End User', 'Client contact who uses the software in day-to-day work.', 'SYSTEM'),
    ('CLIENT_ADMIN', 'Client-side Admin', 'Administrator at the client site, not an ONESALEZ administrator.', 'SYSTEM'),
    ('OWNER', 'Owner', 'Owner or principal of the client business.', 'SYSTEM')
ON DUPLICATE KEY UPDATE
    role_name = VALUES(role_name),
    description = VALUES(description);
