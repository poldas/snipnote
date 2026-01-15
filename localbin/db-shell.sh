#!/bin/bash
echo "🐘 Connecting to snipnote database..."
docker compose exec database psql -U app_user -d snipnote
