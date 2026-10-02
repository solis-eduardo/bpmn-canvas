#!/bin/sh
set -e

# Boot-time work only applies when starting the FPM server. One-off commands
# (`docker compose run app php artisan ...`) skip it, so they don't migrate,
# seed or rewrite caches as a side effect.
if [ "$1" = "php-fpm" ]; then
    # The diagrams volume is mounted from the host and may belong to a UID
    # different from the www-data user that runs the PHP-FPM workers. Without
    # this, creating, renaming, or deleting Diagrams fails with "Permission
    # denied". Only entries with the wrong owner are touched (idempotent,
    # cheap on restarts, and catches files copied in by another UID).
    find /var/www/html/storage/app/diagrams ! -user www-data \
        -exec chown www-data:www-data {} +

    # Artisan runs as www-data so files it creates (caches, logs) stay
    # writable by the FPM workers.
    # (BusyBox `su` can't forward option-like args, so they go in the -c
    # string; every call here uses simple, space-free arguments.)
    artisan() { su -s /bin/sh www-data -c "php artisan $*"; }

    artisan migrate --force
    artisan db:seed --class='Database\\Seeders\\AdminUserSeeder' --force

    # Only this container gets the config/route cache; queue and scheduler
    # read config uncached, which is functionally identical.
    if [ "$APP_ENV" = "production" ]; then
        artisan optimize
    fi
fi

exec "$@"
