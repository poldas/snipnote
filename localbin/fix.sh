#!/bin/bash
# Runs PHP-CS-Fixer to automatically fix coding standard violations.
# Usage: ./localbin/fix.sh [path]
# Set USE_DOCKER=false to run locally (e.g. in CI)

echo "🛠️  Running PHP CS Fixer..."

CMD="vendor/bin/php-cs-fixer fix $@"

if [ "${USE_DOCKER:-true}" = "true" ]; then
    docker compose exec -T app $CMD
else
    $CMD
fi
