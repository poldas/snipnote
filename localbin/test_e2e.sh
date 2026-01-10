#!/usr/bin/env bash
set -euo pipefail

echo "🚀 Preparing E2E test environment..."

# Set environment variables
export E2E_BASE_URL=http://localhost:8080
export E2E_WEB_SERVER_CMD="./localbin/start.sh"

# Start Docker environment if not running
if ! docker compose ps | grep -q "app"; then
    echo "🐳 Starting Docker containers..."

    # Build assets first (from start.sh logic)
    echo "Building assets..."
    ./localbin/assets.sh

    # Start containers in detached mode
    echo "Starting Docker stack..."
    docker compose up -d

    # Give containers time to start
    echo "⏳ Waiting for containers to start..."
    sleep 5
fi

# Wait for containers to be healthy
echo "⏳ Waiting for services to be ready..."
docker compose exec -T app timeout 30 bash -c 'until php bin/console doctrine:query:dql "SELECT COUNT(u) FROM App\Entity\User u" --env=test >/dev/null 2>&1; do sleep 2; done' || true

# Reset test database
echo "🗄️ Preparing test database..."
docker compose exec -T app php bin/console doctrine:database:drop --force --if-exists --env=test || true
docker compose exec -T app php bin/console doctrine:database:create --if-not-exists --env=test
docker compose exec -T app php bin/console doctrine:migrations:migrate --no-interaction --env=test

echo "✅ Database ready, starting E2E tests..."
npm run e2e
