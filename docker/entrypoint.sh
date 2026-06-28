#!/bin/sh
set -eu

mkdir -p \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

gosu www-data php artisan optimize

if [ "$#" -eq 0 ]; then
    set -- apache2-foreground
fi

if [ "$1" = "apache2-foreground" ]; then
    exec "$@"
fi

exec gosu www-data "$@"
