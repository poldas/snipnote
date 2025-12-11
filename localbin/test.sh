#!/bin/bash
docker compose exec app bash -c "./bin/phpunit --display-phpunit-notice $*"
