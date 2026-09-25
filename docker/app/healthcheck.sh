#!/bin/sh
# Docker healthcheck for the `app` (PHP-FPM) service.
#
# The app container runs php-fpm only (no HTTP server), so we can't just
# curl a URL. Instead we speak FastCGI directly to the FPM socket with
# cgi-fcgi, asking it to run public/index.php against the Laravel `/up`
# route exactly as nginx would. That route (see app/Listeners/CheckDatabaseHealth)
# only returns 200 when PHP-FPM actually answered the request *and* the
# database connection succeeded, so a single check covers both.
set -e

RESPONSE=$(SCRIPT_FILENAME=/var/www/html/public/index.php \
    SCRIPT_NAME=/index.php \
    REQUEST_URI=/up \
    REQUEST_METHOD=GET \
    QUERY_STRING="" \
    CONTENT_TYPE="" \
    CONTENT_LENGTH=0 \
    HTTP_ACCEPT="application/json" \
    cgi-fcgi -bind -connect 127.0.0.1:9000)

echo "$RESPONSE" | grep -q '"status":"up"'
