#!/bin/sh
set -e

cd /var/www/html

# Ensure storage & cache dirs exist and are writable by www-data
# (volumes may override permissions set during docker build)
mkdir -p storage/logs storage/framework/sessions storage/framework/views storage/framework/cache bootstrap/cache database
chown -R www-data:www-data storage bootstrap/cache database

# All config comes from Dokploy environment variables — no .env file
rm -f .env
rm -f bootstrap/cache/config.php

# Map DB_LINK to DB_URL for Laravel (config/database.php reads DB_URL)
# Auto-detect DB_CONNECTION from URL scheme (mysql://, pgsql://, mariadb://, sqlite://)
if [ -n "$DB_LINK" ]; then
    export DB_URL="$DB_LINK"
    SCHEME=$(echo "$DB_LINK" | sed -n 's|^\([a-z]*\)://.*|\1|p')
    case "$SCHEME" in
        mysql)    export DB_CONNECTION=mysql ;;
        pgsql|postgres|postgresql) export DB_CONNECTION=pgsql ;;
        mariadb)  export DB_CONNECTION=mariadb ;;
        sqlite)   export DB_CONNECTION=sqlite ;;
        *)        export DB_CONNECTION=mysql ;;
    esac
fi

# Create SQLite database if using sqlite and it doesn't exist
if [ "$DB_CONNECTION" = "sqlite" ]; then
    DB_PATH="${DB_DATABASE:-/var/www/html/database/database.sqlite}"
    if [ ! -f "$DB_PATH" ]; then
        touch "$DB_PATH"
        chown www-data:www-data "$DB_PATH"
    fi
fi

# Generate APP_KEY if not set
if [ -z "$APP_KEY" ]; then
    php artisan key:generate --force
fi

# Debug: print DB config so we can verify in Dokploy logs
echo "=== DB Config ==="
echo "DB_CONNECTION=$DB_CONNECTION"
echo "DB_HOST=$DB_HOST"
echo "DB_PORT=$DB_PORT"
echo "DB_DATABASE=$DB_DATABASE"
echo "DB_USERNAME=$DB_USERNAME"
echo "DB_LINK set: $([ -n "$DB_LINK" ] && echo 'yes (detected driver: '"$DB_CONNECTION"')' || echo 'no')"
echo "================="

# Run migrations (don't let failure prevent container from starting)
php artisan migrate --force || echo "WARNING: Migration failed — check DB connection"

# Cache config from environment variables
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

# Start supervisor (nginx + php-fpm + queue worker + scheduler)
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
