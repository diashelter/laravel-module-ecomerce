#!/bin/sh
set -e

cd /var/www/backend

# Only the "api" container installs dependencies; the worker waits for them.
if [ "${INSTALL_DEPENDENCIES:-false}" = "true" ] && [ ! -f vendor/autoload.php ]; then
    echo "Installing Composer dependencies..."
    composer install --no-interaction --prefer-dist
fi

until [ -f vendor/autoload.php ]; do
    echo "Waiting for vendor/autoload.php..."
    sleep 2
done

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache 2>/dev/null || true

exec "$@"
