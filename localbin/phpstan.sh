#!/bin/bash
# Usage: ./localbin/phpstan.sh [args]
# Set USE_DOCKER=false to run locally (e.g. in CI)

echo "🔍 Running PHPStan (Level 6)..."

CMD="vendor/bin/phpstan analyse -c phpstan.dist.neon --memory-limit=1G $@"

if [ "${USE_DOCKER:-true}" = "true" ]; then
    docker compose exec -T app $CMD
else
    $CMD
fi