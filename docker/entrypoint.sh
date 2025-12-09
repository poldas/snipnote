#!/bin/sh
set -e

# Ensure an .env file exists so Symfony Dotenv does not fail when APP_ENV is set
[ -f /var/www/html/.env ] || touch /var/www/html/.env

# Warm cache using real runtime environment variables (prod only)
if [ "${APP_ENV:-dev}" = "prod" ]; then
  php bin/console cache:clear --no-warmup --env=prod
  php bin/console cache:warmup --env=prod
fi

# Preserve default php-apache entrypoint behavior
exec docker-php-entrypoint "$@"

