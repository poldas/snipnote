#!/bin/bash
set -e

echo "🔄 Updating application..."

# 1. Pull najnowszego obrazu aplikacji
echo "📦 Pulling latest image..."
docker compose --env-file .env -f docker-compose.prod.yml pull app

# 2. Restart TYLKO kontenera aplikacji (Traefik i sieci pozostają nietknięte)
echo "🔄 Restarting app container..."
docker compose --env-file .env -f docker-compose.prod.yml up -d app --force-recreate --no-deps

# 3. Poczekaj na healthcheck
echo "⏳ Waiting for app to be healthy..."
timeout 60 sh -c 'until [ "$(docker inspect snipnote-app-1 --format "{{.State.Health.Status}}")" = "healthy" ]; do sleep 2; done' || echo "⚠️  Warning: Timeout waiting for healthy status"

# 4. Sprawdź status
echo "✅ Status:"
docker compose --env-file .env -f docker-compose.prod.yml ps app

# 5. Test
echo "🧪 Testing..."
curl -k -I https://snipnote.pl/ 2>&1 | head -1

echo "✨ Update complete!"

