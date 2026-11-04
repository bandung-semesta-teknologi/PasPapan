#!/bin/sh
set -eu

cd /var/www/html

: "${APP_KEY:?Set APP_KEY in .env before starting the application}"

mkdir -p \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

php artisan storage:link --force

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    php artisan migrate --force
fi

if [ "${RUN_SEEDERS:-true}" = "true" ]; then
    php artisan paspapan:seed-real
fi

if [ "${RUN_OPTIMIZE:-true}" = "true" ]; then
    php artisan optimize
fi

if [ "${1:-}" = "php-fpm" ]; then
    exec "$@"
fi

exec su-exec www-data "$@"
