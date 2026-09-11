# ONESALEZ Service CRM API

Production-oriented PHP 8.3 REST API for client management, employee access, service tickets, leads, analytics, and RBAC.

## Requirements

- PHP 8.3 or newer with PDO MySQL, JSON, and mbstring
- MariaDB 11.8 or compatible MySQL database
- Composer 2
- Apache with `mod_rewrite`, or another web server routing requests to `public/index.php`

## Local setup

```bash
cp .env.example .env
composer install
php bin/migrate.php
php -S 127.0.0.1:8080 -t public
```

Generate a strong `APP_KEY` with `php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"` and place it in `.env`.

Create the first administrator after migrations:

```bash
ADMIN_CODE=EMP001 ADMIN_NAME="System Admin" ADMIN_EMAIL=admin@example.com \
ADMIN_PASSWORD="a-long-random-password" php bin/create-admin.php
```

On Windows, set those variables through PowerShell before running the command.

## Quality checks

```bash
composer cs
composer analyse
composer test
composer check
```

## API conventions

All endpoints are under `/api/v1`. Protected routes require `Authorization: Bearer <access-token>`. Client and employee identities use separate account tables and the login request must declare `realm` as `client` or `employee`.

API responses always contain `success`, `data`, `error`, and an ISO-8601 `timestamp`. List endpoints additionally return pagination metadata.

Ticket acceptance is atomic: only one employee can move an `OPEN` ticket to `ACCEPTED`. Release, completion, and decline operations are transactional and permanently recorded in attempt and status history.

## Password-reset email

Password-reset requests write to `email_jobs` and immediately return a non-enumerating response. Run `php bin/email-worker.php` from cron every minute. Each run safely claims one job using `FOR UPDATE SKIP LOCKED`; multiple workers may run concurrently.

## Hostinger deployment

1. Point the domain document root to `public`, not the repository root.
2. Upload the project outside the public directory when the hosting layout permits it.
3. Run `composer install --no-dev --optimize-autoloader` with PHP 8.3.
4. Create `.env` from `.env.example`; never upload `DEPLOYMENT_PRIVATE.md`.
5. Run `php bin/migrate.php`.
6. Ensure `logs` is writable only by the PHP/web-server user.
7. Schedule the email worker and database backup jobs.
8. Keep `APP_DEBUG=false` and serve HTTPS only.

## Database backups

Take an encrypted daily `mariadb-dump --single-transaction` backup, retain daily backups for 14 days and monthly backups for 12 months, and copy them to storage outside the hosting account. Test a restore quarterly. Back up before every migration or release.

## Docker

Copy `.env.example` to `.env`, provide strong local database passwords, then run `docker compose up --build`. The API is exposed on `http://localhost:8080`; production TLS should terminate at a trusted reverse proxy.

## Security notes

- Passwords use bcrypt with cost 12; reset, invitation, and refresh tokens are stored only as SHA-256 hashes.
- SQL uses native PDO prepared statements. Dynamic sort columns and table names come only from server-owned allowlists.
- Client access is tenant-scoped by `client_id`, including validation of the contact’s permitted ticket location.
- Bearer-token JSON APIs do not use cookie authentication and therefore do not need CSRF tokens. `CsrfMiddleware` is provided for any future cookie-backed form endpoints.
- Refresh tokens rotate on every use. Failed logins lock an account for 15 minutes after five attempts.
- Refresh tokens are delivered only through Secure, HttpOnly, SameSite cookies. For local HTTP development, set `REFRESH_COOKIE_SECURE=false`; production must keep it `true`.

See [OpenAPI](docs/openapi.yaml), [database schema](docs/database-schema.md), and [architecture decision](docs/adr/0001-framework-free-clean-architecture.md).

### Password-reset email

Set `SMTP_HOST=smtp.hostinger.com`, `SMTP_PORT=465`, `SMTP_ENCRYPTION=ssl`, `SMTP_USERNAME`, `SMTP_PASSWORD`, `MAIL_FROM`, and `MAIL_FROM_NAME` in the private `.env`. The sender should match the authenticated mailbox. Never commit real credentials.

Run `php bin/email-worker.php` every minute (Hostinger Advanced → Cron Jobs). The worker serializes execution with a database advisory lock, skips expired/superseded reset links, and records SMTP acceptance or failure in `email_jobs`. `SENT` means accepted by SMTP; inbox placement is not guaranteed. Failed attempts retry after two minutes, at most three times. Inspect job ID, status, attempts, processed time, and last_error; do not expose email bodies containing reset tokens.

The forgot-password form confirms receipt of the request without revealing whether an account exists. A password changes only when the recipient completes the reset form. Links expire 30 minutes after requesting them; requesting another link supersedes the previous one.
