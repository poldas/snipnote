#!/bin/bash
LOG_FILE="var/log/dev.log"
if [ "$1" == "test" ]; then
    LOG_FILE="var/log/test.log"
fi

echo "📖 Tailing $LOG_FILE..."
docker compose exec app tail -f "$LOG_FILE"
