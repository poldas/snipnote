#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd -- "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

APP_ID="$(docker compose ps -q app || true)"
if [ -z "$APP_ID" ]; then
    echo "App container not running. Starting app for asset build..."
    docker compose up -d app
fi

echo "Cleaning up old assets..."
rm -rf public/assets/*

echo "Building frontend (Node in disposable container)..."
docker run --rm -v "$ROOT":/app -w /app node:20-alpine sh -c "npm ci && npm run tailwind:build"

echo "Installing importmap assets..."
docker compose exec -T app php bin/console importmap:install

echo "Compiling asset map..."
docker compose exec -T app php bin/console asset-map:compile

echo "Assets ready."