# --- Etapa 1: baixa o bpmn-js (servido localmente, sem CDN) ---
FROM node:22-alpine AS vendor
ARG BPMN_JS_VERSION=18.30.0
WORKDIR /build
RUN npm install --no-audit --no-fund --omit=dev bpmn-js@${BPMN_JS_VERSION}

# --- Etapa 2: dependências PHP (Composer), sem dev ---
FROM composer:2 AS composer-deps
WORKDIR /build
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --no-scripts --optimize-autoloader

# --- Etapa 3: aplicação PHP-FPM (Laravel) ---
FROM php:8.4-fpm-alpine AS app
RUN apk add --no-cache postgresql-dev git \
    && docker-php-ext-install pdo pdo_pgsql opcache \
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
