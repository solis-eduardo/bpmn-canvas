#!/usr/bin/env bash
# Builds the throwaway "stage" tree that gets bundled into php-wasm:
#   composer install --no-dev + vite build + vendored bpmn-js + SQLite file
#   + the spike overlay (stub VersionRepository). Output: $OUT (default ./stage)
# Needs: git, docker (composer image), node/npm, php (any 8.x, host) for migrating.
set -euo pipefail
HERE="$(cd "$(dirname "$0")" && pwd)"
REPO="$(cd "$HERE/../.." && pwd)"
OUT="${OUT:-$HERE/stage}"

rm -rf "$OUT"; mkdir -p "$OUT"
git -C "$REPO" archive HEAD | tar -x -C "$OUT" --exclude=tests --exclude=spikes --exclude=docs

docker run --rm --user "$(id -u):$(id -g)" -v "$OUT:/app" -w /app composer:2.9.8 \
  composer install --no-dev --no-interaction --no-progress --no-scripts --optimize-autoloader --ignore-platform-reqs

( cd "$OUT" && npm ci --no-audit --no-fund && npm run build && rm -rf node_modules )
( cd "$OUT/docker/vendor" && npm ci --no-audit --no-fund --omit=dev )
V="$OUT/public/vendor"; N="$OUT/docker/vendor/node_modules"
mkdir -p "$V/bpmn-js" "$V/bpmn-js-differ"
cp -r "$N/bpmn-js/dist/assets" "$V/bpmn-js/"
cp "$N"/bpmn-js/dist/bpmn-modeler.production.min.js "$N"/bpmn-js/dist/bpmn-navigated-viewer.production.min.js "$V/bpmn-js/"
cp "$N/bpmn-js-differ/dist/index.esm.js" "$V/bpmn-js-differ/"
cp "$N/min-dash/dist/index.js" "$V/bpmn-js-differ/min-dash.esm.js"
rm -rf "$OUT/docker"

# Spike overlay: stub the git-backed VersionRepository (no git/proc_open in wasm).
cp -r "$HERE/overlay/." "$OUT/"
php "$HERE/patch-stage.php" "$OUT"

# Env + pre-migrated, seeded SQLite. In wasm the DB lives at /persist/db.sqlite (OPFS-backed).
cp "$REPO/.env.example" "$OUT/.env"
sed -i -e 's/^APP_ENV=.*/APP_ENV=production/' -e 's/^APP_DEBUG=.*/APP_DEBUG=true/' \
  -e 's#^APP_URL=.*#APP_URL=http://localhost:8080#' -e 's/^DB_CONNECTION=.*/DB_CONNECTION=sqlite/' \
  -e '/^DB_HOST/d;/^DB_PORT/d;/^DB_DATABASE/d;/^DB_USERNAME/d;/^DB_PASSWORD/d' \
  -e 's/^SESSION_DRIVER=.*/SESSION_DRIVER=database/' -e 's/^QUEUE_CONNECTION=.*/QUEUE_CONNECTION=sync/' \
  -e 's/^CACHE_STORE=.*/CACHE_STORE=file/' -e 's/^ADMIN_EMAIL=.*/ADMIN_EMAIL=demo@example.com/' \
  -e 's/^ADMIN_PASSWORD=.*/ADMIN_PASSWORD=demo-password/' "$OUT/.env"
printf 'DB_DATABASE=/persist/db.sqlite\nLOG_CHANNEL=stderr\n' >> "$OUT/.env"
grep -q '^ADMIN_PASSWORD=' "$OUT/.env" || printf 'ADMIN_EMAIL=demo@example.com\nADMIN_PASSWORD=demo-password\n' >> "$OUT/.env"
( cd "$OUT" && php artisan key:generate --force >/dev/null \
  && touch db.sqlite && DB_DATABASE="$OUT/db.sqlite" php artisan migrate --force --seed )
mkdir -p "$OUT"/storage/{app/diagrams,framework/{sessions,cache,views},logs} "$OUT/bootstrap/cache"
echo "stage ready: $OUT"
