#!/bin/bash
# Runs PHPUnit tests inside the 'app' container.
# Usage: ./localbin/test.sh [phpunit-options]
docker compose exec app bash -c "./bin/phpunit --display-phpunit-notice $*"
