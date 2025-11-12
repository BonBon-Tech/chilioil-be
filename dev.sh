#!/bin/bash

# ChiliOil Project Helper Script (Shared Infra Mode)
set -e
SERVICE=${2:-php}

function header(){ echo -e "\n== $1 =="; }

case "$1" in
  up)
    header "Starting project containers"
    docker-compose up -d --build
    ;;
  down)
    header "Stopping project containers"
    docker-compose down
    ;;
  restart)
    header "Restarting project containers"
    docker-compose restart
    ;;
  logs)
    header "Logs: $SERVICE"
    docker-compose logs -f $SERVICE
    ;;
  bash)
    header "Shell into $SERVICE"
    docker-compose exec $SERVICE bash
    ;;
  artisan)
    shift
    docker-compose exec php php artisan "$@"
    ;;
  composer)
    shift
    docker-compose exec php composer "$@"
    ;;
  init)
    header "Initial project setup"
    docker-compose up -d --build
    docker-compose exec php composer install
    docker-compose exec php php artisan key:generate
    docker-compose exec php php artisan jwt:secret || true
    docker-compose exec php php artisan migrate --seed
    ;;
  perms)
    header "Fixing permissions"
    docker-compose exec php bash -c "chown -R www-data:www-data storage bootstrap/cache && chmod -R 775 storage bootstrap/cache"
    ;;
  queue-restart)
    header "Restart queue worker"
    docker-compose restart queue
    ;;
  optimize)
    header "Optimize caches"
    docker-compose exec php php artisan optimize
    ;;
  clear)
    header "Clearing all caches"
    docker-compose exec php php artisan optimize:clear
    ;;
  *)
    echo "Usage: ./dev.sh <command> [service]"
    echo ""
    echo "NOTE: Nginx runs in local-environtment shared infrastructure."
    echo "      Start it with: cd ../local-environtment && docker-compose up -d"
    echo ""
    echo "Commands:"
    echo "  up              Start project containers (PHP, Queue)"
    echo "  down            Stop project containers"
    echo "  restart         Restart containers"
    echo "  logs [service]  Tail logs (php|queue)"
    echo "  bash [service]  Shell into service"
    echo "  artisan <cmd>   Run artisan command"
    echo "  composer <cmd>  Run composer command"
    echo "  init            Full initial setup"
    echo "  perms           Fix storage/cache permissions"
    echo "  queue-restart   Restart queue worker"
    echo "  optimize        Cache config/routes/views"
    echo "  clear           Clear all caches"
    exit 0
    ;;
esac

