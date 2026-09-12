-- Additive migration: preserve applied contracts and all historical service data.
ALTER TABLE leads ADD COLUMN IF NOT EXISTS converted_client_id BIGINT UNSIGNED NULL;
CREATE INDEX IF NOT EXISTS ix_leads_converted_client ON leads (converted_client_id);
CREATE INDEX IF NOT EXISTS ix_tickets_queue ON service_tickets (is_deleted, ticket_status, created_at, ticket_id);
CREATE INDEX IF NOT EXISTS ix_tickets_client_location ON service_tickets (client_id, location_id, is_deleted, ticket_status, ticket_id);
CREATE INDEX IF NOT EXISTS ix_tickets_created ON service_tickets (is_deleted, created_at, ticket_id);
