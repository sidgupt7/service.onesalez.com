-- Upgrade an already-created client schema with email-only authentication.
-- The baseline 001 migration already includes these definitions for new databases.

SET NAMES utf8mb4;
SET time_zone = '+05:30';

ALTER TABLE client_contacts
    MODIFY COLUMN email VARCHAR(254) NOT NULL;

ALTER TABLE client_contacts
    DROP INDEX IF EXISTS ix_client_contacts_email;

ALTER TABLE client_contacts
    ADD UNIQUE INDEX IF NOT EXISTS uq_client_contacts_email (email);

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

