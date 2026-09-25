#!/bin/sh
set -e

# Unlike docker/app/entrypoint.sh, this container never runs migrations or
# seeders itself — the `app` service already owns that, and running
# `migrate` from two containers at once on startup risks a race against
# Postgres. It only needs the schema to exist before its first scheduled
# `backup:run`, which the schedule's cadence (see bootstrap/app.php)
# comfortably outlives `app`'s own startup migration.
#
# Runs as the container's default root user (unlike `app`'s php-fpm
# workers, which php-fpm itself drops to www-data): a scheduler process
# writing to a host-mounted backups/ volume as root avoids the
# host-UID-mismatch "Permission denied" class of problem entirely, since
# there's no separate user to reconcile ownership for.
exec "$@"
