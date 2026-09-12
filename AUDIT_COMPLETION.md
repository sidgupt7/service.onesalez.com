# Application audit — 12 September 2026

Scope: the deployed PHP/MariaDB and React service CRM, measured against
`BASE_UNDERSTANDING_V1.md`, `WEB_APP_HANDBOOK.md`, and the remaining-work inventory
in `agent.md`. Work is isolated on `audit-completion` until validated.

## Baseline

- Backend: 14 tests / 31 assertions pass; one runtime deprecation.
- PHPStan level 8: two errors (SMTP time limit assigned to wrong object; unchecked PDO query result).
- PSR-12: seven errors and one warning.
- Frontend: 10 unit tests pass; production build succeeds.
- Initial JavaScript: 570.04 kB / 164.66 kB gzip, above the build warning threshold.
- Browser tests initially blocked by missing Playwright Chromium, not application assertions.

## Completion checklist

- [x] Enforce ticket location and declined-ticket visibility on the server.
- [x] Correct native-PDO search parameters and transactional ticket history/state changes.
- [x] Close refresh/reset races, revoke old sessions, recheck account and permission state.
- [x] Preserve passwords exactly and validate dates and other input consistently.
- [x] Replace placeholder dashboard with live operational metrics.
- [x] Add server pagination and accurate counts to ticket screens; full, safe report export.
- [x] Complete leads, team management, and account settings navigation.
- [x] Review client maintenance, contact/location removal, and assisted portal access.
- [x] Reduce initial bundle and persist theme; review loading/error/accessibility states.
- [x] Add isolated MariaDB integration checks and meaningful browser workflow coverage.
- [x] Run static analysis, style, dependency audit, build, unit and browser checks.
- [x] Review migration/deployment recovery and reconcile API and operational documentation.
- [ ] Deploy verified changes and verify production without mutating customer records.

Attachments, SLA policy, native MAUI apps, and push notifications remain separate
product initiatives in the source requirements, not assumed business rules.
This file records evidence and unresolved limits; passing mocks alone does not
establish production completeness.

## Local verification results

- PHP 8.5.8: **35 tests, 207 assertions** including 18 real MariaDB integration tests. Concurrent acceptance/refresh/last-admin changes, location/role isolation, reset expiry/replay/revocation, lifecycle rollback, real client conversion and duplicate onboarding are covered.
- PHPStan level 8 and PSR-12 pass. Composer and npm audits report zero known advisories for the installed locked dependencies.
- TypeScript, production build and **18 frontend unit tests** pass. CSV tests export 1,001 rows over three batches and reject spreadsheet formulas.
- **20 browser scenarios** pass across desktop Chromium and Pixel 7 Chromium emulation. These use mocked APIs with service workers blocked. Desktop/mobile dashboard screenshots were reviewed; table overflow is confined to its scrollable region.
- Fresh disposable schema applies all nine migrations; second execution is a no-op. A compressed backup restored into a second disposable schema with matching 20-ticket/35-client counts at the time of that exercise. Production records were not used as test fixtures.
- Production read-only inventory before release: PHP 8.3.33, MariaDB 11.8.9, OPcache, all eight pre-audit migrations and database connectivity verified. Customer business records were not changed.
- OpenAPI parses and matches all **58** route method/path pairs. CI will additionally verify PHP 8.3, MariaDB 11.8, coverage and the container image before release.

## Performance measurements

The original single entry JavaScript bundle was 570.04 kB (164.66 kB gzip).
The new entry plus eagerly shared auth module totals **471.20 kB** (147.59 kB
gzip), about **17% less uncompressed / 10% less gzip**. Other screens load as
route chunks. PWA background precaching includes those screens, so total cached
assets are not reduced by the same percentage. Current precache: about 627 KiB.

Local warm-query benchmark: PHP 8.5 / MariaDB 11.4, **10,026 synthetic tickets**,
30 samples per operation. The host also ran browser/build checks; these are
application query timings, not network latency or a production capacity promise.

| Operation | p50 | p95 | Returned rows |
| --- | ---: | ---: | ---: |
| Ticket first page | 135 ms | 424 ms | 25 |
| Ticket page 200 | 105 ms | 112 ms | 25 |
| Ticket wildcard search | 161 ms | 180 ms | 25 |
| Ledger first page | 213 ms | 259 ms | 50 |
| Dashboard | 51 ms | 55 ms | aggregates |

Ticket payload was about 17 kB and ledger payload about 54 kB, independent of
fetching every matching row into the browser. Full CSV export intentionally
walks all matching batches. Exact counts and wildcard searches remain workload
sensitive; no unsupported high-volume throughput claim is made.

## Evidence still pending

Branch CI, container build and PHP 8.3/MariaDB 11.8 checks passed. Measured backend line coverage is **88.42% (2,108/2,384 lines)** and method coverage is **73.77%**. An 80% line-coverage gate now protects deployment. Deployment and live cache verification remain pending. See agent.md section 18 for remaining operational
policy and separate product initiatives. This audit is not a claim that every
possible bug, browser or disaster scenario has been tested.

Additional validation: an unmocked local browser workflow passed against PHP/MariaDB (onboarding, client login, reload/refresh, ticket creation, public reply, employee acceptance/completion and client resolution read), with no browser exceptions. A private database backup was also created successfully on Hostinger before deployment. Initial CI measured 52.63% backend line coverage; expanded HTTP workflows raised this to 88.42%. CI caught a missing unzip utility in the Docker image; it is now included.
