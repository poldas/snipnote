#!/usr/bin/env bash
set -euo pipefail

echo "🧹 Cleaning E2E test environment..."

# Stop containers
echo "Stopping Docker containers..."
docker compose down --volumes --remove-orphans 2>/dev/null || true

# Clean sessions
echo "Cleaning PHP sessions..."
sudo rm -rf /tmp/sessions/ 2>/dev/null || true
mkdir -p /tmp/sessions
chmod 777 /tmp/sessions

# Clean browser cache
echo "Cleaning browser cache..."
rm -rf ~/.cache/playwright/ 2>/dev/null || true
rm -rf e2e/artifacts/ 2>/dev/null || true

# Clean test database if running
echo "Resetting test database..."
if docker compose ps | grep -q "app"; then
    docker compose exec -T app php bin/console doctrine:database:drop --force --if-exists --env=test 2>/dev/null || true
    docker compose exec -T app php bin/console doctrine:database:create --if-not-exists --env=test 2>/dev/null || true
    docker compose exec -T app php bin/console doctrine:migrations:migrate --no-interaction --env=test 2>/dev/null || true
fi

echo "✅ E2E environment cleaned successfully!"