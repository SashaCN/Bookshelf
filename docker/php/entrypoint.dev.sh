#!/bin/sh
set -e

# First start on a fresh clone: install PHP dependencies into the bind-mounted backend/.
if [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --prefer-dist
fi

exec "$@"
