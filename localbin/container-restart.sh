#!/bin/bash
SERVICE=${1:-app}
echo "🔄 Restarting service: $SERVICE..."
docker compose restart "$SERVICE"
