#!/bin/bash
echo "🧹 Clearing Symfony cache..."
docker compose exec app php bin/console cache:clear
