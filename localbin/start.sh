#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd -- "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

ENV_FILE="$ROOT/.env"
if [ ! -f "$ENV_FILE" ]; then
    if [ -f "$ROOT/local.env.example" ]; then
        cp "$ROOT/local.env.example" "$ENV_FILE"
        echo "Created .env from local.env.example"
    else
        echo "Missing .env and local.env.example – aborting."
        exit 1
    fi
fi

echo "Building assets..."
"$ROOT/localbin/assets.sh"

echo "Starting Docker stack (logs attached)..."
docker compose up
