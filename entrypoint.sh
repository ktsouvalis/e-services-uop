#!/usr/bin/env bash
set -euo pipefail

APP_DIR=${APP_WORKDIR:-/var/www/html/dgu-services}
cd "$APP_DIR"
echo "[entrypoint] Starting container as $(id -u):$(id -g) in $APP_DIR"

if [ "${BOOTSTRAP:-0}" = "1" ]; then
  chown -R www-data:www-data ${APP_DIR}/storage
  chmod -R 755 ${APP_DIR}/storage
  
  php artisan migrate || true
  php artisan db:seed || true

  npm install
  npm run build
fi

echo "[entrypoint] Handing off to CMD: $*"
exec "$@"
