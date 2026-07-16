#!/bin/sh
set -e

php artisan config:clear
php artisan config:cache
php artisan route:cache

# recria o link se não existir (idempotente)
if [ ! -L public/storage ]; then
    php artisan storage:link
fi

exec "$@"