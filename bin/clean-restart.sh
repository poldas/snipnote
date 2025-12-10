#!/bin/bash
set -e

echo "🧹 Cleaning up..."

# 1. Zatrzymaj i usuń wszystkie kontenery i volume (zachowuje database_data)
docker compose --env-file .env -f docker-compose.prod.yml down

# 2. Usuń nieużywane sieci
docker network prune -f

# 3. Usuń nieużywane obrazy (opcjonalne)
docker image prune -f

echo "🚀 Starting fresh..."

# 4. Uruchom wszystkie serwisy
docker compose --env-file .env -f docker-compose.prod.yml up -d

# 5. Poczekaj na startup
echo "⏳ Waiting for startup (30s)..."
sleep 30

# 6. Sprawdź status
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

