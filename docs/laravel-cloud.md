# Laravel Cloud Deployment Runbook

Provider-neutral preparation completed; this file documents the exact steps an
operator performs when connecting the repository to Laravel Cloud.
**No deployment has been executed from this repository.**

## Compatibility summary

| Requirement | Application | Laravel Cloud |
|---|---|---|
| PHP | ^8.3 (developed on 8.5.9) | 8.2 / 8.3 / 8.4 / **8.5 default** |
| Extensions | bcmath, pdo_pgsql, mbstring, openssl, dom, fileinfo, ctype, tokenizer | all available |
| Database | PostgreSQL via standard env vars | Managed PostgreSQL resource (auto-injected `DB_*`) |
| Cache | env-driven `CACHE_STORE` (+ optional `REDIS_*`) | Laravel Valkey (auto-injects `CACHE_STORE`, `REDIS_HOST`, `REDIS_PASSWORD`) |
| Queue | `QUEUE_CONNECTION=database` locally; sync notifications by design | Managed queues (optional) |
| Scheduler | No scheduled tasks today | Platform scheduler available when tasks are added |
| Assets | Vite build (`public/build`) | Built during the build phase |

Select **PHP 8.5** in the environment's Runtime settings.

## Environment variables (set in the Cloud dashboard — never committed)

Critical:

```
APP_ENV=production            # staging: staging
APP_DEBUG=false               # mandatory in production
APP_KEY=<generate: php artisan key:generate --show>
APP_URL=https://<real-domain> # staging: non-production domain
```

Database (auto-injected when attaching the Postgres resource; override only if needed):

```
DB_CONNECTION=pgsql
DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD
DB_SSLMODE=require            # verify-full if the provider supports it
```

Session / cache / queue:

```
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
CACHE_STORE=database          # becomes valkey automatically when Valkey is attached
QUEUE_CONNECTION=database     # sync notifications stay synchronous regardless
```

Mail, backups, proxies:

```
MAIL_MAILER MAIL_HOST MAIL_PORT MAIL_USERNAME MAIL_PASSWORD MAIL_FROM_ADDRESS MAIL_FROM_NAME
BACKUP_PG_DUMP_BINARY=pg_dump
BACKUP_PG_RESTORE_BINARY=pg_restore
TRUSTED_PROXIES=*             # Cloud terminates HTTPS at the edge; "*" is expected here
```

Never paste real values into this repository.

## Build & deploy commands (environment settings)

Build command:

```sh
composer install --no-dev && npm ci && npm run build && php artisan optimize
```

Deploy command:

```sh
php artisan migrate --force
```

Notes:
- `php artisan optimize` belongs in the **build** phase per Cloud guidance.
- Do **not** add `queue:restart`, `optimize:clear` or `storage:link` to deploy
  commands — Cloud manages workers/restarts itself and filesystem changes from
  deploy commands do not persist.
- Migrations must only run after a verified external backup exists (see below).

## What Cloud does automatically

- Builds a Docker image for the selected PHP version; zero-downtime swap on release.
- Restarts queue workers after each deployment.
- Runs scheduled tasks through the platform scheduler once tasks exist.
- Injects database/cache connection variables for attached resources.

## Filesystem & backups — IMPORTANT

The application writes backups to the **private local disk**
(`storage/app/private/backups`). On Laravel Cloud the application filesystem is
**ephemeral**: local files do not survive a new deployment instance.

Therefore, until backups are moved to Laravel Cloud Object Storage (future
phase), operators MUST export backup archives off the platform immediately
after creation and treat local copies as transient. Cloud-managed database
backups (if enabled on the resource) are a separate layer and do not replace
the application-level verified backup strategy.

Classification:
- Public web assets: built `public/build` only (no storage symlink used).
- Private application files: logs, sessions, framework cache (ephemeral).
- Backup archives: private disk (ephemeral) → must be exported externally.

## Health checks

- `GET /health` — liveness (minimal JSON, preferred endpoint).
- `GET /health/ready` — readiness (database + storage).
- `/monitoring` — internal admin dashboard (platform metrics/logs remain separate).

## Staging vs production

Create two environments from the same repository:

| | Staging | Production |
|---|---|---|
| Branch | `main` (or future `develop`) | `main` |
| APP_ENV | staging | production |
| APP_KEY | dedicated key — never reuse the production key | dedicated key |
| Database | separate resource | separate resource |
| Domain | cloud subdomain | custom domain (operator-provided) |

## Rollback

- Application: redeploy the previous known-good commit (push-to-deploy of that
  commit, or Deploy Hook with `commit_hash`).
- Database: restore from the verified external pg_dump archive. Never assume
  migrations are reversible; no automatic rollback is implemented.

## Operator checklist

1. Connect the GitHub repository to Laravel Cloud.
2. Create the staging environment (PHP 8.5 runtime).
3. Configure staging environment variables (dedicated APP_KEY/APP_URL).
4. Attach the staging PostgreSQL resource.
5. Optionally attach Laravel Valkey for cache/sessions/queues.
6. Deploy staging; migrations gated behind a verified backup.
7. Verify `/health`, `/health/ready`.
8. Run `php artisan app:production-check` from the environment Commands tab.
9. Smoke-test: login, 2FA, company switcher, reports, PDF, audit log,
   notifications, backups.
10. Repeat 2–9 for production (own APP_KEY/database/domain).
11. Take and verify an external backup before the first production migration.
12. Configure the custom domain; confirm HTTPS; keep `APP_URL` in sync.
13. Keep this rollback strategy documented alongside the release notes.
