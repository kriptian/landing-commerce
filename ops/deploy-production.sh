#!/usr/bin/env bash

set -Eeuo pipefail

PROJECT_DIR="/home/u747542941/domains/ondigitalsolution.com/public_html"
CONFIG_FILE="/home/u747542941/.config/landing-commerce-deploy"
BACKUP_SCRIPT="/home/u747542941/deploy/backup-production.php"
LOCK_FILE="/home/u747542941/.landing-commerce-deploy.lock"
TARGET_SHA="${1:-}"
EXPECTED_ORIGIN="https://github.com/kriptian/landing-commerce.git"

if [[ ! "$TARGET_SHA" =~ ^[0-9a-f]{40}$ ]]; then
    echo "Invalid deployment commit."
    exit 2
fi

exec 9>"$LOCK_FILE"
if ! flock -n 9; then
    echo "Another production deployment is already running."
    exit 3
fi

if [[ ! -r "$CONFIG_FILE" ]]; then
    echo "Missing protected deployment configuration."
    exit 4
fi

# shellcheck disable=SC1090
source "$CONFIG_FILE"

if [[ -z "${MAINTENANCE_SECRET:-}" ]]; then
    echo "Missing maintenance secret."
    exit 5
fi

cd "$PROJECT_DIR"

ACTUAL_ORIGIN="$(git remote get-url origin)"
if [[ "$ACTUAL_ORIGIN" != "$EXPECTED_ORIGIN" && "$ACTUAL_ORIGIN" != "git@github.com:kriptian/landing-commerce.git" ]]; then
    echo "The production origin does not match the authorized repository."
    exit 7
fi

echo "Fetching origin/main..."
git fetch origin main

REMOTE_SHA="$(git rev-parse origin/main)"
if [[ "$REMOTE_SHA" != "$TARGET_SHA" ]]; then
    echo "The requested commit is not the current origin/main."
    exit 6
fi

PREVIOUS_SHA="$(git rev-parse HEAD)"
echo "Current commit: $PREVIOUS_SHA"
echo "Target commit:  $TARGET_SHA"

php "$BACKUP_SCRIPT"

MAINTENANCE_ENABLED=0
MIGRATION_STARTED=0

recover_deployment() {
    local exit_code=$?
    trap - EXIT INT TERM
    set +e

    if [[ "$exit_code" != 0 && "$MAINTENANCE_ENABLED" == 1 ]]; then
        if [[ "$MIGRATION_STARTED" == 0 ]]; then
            echo "Deployment failed before migrations. Restoring $PREVIOUS_SHA..."
            git reset --hard "$PREVIOUS_SHA"
            composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist
            php artisan optimize:clear
            php artisan optimize
        else
            echo "Deployment failed after migrations started; keeping target code for schema compatibility."
            php artisan optimize:clear
        fi

        php artisan up
    fi

    exit "$exit_code"
}
trap recover_deployment EXIT
trap 'exit 130' INT
trap 'exit 129' HUP
trap 'exit 143' TERM

php artisan down --secret="$MAINTENANCE_SECRET"
MAINTENANCE_ENABLED=1
echo "Maintenance mode enabled."

git reset --hard "$TARGET_SHA"
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist
php artisan optimize:clear
MIGRATION_STARTED=1
php artisan migrate --force
php artisan optimize
php -r 'function_exists("opcache_reset") && opcache_reset();' || true
php artisan up
MAINTENANCE_ENABLED=0

echo "Production deployment completed: $TARGET_SHA"
