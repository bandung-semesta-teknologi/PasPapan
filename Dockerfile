# Build browser assets in a throwaway stage so Bun and node_modules are not
# included in the runtime image.
FROM oven/bun:1.3.6-alpine AS assets

WORKDIR /var/www/html

COPY package.json bun.lock ./
RUN bun install --frozen-lockfile

COPY . .
RUN bun run build


# Compile only the PHP extensions required by the application. Development
# packages are removed in this stage, leaving just the shared runtime libs.
FROM php:8.3-fpm-alpine AS php-base

RUN apk add --no-cache \
        freetype \
        icu-libs \
        libjpeg-turbo \
        libpng \
        libpq \
        libzip \
        oniguruma \
        su-exec \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        freetype-dev \
        icu-dev \
        libjpeg-turbo-dev \
        libpng-dev \
        libzip-dev \
        oniguruma-dev \
        postgresql-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        gd \
        intl \
        mbstring \
        opcache \
        pcntl \
        pdo_pgsql \
        zip \
    && apk del .build-deps

COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/zz-opcache.ini
COPY docker/www.conf /usr/local/etc/php-fpm.d/www.conf


# Install production Composer dependencies and generate Laravel's package
# manifest. Composer and all build-only source stay out of the final image.
FROM php-base AS vendor

WORKDIR /var/www/html

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY . .
COPY --from=assets /var/www/html/public/build ./public/build

RUN mkdir -p storage/framework/cache/data storage/framework/sessions \
        storage/framework/views storage/logs bootstrap/cache \
    && composer install \
        --no-dev \
        --prefer-dist \
        --no-interaction \
        --no-progress \
        --optimize-autoloader


# PHP-FPM application runtime.
FROM php-base AS app

WORKDIR /var/www/html

COPY --from=vendor --chown=www-data:www-data /var/www/html /var/www/html
COPY --chown=www-data:www-data --chmod=755 docker/entrypoint.sh /usr/local/bin/paspapan-entrypoint

RUN mkdir -p storage/app/public storage/framework/cache/data \
        storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

ENTRYPOINT ["paspapan-entrypoint"]
CMD ["php-fpm", "-F"]


# Caddy serves only public assets and forwards PHP requests to the app service.
# It uses the app storage volume read-only for files exposed through /storage.
FROM caddy:2-alpine AS web

COPY docker/Caddyfile /etc/caddy/Caddyfile
COPY --from=assets /var/www/html/public /var/www/html/public
