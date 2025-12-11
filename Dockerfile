# syntax=docker/dockerfile:1.7
FROM php:8.4-fpm-bookworm AS base
ARG APP_ENV=prod
ENV APP_ENV=${APP_ENV} \
    APP_DEBUG=0 \
    COMPOSER_ALLOW_SUPERUSER=1 \
    PATH="/app/bin:${PATH}"
WORKDIR /app

# System deps + PHP extensions
RUN apt-get update \
 && apt-get install -y --no-install-recommends \
      git unzip libpq-dev libicu-dev libzip-dev zlib1g-dev \
 && docker-php-ext-install -j$(nproc) intl pdo_pgsql zip opcache \
 && apt-get clean \
 && rm -rf /var/lib/apt/lists/*

# Opcache for production
COPY docker/php-opcache.ini /usr/local/etc/php/conf.d/opcache.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

FROM base AS builder
# Install PHP deps with cache (needs full app so Symfony scripts can run)
COPY . .
RUN --mount=type=cache,target=/tmp/composer-cache \
    composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
# Uncomment if you want compiled env for prod
# RUN composer dump-env prod
# Uncomment if you need asset build (tailwind/asset-mapper)
# RUN npm ci && npm run build

FROM base AS runtime
# App code
COPY --from=builder /app /app

# Ensure writable cache/logs
RUN mkdir -p /app/var && chown -R www-data:www-data /app/var
USER www-data

EXPOSE 9000
HEALTHCHECK --interval=30s --timeout=5s --retries=3 CMD php-fpm -t >/dev/null || exit 1
CMD ["php-fpm"]