#!/usr/bin/env bash
set -euo pipefail

echo "[bootstrap] Setting up Laravel storage permissions..."

chown -R www-data:www-data storage || true
chmod -R 755 storage || true

echo "[bootstrap] Laravel storage permissions set."
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

echo "[bootstrap] Completed cache build & permission setup."