#!/bin/sh
set -e

php artisan migrate --force
php artisan db:seed --class=Database\\Seeders\\AdminUserSeeder --force

exec "$@"
