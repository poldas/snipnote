#!/bin/bash
if [ -z "$1" ]; then
    echo "Usage: ./localbin/db-query.sh \"SELECT * FROM users\""
    exit 1
fi
docker compose exec app php bin/console doctrine:dbal:run-sql "$1"
