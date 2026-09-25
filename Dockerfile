# syntax=docker/dockerfile:1

# --- Stage 1: download bpmn-js + the BPMN diff library (served locally, no CDN) ---
FROM node:22-alpine AS vendor
ARG BPMN_JS_VERSION=18.30.0
ARG BPMN_JS_DIFFER_VERSION=3.2.0
WORKDIR /build
RUN --mount=type=cache,target=/root/.npm \
    npm install --no-audit --no-fund --omit=dev --save-exact \
        bpmn-js@${BPMN_JS_VERSION} \
        bpmn-js-differ@${BPMN_JS_DIFFER_VERSION}

# --- Stage 2: Tailwind/Vite assets (built at image build time, no CDN at runtime) ---
FROM node:22-alpine AS vite-build
WORKDIR /build
COPY package.json package-lock.json ./
RUN --mount=type=cache,target=/root/.npm \
    npm ci --no-audit --no-fund
COPY vite.config.js ./
COPY resources/ ./resources/
RUN npm run build

# --- Stage 3: PHP dependencies (Composer), no dev ---
FROM composer:2 AS composer-deps
ENV COMPOSER_CACHE_DIR=/tmp/composer-cache
WORKDIR /build
COPY composer.json composer.lock ./
RUN --mount=type=cache,target=/tmp/composer-cache \
    composer install --no-dev --no-interaction --no-progress --no-scripts --optimize-autoloader

# --- Stage 4: PHP-FPM application (Laravel) ---
FROM php:8.4-fpm-alpine AS app
# libpq-dev (not postgresql-dev) avoids transitively pulling in llvm/clang/icu
# (Postgres server JIT support, irrelevant to the pdo_pgsql client); the build
# headers go into a virtual group removed at the end of the layer.
RUN --mount=type=cache,target=/var/cache/apk,sharing=locked \
    apk add --update --virtual .build-deps $PHPIZE_DEPS libpq-dev \
    && apk add --update libpq git postgresql16-client fcgi \
    && docker-php-ext-install -j$(nproc) pdo pdo_pgsql opcache \
    && apk del .build-deps \
    && git config --system --add safe.directory '*'
WORKDIR /var/www/html

COPY --from=composer-deps /build/vendor ./vendor
COPY . .
COPY --from=vite-build /build/public/build/ ./public/build/
COPY --from=composer-deps /usr/bin/composer /usr/bin/composer
RUN composer dump-autoload --no-dev --optimize \
    && mkdir -p storage/app/diagrams storage/app/backup \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/app/entrypoint.sh /entrypoint.sh
COPY docker/scheduler/entrypoint.sh /entrypoint-scheduler.sh
COPY docker/app/healthcheck.sh /usr/local/bin/healthcheck.sh
RUN chmod +x /entrypoint.sh /entrypoint-scheduler.sh /usr/local/bin/healthcheck.sh

VOLUME ["/var/www/html/storage/app/diagrams"]
ENTRYPOINT ["/entrypoint.sh"]
CMD ["php-fpm"]

# --- Stage 5: nginx (reverse proxy + static files) ---
FROM nginx:stable-alpine AS web
COPY --from=vendor /build/node_modules/bpmn-js/dist/assets/                                 /var/www/html/public/vendor/bpmn-js/assets/
COPY --from=vendor /build/node_modules/bpmn-js/dist/bpmn-modeler.production.min.js          /var/www/html/public/vendor/bpmn-js/
COPY --from=vendor /build/node_modules/bpmn-js/dist/bpmn-navigated-viewer.production.min.js /var/www/html/public/vendor/bpmn-js/

# bpmn-js-differ ships as an ES module (no CDN, no bundler in this project —
# see ADR-0007): its only non-relative import ("min-dash") is resolved via a
# browser import map declared in the editor view, pointing at min-dash's own
# vendored ES module build. min-dash's package.json has no separate
# "*.esm.js" entry — its "main"/"exports" file (dist/index.js) is itself a
# native ES module (the package sets "type": "module"), so that's the file
# to vendor, renamed only for clarity alongside bpmn-js-differ's own
# index.esm.js.
COPY --from=vendor /build/node_modules/bpmn-js-differ/dist/index.esm.js     /var/www/html/public/vendor/bpmn-js-differ/index.esm.js
COPY --from=vendor /build/node_modules/min-dash/dist/index.js               /var/www/html/public/vendor/bpmn-js-differ/min-dash.esm.js
COPY docker/web/default.conf /etc/nginx/conf.d/default.conf
COPY public/ /var/www/html/public/
COPY --from=vite-build /build/public/build/ /var/www/html/public/build/

EXPOSE 80
