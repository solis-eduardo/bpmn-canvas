# syntax=docker/dockerfile:1

# --- Stage 1: download bpmn-js (served locally, no CDN) ---
FROM node:22-alpine AS vendor
ARG BPMN_JS_VERSION=18.30.0
WORKDIR /build
RUN --mount=type=cache,target=/root/.npm \
    npm install --no-audit --no-fund --omit=dev --save-exact bpmn-js@${BPMN_JS_VERSION}

# --- Stage 2: PHP dependencies (Composer), no dev ---
FROM composer:2 AS composer-deps
ENV COMPOSER_CACHE_DIR=/tmp/composer-cache
WORKDIR /build
COPY composer.json composer.lock ./
RUN --mount=type=cache,target=/tmp/composer-cache \
    composer install --no-dev --no-interaction --no-progress --no-scripts --optimize-autoloader

# --- Stage 3: PHP-FPM application (Laravel) ---
FROM php:8.4-fpm-alpine AS app
# libpq-dev (not postgresql-dev) avoids transitively pulling in llvm/clang/icu
# (Postgres server JIT support, irrelevant to the pdo_pgsql client); the build
# headers go into a virtual group removed at the end of the layer.
RUN --mount=type=cache,target=/var/cache/apk,sharing=locked \
    apk add --update --virtual .build-deps $PHPIZE_DEPS libpq-dev \
    && apk add --update libpq git \
    && docker-php-ext-install -j$(nproc) pdo pdo_pgsql opcache \
    && apk del .build-deps \
    && git config --system --add safe.directory '*'
WORKDIR /var/www/html

COPY --from=composer-deps /build/vendor ./vendor
COPY . .
COPY --from=composer-deps /usr/bin/composer /usr/bin/composer
RUN composer dump-autoload --no-dev --optimize \
    && mkdir -p storage/app/diagrams \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/app/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

VOLUME ["/var/www/html/storage/app/diagrams"]
ENTRYPOINT ["/entrypoint.sh"]
CMD ["php-fpm"]

# --- Stage 4: nginx (reverse proxy + static files) ---
FROM nginx:stable-alpine AS web
COPY --from=vendor /build/node_modules/bpmn-js/dist/assets/                                 /var/www/html/public/vendor/bpmn-js/assets/
COPY --from=vendor /build/node_modules/bpmn-js/dist/bpmn-modeler.production.min.js          /var/www/html/public/vendor/bpmn-js/
COPY --from=vendor /build/node_modules/bpmn-js/dist/bpmn-navigated-viewer.production.min.js /var/www/html/public/vendor/bpmn-js/
COPY docker/web/default.conf /etc/nginx/conf.d/default.conf
COPY public/ /var/www/html/public/

EXPOSE 80
