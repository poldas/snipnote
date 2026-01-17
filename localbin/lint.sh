#!/bin/bash
set -e

echo "🔒 Running Composer Security Audit..."
# Security audit is fast and usually doesn't need a separate script
docker compose exec -T app composer audit

./localbin/lint-syntax.sh
./localbin/lint-style.sh
./localbin/phpstan.sh
