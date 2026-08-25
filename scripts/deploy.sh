#!/usr/bin/env bash
#
# Provider-neutral production deployment for compta-tunisie.
#
# TARGET HOSTS: manual/VPS-style hosts where the operator controls the
# machine. On Laravel Cloud this script is NOT used — Cloud runs its own
# build/deploy commands (see docs/laravel-cloud.md); maintenance mode,
# worker restarts and zero-downtime swaps are handled by the platform.
#
# Run ON THE TARGET HOST, inside the release checkout, with the production
# environment already configured (.env or platform environment variables).
#
# Required environment:
#   APP_URL          HTTPS URL of the production application
# Optional environment:
#   PHP_BIN          php binary (default: php)
#   COMPOSER_BIN     composer binary (default: composer)
#   BACKUP_VERIFIED  set to "true" to allow running pending migrations
#                    (only after an EXTERNAL verified pg_dump backup exists)
#   HEALTH_TIMEOUT   seconds to wait for /health (default: 30)
#
# Guarantees:
#   - never uses destructive database shortcuts or upgrades dependencies
#   - migrations only run with an operator-verified external backup
#   - maintenance mode is always lifted, even on failure (trap)
#   - deployment is failed loudly unless /health reports status ok
set -euo pipefail

: "${APP_URL:?APP_URL must point to the HTTPS production domain}"

PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
BACKUP_VERIFIED="${BACKUP_VERIFIED:-false}"
HEALTH_TIMEOUT="${HEALTH_TIMEOUT:-30}"

fail() {
    echo "DEPLOY FAILED: $*" >&2
    echo "Rollback: redeploy the previous Git release; restore database from the last verified backup only if migrations were applied." >&2
    exit 1
}

echo "==> Deploying commit $(git rev-parse --short HEAD) on branch $(git rev-parse --abbrev-ref HEAD)"

# ---------------------------------------------------------------------------
# 1. Dependencies (production: no dev packages, dependency versions frozen)
# ---------------------------------------------------------------------------
"$COMPOSER_BIN" install --no-dev --optimize-autoloader --no-interaction

# ---------------------------------------------------------------------------
# 2. Frontend assets
# ---------------------------------------------------------------------------
npm ci
npm run build

if [ ! -f public/build/manifest.json ]; then
    fail "public/build/manifest.json missing after npm run build"
fi

# ---------------------------------------------------------------------------
# 3. Maintenance window (always lifted through the EXIT trap)
# ---------------------------------------------------------------------------
"$PHP_BIN" artisan down
restore() { "$PHP_BIN" artisan up || true; }
trap restore EXIT

# ---------------------------------------------------------------------------
# 4. Database migrations — gated behind a verified external backup
# ---------------------------------------------------------------------------
PENDING=$("$PHP_BIN" artisan migrate --status 2>/dev/null | grep -c "Pending" || true)

if [ "${PENDING:-0}" -gt 0 ]; then
    if [ "$BACKUP_VERIFIED" != "true" ]; then
        fail "$PENDING pending migration(s), but BACKUP_VERIFIED != true. Take an external pg_dump custom-archive backup, verify it (size > 0, checksum, pg_restore --list), then re-run with BACKUP_VERIFIED=true."
    fi
    echo "==> Running $PENDING pending migration(s)"
    "$PHP_BIN" artisan migrate --force
else
    echo "No pending migrations."
fi

# ---------------------------------------------------------------------------
# 5. Framework optimizations (Laravel 13: config+route+view+event caches)
# ---------------------------------------------------------------------------
"$PHP_BIN" artisan optimize

# ---------------------------------------------------------------------------
# 6. Close the maintenance window before verification
# ---------------------------------------------------------------------------
"$PHP_BIN" artisan up
trap - EXIT

# ---------------------------------------------------------------------------
# 7. Readiness gate
# ---------------------------------------------------------------------------
"$PHP_BIN" artisan app:production-check || fail "app:production-check reported FAILures."

# ---------------------------------------------------------------------------
# 8. Health gate
# ---------------------------------------------------------------------------
HEALTH_BODY=""
for _ in $(seq 1 "$HEALTH_TIMEOUT"); do
    if HEALTH_BODY=$(curl -fsS --max-time 5 "${APP_URL%/}/health" 2>/dev/null); then
        break
    fi
    sleep 1
done

echo "$HEALTH_BODY" | grep -q '"status":"ok"' || fail "/health did not report ok within ${HEALTH_TIMEOUT}s"

echo "DEPLOY SUCCESSFUL: $(git rev-parse --short HEAD) is live and healthy."
