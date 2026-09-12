#!/usr/bin/env bash

set -euo pipefail

export APP_ENV=testing
export APP_URL=http://localhost:8001
export DB_DATABASE=testing
export SESSION_DRIVER=database
export CACHE_STORE=array
export MAIL_MAILER=array
export QUEUE_CONNECTION=sync
export DUSK_DRIVER_URL=http://selenium:4444/wd/hub
export DUSK_APP_HOST="${DUSK_APP_HOST:-laravel.test}"

hot_file=public/hot
hot_backup=''
server_pid=''

cleanup() {
    if [[ -n "$server_pid" ]]; then
        kill "$server_pid" 2>/dev/null || true
    fi

    if [[ -n "$hot_backup" && -f "$hot_backup" ]]; then
        mv -f "$hot_backup" "$hot_file"
    fi
}

trap cleanup EXIT

if [[ -f "$hot_file" ]]; then
    hot_backup="$(mktemp)"
    cp "$hot_file" "$hot_backup"
    rm "$hot_file"
fi

php tests/assert-testing-database.php
npm run build

unset PHP_CLI_SERVER_WORKERS
(
    cd public
    exec php -d variables_order=EGPCS -S 0.0.0.0:8001 \
        ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
) > storage/logs/dusk-server.log 2>&1 &
server_pid=$!

for attempt in {1..30}; do
    if curl --fail --silent http://127.0.0.1:8001 > /dev/null; then
        break
    fi

    if [[ "$attempt" == 30 ]]; then
        echo "Dusk application server did not start. See storage/logs/dusk-server.log." >&2
        exit 1
    fi

    sleep 1
done

php artisan dusk "$@"
