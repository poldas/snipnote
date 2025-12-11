#!/bin/bash
set -e

echo "🧹 Cleaning up..."

# 1. Zatrzymaj i usuń wszystkie kontenery i volume (zachowuje database_data)
docker compose --env-file .env -f docker-compose.prod.yml down

# 2. Usuń nieużywane sieci
docker network prune -f

# 3. Usuń nieużywane obrazy (opcjonalne)
docker image prune -f

echo "📦 Pulling latest images..."

# 4. Pobierz najnowsze obrazy z GitHub Container Registry
docker compose --env-file .env -f docker-compose.prod.yml pull

# 5. Pokaż informacje o pobranym obrazie aplikacji
echo ""
echo "📋 Application image info:"
docker inspect ghcr.io/poldas/snipnote:prod --format='{{.RepoDigests}}' 2>/dev/null | head -1 || echo "Image not found locally (will be pulled)"
docker inspect ghcr.io/poldas/snipnote:prod --format='Created: {{.Created}}' 2>/dev/null || echo ""

echo ""
echo "🚀 Starting fresh..."

# 6. Uruchom wszystkie serwisy
docker compose --env-file .env -f docker-compose.prod.yml up -d

# 7. Poczekaj na startup
echo "⏳ Waiting for startup (30s)..."
sleep 30

# 8. Sprawdź status
echo "✅ Status check:"
docker compose --env-file .env -f docker-compose.prod.yml ps

echo ""
echo "🔍 Testing HTTPS:"
curl -k -I https://snipnote.pl/ 2>&1 | head -1

echo ""
echo "📊 Healthcheck status:"
docker logs snipnote-traefik-1 2>&1 | grep -i healthcheck | tail -5 || echo "No healthcheck issues found (good!)"

echo ""
echo "✨ Done!"

