# Database schema

## Client domain

- `clients` owns `client_locations` and `client_contacts`.
- `client_contact_roles` identifies system operators, end users, client-side administrators, and owners.
- `client_contact_locations` grants a contact selected locations; `has_all_locations` grants all client locations.
- `client_user_accounts` is one-to-one with `client_contacts` and contains authentication state only.

## Employee domain

- `employees` is the ONESALEZ employee master.
- `employee_roles` and `employee_role_assignments` provide many-to-many roles.
- `permissions` and `employee_role_permissions` translate roles into API permissions.
- `employee_user_accounts` is one-to-one with employees.
- `teams` and `employee_team_members` provide operational grouping and team-lead designation.

## Service domain

- `service_tickets` holds the current console state and links client, location, reporting contact, and current employee.
- `service_attempts` records every acceptance. Released attempts return the ticket to Open; completed attempts close it.
- `service_status_history` is the append-only status audit.
- `ticket_messages` stores the client/employee conversation and employee-only internal notes.

## Supporting domains

- `leads` contains sales prospects before they become clients.
- `auth_refresh_tokens` stores hashed, rotating refresh tokens for both identity realms.
- `api_rate_limits` implements a shared database-backed rate limiter.
- `email_jobs` is the asynchronous email outbox.
- `schema_migrations` records applied PHP migrations.

All business masters use soft deletion. Foreign keys use `ON DELETE RESTRICT`, creation user/time are mandatory, and reporting indexes cover ticket status, client, employee, location, and dates.

