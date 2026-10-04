# syntax=docker/dockerfile:1

FROM php:8.4-fpm-alpine AS base

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_mysql bcmath intl opcache pcntl zip \
    && apk add --no-cache git unzip

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html

# Development image: source code is bind-mounted, dependencies are installed on first start.
FROM base AS dev

ARG APP_UID=1000
ARG APP_GID=1000

RUN addgroup -g ${APP_GID} app \
    && adduser -D -u ${APP_UID} -G app app

COPY entrypoint.dev.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

USER app

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]
