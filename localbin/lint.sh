#!/bin/bash
set -e

echo "🔒 Running Composer Security Audit..."
docker compose exec app composer audit

echo "🎨 Checking Code Style..."
docker compose exec app vendor/bin/php-cs-fixer fix --dry-run --diff

echo "🧠 Checking Static Analysis..."
./localbin/phpstan.sh
