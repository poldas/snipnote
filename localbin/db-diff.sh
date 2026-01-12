#!/bin/bash
echo "🔍 Generating new migration based on entity changes..."
docker compose exec app php bin/console make:migration
