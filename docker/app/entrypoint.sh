#!/bin/sh
set -e

# Ensure storage directories are writable.
chmod -R ug+rwX storage bootstrap/cache

# Wait for the database to accept connections.
if [ "$DB_HOST" ]; then
    echo "Waiting for MySQL at ${DB_HOST}:${DB_PORT:-3306} ..."
    i=0
    until php -r 'try { new PDO("mysql:host=".getenv("DB_HOST").";port=".(getenv("DB_PORT") ?: "3306"), getenv("DB_USERNAME"), getenv("DB_PASSWORD")); } catch (Throwable $e) { exit(1); }' 2>/dev/null; do
        i=$((i + 1))
        if [ "$i" -ge 60 ]; then
            echo "MySQL did not become ready in time."
            exit 1
        fi
        sleep 1
    done
    echo "MySQL is ready."
fi

# Run migrations (and optional seed) on startup so `docker compose up` is enough.
if [ "${DB_MIGRATE:-true}" != "false" ]; then
    php artisan migrate --force || true
fi

if [ "${DB_SEED:-false}" = "true" ]; then
    php artisan db:seed --force || true
fi

exec php-fpm