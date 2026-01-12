#!/bin/bash
echo "🔄 Running database migrations..."
docker compose exec app php bin/console doctrine:migrations:migrate --no-interaction
