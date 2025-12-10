#!/bin/bash
set -e

echo "🔄 Updating application..."

# 1. Sprawdź obecny obraz
echo "📋 Current image:"
docker inspect snipnote-app-1 --format='Image: {{.Image}}' 2>/dev/null | cut -c1-70 || echo "Container not running"

# 2. Pull najnowszego obrazu aplikacji
echo ""
echo "📦 Pulling latest image..."
docker compose --env-file .env -f docker-compose.prod.yml pull app

# 3. Sprawdź czy obraz się zmienił
echo ""
echo "📋 New image info:"
docker inspect ghcr.io/poldas/snipnote:prod --format='Digest: {{index .RepoDigests 0}}' 2>/dev/null | cut -c1-80 || echo "Image info unavailable"
docker inspect ghcr.io/poldas/snipnote:prod --format='Created: {{.Created}}' 2>/dev/null || echo ""

# 4. Restart TYLKO kontenera aplikacji (Traefik i sieci pozostają nietknięte)
echo ""
echo "🔄 Restarting app container..."
docker compose --env-file .env -f docker-compose.prod.yml up -d app --force-recreate --no-deps

# 5. Poczekaj na healthcheck
echo "⏳ Waiting for app to be healthy..."
timeout 60 sh -c 'until [ "$(docker inspect snipnote-app-1 --format "{{.State.Health.Status}}")" = "healthy" ]; do sleep 2; done' || echo "⚠️  Warning: Timeout waiting for healthy status"

# 6. Sprawdź status
echo ""
echo "✅ Status:"
docker compose --env-file .env -f docker-compose.prod.yml ps app

# 7. Test
echo ""
echo "🧪 Testing..."
curl -k -I https://snipnote.pl/ 2>&1 | head -1

echo ""
echo "✨ Update complete!"

