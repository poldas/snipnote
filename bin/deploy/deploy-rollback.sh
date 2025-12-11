#!/usr/bin/env bash
set -euo pipefail

# Roll back the app service to a specific image tag (e.g., sha-abcdef or prod).
# Usage:
#   APP_IMAGE_TAG=sha-abcdef ./rollback.sh
#   (Optionally set APP_IMAGE to override registry/image; defaults to ghcr.io/poldas/snipnote)
#
# Assumes:
#   - env.prod exists in repo root with required secrets.
#   - docker-compose.prod.yml present.
#   - User has permissions to run docker (or use sudo docker ...).

REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$REPO_DIR"

APP_IMAGE="${APP_IMAGE:-ghcr.io/poldas/snipnote}"
APP_IMAGE_TAG="${APP_IMAGE_TAG:-prod}"
ENV_FILE="${ENV_FILE:-env.prod}"

if [[ ! -f "$ENV_FILE" ]]; then
  echo "Env file '$ENV_FILE' not found. Create it from env.prod.example and fill secrets." >&2
  exit 1
fi

echo "==> Using image: ${APP_IMAGE}:${APP_IMAGE_TAG}"
echo "==> Env file: ${ENV_FILE}"

# Pull target tag
docker compose --env-file "$ENV_FILE" -f docker-compose.prod.yml pull app

# Update/ensure env overrides for image/tag if provided via environment
export APP_IMAGE APP_IMAGE_TAG

# Restart only the app service with the pulled image
docker compose --env-file "$ENV_FILE" -f docker-compose.prod.yml up -d app

echo "==> Rollback requested to ${APP_IMAGE}:${APP_IMAGE_TAG} completed. Verify with:"
echo "    docker compose --env-file $ENV_FILE -f docker-compose.prod.yml ps"
echo "    curl -I https://snipnote.pl"

