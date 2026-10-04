#!/bin/sh
set -e

# First start on a fresh clone: install PHP dependencies into the bind-mounted backend/.
if [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --prefer-dist
fi

# Settings come from docker-compose.yml. An empty file only stops phpdotenv from emitting
# a "file not found" warning in every test run on a fresh clone (the file is git-ignored).
[ -f .env ] || touch .env

exec "$@"
