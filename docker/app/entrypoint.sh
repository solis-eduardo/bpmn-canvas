#!/bin/sh
set -e

# The diagrams volume is mounted from the host and may belong to a UID
# different from the www-data user that runs the PHP-FPM workers (see
# docker/web — that container isn't affected, only this one runs as root
# before php-fpm drops privilege per-process). Without this, creating,
# renaming, or deleting Diagrams fails with "Permission denied" if the
# host directory wasn't created/adjusted for the container's UID.
chown -R www-data:www-data /var/www/html/storage/app/diagrams

php artisan migrate --force
php artisan db:seed --class=Database\\Seeders\\AdminUserSeeder --force

exec "$@"
