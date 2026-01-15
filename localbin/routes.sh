#!/bin/bash
echo "🔍 Listing application routes..."
docker compose exec app php bin/console debug:router
