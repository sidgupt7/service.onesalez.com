# ONESALEZ Service CRM — Application and Agent Handbook

Version: 1.1  
Source review date: 11 September 2026  
Project folder: `C:\Users\sid\Documents\ONESALEZ_SERVICE_CRM`  
Deployment website: `https://service.onesalez.com`

This handbook describes the business requirements, functionality found in the current source, database design, installation, deployment, and remaining work. It is intended for the project owner, developers, and future coding agents.

**Evidence boundary:** “Implemented” means code exists in this workspace. This documentation review did not execute migrations, inspect the live database, deploy files, or run application tests. Local code, previously uploaded releases, and the live database can differ. Database object descriptions below are derived from the versioned migrations and PHP migration runner.

## Index

1. [Business purpose and scope](#1-business-purpose-and-scope)
2. [Technology stack](#2-technology-stack)
3. [Project structure and architecture](#3-project-structure-and-architecture)
4. [Users, roles, and access](#4-users-roles-and-access)
5. [Application functionality](#5-application-functionality)
6. [Service ticket lifecycle](#6-service-ticket-lifecycle)
7. [Web routes and API reference](#7-web-routes-and-api-reference)
8. [Database environment and conventions](#8-database-environment-and-conventions)
9. [Database schemas and table dictionary](#9-database-schemas-and-table-dictionary)
10. [Database relationships and integrity](#10-database-relationships-and-integrity)
11. [Views, procedures, functions, triggers, and events](#11-views-procedures-functions-triggers-and-events)
12. [Database migrations](#12-database-migrations)
13. [Pre-installation, installation, and running the application](#13-pre-installation-installation-and-running-the-application)
14. [Configuration reference](#14-configuration-reference)
15. [Hostinger deployment](#15-hostinger-deployment)
16. [Security, logging, and operations](#16-security-logging-and-operations)
17. [Testing and quality checks](#17-testing-and-quality-checks)
18. [Remaining work and known limitations](#18-remaining-work-and-known-limitations)
19. [Troubleshooting](#19-troubleshooting)
20. [Maintenance rules and document references](#20-maintenance-rules-and-document-references)

Setup shortcuts: [Pre-installation guide](#131-pre-installation-guide) · [Backend installation](#132-backend-setup) · [Administrator setup](#133-first-administrator-and-account-recovery) · [Frontend installation](#134-frontend-setup) · [Start the application](#135-start-the-two-servers) · [Installation verification](#137-post-installation-verification) · [Hostinger deployment](#15-hostinger-deployment).

## 1. Business purpose and scope

ONESALEZ develops software and provides ongoing support to trading businesses engaged in retail and wholesale. A client can have multiple locations across multiple Indian states. A contact can represent one, several, or all locations of that client.

The CRM records client details, locations, contacts, employee accounts, service requests, service attempts, conversations, and outcomes. Employees share a service queue. Management needs one reporting system that can show the complete service ledger, client activity, employee activity, pending work, and declined requests for a date range.

The original business rules are:

- ONESALEZ staff onboard clients, locations, and contact persons.
- Email identifies a login; mobile numbers are not login identifiers. Contact telephone fields still exist in the schema.
- A client contact submits an issue and receives a unique service request number.
- An employee accepts an open ticket, works on it, and either completes it or releases it with a note.
- Released tickets return to the shared open queue with previous attempts retained.
- Authorized ONESALEZ administrators can decline service requests, with a reason and history.
- Business deletion is soft deletion, with creation user and creation timestamp recorded.
- An ONESALEZ administrator and a client-site administrator are separate identities with different authority.

The current delivery is a React web application/PWA backed by a PHP API. A separate native MAUI application is not present. Leads and teams extend the original service-management scope.

## 2. Technology stack

### 2.1 Frontend

| Technology | Purpose | Current use/status |
| --- | --- | --- |
| React | Component-based web interface | Application pages and reusable components |
| TypeScript | Static checking of frontend code and API types | Project uses TypeScript configuration and typed feature APIs |
| Vite | Local server and production compilation | Generates static output in `webapp/dist` |
| Tailwind CSS | Responsive styling and themes | Integrated through `@tailwindcss/vite` |
| shadcn-style components | Reusable UI foundation | Local Button/Input components; a complete shadcn/ui component library is not present |
| React Router | Navigation and protected routes | Login, portal, console, and nested administration routes |
| TanStack Query | Server state, API caching, invalidation, refresh | Used across authentication-adjacent and business screens |
| TanStack Table | Sorting, filters, pagination | Approved and installed; not consistently adopted across screens |
| React Hook Form + Zod | Forms and validation | Installed and used in form foundations; adoption varies by module |
| react-i18next + i18next | Multilingual support | Installed; application-wide translations and language switching remain work |
| Lucide React | Icons | Used in navigation, forms, and workflow controls |
| Recharts | Dashboard charts | Installed; executive dashboard charts remain work |
| vite-plugin-pwa | Installable application shell | Manifest and service-worker generation configured |
| Vitest + Testing Library | Unit/component testing | Login component test and test setup exist |
| Playwright | Browser/feature testing | Login and client-onboarding test files exist |

Frontend dependencies are declared in `webapp/package.json`. Many declarations use `latest`; the committed `package-lock.json` is the reproducible version record. Use `npm ci` for an existing checkout instead of updating dependencies unintentionally.

### 2.2 Backend

| Technology | Purpose | Configuration/source |
| --- | --- | --- |
| PHP 8.3+ | HTTP API and business logic | `backend/composer.json` requires `^8.3`; strict typing used in PHP source |
| Composer 2 | Dependencies and PSR-4 autoloading | `App\\` maps to `backend/src/` |
| PDO MySQL | SQL connections, prepared statements, transactions | `src/Database/Database.php` |
| firebase/php-jwt | Signed access tokens | `TokenService` |
| PHP bcrypt | Password and PIN hashing | `password_hash()` / `password_verify()`, cost 12 |
| vlucas/phpdotenv | Environment configuration | `.env` loaded during bootstrap and CLI tasks |
| Monolog | Structured JSON logs with rotation | `LoggerFactory` |
| Custom MVC/service/repository architecture | Business rules separated from HTTP and SQL | Framework-free application; no Laravel or Symfony framework dependency |

PHP 8.2 is below the declared runtime requirement even if some source happens to execute with it. Composer currently sets an emulated PHP platform and disables its generated platform check; this does not establish actual runtime compatibility. Check the CLI and hosting PHP versions explicitly.

### 2.3 Database, infrastructure, and development tools

| Technology | Purpose | Current position |
| --- | --- | --- |
| MariaDB 11.8 family | Relational data store | SQL targets MariaDB; older project notes report 11.8.6, not freshly verified |
| InnoDB / utf8mb4 | Transactions, foreign keys, Unicode | Used by table definitions |
| Hostinger Business Web Hosting | Production static webapp and PHP execution | Existing deployment target supplied by project owner |
| Apache-compatible `.htaccess` rules | HTTPS, API routing, SPA fallback, source protection | Templates in `.deployment/` |
| Node.js + npm | Frontend development/build only | No Node.js/Express production backend is needed |
| Python + PyMySQL | Optional manual SQL migration runner | `backend/database/apply_migration.py` |
| Docker / Docker Compose | Optional local API and MariaDB environment | `backend/Dockerfile`, `backend/docker-compose.yml` |
| PHP OPcache | Production PHP performance | Docker PHP configuration provided |
| PHPUnit | Backend tests | Unit and feature suites |
| PHPStan | Static analysis | Level 8 with a documented heterogeneous-array exclusion |
| PHPCS | Coding-style checks | Composer `cs` command |
| GitHub Actions | Automated backend quality checks | CI workflow includes an 80% coverage gate |

PDO is synchronous. The database wrapper reuses its connection and enables persistent PDO connections through configuration; it is not a dedicated pool manager. Email is queued for later processing, but the email worker itself uses synchronous PHP `mail()`.

## 3. Project structure and architecture

```text
ONESALEZ_SERVICE_CRM/
  agent.md                     This indexed application handbook
  BASE_UNDERSTANDING_V1.md     Original business understanding
  WEB_APP_HANDBOOK.md          Approved frontend/UX requirements
  API_USAGE_GUIDE.md           API examples and request syntax
  start-dev.bat               Starts the local API and Vite server
  ASSETS/                     Original branding assets
  backend/
    bootstrap.php             Dependency wiring and actual API route registration
    composer.json             PHP dependencies and quality scripts
    config/                   Application and database configuration
    public/                   PHP HTTP entry point and web-server rules
    src/
      Controllers/            HTTP input/output coordination
      Services/               Business rules and workflow orchestration
      Repositories/           SQL and persistence operations
      Models/                 Actor identity model
      Database/               PDO abstraction and query builder
      Middleware/             Authentication, permissions, CORS, rate limits, headers
      Exceptions/             Typed application exceptions
      Http/                   Request, response, and router
      Support/                Dependency container, logging, exception handling
      Utils/                  Validation and input helpers
    database/migrations/      Canonical SQL migration files
    database/apply_migration.py
    bin/                      Migrations, admin recovery, email, coverage utilities
    docs/                     OpenAPI, database summary, architecture decision
    tests/                    PHPUnit tests
    logs/                     Runtime logs
    docker/                   Apache and PHP container configuration
    vendor/                   Composer-generated dependencies
  webapp/
    src/app/                  Routes and QueryClient configuration
    src/features/             Auth, admin, clients, employees, portal, service, reports
    src/components/           Shared UI components
    src/lib/                  API client and helpers
    src/styles/               Global styling
    src/test/                 Component test support
    public/                   Browser assets and icons
    tests/e2e/                Playwright tests
    dist/                     Generated production build
  .github/workflows/          CI configuration
  .deployment/               Private deployment material and routing templates
  .release/                  Prepared release copies, not canonical source
```

PHP-related application files belong under `backend/`. Use `backend/database/` as the migration source rather than the legacy root `database/` directory. Never edit generated `vendor/`, `node_modules/`, `dist/`, or a historical release to implement a source change.

Request flow: browser page → feature API helper → PHP entry point → global middleware → router/route middleware → controller → service → repository → PDO/database. Responses return through the JSON response wrapper. Dependencies are wired in `backend/bootstrap.php`; exceptions are translated into HTTP responses and logs.

## 4. Users, roles, and access

### 4.1 Separate identity realms

| Realm | Identity table | Authentication table | Meaning |
| --- | --- | --- | --- |
| `client` | `client_contacts` | `client_user_accounts` | Person employed by or representing a customer |
| `employee` | `employees` | `employee_user_accounts` | ONESALEZ employee |

API actors use `CLIENT_CONTACT` and `EMPLOYEE`. Login includes the realm, allowing the API to select the correct authentication model. Passwords belong in authentication tables, not in client or employee master records.

### 4.2 Client roles

`SYSTEM_OPERATOR`, `END_USER`, `CLIENT_ADMIN`, and `OWNER` describe the contact's role at the client business. `CLIENT_ADMIN` does not grant ONESALEZ system administration. `has_all_locations` or rows in `client_contact_locations` specify location access.

### 4.3 ONESALEZ roles and seeded permissions

| Employee role | Seeded permissions |
| --- | --- |
| `SERVICE_EMPLOYEE` | `tickets.manage` |
| `SERVICE_ADMIN` | `tickets.manage`, `tickets.decline`, `clients.manage`, `leads.manage`, `analytics.view` |
| `SYSTEM_ADMIN` | All six seeded permissions, including `employees.manage` |

Employees can have multiple roles through `employee_role_assignments`. Permissions are linked to roles through `employee_role_permissions`.

**Current implementation differences:** client maintenance in `ClientService` generally checks only that the caller is an employee. It does not consistently enforce `clients.manage`. Its contact-management path restricts assignment of `CLIENT_ADMIN` to a `SYSTEM_ADMIN`, but onboarding and registration use different paths. `UserService::registerContact()` separately permits a client-side administrator to create contacts through `/auth/register`. These paths need reconciliation with the approved admin-only rules; do not infer identical authorization from screen labels or the seed permission matrix.

## 5. Application functionality

### 5.1 Authentication and account recovery

Implemented source includes email/password login for both realms, account status and lock checks, signed access tokens, rotating refresh tokens, session restoration, logout, password change, password-reset requests, reset submission, and authenticated account registration.

The browser keeps the access token in memory. Refresh tokens use an HttpOnly cookie, with Secure and SameSite settings. Defaults are a 15-minute access token and a 30-day refresh lifetime. Failed password attempts trigger temporary locking. Forgot-password returns a non-enumerating response and queues email work.

Trusted-device quick login has enrollment, PIN verification, expiry, lockout, and revocation support. `auth_trusted_devices` stores a hashed device credential and bcrypt PIN hash. This is an optional convenience login after enrollment, not mobile-number authentication or a second-factor MFA implementation.

CLI utilities create the initial system administrator and recover an existing administrator account. Passwords are supplied through environment variables and are not documented here.

### 5.2 Employee workspace and navigation

The administration layout has a sidebar, mobile navigation, header/account identity, page navigation, API health indication, theme switching, password change, and logout. Employee administration is restricted by its route to `SYSTEM_ADMIN`; reports require `analytics.view`.

The overview currently displays account/access information and static status cards. It does not yet load the executive service metrics from the analytics API. In particular, the overview's static “Connected” wording is not database readiness evidence.

### 5.3 Client onboarding and maintenance

The onboarding wizard collects the client business, initial location, and initial client administrator. The backend validates identifiers and contact information, checks conflicts, hashes the password, and creates the related records through a transaction.

Client master information includes code, legal/display names, GSTIN/PAN, primary contact details, website, notes, active status, and audit fields. Client management supports listing/search, details, edits, suspension/reactivation, and API soft deletion. Location management supports creation, editing, and activation changes. Contact management supports personal details, role, permitted sites, portal access, password setting, and activation changes.

Most descriptive client/location/contact fields are normalized to uppercase; email is normalized separately. A contact may have a stored mobile number for service callbacks even though email is the login identifier. Creating a login requires a password under the current provisioning flow; an invitation-completion workflow is not fully exposed merely because invitation columns exist.

### 5.4 Client portal

Authenticated client contacts can view client-related ticket information, choose an authorized active location when raising an issue, enter a subject/description/priority, receive a service request number, view ticket details and attempts, and use the conversation interface.

Clients can edit the problem description while a ticket is open or accepted. Previous and replacement descriptions are retained in `service_ticket_description_history`. Employee-only internal messages are filtered out of client message responses.

Ticket creation, listing, detail, description updates, and public messages enforce the contact's active client and location grants. Declined visibility requires tickets.decline. These controls were exercised against MariaDB during the 12 September audit.

### 5.5 Service console

Employees see Open, Accepted, and Completed queues; the Declined tab is conditionally shown to users with decline authority. Queue/detail queries refresh every 15 seconds, with manual refresh available. This is polling, not WebSocket push.

The workspace supports acceptance, release with note, completion with resolution, authorized decline with reason, priority updates, shared conversation, internal notes, and viewing previous service attempts and description changes. Only the accepting employee can release or complete an accepted ticket. Other employees can see it remains assigned.

Ticket assignment currently means self-acceptance from the shared queue. There is no general dispatch/reassignment feature. API soft deletion is allowed only for completed or declined tickets. General subject editing, attachment handling, reopening, and SLA escalation are not implemented.

### 5.6 Employee management and teams

System administrators can create employees with login accounts and roles, edit employee details/roles, suspend/reactivate access, reset passwords, and soft-delete employees. Employee data includes code, official email, name, mobile, designation, department, joining date, and employment status.

The service checks prevent self-suspension/self-deletion and removal of one's own system-admin role. It also checks that at least one active system administrator remains. Simultaneous administrative changes still require concurrency tests.

Team creation and membership assignment APIs exist. Membership can identify a team lead. A complete team-management screen and full membership update/removal workflow are not present.

### 5.7 Leads

The backend provides lead creation, retrieval, update, soft deletion, list/search/filter/pagination, assignment to an employee, and bulk status updates. Statuses are `NEW`, `CONTACTED`, `QUALIFIED`, `WON`, and `LOST`.

The frontend Leads menu is disabled and there is no active Leads route. Changing a lead to `WON` does not automatically create a client; lead conversion remains a separate feature to implement.

### 5.8 Unified service reporting

`/admin/reports` uses a single service ledger with date range, client, location, employee, status, and text filters. It provides presets for the requested reporting categories, summary counts, average resolution minutes, a ledger table, and CSV export.

`PENDING` is a report filter meaning `OPEN` plus `ACCEPTED`; it is not a stored ticket status. The ledger selects tickets by their **creation date**. Employee filtering selects tickets with an attempt by that employee, not only tickets currently assigned to them. Rows include client/location names, reporting contact, participating employees, attempt count, completion/resolution, and decline information.

The analytics endpoint returns status totals and per-employee attempts, completions, releases, and average attempt duration. Despite its `team_performance` response key, this query groups by employee rather than by the `teams` table.

The current reporting screen requests at most 500 rows and exports those loaded rows. The ticket portal/console fetch the first 100 tickets. Full pagination and complete export across larger datasets remain work.

### 5.9 PWA and user experience

The PWA configuration generates an installable shell with a manifest, icon, navigation fallback, and automatic service-worker updates. Runtime API caching is empty. Offline ticket creation, mutation queues, conflict resolution, and push notifications are not implemented. Language packages and charting packages are installed, but their presence is not evidence of completed multilingual screens or dashboard charts.

## 6. Service ticket lifecycle

| Action | Required current state | Result | Records and important rules |
| --- | --- | --- | --- |
| Client creates ticket | New request | `OPEN` | Client, location, reporting contact, description, request number; initial status history |
| Employee accepts | `OPEN` | `ACCEPTED` | Conditional update assigns employee/time; creates numbered `IN_PROGRESS` attempt and history |
| Accepting employee releases | `ACCEPTED` | `OPEN` | Ends attempt as `RELEASED`, saves service note, clears current ownership, retains history |
| Accepting employee completes | `ACCEPTED` | `COMPLETED` | Ends attempt as `COMPLETED`, saves final resolution and completing employee/time |
| Authorized employee declines | `OPEN` or `ACCEPTED` | `DECLINED` | Saves reason and declining employee/time; active attempt becomes `CANCELLED_BY_DECLINE` |
| Client edits description | `OPEN` or `ACCEPTED` | Unchanged | Stores old/new description and changing contact |
| Soft delete | `COMPLETED` or `DECLINED` | Hidden by deletion filter | Preserves ticket and associated records |

Acceptance and attempt-ending transitions use database transactions. The conditional acceptance update ensures one employee wins a competing acceptance. The creation of a ticket and its first history row are currently separate service calls rather than one encompassing transaction.

The original request described administrator decline “at any stage.” Current code rejects decline after completion or an earlier decline. The documented current behavior is therefore narrower; any change needs an explicit lifecycle decision and matching tests.

## 7. Web routes and API reference

### 7.1 Browser routes

| Route | Screen/access |
| --- | --- |
| `/login` | Email/password and quick-login interface |
| `/reset-password` | Password-reset form using reset link parameters |
| `/portal` | Client-contact portal |
| `/console` | Employee service console |
| `/admin` | Employee workspace overview/layout |
| `/admin/clients` | Employee client onboarding/maintenance |
| `/admin/employees` | System-administrator employee management |
| `/admin/reports` | Service ledger for users with analytics access |

### 7.2 API groups

All paths below are relative to `/api/v1`. `backend/bootstrap.php` is the actual route registry. Consult [API_USAGE_GUIDE.md](API_USAGE_GUIDE.md) and [OpenAPI](backend/docs/openapi.yaml) for field-level examples, and compare them with current controllers before changing a contract.

| Group | Methods and paths | Function |
| --- | --- | --- |
| Health | `GET /health` | API liveness; no database query |
| Login/session | `POST /auth/login`, `/auth/refresh`, `/auth/logout` | Establish, renew, end session |
| Passwords | `POST /auth/forgot-password`, `/auth/reset-password`, `/auth/change-password` | Recovery and authenticated password changes |
| Trusted device | `POST /auth/pin-login`, `POST /auth/trusted-device`, `DELETE /auth/trusted-device` | Quick login, enrollment, revocation |
| Registration | `POST /auth/register` | Authenticated account provisioning; see authorization differences |
| Clients | `GET/POST /customers`, `GET/PUT/DELETE /customers/{id}` | Master list/create/read/update/soft delete |
| Onboarding | `POST /customers/onboard` | Create client, initial site, administrator/account |
| Client status/history | `POST /customers/{id}/{action}`, `GET /customers/{id}/service-history` | Suspend/reactivate and history |
| Locations | `POST /customers/{id}/locations`, `PUT /customers/{id}/locations/{location_id}`, `POST .../{location_id}/{action}` | Add/edit/change active status |
| Contacts | `POST /customers/{id}/contacts`, `PUT /customers/{id}/contacts/{contact_id}`, `POST .../{contact_id}/{action}` | Add/edit/change active status and portal account details |
| Leads | `GET/POST /leads`, `GET/PUT/DELETE /leads/{id}`, `POST /leads/bulk-status` | Lead management |
| Tickets | `GET/POST /tickets`, `GET/DELETE /tickets/{id}` | List/create/detail/terminal soft delete |
| Ticket actions | `POST /tickets/{id}/{action}` | `accept`, `release`, `complete`, `decline` |
| Ticket content | `PUT /tickets/{id}/priority`, `PUT /tickets/{id}/description`, `POST /tickets/{id}/messages` | Priority, description revision, conversation |
| Analytics | `GET /analytics`, `GET /reports/service-ledger` | Metrics and unified ledger |
| Employees | `GET/POST /users`, `GET/PUT/DELETE /users/{id}` | Employee administration |
| Employee access | `POST /users/{id}/suspend`, `POST /users/{id}/reactivate`, `PUT /users/{id}/password` | Account lifecycle/recovery |
| Teams | `POST /teams`, `POST /teams/{team_id}/members` | Team creation and membership |

Protected requests send `Authorization: Bearer <access-token>`. Login/reset endpoints have their own access and rate-limit rules; refresh uses the browser cookie. Mutation payloads are JSON. Generic `{action}` routes validate supported actions rather than accepting arbitrary commands.

Responses contain `success`, `data`, `error`, and an ISO-8601 `timestamp`. Standard list controllers may put pagination under `meta`; the reporting response contains `items`, `summary`, `filters`, `page`, `limit`, and `total` inside `data`. Do not assume every list has the same nested data shape.

## 8. Database environment and conventions

### 8.1 Environment details

| Item | Project configuration |
| --- | --- |
| Database/schema | `u606070148_os_service` |
| Database account | `u606070148_serv` |
| Remote database host supplied by owner | `srv1015.hstgr.io` |
| Database port | `3306` by default |
| Deployed PHP connection host | Set `DB_HOST` for the hosting environment; `.env.example` uses `localhost` |
| SQL dialect | MariaDB; do not assume all migrations run unchanged on Oracle MySQL |
| Main engine/encoding | InnoDB, `utf8mb4`, `utf8mb4_unicode_ci` |
| Database session timezone | `+05:30` |
| Application timezone | `Asia/Kolkata` default |

Database passwords, SSH keys, and administrator passwords are intentionally excluded. Use the local ignored private deployment record and environment configuration. These connection identifiers describe the project configuration, not a fresh reachability check.

### 8.2 Shared columns and conventions

Most business tables have unsigned numeric primary keys and `created_by VARCHAR(100)` plus `created_at DATETIME(6)`. The creation user is a textual actor identifier, not a foreign key to one shared user table. `created_at` normally defaults to `CURRENT_TIMESTAMP(6)`.

Mutable business records generally add `updated_by` and `updated_at`. Soft-deletable records use `is_deleted`, `deleted_by`, and `deleted_at`; many tables have a CHECK requiring deletion actor/time when deleted. Active/suspended state is separate from deletion.

Not every supporting table follows the master-record pattern. Tokens use expiry/revocation, rate-limit rows use expiry, description history is append-only by application behavior, permission links have no soft-delete columns, and migration rows have only filename/time. Foreign keys are generally restrictive; they do not universally prevent physical deletion of an unreferenced row. Soft deletion must remain an application policy.

## 9. Database schemas and table dictionary

There is one MySQL/MariaDB database schema with logical client, employee, service, sales, authentication, and infrastructure domains. The current SQL defines **24 distinct application tables**; the PHP runner creates **one additional `schema_migrations` table**, giving 25 expected tables after all migrations through 006. This is the expected source-defined inventory, not a live table count.

### 9.1 Client domain — six tables

| Table and primary key | Significant fields | Description and functionality |
| --- | --- | --- |
| `clients` — `client_id` | `client_code`, `legal_name`, `display_name`, `gstin`, `pan`, `primary_email`, `primary_phone`, `website_url`, `notes`, `is_active` | Trading-house master and tenant boundary. Code and non-null GSTIN are unique. Owns locations, contacts, and service tickets. Includes full creation/update/deletion audit fields. |
| `client_locations` — `location_id` | `client_id`, `location_code`, `location_name`, `location_type`, address lines, landmark, city, district, state, postal code, GSTIN, email, phone, primary/active flags | Physical client site. Type is `HEAD_OFFICE`, `BRANCH`, `WAREHOUSE`, or `OTHER`. Location code is unique within a client. Indexed for client status and state/city lookup. |
| `client_contact_roles` — `contact_role_id` | `role_code`, `role_name`, `description` | Seeded client role dictionary: operator, end user, client administrator, owner. Unique code; role applies to a contact. Includes master audit/soft-delete fields. |
| `client_contacts` — `contact_id` | `client_id`, `contact_role_id`, `full_name`, `designation`, `email`, `mobile_number`, `alternate_number`, `has_all_locations`, `is_primary_contact`, `is_active` | Person associated with one client and one client role. Email is required and globally unique within this table. Mobile is required contact data. References account and location grants. |
| `client_user_accounts` — `account_id` | Unique `contact_id`, `password_hash`, `account_status`, invitation/reset hashes and expiries, failed attempts, lock time, login/password timestamps, suspension actor/time/reason | Optional one-to-one authentication record for a contact. Status is `INVITED`, `ACTIVE`, or `SUSPENDED`. Nullable hash supports an invited/unprovisioned account; passwords are never stored as plaintext. |
| `client_contact_locations` — `contact_location_id` | `contact_id`, `location_id`, creation/update/deletion fields | Many-to-many grant for selected locations. Unique contact/location pair. Used when the contact does not have `has_all_locations=TRUE`. Application validation ensures both records belong to the same client. |

Source: [001_create_client_tables.sql](backend/database/migrations/001_create_client_tables.sql); authentication compatibility upgrade in [002_apply_client_authentication.sql](backend/database/migrations/002_apply_client_authentication.sql).

### 9.2 Employee and authorization domain — eight tables

| Table and primary key | Significant fields | Description and functionality |
| --- | --- | --- |
| `employees` — `employee_id` | `employee_code`, `full_name`, `official_email`, mobile, designation, department, joining date, `employment_status` | ONESALEZ staff master. Code and email are unique. Employment status is `ACTIVE`, `SUSPENDED`, or `LEFT`. Includes audit/soft-delete fields. |
| `employee_roles` — `employee_role_id` | `role_code`, `role_name`, `description` | Seeded `SERVICE_EMPLOYEE`, `SERVICE_ADMIN`, `SYSTEM_ADMIN` roles. Separate from client role dictionary. |
| `employee_role_assignments` — `employee_role_assignment_id` | `employee_id`, `employee_role_id`, audit/deletion fields | Many-to-many assignment of roles to employees; employee/role pair is unique. |
| `employee_user_accounts` — `account_id` | Unique `employee_id`, password hash, account status, invitation/reset hashes and expiries, lockout/login/password/suspension fields | Employee login state. Follows the client-account pattern while referencing an employee identity. |
| `teams` — `team_id` | Unique `team_name`, `description`, audit/deletion fields | Operational employee grouping. Does not itself own or assign tickets. |
| `employee_team_members` — `team_member_id` | `team_id`, `employee_id`, `is_team_lead`, creation/deletion fields | Many-to-many membership with a team-lead indicator. Team/employee pair is unique. |
| `permissions` — `permission_id` | Unique `permission_code`, `description`, creation fields | API permission dictionary containing the six permissions in Section 4. No soft-delete columns. |
| `employee_role_permissions` — `employee_role_permission_id` | `employee_role_id`, `permission_id`, creation fields | Unique role/permission mapping used to build employee capabilities. No separate per-employee permission override table. |

Sources: [employee.sql](backend/database/migrations/employee.sql) and [003_application_features.sql](backend/database/migrations/003_application_features.sql).

### 9.3 Service domain — five tables

| Table and primary key | Significant fields | Description and functionality |
| --- | --- | --- |
| `service_tickets` — `ticket_id` | Unique `service_request_number`; client/location/reporting-contact IDs; subject/description; priority/status; current employee and accepted time; completion employee/time/resolution; decline employee/time/reason; audit/deletion fields | Current service request state. Priority is `NORMAL`, `HIGH`, or `URGENT`; status is `OPEN`, `ACCEPTED`, `COMPLETED`, or `DECLINED`. CHECK constraints require completion or decline metadata for those terminal statuses. |
| `service_attempts` — `attempt_id` | `ticket_id`, `attempt_number`, `employee_id`, `attempt_status`, `accepted_at`, `ended_at`, `service_note`, audit/deletion fields | One row per employee acceptance. Unique ticket/attempt number. Status is `IN_PROGRESS`, `RELEASED`, `COMPLETED`, or `CANCELLED_BY_DECLINE`. Finished attempts require end time and note. Supports repeated attempts and employee-duration reporting. |
| `service_status_history` — `status_history_id` | `ticket_id`, nullable `from_status`, `to_status`, `actor_type`, nullable contact/employee IDs, `change_note`, creation/deletion fields | Historical status transitions. Actor is `CLIENT_CONTACT`, `EMPLOYEE`, or `SYSTEM`; CHECK enforces corresponding actor IDs. Initial creation has no previous status. No update/delete history API is exposed, although the table has soft-delete fields. |
| `ticket_messages` — `message_id` | `ticket_id`, `author_type`, contact/employee IDs, `message`, `is_internal`, audit/deletion fields | Ticket conversation and internal employee notes. CHECK constrains author identity and prevents client-authored internal notes. Indexed by ticket/deletion/time. |
| `service_ticket_description_history` — `description_history_id` | `ticket_id`, `previous_description`, `new_description`, `changed_by_contact_id`, `created_by`, `created_at` | Description revision log. Retains who changed the description, before/after text, and timestamp. References ticket and contact; indexed by ticket/time. No soft-delete/update columns. |

Sources: [service.sql](backend/database/migrations/service.sql), migration 003, and [006_ticket_description_history.sql](backend/database/migrations/006_ticket_description_history.sql).

Request numbers are generated in PHP using `SR-` plus a UTC date and random hexadecimal suffix. They are unique business references, not sequential invoice-style counters. Internal primary keys remain auto-increment numeric IDs.

### 9.4 Sales domain — one table

`leads` has primary key `lead_id`. It stores `business_name`, `contact_name`, required email, phone, source, status, optional `assigned_employee_id`, notes, and creation/update/deletion metadata. The employee foreign key records responsibility. Indexes support status/deletion/name searches and employee/status queries. No foreign key or conversion record currently links a won lead to a new client.

### 9.5 Authentication and infrastructure — five tables including migration tracking

| Table and primary key | Significant fields | Description and functionality |
| --- | --- | --- |
| `auth_refresh_tokens` — `refresh_token_id` | `actor_type`, `actor_id`, unique `token_hash`, expiry, revocation, `replaced_by_token_id`, user agent, IP, creation fields | Hashed refresh credentials for both identity realms, with a self-reference to replacement tokens. Actor validity is enforced in application logic rather than a polymorphic foreign key. Indexed by actor/revocation and expiry. |
| `auth_trusted_devices` — `trusted_device_id` | Unique `device_id` and `token_hash`, actor type/ID, device name, PIN hash, expiry, failed attempts/lock time, last use/IP, user agent, revocation, creation fields | Registered device and PIN credential for quick login. Separate from password authentication and refresh tokens. Actor and expiry/revocation indexes support lookup and cleanup. |
| `api_rate_limits` — `rate_limit_key` | `window_started_at`, `request_count`, `expires_at` | Shared request-counter windows across PHP requests/workers. Key is a 64-character hash. Technical expiry-based table without business audit/soft-delete columns. |
| `email_jobs` — `email_job_id` | Recipient, subject, body, `job_status`, attempts, available/processed times, last error, creation fields | Email outbox. Status is `PENDING`, `SENT`, or `FAILED`. Queue index is status/available time. Current consumer handles password-recovery email through `mail()`. |
| `schema_migrations` — `migration` | Filename primary key; `applied_at` | Created by `bin/migrate.php`, not an SQL migration. Records completed migration filenames; there are no checksums, rollback scripts, or migration locks. |

## 10. Database relationships and integrity

The key relationships are:

- A client owns many locations and contacts; each contact has a client role and zero or one account.
- Contacts obtain access to locations through explicit grants or their all-locations flag.
- An employee has zero or one login account, multiple assigned roles, and multiple team memberships.
- Roles have multiple permissions; both role systems remain separate.
- A ticket references one client, one location, and one reporting contact. Employee references identify current ownership, completion, and decline.
- A ticket has many attempts, status transitions, conversation messages, and description revisions.
- An employee has many service attempts and may be assigned many leads.

Foreign keys use restrictive deletion/update behavior. Same-client consistency between a ticket's client, location, and reporting contact is not enforced by a composite foreign key; service validation is essential. The contact/location grant table has the same application-level same-client requirement.

Unique constraints protect client codes/GSTINs, location codes within a client, contact email, employee code/email, role codes, account-to-person links, request numbers, ticket attempt numbers, membership pairs, and authentication hashes. Soft-deleted records continue to occupy their unique values; reinstatement or reuse needs deliberate handling.

Ticket indexes cover console status/deletion/date, client/status/date, current employee/status, and location/date. Attempt indexes cover employee/acceptance time and outcome/acceptance time. These support ordinary service reporting, but large data volumes and substring searches still need query-plan/performance tests.

## 11. Views, procedures, functions, triggers, and events

### 11.1 Database views

No `CREATE VIEW` definitions were found in the project's SQL migrations. Reporting is performed by parameterized repository queries; `serviceLedger()`, `summary()`, and `teamPerformance()` in `AnalyticsRepository` are PHP methods, not database views.

### 11.2 Stored procedures

No stored procedures are defined in the migrations. Client onboarding, authentication, ticket acceptance/release/completion/decline, and employee administration run through PHP services/repositories. There are no project procedure names or parameter contracts to list at this version.

Both migration runners split SQL on semicolons. They do not support arbitrary stored-procedure bodies or MySQL client `DELIMITER` directives. Extend the runner before adding routines that contain internal semicolons.

### 11.3 Stored functions, triggers, and scheduled database events

No application-defined stored functions, triggers, or database events were found. SQL built-ins such as `NOW()`, `UPPER()`, `TIMESTAMPDIFF()`, and `GROUP_CONCAT()` are used but are not project-created routines. Timestamp defaults, `ON UPDATE` columns, foreign keys, and CHECK constraints are table features rather than triggers. Email scheduling is an external cron responsibility.

### 11.4 Read-only live inventory queries

Use these queries in the selected database when a live schema audit is authorized. They were not executed for this handbook.

```sql
SELECT DATABASE() AS database_name, VERSION() AS server_version;

SELECT TABLE_NAME, TABLE_TYPE, ENGINE, TABLE_COLLATION
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
ORDER BY TABLE_TYPE, TABLE_NAME;

SELECT TABLE_NAME, VIEW_DEFINITION
FROM information_schema.VIEWS
WHERE TABLE_SCHEMA = DATABASE();

SELECT ROUTINE_NAME, ROUTINE_TYPE, DATA_TYPE, ROUTINE_DEFINITION
FROM information_schema.ROUTINES
WHERE ROUTINE_SCHEMA = DATABASE();

SELECT TRIGGER_NAME, EVENT_OBJECT_TABLE, ACTION_TIMING,
       EVENT_MANIPULATION, ACTION_STATEMENT
FROM information_schema.TRIGGERS
WHERE TRIGGER_SCHEMA = DATABASE();

SELECT EVENT_NAME, STATUS, EVENT_DEFINITION
FROM information_schema.EVENTS
WHERE EVENT_SCHEMA = DATABASE();
```

Metadata visibility depends on database privileges. Objects created manually on Hostinger may exist even when no matching migration exists locally. Record those separately rather than silently treating them as versioned objects.

## 12. Database migrations

### 12.1 Required order

| Order | File | Effect |
| --- | --- | --- |
| 1 | `001_create_client_tables.sql` | Six client/location/contact/authentication tables and client role seeds |
| 2 | `002_apply_client_authentication.sql` | Compatibility upgrade for older clients; required unique email/account definition |
| 3 | `employee.sql` | Four employee identity/role/account tables and employee role seeds |
| 4 | `service.sql` | Tickets, attempts, and status history |
| 5 | `003_application_features.sql` | Leads, refresh tokens, messages, rate limits, teams/memberships, permissions/mappings, email outbox |
| 6 | `004_trusted_device_pin_login.sql` | Trusted-device authentication table |
| 7 | `005_uppercase_client_master.sql` | Converts selected existing client/location/contact text fields to uppercase |
| 8 | `006_ticket_description_history.sql` | Description-revision history table |

Use the explicit order in `backend/bin/migrate.php`, not alphabetical filename sorting. Migration 002 uses MariaDB-specific conditional index syntax. Migration 005 changes existing data and is not a structural-only migration.

### 12.2 Preferred runner

From `backend/`, after configuring the correct database:

```powershell
php bin/migrate.php
```

The runner checks `schema_migrations`, skips recorded filenames, executes statements, then records completion. MySQL/MariaDB DDL may commit implicitly; an error can leave partial changes without a recorded completed migration. Do not assume rollback occurred. Back up first, inspect the actual schema after failures, and avoid running two migration processes simultaneously.

### 12.3 Optional Python runner

```powershell
Set-Location C:\Users\sid\Documents\ONESALEZ_SERVICE_CRM\backend
python -m pip install -r database/requirements.txt
# Supply DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD as process environment variables.
python database/apply_migration.py database/migrations/006_ticket_description_history.sql
```

This runner applies only the supplied file, uses autocommit, and does not load `backend/.env` or maintain `schema_migrations`. Its printed table inventory is a hardcoded list that omits some later tables. Do not mix runners without reconciling migration history; successful output is not a full schema comparison.

## 13. Pre-installation, installation, and running the application

### 13.1 Pre-installation guide

Complete this section before installing project dependencies, running migrations, or launching the servers. The commands are instructions for the installer; they were not run to install software during this documentation update.

#### 13.1.1 Choose the installation target

| Target | What must be available | Next sections |
| --- | --- | --- |
| Local Windows development | PHP, Composer, Node.js/npm, project source, development MariaDB access | Follow Sections 13.1–13.5, then 13.7 |
| Local Docker backend | Docker with Compose, project source, Node.js/npm for frontend work | Follow environment preparation below, then 13.6 and 13.7 |
| Hostinger production | PHP runtime, database, domain/HTTPS, upload/SSH access, locally prepared release | Complete hosting preparation in 13.1.5 and deploy using Section 15 |

For an existing installation, preserve `.env`, database contents, and local changes. Install missing dependencies and apply only pending migrations; initial administrator creation is for a fresh installation, not a repeat startup task.

#### 13.1.2 Required software and versions

| Component | Requirement | Preparation |
| --- | --- | --- |
| PHP | 8.3 or newer, within the project's PHP 8.x constraint | Make the intended `php.exe` available on PATH. PHP 8.2 bundled with an older local server package does not meet the declared requirement. |
| PHP extensions | PDO, PDO MySQL, JSON; mbstring recommended | Check the loaded CLI `php.ini` and enable `pdo_mysql` and `mbstring` as needed. Development test dependencies may require additional extensions; use Composer's platform check. |
| Composer | Version 2 | Configure it to use the same PHP installation as the terminal. |
| Node.js/npm | A version supported by the locked frontend dependencies | The current lockfile contains Vite 8.0.16 with Node requirement `^20.19.0 || >=22.12.0`. This is the recorded package requirement, not a claim about currently supported Node release lifetimes. Recheck after dependency upgrades. |
| MariaDB | Compatible with the project's MariaDB 11.8-targeted migrations | Use a separate development database or prepare the intended Hostinger schema. A database server need not be installed locally if an authorized remote development database is available. |
| Browser | Browser capable of running the React app | Use browser developer tools for network/cookie checks. PWA installation availability depends on browser/device. |
| Git | Needed when obtaining/updating source through Git | Use the existing project checkout or the team's actual repository location; no repository URL is assumed here. |
| Python + PyMySQL | Optional | Required only for the alternative manual migration runner. |
| Docker + Compose | Optional | Required only for container-based API/database setup. |

The Composer dependency graph includes an mbstring polyfill, but production and test runtime requirements should still be checked explicitly. Node.js is a development/build tool for this application and does not need to run on Hostinger.

After installing or changing PATH, reopen PowerShell. Locate the selected executables and inspect PHP configuration:

```powershell
Get-Command php, composer, node, npm
php --ini
```

Verify executable versions in the terminal that will launch the application:

```powershell
php -v
php -m
composer --version
node --version
npm --version
```

If multiple PHP installations exist, check the executable path returned by `Get-Command php` before changing `php.ini`. CLI PHP and Hostinger's website PHP can load different configurations. If PowerShell blocks `npm.ps1`, the Windows command shim `npm.cmd` can run the same npm commands without changing execution policy.

#### 13.1.3 Obtain and prepare the project files

1. Place or open the source checkout at `C:\Users\sid\Documents\ONESALEZ_SERVICE_CRM`, or substitute the actual checkout path in all commands.
2. Confirm that `backend/composer.json`, `backend/composer.lock`, `backend/.env.example`, `backend/database/migrations/`, `webapp/package.json`, and `webapp/package-lock.json` are present.
3. Confirm that you can write within the project and that PHP can create/write `backend/logs/`.
4. Keep existing environment files and secrets private. Do not copy a production database password into a frontend environment file.
5. Allow network access for Composer/npm dependency downloads. If dependencies are already installed, use the lockfiles to keep their versions consistent.

#### 13.1.4 Prepare database access and local ports

Before `php bin/migrate.php`, collect the intended `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASSWORD`. Create the database and an application account through the database administrator's tools if they do not exist; the migration runner creates tables inside an existing database, not the database or login itself.

The migration account needs the schema privileges required by the supplied DDL, including table creation and alteration/index changes, as well as the data privileges used by migrations. Application runtime needs read/write privileges for its own schema. Avoid using a database root account for normal application access.

For an existing database, confirm its name and take a recoverable backup before migration. If connecting remotely, confirm that the configured database server permits the development machine's connection. A successful TCP test proves only that the port responds, not that credentials or permissions are valid.

Optional connection test, with the hostname replaced by the intended database host:

```powershell
Test-NetConnection -ComputerName '<database-host>' -Port 3306
```

Check the default local API/frontend ports:

```powershell
Get-NetTCPConnection -State Listen -LocalPort 8000,5173 -ErrorAction SilentlyContinue |
    Select-Object LocalAddress, LocalPort, OwningProcess
```

No returned listener means those ports are not currently listening. If a listener exists, identify it before starting another server; do not stop unrelated processes. If choosing alternate ports, also update the frontend API base URL, backend CORS origin, backend `APP_URL` (the frontend address), and the commands used to launch the servers. Use `127.0.0.1` consistently for local URLs.

#### 13.1.5 Prepare production hosting before upload

Confirm the domain points to the intended website directory, HTTPS is available, and the hosting PHP runtime meets the backend requirement. Confirm database credentials and access from PHP, upload/SSH access, writable private logs, and support for the project's `.htaccess` routing/source-protection rules.

Prepare a private production `.env`, an independently generated signing key for a new environment, a database backup, and a matching previous release when upgrading. Plan a non-overlapping email-worker schedule and verify the host's mail transport. Production frontend configuration must use `/api/v1` for the supplied same-domain layout. See Section 15 for exact paths, file mapping, and deployment sequence.

#### 13.1.6 Ready-to-install checklist

- [ ] Installation target selected: development, Docker, or production.
- [ ] Correct PHP, Composer, and frontend build-tool versions available.
- [ ] Required PHP extensions and the active `php.ini` identified.
- [ ] Project source and dependency lockfiles present.
- [ ] Intended database exists; credentials and privileges are available privately.
- [ ] Existing environment configuration preserved and existing database backed up before migration.
- [ ] Local ports/URLs agreed, or production domain/HTTPS/routing prepared.
- [ ] Log-directory write access and dependency-download access available.

Proceed with backend installation below, then frontend installation and the installation verification steps. Docker users can follow Section 13.6 for the API/database instead of installing a native local backend.

### 13.2 Backend setup

```powershell
Set-Location C:\Users\sid\Documents\ONESALEZ_SERVICE_CRM\backend
composer install
composer check-platform-reqs
if (!(Test-Path .env)) { Copy-Item .env.example .env }
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Edit `backend/.env` with the generated `APP_KEY` and the intended development database credentials. Keep existing secrets if an environment is already configured. Suggested local HTTP settings are:

```dotenv
APP_ENV=development
APP_DEBUG=true
APP_URL=http://127.0.0.1:5173
JWT_ISSUER=onesalez-service-crm-local
REFRESH_COOKIE_SECURE=false
REFRESH_COOKIE_SAMESITE=Strict
CORS_ALLOWED_ORIGINS=http://127.0.0.1:5173
```

`APP_URL` must point to the frontend because password-reset links open a browser page. Set all `DB_*` values separately. Prefer a development database over using production data during development. Apply migrations only after confirming the database target:

```powershell
php bin/migrate.php
```

### 13.3 First administrator and account recovery

For a fresh database, set `ADMIN_CODE`, `ADMIN_NAME`, `ADMIN_EMAIL`, and `ADMIN_PASSWORD`, then run:

```powershell
php bin/create-admin.php
```

For existing administrator recovery, use the dedicated script after setting its `ADMIN_*` environment variables:

```powershell
php bin/reset-admin-password.php
Remove-Item Env:ADMIN_PASSWORD -ErrorAction SilentlyContinue
```

The recovery script activates/upserts the administrator identity/account, ensures the system-admin role, and revokes refresh/trusted-device credentials. It is an intentional account change, not a routine startup step. Use a new strong password supplied privately; no default administrator password is part of installation.

### 13.4 Frontend setup

```powershell
Set-Location C:\Users\sid\Documents\ONESALEZ_SERVICE_CRM\webapp
npm ci
```

Create or edit `webapp/.env.local` with this public development configuration:

```dotenv
VITE_API_BASE_URL=http://127.0.0.1:8000/api/v1
```

Only public configuration belongs in `VITE_*` variables; these values become browser code. Restart Vite after changing environment files.

### 13.5 Start the two servers

Terminal 1:

```powershell
Set-Location C:\Users\sid\Documents\ONESALEZ_SERVICE_CRM\backend
php -S 127.0.0.1:8000 -t public
```

Terminal 2:

```powershell
Set-Location C:\Users\sid\Documents\ONESALEZ_SERVICE_CRM\webapp
npm run dev -- --host 127.0.0.1
```

Open `http://127.0.0.1:5173/login`. Test API liveness at `http://127.0.0.1:8000/api/v1/health`. Keep both terminals running. The `-t public` argument selects the PHP entry-point directory. Use `127.0.0.1` consistently instead of mixing it with `localhost`, especially for cookies and CORS.

After installing dependencies/configuration, the root `start-dev.bat` offers a convenience launcher. Its port checks detect a listener, not whether that listener is the correct CRM process. Stop local foreground servers with Ctrl+C in their terminals.

### 13.6 Optional Docker setup

From `backend/`, prepare `.env` with `DB_HOST=database`, strong database/user/root passwords, and the relevant local application settings. Set `DB_ROOT_PASSWORD` explicitly rather than accepting the Compose fallback.

```powershell
docker compose up -d --build
docker compose exec api php bin/migrate.php
```

The container API uses `http://127.0.0.1:8080`; point the local frontend API URL there when using Docker. The Compose file starts the API and database, not Vite. Migrations are a separate step. This handbook does not claim that the container build has been verified in the current review.

### 13.7 Post-installation verification

After completing the selected installation path:

1. Confirm dependency installation and PHP platform checks succeed. Resolve missing-extension/version errors before continuing.
2. Confirm migration completion in the intended database. Read `schema_migrations` and compare actual objects with Sections 9–12; a failed migration may have partly executed.
3. Start the API/frontend and request API liveness locally:

   ```powershell
   Invoke-RestMethod http://127.0.0.1:8000/api/v1/health
   ```

4. Open `http://127.0.0.1:5173/login` and sign in using a provisioned account. Check that browser API requests use the intended API host and return JSON.
5. Reload the browser to check session restoration, then log out and confirm a protected page requires authentication. A successful health response alone does not test database access or authentication.
6. In a development/test database, verify client onboarding and one complete ticket cycle: create, accept, release, accept again, and complete. Check that both attempts and notes remain visible. Use controlled test accounts/data.
7. Verify password-recovery email separately after configuring the worker; database queue insertion alone does not prove delivery.
8. Run the relevant quality commands from Section 17 and record which checks passed. For production, use the deployment smoke checks in Section 15 with the real HTTPS domain.

Installation is ready for normal use when the selected environment can serve the frontend, authenticate against its intended database, restore/end a session, and complete the essential workflow checks. Section 18 still records product gaps that installation itself does not resolve.

## 14. Configuration reference

| Variables | Purpose/default behavior |
| --- | --- |
| `APP_ENV`, `APP_DEBUG` | Environment name and error display; production debug must be false |
| `APP_URL`, `APP_TIMEZONE` | Frontend URL for recovery links; application timezone |
| `APP_KEY` | Secret signing key; generate securely and keep outside browser/deployment docs |
| `JWT_ISSUER`, `JWT_ACCESS_TTL`, `JWT_REFRESH_TTL` | Token issuer, access lifetime (900 seconds default), refresh lifetime (2592000 seconds default) |
| `TRUSTED_DEVICE_TTL` | Quick-login enrollment lifetime; default 15552000 seconds |
| `REFRESH_COOKIE_NAME`, `REFRESH_COOKIE_SECURE`, `REFRESH_COOKIE_SAMESITE` | Refresh cookie identity and transport/cross-site policy |
| `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` | Database connection |
| `DB_CONNECT_RETRIES`, `DB_PERSISTENT` | Connection retry count and persistent PDO option; inspect actual config when changing |
| `LOG_LEVEL`, `LOG_MAX_FILES` | Monolog threshold and retained rotating log files; defaults info/14 |
| `RATE_LIMIT_REQUESTS`, `RATE_LIMIT_WINDOW` | Request-window rate limit; defaults 120 requests/60 seconds |
| `CORS_ALLOWED_ORIGINS` | Comma-separated permitted browser origins |
| `MAIL_FROM` | Sender used by the email worker |
| `DB_ROOT_PASSWORD` | Docker MariaDB root initialization only |
| `ADMIN_CODE`, `ADMIN_NAME`, `ADMIN_EMAIL`, `ADMIN_PASSWORD` | CLI administrator provisioning/recovery inputs |
| `VITE_API_BASE_URL` | Public browser API base URL; separate local and production values |

The email worker uses authenticated SMTP through PHPMailer. Configure SMTP_HOST, SMTP_PORT, SMTP_ENCRYPTION (ssl or tls), SMTP_USERNAME, SMTP_PASSWORD, MAIL_FROM, and MAIL_FROM_NAME in the private server environment. Separate staging settings must be configured as deployment environments rather than assuming a dedicated staging-config file exists.

## 15. Hostinger deployment

### 15.1 Target and file mapping

The project owner's confirmed deployment directory is:

```text
/home/u606070148/domains/onesalez.com/public_html/service
```

The supplied SSH endpoint is user `u606070148`, host `194.5.156.162`, port `65002`. Credentials/keys remain private. A deployment connects with `ssh -p 65002 u606070148@194.5.156.162` when access is configured.

The repository's shared-hosting templates expect this mapping:

| Server path relative to `service/` | Source/content |
| --- | --- |
| `index.html`, `assets/`, icons, manifest, service-worker files | Contents of `webapp/dist/` |
| `.htaccess` | `.deployment/root.htaccess` |
| `api/index.php` | `.deployment/api-index.php` |
| `app/` | Backend runtime including bootstrap, src, config, vendor, bin, migrations, private `.env`, logs |
| `app/.htaccess` | `.deployment/app.htaccess`, denies browser access |

The root rewrite sends `/api/v1/...` to PHP, serves real static assets, and returns `index.html` for browser routes such as `/login`. The template blocks requests to `app/` and dotfiles. When hosting permits, putting PHP source outside the document root is stronger; changing to that layout also requires updating the front-controller include path.

### 15.2 Build and release process

1. Confirm PHP 8.3+ for the site's web runtime and CLI; verify database configuration and make a backup.
2. Run the quality checks in Section 17 against the intended release source.
3. Use `webapp/.env.production` with `VITE_API_BASE_URL=/api/v1` for same-domain hosting.
4. From `webapp/`, run `npm ci` and `npm run build`.
5. Inspect generated assets for unintended development API URLs. Environment variables inherited by the build process and `.env.production.local` can override normal settings.
6. Prepare PHP dependencies in a release directory with `composer install --no-dev --prefer-dist --optimize-autoloader` and verify runtime requirements. Keep the normal development checkout's test dependencies available.
7. Upload the mapped frontend/backend runtime, preserving the server's private environment configuration. Do not upload the entire workspace or private deployment folder.
8. Configure production `APP_URL`/`JWT_ISSUER`, signing key, database connection, exact production CORS origin, `APP_DEBUG=false`, and `REFRESH_COOKIE_SECURE=true`.
9. Apply pending migrations through the deployed `app/bin/migrate.php`, then verify expected schema objects.
10. Ensure PHP can write the private log directory. Configure email processing and backup scheduling.
11. Verify HTTPS browser login, refresh, logout, protected routes, password recovery, client/site access, and the ticket lifecycle. Test a direct navigation to `/admin/clients` to confirm SPA routing.
12. Retain the previous release for rollback. Code rollback does not automatically reverse database migrations or data changes.

Node.js does not run on Hostinger for this architecture: upload compiled static assets and run the PHP API. The current local production env file selects `/api/v1`; this source review does not verify whether that corrected build is deployed live.

### 15.3 Automatic GitHub deployment (12 September 2026)

The login page includes **Reset app** for stale browser data. It unregisters this application's service worker, deletes its ONESALEZ/Workbox caches, and reloads the login page with a fresh URL. Cookies, saved PIN registrations, and local account preferences are preserved. Failed cache clearing displays an error rather than silently claiming success.

Production frontend builds always use the same-origin `/api/v1`, even if a local development environment URL is present. HTML and service-worker files carry no-cache headers. Login/reset/API routes bypass service-worker caches; other navigation uses a network-first cache. After migrating from an older cached PWA, clear only that site's service-worker/cache storage and reload if it still calls localhost. This does not require clearing cookies or saved account preferences.

The repository now includes `.github/workflows/deploy-hostinger.yml` and public deployment templates under `deploy/`. Pushes to `main` build/test the frontend, run PHP tests, install production dependencies, and deploy the resulting bundle to the existing `service` directory over SSH. The workflow also supports manual dispatch on `main`.

The private server receiver lives outside the document root at `/home/u606070148/.onesalez-service-deploy/receive-release.sh`. A dedicated forced-command key is stored in GitHub Actions secrets with the pinned SSH host identity. The receiver preserves the server environment, logs, and uploads, backs up existing code/configuration, and restores previous files if deployment or live smoke checks fail. Database migrations remain manual. See `deploy/README.md` for configuration, limitations, backups, and rollback instructions. Workflow presence alone does not establish that a particular run succeeded; consult GitHub Actions and the live `deploy-version.txt`.

## 16. Security, logging, and operations

### 16.1 Implemented foundations

The code provides native PDO prepared statements, allowlisted dynamic fields, transaction helpers, bcrypt hashes, token hashing, JWT validation, tenant checks, permission middleware, input validation, login lockouts, rate limits, CORS, security headers, and exception handling. These are foundations whose edge cases still require the work/tests in Section 18.

`CsrfMiddleware` exists but is not attached to current routes in bootstrap. Bearer-token endpoints and cookie-backed refresh/logout have different exposure; review cookie/origin behavior for the actual deployment instead of assuming that an unused middleware protects every request.

JWT authentication currently decodes token claims without checking current account/role state on every request. Suspension or password reset may revoke renewal credentials while an already issued access token remains valid until expiry. Immediate access revocation needs an additional mechanism if required.

### 16.2 Logs and email processing

Monolog writes JSON application logs with date-based rotation and configurable retained-file count. The exception handler records request context and exceptions. Rotation is not a maximum-byte file cap, and a comprehensive request-access/audit log should not be assumed from the error logger alone. Keep logs private and avoid recording passwords/tokens.

`php bin/email-worker.php` processes up to 20 eligible jobs within a 45-second loop budget. Hostinger runs it every minute. A connection-scoped database advisory lock prevents overlapping workers; SMTP failures retry after two minutes, up to three attempts. Expired or superseded reset links are skipped. SENT means the SMTP server accepted the message, not proof of inbox delivery. A process crash after SMTP acceptance but before the status update can still cause a retry. Credentials and reset links are excluded from worker logs.

### 16.3 Backups and maintenance

The existing backend README proposes encrypted daily database backups, 14 daily copies, 12 monthly copies, off-account storage, and quarterly restore tests. Treat this as the intended operational policy; no evidence in this review proves those jobs are configured.

Back up before migrations and releases. Use a consistent transactional MariaDB backup and include schema/routine/event definitions if live objects have been added. Store credentials outside command history where practical. Retain a matching code release, and test a restore into an isolated database.

Expiry cleanup for refresh tokens, trusted devices, rate-limit counters, and processed email jobs needs scheduled retention policies. Do not delete business history as a generic maintenance shortcut.

## 17. Testing and quality checks

### 17.1 Available commands

Backend, from `backend/`:

```powershell
composer cs
composer analyse
composer test
composer check
```

Frontend, from `webapp/`:

```powershell
npm run typecheck
npm run test
npm run build
npm run check
npx playwright install chromium
npm run test:e2e
```

Inspect Playwright configuration/test setup before running browser tests; tests must use appropriate local/test services and should not mutate production client data.

### 17.2 Coverage and current test scope

Verification was expanded on 12 September 2026. See [AUDIT_COMPLETION.md](AUDIT_COMPLETION.md) for measured results and evidence limits. The reusable quality workflow runs migrations twice against MariaDB, service/repository integration tests with concurrency, PHPStan level 8, PSR-12, dependency audits, TypeScript/build/unit/browser checks and a Docker build. Coverage is measured in CI; no unmeasured percentage is claimed.

## 18. Remaining work and known limitations

The audit completed the previous P1 source findings and missing web workflows: employee/client authority, location and Declined scope, native-PDO search, atomic ticket history, refresh/reset/session races, real dashboard, paginated ticket/directory screens, complete CSV export, Leads conversion, Teams CRUD, Settings, soft removal and customer conversation. Password recovery email delivery and user reset success were verified in the preceding recovery work. The audit does not resend customer recovery emails.

See [API_CHANGES.md](API_CHANGES.md) for current contracts and recovery procedures. The OpenAPI route inventory covers all 58 registered method/path pairs. Earlier descriptions or examples must be interpreted using these current contracts.

Remaining limits and product decisions:

- Coverage and browser scenarios do not cover every branch, browser engine or assistive technology. The measured figure belongs in the audit evidence; an 80% target is not established simply by configuring coverage collection.
- Performance measurements use synthetic data in a local MariaDB instance, not a production multi-user capacity test. Wildcard search and exact report counts still require scanning matching rows; very large installations will need workload-specific profiling.
- Production health is liveness, not database readiness. The deployment audit separately inventories the schema and verifies database reachability. Continuous external uptime alerting and off-host backup retention require a chosen provider/destination and retention policy.
- Private code/database backups are created before deployment. Local restore verification does not establish a production disaster-recovery time. Logs rotate locally; long-term audit/session/email technical-table retention must follow the owner's policy.
- The PWA caches application assets; mutations require a working API. Browser checks use Chromium desktop/mobile emulation, with mocked API tests isolated from service workers. Real cache/update checks are recorded separately.
- English remains the initial supported language. Additional language catalogs require language selection and translation review. Onboarding uses employee-assisted active accounts with an initial password; invitation acceptance is not an enabled workflow.
- Attachments, SLA rules, native MAUI clients, push notifications and offline mutation queues are separate product initiatives. No guessed business rules have been introduced. Employee performance is available; team-level aggregation is a possible reporting extension.
- The deployment uses staged file copies and an atomic frontend entry-point switch, not fully atomic backend release directories. Keep migrations backward compatible. CSV batches are not a database snapshot during concurrent changes.

## 19. Troubleshooting

| Symptom | Checks and likely next action |
| --- | --- |
| `ERR_CONNECTION_REFUSED` locally | Verify the expected process is listening on 8000/5173, keep terminals open, and use the same `127.0.0.1` host in launch commands and browser/API configuration. A startup message from an exited process is not enough. |
| API returns 404 or an HTML page | Run PHP with `-t public`; inspect production API rewrite and React fallback mapping. |
| Live login tries `127.0.0.1` | Inspect browser Network requests and compiled build configuration; rebuild with production `/api/v1`, upload matching assets, and refresh stale PWA caches. |
| Login succeeds but reload loses session | Check refresh cookie Secure/SameSite settings, same host use, browser credentials inclusion, CORS allowlist, expiry, and the refresh response. |
| Password appears correct but login fails | Confirm realm/email, active master/account state, deletion flags, lockout time, and that the API points to the intended database. Use recovery only after identifying the target account/environment. |
| Health is OK but business pages fail | `/health` is liveness only. Inspect private PHP logs, database connectivity, missing migrations, and authorization; it does not test a DB connection. |
| Search fails with parameter errors | Inspect reused named placeholders under native PDO prepares; bind each occurrence separately. |
| Older tickets or CSV rows missing | Check server pagination, active filters and location permissions; CSV now retrieves all matching batches. |
| Reset email does not arrive | Verify a pending `email_jobs` row, worker execution, sender configuration, transport result, retries, and spam/delivery behavior. |
| New PHP syntax/dependency errors | Compare actual PHP runtime to the declared 8.3+ requirement; Composer's emulated platform does not upgrade the installed interpreter. |

## 20. Maintenance rules and document references

### 20.1 Rules for future contributors and agents

- Read this handbook and the relevant source before modifying a business workflow. Prefer the available codebase-memory graph tools for code discovery; use file search when graph tools are unavailable or for configuration/text lookup.
- Keep ONESALEZ employees and client contacts separate, and preserve tenant/location identifiers throughout requests and persistence.
- Keep PHP code in `backend/`, frontend code in `webapp/`, and shared owner-facing documentation in the base folder.
- Preserve existing user changes. Implement new schema changes as explicit migrations; do not silently rewrite an already applied schema contract.
- Maintain soft-delete/audit behavior, transaction boundaries, and historical records. Never convert a release into deletion of its attempt.
- Treat source-defined functionality, deployed functionality, and tested functionality as different evidence levels. After database work, report reachability, whether statements executed or may have partly executed, and read-back results.
- Keep credentials in private environment/secret storage. Do not copy passwords, tokens, private keys, or private deployment records into public documentation, frontend code, commits, or logs.
- Update this handbook when workflows, routes, table objects, installation, or remaining-work status change. Record a review date and remove resolved limitations only with supporting verification.

### 20.2 Source index

| Document/source | Purpose |
| --- | --- |
| [BASE_UNDERSTANDING_V1.md](BASE_UNDERSTANDING_V1.md) | Original business scope and decisions |
| [WEB_APP_HANDBOOK.md](WEB_APP_HANDBOOK.md) | Approved technology/UX/design expectations; some requirements remain aspirational |
| [API_USAGE_GUIDE.md](API_USAGE_GUIDE.md) | Endpoint syntax and examples |
| [backend/README.md](backend/README.md) | Backend setup, security and operations overview |
| [webapp/README.md](webapp/README.md) | Frontend commands and foundation overview |
| [Database README](backend/database/README.md) | Earlier migration notes |
| [Database summary](backend/docs/database-schema.md) | Short relationship overview; does not cover every later feature |
| [OpenAPI](backend/docs/openapi.yaml) | Machine-readable API documentation |
| [Architecture decision](backend/docs/adr/0001-framework-free-clean-architecture.md) | Framework-free architecture rationale |
| [Backend route registry](backend/bootstrap.php) | Actual registered API endpoints and middleware |
| [Frontend route registry](webapp/src/app/App.tsx) | Actual enabled browser routes |
| [PHP migration runner](backend/bin/migrate.php) | Canonical migration ordering and tracking |

This file uses the requested filename `agent.md`. Automatic repository-instruction discovery commonly uses the separate name `AGENTS.md`; this handbook can be referenced by such an instruction file without duplicating its contents.
