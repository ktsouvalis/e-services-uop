#!/usr/bin/env bash
set -euo pipefail

APP_DIR=${APP_WORKDIR:-/var/www/html/dgu-services}
cd "$APP_DIR"

echo "[entrypoint] Starting container as $(id -u):$(id -g) in $APP_DIR"

# Decide whether to run bootstrap tasks
if [ "${BOOTSTRAP:-0}" = "1" ]; then
    echo "[entrypoint] BOOTSTRAP=1 -> running artisan bootstrap script"
    
    echo "[bootstrap] Setting up Laravel storage permissions..."
    chown -R www-data:www-data storage || true
    chmod -R 755 storage || true
    echo "[bootstrap] Laravel storage permissions set."
    
    echo "[bootstrap] Applying migrations..."
    php artisan migrate --force || true
    echo "[bootstrap] Migrations applied."

    echo "[bootstrap] Seeding database..."
    php artisan db:seed --force || true
    echo "[bootstrap] Database seeded."

    echo "[bootstrap] Building Laravel caches..."
    php artisan config:clear || true
    php artisan route:clear || true
    php artisan view:clear || true
    php artisan event:clear || true
    php artisan package:discover || true
    php artisan config:cache || true
    php artisan route:cache || true
    php artisan view:cache || true
    php artisan event:cache || true
    echo "[bootstrap] Completed cache build."
else
  echo "[entrypoint] BOOTSTRAP disabled (BOOTSTRAP=${BOOTSTRAP:-0}); skipping bootstrap tasks"
fi

echo "[entrypoint] Handing off to CMD: $*"
exec "$@"
