CREATE TABLE IF NOT EXISTS service_ticket_description_history (
    description_history_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ticket_id BIGINT UNSIGNED NOT NULL,
    previous_description TEXT NOT NULL,
    new_description TEXT NOT NULL,
    changed_by_contact_id BIGINT UNSIGNED NOT NULL,
    created_by VARCHAR(100) NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (description_history_id),
    KEY ix_description_history_ticket (ticket_id, created_at),
    CONSTRAINT fk_description_history_ticket FOREIGN KEY (ticket_id)
        REFERENCES service_tickets (ticket_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_description_history_contact FOREIGN KEY (changed_by_contact_id)
        REFERENCES client_contacts (contact_id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
