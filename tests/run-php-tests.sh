#!/usr/bin/env bash

set -euo pipefail

export APP_ENV=testing
export DB_DATABASE=testing
export SESSION_DRIVER=array
export CACHE_STORE=array
export MAIL_MAILER=array
export QUEUE_CONNECTION=sync

php tests/assert-testing-database.php
php artisan test "$@"
