#!/bin/sh
set -e

# Settings come from the container environment. Caching them (and routes, events, views) makes every
# request faster and means no environment file is read at all.
php artisan optimize

exec "$@"
