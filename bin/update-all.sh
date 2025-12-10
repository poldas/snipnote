#!/bin/bash
set -e

echo "🔄 Updating all services..."

# 1. Pokaż obecne obrazy
echo "📋 Current images:"
docker compose --env-file .env -f docker-compose.prod.yml images

# 2. Pull wszystkich obrazów
echo ""
echo "📦 Pulling all images..."
docker compose --env-file .env -f docker-compose.prod.yml pull

# 3. Pokaż nowe obrazy
echo ""
echo "📋 New images:"
docker inspect ghcr.io/poldas/snipnote:prod --format='App: {{index .RepoDigests 0}}' 2>/dev/null | cut -c1-80 || echo "App image info unavailable"
docker inspect traefik:v3.6 --format='Traefik: {{index .RepoDigests 0}}' 2>/dev/null | cut -c1-80 || echo "Traefik image info unavailable"
docker inspect postgres:16-alpine --format='Postgres: {{index .RepoDigests 0}}' 2>/dev/null | cut -c1-80 || echo "Postgres image info unavailable"

# 4. Rolling update wszystkich serwisów
echo ""
echo "🔄 Updating services..."
docker compose --env-file .env -f docker-compose.prod.yml up -d --remove-orphans

# 5. Poczekaj na startup
echo "⏳ Waiting 30s for startup..."
sleep 30

# 6. Sprawdź status
echo ""
echo "✅ Status:"
docker compose --env-file .env -f docker-compose.prod.yml ps

# 7. Test
echo ""
echo "🧪 Testing..."
curl -k -I https://snipnote.pl/ 2>&1 | head -1

echo ""
echo "✨ Update complete!"

