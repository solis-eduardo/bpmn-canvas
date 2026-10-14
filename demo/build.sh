#!/usr/bin/env bash
# Builds the browser demo (ADR-0018) into demo/dist:
#   - the static shell (Vite): boot page, Service Worker and the php-wasm loader;
#   - app.zip: the real app built from HEAD (composer --no-dev, vite assets, vendored bpmn-js),
#     with a pre-migrated and seeded SQLite file (DemoSeeder) that the shell copies into OPFS.
# Needs git, PHP 8.4 with pdo_sqlite and zip, Composer, Node 22 and npm. Only demo/.stage and
# demo/dist are written; the repository is read through `git archive HEAD`.
set -euo pipefail

DEMO="$(cd "$(dirname "$0")" && pwd)"
REPO="$(cd "$DEMO/.." && pwd)"
STAGE="$DEMO/.stage"
DIST="$DEMO/dist"

rm -rf "$STAGE" "$DIST"
mkdir -p "$STAGE"
git -C "$REPO" archive HEAD | tar -x -C "$STAGE"

# 1. The app: production dependencies, front-end assets and vendored bpmn-js (as in the Dockerfile).
cd "$STAGE"
composer install --no-dev --no-interaction --no-progress --no-scripts --optimize-autoloader
(cd docker/vendor && npm ci --no-audit --no-fund --omit=dev)
npm ci --no-audit --no-fund
npm run build
VENDOR_PUBLIC=public/vendor
NODE_VENDOR=docker/vendor/node_modules
mkdir -p "$VENDOR_PUBLIC/bpmn-js" "$VENDOR_PUBLIC/bpmn-js-differ"
cp -r "$NODE_VENDOR/bpmn-js/dist/assets" "$VENDOR_PUBLIC/bpmn-js/"
cp "$NODE_VENDOR/bpmn-js/dist/bpmn-modeler.production.min.js" \
   "$NODE_VENDOR/bpmn-js/dist/bpmn-navigated-viewer.production.min.js" "$VENDOR_PUBLIC/bpmn-js/"
cp "$NODE_VENDOR/bpmn-js-differ/dist/index.esm.js" "$VENDOR_PUBLIC/bpmn-js-differ/"
cp "$NODE_VENDOR/min-dash/dist/index.js" "$VENDOR_PUBLIC/bpmn-js-differ/min-dash.esm.js"
rm -rf node_modules docker/vendor

# 2. Configuration, patches and the seeded database. .env.demo is the demo configuration; the
#    database is created here and the shell copies it to /persist in the browser.
cp .env.demo .env
php "$DEMO/stage-patches.php" "$STAGE"
mkdir -p storage/app/diagrams storage/framework/sessions storage/framework/views storage/framework/cache \
         storage/logs bootstrap/cache
php artisan package:discover --ansi
touch database/demo-seed.sqlite
DB_DATABASE="$STAGE/database/demo-seed.sqlite" \
    php artisan migrate --force --seed --seeder='Database\Seeders\DemoSeeder'
php artisan lang:export

# 3. The shell (Vite) and the zipped app, trimmed of what the running app never reads.
cd "$DEMO"
npm ci --no-audit --no-fund
npx vite build
find "$DIST/assets" -name 'php_*.wasm' ! -name 'php_8_4-*' -delete
find "$DIST/assets" \( -name 'intl-*.so' -o -name 'icu-*.dat' \) -delete
ls "$DIST"/assets/php_8_4-*.wasm > /dev/null

cd "$STAGE"
zip -qr9 "$DIST/app.zip" . -x \
    'vendor/*/*/tests/*' 'vendor/*/*/Tests/*' 'vendor/*/*/docs/*' 'vendor/*/*/.git*' \
    'storage/logs/*' 'node_modules/*' 'tests/*' 'backups/*' 'diagrams/*' '.git/*'
touch "$DIST/.nojekyll"

# 4. Bundle size: reported in the job summary to catch regressions. The browser fetches one of the
#    two PHP 8.4 runtime variants (JSPI or asyncify), so both are listed but only one counts as loaded.
human() { LC_ALL=C numfmt --to=iec --format='%.1f' "$1"; }
REPORT="$(mktemp)"
{
    echo "### Browser demo bundle"
    echo
    echo "| Part | Raw | Gzip -9 |"
    echo "|---|---:|---:|"
    total_raw=0
    total_gzip=0
    other_raw=0
    other_gzip=0
    other_count=0
    row() { echo "| $1 | $(human "$2") | $(human "$3") |"; }
    while IFS= read -r file; do
        raw=$(stat -c %s "$file")
        gz=$(gzip -9c "$file" | wc -c)
        total_raw=$((total_raw + raw))
        total_gzip=$((total_gzip + gz))
        case "$(basename "$file")" in
            php_8_4-*.wasm) row "\`${file#"$DIST"/}\` (PHP 8.4 runtime variant)" "$raw" "$gz" ;;
            app.zip) row "\`app.zip\` (app, vendor, seeded database)" "$raw" "$gz" ;;
            *)
                other_raw=$((other_raw + raw))
                other_gzip=$((other_gzip + gz))
                other_count=$((other_count + 1))
                ;;
        esac
    done < <(find "$DIST" -type f ! -name .nojekyll | sort)
    row "Shell, loaders and service worker ($other_count files)" "$other_raw" "$other_gzip"
    row "**Total shipped**" "$total_raw" "$total_gzip"
} | tee "$REPORT"
if [ -n "${GITHUB_STEP_SUMMARY:-}" ]; then
    cat "$REPORT" >> "$GITHUB_STEP_SUMMARY"
fi
rm -f "$REPORT"

echo "demo bundle ready: $DIST"
