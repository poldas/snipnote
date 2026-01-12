#!/bin/bash
echo "🔍 Running PHPStan Static Analysis..."
docker compose exec app vendor/bin/phpstan analyse -c phpstan.neon "$@"
