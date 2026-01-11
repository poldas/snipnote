#!/usr/bin/env bash
set -euo pipefail

echo "🚀 Preparing E2E test environment..."

# Set environment variables
export E2E_BASE_URL=http://localhost:8080

# Start Docker environment if not running
if ! docker compose ps | grep -q "app"; then
    echo "🐳 Starting Docker containers..."

    # Build assets first
    echo "Building assets..."
    ./localbin/assets.sh

    # Start containers in detached mode
    echo "Starting Docker stack..."
    docker compose up -d

    # Give containers time to start
    echo "⏳ Waiting for containers to start..."
    sleep 5
fi

# Wait for containers to be healthy (check DB connection from inside app)
echo "⏳ Waiting for services to be ready..."
docker compose exec -T app timeout 30 bash -c 'until php bin/console dbal:run-sql "SELECT 1" >/dev/null 2>&1; do sleep 2; done' || true

echo "🔄 Applying database migrations..."
docker compose exec -T app php bin/console doctrine:migrations:migrate --no-interaction

echo "✅ Environment ready, starting E2E tests..."
npm run e2e