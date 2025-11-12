# ChiliOil Backend - Docker (Shared Infrastructure Mode)

This project uses a shared development infrastructure located in `../local-environtment` providing MySQL, Redis, Nginx, Mailhog, and phpMyAdmin. This directory only defines project-specific runtime containers: PHP-FPM and Queue workers.

## Overview

Services defined here (in this project):
- **php** (PHP-FPM + Composer + Artisan)
- **queue** (Laravel queue worker)

Shared services (from `local-environtment/docker-compose.yml`):
- **nginx** (serves all projects on port 80, configured via virtual hosts)
- **mysql** (host: `mysql`, port: 3306)
- **redis** (host: `redis`, port: 6379)
- **mailhog** (SMTP: 1025, UI: 8025)
- **phpmyadmin** (port: 8080)

All containers join the external Docker network `dev-network`.

## Prerequisites

1. Start shared infrastructure (includes Nginx, MySQL, Redis, etc.):
```bash
cd ../local-environtment
docker-compose up -d
```

2. Ensure external network exists (auto-created by bringing infra up):
```bash
docker network ls | grep dev-network
```

3. Verify Nginx configuration exists for this project:
```bash
ls ../local-environtment/nginx/conf.d/chilioil.conf
```

## Environment Setup

Copy `.env.example` to `.env` if you haven't:
```bash
cp .env.example .env
```

Edit database/redis/mail settings for Docker:
```env
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=chilioil
DB_USERNAME=root
DB_PASSWORD=root

QUEUE_CONNECTION=redis
REDIS_HOST=redis
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=mailhog
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="noreply@example.test"
MAIL_FROM_NAME="ChiliOil"
```

## Build & Start

```bash
docker-compose up -d --build
```

Access the application at: **http://localhost** (served by shared Nginx on port 80)

To view logs or check if your project is running:
```bash
docker-compose logs -f php
docker logs dev-nginx  # Nginx runs in local-environtment
```

## First-Time Initialization

```bash
docker-compose exec php composer install
docker-compose exec php php artisan key:generate
docker-compose exec php php artisan jwt:secret
docker-compose exec php php artisan migrate --seed
```

## Common Commands

```bash
# Rebuild
docker-compose build --no-cache

# Start / Stop
docker-compose up -d
docker-compose down

# Logs (project services)
docker-compose logs -f php
docker-compose logs -f queue

# Nginx logs (shared service)
docker logs -f dev-nginx

# Artisan
docker-compose exec php php artisan config:cache

# Composer
docker-compose exec php composer require vendor/package

# Queue restart
docker-compose restart queue

# Restart Nginx (after config changes)
cd ../local-environtment
docker-compose restart nginx
```

## Helper Script

Use the provided `dev.sh` script for common tasks:
```bash
./dev.sh up          # Start project containers
./dev.sh init        # Full initial setup
./dev.sh artisan migrate
./dev.sh composer install
./dev.sh logs php
./dev.sh bash        # Shell into PHP container
```

Run `./dev.sh` without arguments to see all available commands.

## Xdebug (Optional)

Enable by passing build arg or env:
```bash
INSTALL_XDEBUG=true docker-compose build php
```
Update IDE to connect to port 9003 (default Xdebug 3) and set path mapping: host project root -> `/var/www`.

## Folder Mounts

- Source code bind-mounted, reflecting live changes.
- `docker/php/local.ini` adds project-specific PHP overrides.

## Healthchecks

- php: simple `php -v`
- queue relies on php build and will restart automatically if command exits.

## Queue Worker Tuning

Adjust command in `docker-compose.yml`:
```yaml
command: php artisan queue:work --sleep=3 --tries=3 --max-time=3600
```
Consider using `horizon` for advanced monitoring (can add a service later).

## Updating Dependencies

```bash
docker-compose exec php composer update
```

After updating, clear caches:
```bash
docker-compose exec php php artisan optimize:clear
```

## Permissions Fix (if needed)

```bash
docker-compose exec php bash -c "chown -R www-data:www-data storage bootstrap/cache && chmod -R 775 storage bootstrap/cache"
```

## Mail Testing

Visit Mailhog UI at: http://localhost:8025

## phpMyAdmin

Visit: http://localhost:8080 (Host: mysql, User: root, Pass: root)

## Adding Another Project

New project docker-compose should declare:
```yaml
networks:
  dev-network:
    external: true
```
And use hosts `mysql`, `redis`, `mailhog`.

## Troubleshooting

1. Network missing: ensure shared infra started.
2. MySQL access denied: verify credentials in `.env` match root or created user.
3. 502 Bad Gateway: check php container healthy: `docker-compose ps`.
4. Queue not processing: ensure `QUEUE_CONNECTION=redis` and redis is reachable.

### JWT secret is not set / `error: secret not set`
If you see errors like `The jwt secret is not set` or similar when authenticating:
1. Ensure `.env` exists (copy from `.env.example`).
2. Generate the secret (inside the php container or locally):

```bash
# Inside container
docker compose exec php php artisan jwt:secret

# Or locally then rebuild image if baking into production
php artisan jwt:secret
```
This command writes a random `JWT_SECRET=` value to your `.env` file.
3. Commit `.env.example` (never commit the actual `.env`) – it now includes a `JWT_SECRET=` placeholder for clarity.
4. For production runtime, pass the secret via environment variable instead of baking it into the image:

```bash
docker run --env-file .env -p 9000:9000 chilioil-app:prod
# or explicitly
docker run -e JWT_SECRET="$(openssl rand -hex 64)" -e APP_KEY="base64:..." chilioil-app:prod
```
5. After setting the secret, clear cached config if previously cached:
```bash
docker compose exec php php artisan config:clear
```

Secret rotation tip: To rotate, set a new `JWT_SECRET`, then invalidate old tokens by enabling blacklist (already enabled) and optionally clearing the blacklist store.

## Cleanup

```bash
docker-compose down --remove-orphans
```

(Shared infra remains running.)

## Production Notes

This dev setup is not production-hardened. For production:
- Separate image stages (build, runtime)
- Use non-root db user
- Add opcache config
- Disable xdebug
- Harden nginx headers
- Use secrets manager for sensitive env values
