#!/bin/bash
# Checks PHP syntax for errors.
# Set USE_DOCKER=false to run locally (e.g. in CI)

echo "🐘 Checking PHP Syntax..."

if [ "${USE_DOCKER:-true}" = "true" ]; then
    # In Docker, we can run a shell command to find and lint
    docker compose exec -T app sh -c "find src tests -name '*.php' -print0 | xargs -0 -n1 php -l"
else
    # Locally
    find src tests -name '*.php' -print0 | xargs -0 -n1 php -l
fi
