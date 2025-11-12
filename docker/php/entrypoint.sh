#!/usr/bin/env bash
set -euo pipefail

cd /var/www

if [ ! -f .env ]; then
  echo "[entrypoint] .env not found, copying from .env.example" >&2
  cp .env.example .env
fi

# Ensure APP_KEY
if ! grep -q '^APP_KEY=base64:' .env || grep -q '^APP_KEY=\s*$' .env; then
  echo "[entrypoint] Generating APP_KEY" >&2
  php artisan key:generate --force --quiet || true
fi

# Ensure JWT_SECRET
if ! grep -q '^JWT_SECRET=' .env || grep -q '^JWT_SECRET=\s*$' .env; then
  echo "[entrypoint] Generating JWT_SECRET" >&2
  php artisan jwt:secret --force --quiet || true
elif grep -q '^JWT_SECRET=\s*$' .env; then
  echo "[entrypoint] JWT_SECRET blank, generating" >&2
  php artisan jwt:secret --force --quiet || true
fi

# Run pending migrations (optional: skip if you prefer manual)
if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
  echo "[entrypoint] Running migrations" >&2
  php artisan migrate --force --quiet || true
fi

# Cache config/routes/views in non-local env
if [ "${APP_ENV:-local}" != "local" ]; then
  php artisan config:cache || true
  php artisan route:cache || true
  php artisan view:cache || true
fi

echo "[entrypoint] Starting php-fpm" >&2
exec php-fpm

