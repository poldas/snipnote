#!/bin/bash
# Checks Code Style without modifying files.
# Usage: ./localbin/lint-style.sh [extra-args]
# Set USE_DOCKER=false to run locally (e.g. in CI)

echo "🎨 Checking Code Style (Dry Run)..."

# Pass additional args (like --format=checkstyle) if provided
CMD="vendor/bin/php-cs-fixer fix --dry-run --diff $@"

if [ "${USE_DOCKER:-true}" = "true" ]; then
    docker compose exec -T app $CMD
else
    $CMD
fi
