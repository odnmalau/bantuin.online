# syntax=docker/dockerfile:1.7

ARG NODE_IMAGE=node:22-bookworm-slim@sha256:6c74791e557ce11fc957704f6d4fe134a7bc8d6f5ca4403205b2966bd488f6b3
ARG PHP_IMAGE=serversideup/php:8.5-frankenphp@sha256:a0f4447da7612f9bca3c982d0cf33a607cbddf828f4b96a44bfa9f6f037007b6

FROM ${NODE_IMAGE} AS node

FROM ${PHP_IMAGE} AS php-base

USER root

RUN docker-php-serversideup-dep-install-debian poppler-utils

WORKDIR /var/www/html

FROM php-base AS build

ARG VITE_APP_NAME="Bantuin Online"

ENV VITE_APP_NAME="${VITE_APP_NAME}"

USER root

COPY --from=node /usr/local/ /usr/local/

RUN corepack enable pnpm \
    && corepack prepare pnpm@11.6.0 --activate

USER www-data

COPY --chown=www-data:www-data . /var/www/html

RUN --mount=type=cache,target=/var/www/.composer/cache,uid=33,gid=33 \
    composer install \
        --no-dev \
        --no-interaction \
        --no-progress \
        --prefer-dist \
        --optimize-autoloader \
        --classmap-authoritative

RUN --mount=type=cache,target=/var/www/.local/share/pnpm/store,uid=33,gid=33 \
    pnpm install --frozen-lockfile \
    && pnpm run build

FROM php-base AS production

ARG BUILD_REVISION="unknown"
ARG BUILD_SOURCE="https://github.com/odnmalau/bantuin.online"

LABEL org.opencontainers.image.source="${BUILD_SOURCE}" \
      org.opencontainers.image.revision="${BUILD_REVISION}" \
      org.opencontainers.image.title="Bantuin Online" \
      org.opencontainers.image.description="Bantuin Online Laravel application"

ENV PHP_OPCACHE_ENABLE="1" \
    PHP_OPCACHE_VALIDATE_TIMESTAMPS="0"

USER root

COPY --chown=www-data:www-data . /var/www/html
COPY --from=build --chown=www-data:www-data /var/www/html/vendor /var/www/html/vendor
COPY --from=build --chown=www-data:www-data /var/www/html/public/build /var/www/html/public/build
COPY --from=build --chown=www-data:www-data /var/www/html/bootstrap/cache /var/www/html/bootstrap/cache

RUN install -d -o www-data -g www-data \
        /var/www/html/bootstrap/cache \
        /var/www/html/storage/app/private \
        /var/www/html/storage/app/public \
        /var/www/html/storage/framework/cache/data \
        /var/www/html/storage/framework/sessions \
        /var/www/html/storage/framework/views \
        /var/www/html/storage/logs

USER www-data
