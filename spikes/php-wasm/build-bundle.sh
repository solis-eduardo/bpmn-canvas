#!/usr/bin/env bash
# Builds the static site in ./bundle (shell + service worker + php-wasm + app.zip).
# Run ./build-stage.sh first. Serve with: node serve.mjs bundle 8081 /bpmn-canvas/
set -euo pipefail
cd "$(dirname "$0")"
npx vite build --config web/vite.config.js web
# @php-wasm/web statically references every PHP version; only 8.4 is ever fetched, so ship just that.
find bundle/assets -name "php_*.wasm" ! -name "php_8_4-*" -delete
# The app never loads the optional intl extension (lazy-fetched, ~60 MB of ICU data); drop it.
find bundle/assets \( -name "intl-*.so" -o -name "icu-*.dat" \) -delete
rm -f bundle/app.zip
# Drop what the running app never reads (tests/docs of vendor packages, Laravel's dev leftovers).
( cd stage && zip -qr9 ../bundle/app.zip . \
  -x 'vendor/*/*/tests/*' 'vendor/*/*/Tests/*' 'vendor/*/*/docs/*' 'vendor/*/*/.git*' \
     'storage/logs/*' 'node_modules/*' 'tests/*' 'backups/*' 'diagrams/*' )
ls -la bundle bundle/app.zip
