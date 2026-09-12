# Production deployment

Pushes to `main` trigger `.github/workflows/deploy-hostinger.yml`. The workflow builds and tests React, runs PHP tests, installs production PHP dependencies, and deploys to **https://service.onesalez.com**. It can also be run manually from GitHub Actions. Failed builds never reach the server.

Target: `/home/u606070148/domains/onesalez.com/public_html/service`. Hostinger lists this subdomain under the `onesalez.com` hosting account. Other sites are not deployment targets.

Repository Actions secrets:

- `HOSTINGER_DEPLOY_KEY`: dedicated SSH private key. The matching server key is restricted to the installed deployment receiver, with forwarding and interactive access disabled.
- `HOSTINGER_KNOWN_HOSTS`: pinned SSH host identity for port 65002.

The server receiver is installed at `/home/u606070148/.onesalez-service-deploy/receive-release.sh`, outside the public web directory. Changes to the receiver in this repository must be reviewed and installed through an administrator SSH session; ordinary deployments cannot replace it.

The receiver serializes deployments, backs up existing files, preserves `app/.env`, `app/logs/`, and `app/uploads/`, deploys the built bundle, and checks the live API and login HTML. A failed deployment restores the prior files. Old static assets remain available to already-open clients. `deploy-version.txt` records the deployed commit. File copies are not a fully atomic release switch; a short period of mixed files is possible during deployment.

Backups are private under `/home/u606070148/.onesalez-service-deploy/backups/`. They include the server environment file, but exclude logs/uploads. The receiver also creates a compressed database dump before migrations. Review disk usage and remove obsolete backups deliberately; no automatic retention deletion is configured.

Database migrations run automatically after a verified private database backup and before the frontend switch. The migration runner locks, checksums and journals each migration. Code rollback does not reverse database changes. Inspect partial DDL failures before using --retry-reviewed. See API_CHANGES.md for recovery details.

For manual rollback, select a known-good private backup, extract it into a private temporary directory, and use administrator SSH to restore it to the exact service directory while preserving the current `.env`, logs, and uploads. Verify `/api/v1/health` and `/login` afterward. Do not deploy the repository root directly into `public_html`.
