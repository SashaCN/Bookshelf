#!/usr/bin/env bash
# Dumps the database to a compressed file and removes dumps older than 14 days.
# Run it by hand or from cron, e.g. every night at 03:00:
#   0 3 * * * /opt/bookshelf/backup.sh
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
