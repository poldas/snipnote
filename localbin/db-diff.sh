#!/bin/bash
# Generates a new migration by diffing the Doctrine entities against the database schema.
echo "🔍 Generating new migration based on entity changes..."
docker compose exec app php bin/console make:migration
