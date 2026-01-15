#!/bin/bash
echo "🔍 Running PHPStan (Level 6)..."
docker compose exec app vendor/bin/phpstan analyse -c phpstan.dist.neon --memory-limit=1G "$@"