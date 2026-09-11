# Database

MariaDB migrations for the ONESALEZ Service Tracker are stored in `migrations` and must be applied in numeric order. The live Hostinger database currently reports MariaDB 11.8.6.

## Migration 001

`001_create_client_tables.sql` creates:

- `clients`
- `client_locations`
- `client_contact_roles`
- `client_contacts`
- `client_user_accounts`
- `client_contact_locations`

All tables use soft deletion through `is_deleted`, `deleted_by`, and `deleted_at`. Physical deletion is prevented by restrictive foreign keys for related business records.

Every table requires `created_by`; `created_at` is mandatory and defaults to the database timestamp. The `created_by` value is stored as text in this first migration because the ONESALEZ application-user table has not yet been designed.

A contact with `has_all_locations = TRUE` applies to every location belonging to the client. Otherwise, its permitted locations are recorded in `client_contact_locations`.

Client login uses the unique email address stored on `client_contacts`. Authentication state and password, invitation, and reset-token hashes are stored separately in `client_user_accounts`. ONESALEZ employee authentication is outside this client authentication model.

`002_apply_client_authentication.sql` upgrades databases where the original client migration had already been applied before client authentication was added to the baseline.

## Employee migration

`employee.sql` creates:

- `employee_roles`
- `employees`
- `employee_role_assignments`
- `employee_user_accounts`

Employee authentication remains separate from client authentication. Initial employee roles are `SERVICE_EMPLOYEE`, `SERVICE_ADMIN`, and `SYSTEM_ADMIN`.

## Service migration

`service.sql` creates:

- `service_tickets`
- `service_attempts`
- `service_status_history`

The current ticket state supports the Open, Accepted, Completed, and Declined console sections. Each employee acceptance creates an attempt, and every status transition is retained in permanent history.

## Applying a migration

Install `requirements.txt`, provide the `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASSWORD` environment variables, then run:

```powershell
python database\apply_migration.py database\migrations\001_create_client_tables.sql
```
