#!/usr/bin/env bash
# Builds the throwaway "stage" tree that gets bundled into php-wasm:
#   composer install --no-dev + vite build + vendored bpmn-js + SQLite file
#   + the demo env (.env.demo) and database (DemoSeeder). Output: $OUT (default ./stage)
# Needs: git, docker (composer image), node/npm, php (any 8.x, host) for migrating.
set -euo pipefail
HERE="$(cd "$(dirname "$0")" && pwd)"
REPO="${REPO:-$(cd "$HERE/../.." && pwd)}"
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

# Sub-path and CSP patches (spike-only, applied to the staged copy).
php "$HERE/patch-stage.php" "$OUT"

# Env: the real demo's .env.demo (ADR-0018), only APP_URL and the DB path changed. In wasm the DB lives
# at /persist/db.sqlite (OPFS-backed; the shell hardcodes that name). Seeded with DemoSeeder at build time.
cp "$REPO/.env.demo" "$OUT/.env"
sed -i -e 's#^APP_URL=.*#APP_URL=http://localhost:8080#' -e 's#^DB_DATABASE=.*#DB_DATABASE=/persist/db.sqlite#' "$OUT/.env"
( cd "$OUT" && touch db.sqlite \
  && DB_DATABASE="$OUT/db.sqlite" php artisan migrate --force \
  && DB_DATABASE="$OUT/db.sqlite" php artisan db:seed --class=DemoSeeder --force )
mkdir -p "$OUT"/storage/{app/diagrams,framework/{sessions,cache,views},logs} "$OUT/bootstrap/cache"
echo "stage ready: $OUT"
