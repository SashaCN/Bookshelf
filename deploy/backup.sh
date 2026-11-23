#!/usr/bin/env bash
# Dumps the database to a compressed file and removes dumps older than 14 days.
# bootstrap-server.sh installs a cron job for it (every night at 03:00); it can also be run by hand.
# The dumps stay on this machine: copy them off the host (see docs/deploy.md).
set -euo pipefail

cd /opt/bookshelf
BACKUP_DIR="${BACKUP_DIR:-/opt/bookshelf/backups}"
mkdir -p "$BACKUP_DIR"

FILE="$BACKUP_DIR/bookshelf-$(date +%F-%H%M).sql.gz"

docker compose --env-file production.env -f docker-compose.prod.yml exec -T mysql \
    sh -c 'mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction --no-tablespaces bookshelf' \
    | gzip > "$FILE"

find "$BACKUP_DIR" -name 'bookshelf-*.sql.gz' -mtime +14 -delete
echo "Saved $FILE"
