#!/usr/bin/env bash
# Runs on the server (copied there by the deploy workflow): pulls the new images, restarts the
# stack and applies migrations.
#
# The image repository and tag are read from standard input, one per line (the workflow pipes
# them in), or given as arguments for a manual rollout:
#
#   bash /opt/bookshelf/rollout.sh ghcr.io/sashacn <commit-sha>
set -euo pipefail

if [ "$#" -ge 2 ]; then
    IMAGE_REPO="$1"
    IMAGE_TAG="$2"
else
    read -r IMAGE_REPO
    read -r IMAGE_TAG
fi

# Only plain registry paths and tags are accepted, as the values end up in a docker command.
[[ "$IMAGE_REPO" =~ ^[a-z0-9._/-]+$ ]] || { echo "Invalid image repository: '$IMAGE_REPO'" >&2; exit 1; }
[[ "$IMAGE_TAG" =~ ^[A-Za-z0-9._-]+$ ]] || { echo "Invalid image tag: '$IMAGE_TAG'" >&2; exit 1; }

# The registry login only lives as long as this rollout.
trap 'docker logout ghcr.io > /dev/null 2>&1 || true' EXIT

cd /opt/bookshelf
export IMAGE_REPO IMAGE_TAG
compose="docker compose --env-file production.env -f docker-compose.prod.yml"

$compose pull
$compose up -d --remove-orphans
$compose exec -T app php artisan migrate --force < /dev/null
docker image prune -f
