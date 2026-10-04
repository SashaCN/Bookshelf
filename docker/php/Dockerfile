# syntax=docker/dockerfile:1
# The build context is the repository root (see docker-compose.yml and the deploy workflow).

FROM php:8.4-fpm-alpine AS base

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_mysql bcmath intl opcache pcntl zip \
    && apk add --no-cache git unzip

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html

# ---------------------------------------------------------------------------------------------------------------------
# Development image: source code is bind-mounted, dependencies are installed on first start.
FROM base AS dev

ARG APP_UID=1000
ARG APP_GID=1000

RUN addgroup -g ${APP_GID} app \
    && adduser -D -u ${APP_UID} -G app app

COPY docker/php/entrypoint.dev.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

USER app

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]

# ---------------------------------------------------------------------------------------------------------------------
# Production dependencies: no dev packages, optimized class map.
FROM base AS vendor

WORKDIR /app

COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY backend/ ./
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative --no-scripts

# ---------------------------------------------------------------------------------------------------------------------
# Production image: code and dependencies baked in, nothing is mounted.
FROM base AS prod

RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php/prod.ini "$PHP_INI_DIR/conf.d/zz-bookshelf.ini"

COPY --from=vendor --chown=www-data:www-data /app ./

# Registers the packages that are installed (the dev-only ones are not). The key is a throwaway
# that exists only for this command; the real one comes from the environment at runtime.
RUN APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= php artisan package:discover --ansi \
    && chown -R www-data:www-data bootstrap/cache storage

COPY docker/php/entrypoint.prod.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]
