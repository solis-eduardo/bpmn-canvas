# syntax=docker/dockerfile:1

# --- Etapa 1: baixa o bpmn-js (servido localmente, sem CDN) ---
FROM node:22-alpine AS vendor
ARG BPMN_JS_VERSION=18.30.0
WORKDIR /build
RUN --mount=type=cache,target=/root/.npm \
    npm install --no-audit --no-fund --omit=dev --save-exact bpmn-js@${BPMN_JS_VERSION}

# --- Etapa 2: dependências PHP (Composer), sem dev ---
FROM composer:2 AS composer-deps
ENV COMPOSER_CACHE_DIR=/tmp/composer-cache
WORKDIR /build
COPY composer.json composer.lock ./
RUN --mount=type=cache,target=/tmp/composer-cache \
    composer install --no-dev --no-interaction --no-progress --no-scripts --optimize-autoloader

# --- Etapa 3: aplicação PHP-FPM (Laravel) ---
FROM php:8.4-fpm-alpine AS app
# libpq-dev (não postgresql-dev) evita puxar transitivamente llvm/clang/icu
# (suporte a JIT do servidor Postgres, irrelevante para o cliente pdo_pgsql);
# os headers de build ficam num grupo virtual removido ao final da camada.
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

# --- Etapa 4: nginx (reverse proxy + estáticos) ---
FROM nginx:stable-alpine AS web
COPY --from=vendor /build/node_modules/bpmn-js/dist/assets/                                 /var/www/html/public/vendor/bpmn-js/assets/
COPY --from=vendor /build/node_modules/bpmn-js/dist/bpmn-modeler.production.min.js          /var/www/html/public/vendor/bpmn-js/
COPY --from=vendor /build/node_modules/bpmn-js/dist/bpmn-navigated-viewer.production.min.js /var/www/html/public/vendor/bpmn-js/
COPY docker/web/default.conf /etc/nginx/conf.d/default.conf
COPY public/ /var/www/html/public/

EXPOSE 80
