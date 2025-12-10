#!/bin/bash
set -e

echo "🔄 Updating all services..."

# 1. Pull wszystkich obrazów
echo "📦 Pulling all images..."
docker compose --env-file .env -f docker-compose.prod.yml pull

# 2. Rolling update wszystkich serwisów
echo "🔄 Updating services..."
docker compose --env-file .env -f docker-compose.prod.yml up -d --remove-orphans

# 3. Poczekaj na startup
echo "⏳ Waiting 30s for startup..."
sleep 30

# 4. Sprawdź status
echo "✅ Status:"
docker compose --env-file .env -f docker-compose.prod.yml ps

# 5. Test
echo "🧪 Testing..."
curl -k -I https://snipnote.pl/ 2>&1 | head -1

echo "✨ Update complete!"

