#!/bin/sh
# Wrap the existing production server command; do not use during image build.
set -eu

if [ "$#" -eq 0 ]; then
    echo 'Usage: sh scripts/start-container.sh <existing server command> [args...]' >&2
    exit 64
fi

cd "$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)"

mkdir -p bootstrap/cache storage/framework/cache/data storage/framework/sessions \
    storage/framework/views storage/logs

echo '[warehouse] Removing cached code/configuration from the previous build...'
# Separate commands propagate failures. optimize:clear also flushes shared app
# cache (including locks), which should not happen on each container restart.
php artisan config:clear --no-interaction
php artisan route:clear --no-interaction
php artisan event:clear --no-interaction
php artisan view:clear --no-interaction
php artisan filament:clear-cached-components --no-interaction
php artisan icons:clear --no-interaction

if [ "${WAREHOUSE_RUN_MIGRATIONS:-1}" = '1' ]; then
    echo '[warehouse] Applying pending database migrations...'
    php artisan migrate --force --no-interaction
fi

# Routes depend on storage/installed; leave them uncached so an image built
# without that persistent marker cannot lock the running app into setup routes.
echo '[warehouse] Ready; starting server...'
exec "$@"
