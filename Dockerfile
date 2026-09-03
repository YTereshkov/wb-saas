# syntax=docker/dockerfile:1.7

FROM node:22.22.0-bookworm-slim AS node-runtime

FROM php:8.4.14-cli-bookworm AS php-base

ENV COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /var/www/html

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        $PHPIZE_DEPS \
        ca-certificates \
        curl \
        git \
        libicu-dev \
        libonig-dev \
        libpq-dev \
        libxml2-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        dom \
        intl \
        mbstring \
        opcache \
        pcntl \
        pdo_pgsql \
        xml \
        xmlwriter \
        zip \
    && pecl install redis-6.3.0 \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && find /var/lib/apt/lists -mindepth 1 -delete

COPY --from=composer:2.8.12 /usr/bin/composer /usr/local/bin/composer
COPY --from=node-runtime /usr/local/bin/node /usr/local/bin/node
COPY --from=node-runtime /usr/local/lib/node_modules /usr/local/lib/node_modules

RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s /usr/local/lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx

COPY docker/php/conf.d/app.ini /usr/local/etc/php/conf.d/99-sellerscope.ini

FROM php-base AS build

COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-progress --no-scripts

COPY package.json package-lock.json .npmrc ./
RUN npm ci --no-audit --no-fund

COPY . .

RUN composer dump-autoload --optimize --no-interaction \
    && npm run build

FROM php-base AS runtime

ARG APP_UID=1000
ARG APP_GID=1000

RUN groupadd --gid "${APP_GID}" sellerscope \
    && useradd --uid "${APP_UID}" --gid sellerscope --create-home --shell /bin/bash sellerscope

COPY --chown=sellerscope:sellerscope . .
COPY --from=build --chown=sellerscope:sellerscope /var/www/html/vendor ./vendor
COPY --from=build --chown=sellerscope:sellerscope /var/www/html/node_modules ./node_modules
COPY --from=build --chown=sellerscope:sellerscope /var/www/html/public/build ./public/build
COPY --from=build --chown=sellerscope:sellerscope /var/www/html/resources/js ./resources/js

RUN mkdir -p bootstrap/cache storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs \
    && chown -R sellerscope:sellerscope bootstrap/cache storage

USER sellerscope

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
