#!/bin/bash
# Runs PHP-CS-Fixer to automatically fix coding standard violations.
# Usage: ./localbin/fix.sh [path]
echo "🛠️  Running PHP CS Fixer..."
docker compose exec app vendor/bin/php-cs-fixer fix "$@"
