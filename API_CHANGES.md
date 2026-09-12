# API and operational changes — 12 September 2026

The route registry in `backend/bootstrap.php` and the OpenAPI document now cover
the same 58 method/path pairs. Existing examples in `API_USAGE_GUIDE.md` should
be read with these changes.

## Access and sessions

- Client contacts remain separate from ONESALEZ employees. Contacts cannot use
  employee roles to maintain clients or register employees. Any authorized
  employee can maintain clients; employee and team administration requires a
  system administrator.
- Contact ticket list, detail, description and conversation access requires an
  active client/contact/location and the contact's current location grant.
  Internal messages are excluded. Declined tickets require `tickets.decline`
  on all reads, including client history and reports.
- Access JWTs carry a session ID. Each authenticated request checks its active
  refresh session and reloads account status and permissions. Logout, suspension,
  password change and reset revoke sessions immediately. Legacy access tokens
  without a session ID must refresh or sign in again.
- Refresh rotation and reset consumption are transactional and single-use.
  Passwords retain spaces and punctuation exactly. New passwords require at
  least 12 characters, at most 72 UTF-8 bytes, and no null characters.
- Reset email acknowledgement remains non-enumerating; token update and email
  queue insert commit together. SMTP delivery still happens asynchronously.

## Collections and workflows

- `GET /tickets`, `/customers`, `/users`, `/leads` accept `paginated=1` and return
  `data: {items,total,page,limit}`. Tickets also return `counts` for all statuses
  within the same authorized search scope, independent of the selected status.
  Default API limit is 20, maximum 100; list UIs request 25. Without the flag,
  the legacy array in `data` and pagination in top-level `meta` are retained.
- Ledger requests return `{items,summary,filters,page,limit,total}` in `data`;
  maximum batch size is 500. `before_id` enables descending ticket-ID batches
  for complete CSV exports. Exports protect spreadsheet formula text. An export
  is not a transactionally frozen snapshot if records change during download.
- Date filters use inclusive India calendar dates. Invalid dates return 422.
  Reversed dates are normalized. SQL timestamps without offsets mean
  Asia/Kolkata; the UI formats them explicitly in that timezone.
- `POST /leads/{id}/convert` accepts the same `{client,location,administrator}`
  body as onboarding. It creates the client, site, contact and account, then
  marks the lead WON with `converted_client_id` in one transaction. A second
  conversion fails. Onboarding conflicts return validation errors.
- Teams support list/create/update/remove and add/update/remove membership.
  Removed memberships can be restored by adding the same employee again.
- Nested DELETE contact/location routes soft-remove access while preserving
  historical tickets. Contact removal revokes sessions and PIN devices.
- Ticket acceptance creates one attempt under a lock. Release preserves the
  attempt and returns OPEN. Completion closes the attempt. An administrator
  may decline OPEN, ACCEPTED or COMPLETED tickets, with a mandatory reason.
- The client portal now reads and posts public ticket conversation messages.
  The employee console retains the internal-message option.

## Browser behavior

The login page and Settings contain **Reset app**. It clears in-memory application
data and cached application files and reloads the login page. Session cookies and PIN profiles
are retained by the cache reset; Settings offers a separate action to remove
PIN login. This does not clear the browser's unrelated browsing data or change
the account password. Ordinary browser HTTP cache cannot be selectively erased
by JavaScript; application assets use content hashes and the reset reload uses
a fresh URL. Theme settings synchronize between views and browser tabs.

## Deployment and recovery

Main deployments depend on the reusable quality workflow: real MariaDB
migrations/tests, PHP static/style/dependency checks, frontend build/unit/browser
checks, and the PHP container build. Branch failures cannot reach production.

Install changes to `deploy/receive-release.sh` separately through administrator
SSH. The receiver saves private code/configuration and compressed database
backups, then applies migrations before switching the frontend entry point.
It checks live health and login HTML before recording the revision. File rollback
does not reverse additive schema changes; a database backup is for deliberate
recovery, never an automatic overwrite of new customer work.

`php backend/bin/backup.php PRIVATE_EXISTING_DIRECTORY [ENV_DIRECTORY]` uses
`mariadb-dump`, private temporary credentials and gzip. Backups must stay outside
`public_html`. A restore was tested against a second disposable local database.
Server backups need off-host retention as an operational policy; no retention
period or external destination has been invented in this change.

The migration runner holds a database lock, records normalized SQL checksums
and APPLYING/FAILED/APPLIED attempts, and refuses changed or unfinished files.
Applied historical files with no checksum are baselined on first run. MariaDB
DDL can commit partially: inspect the schema and failed statement before using
`--retry-reviewed`; do not edit already applied migration files.
