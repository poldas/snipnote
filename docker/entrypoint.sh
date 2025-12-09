#!/bin/sh
set -e

# Warm cache using real runtime environment variables (prod only)
if [ "${APP_ENV:-dev}" = "prod" ]; then
  php bin/console cache:clear --no-warmup --env=prod
  php bin/console cache:warmup --env=prod
  php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration --env=prod
fi

# Preserve default php-apache entrypoint behavior
exec docker-php-entrypoint "$@"

