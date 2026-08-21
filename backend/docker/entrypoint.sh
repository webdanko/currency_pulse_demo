#!/bin/sh
set -e
cd /var/www/html
ROLE="$1"

install_deps() {
  if [ ! -d vendor ]; then
    echo "==> Installing composer dependencies..."
    composer install --no-interaction --prefer-dist --optimize-autoloader
  fi
}

if [ "$ROLE" = "php-fpm" ]; then
  # The web/app container owns first-run bootstrap.
  install_deps

  [ -f .env ] || cp .env.example .env
  php artisan key:generate --force >/dev/null 2>&1 || true

  echo "==> Waiting for database, then migrating..."
  tries=0
  until php artisan migrate --force 2>/dev/null; do
    tries=$((tries + 1))
    if [ "$tries" -ge 30 ]; then
      echo "!! Database not reachable after 30 tries, continuing anyway."
      break
    fi
    sleep 2
  done

  php artisan db:seed --force >/dev/null 2>&1 || true
  php artisan config:clear >/dev/null 2>&1 || true
else
  # workers/scheduler/reverb: wait until the app container has prepared the code.
  echo "==> [$ROLE] waiting for app bootstrap (vendor + .env)..."
  i=0
  until { [ -d vendor ] && [ -f .env ]; }; do
    i=$((i + 1))
    [ "$i" -ge 60 ] && break
    sleep 2
  done
fi

exec "$@"
