#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd -- "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

# Load .env if present to pick up POSTGRES_* overrides
if [ -f ".env" ]; then
  set -a
  # shellcheck source=/dev/null
  source ".env"
  set +a
fi

USER="${POSTGRES_USER:-postgres}"
DB="${POSTGRES_DB:-postgres}"

docker compose exec -it database psql -U "$USER" -d "$DB"
