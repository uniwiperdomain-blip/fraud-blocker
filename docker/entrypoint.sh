#!/bin/sh
set -e

cd /var/www/html

# All config comes from Dokploy environment variables — no .env file
rm -f .env
rm -f bootstrap/cache/config.php

# Map DB_LINK to DB_URL for Laravel (config/database.php reads DB_URL)
if [ -n "$DB_LINK" ]; then
    export DB_URL="$DB_LINK"
    export DB_CONNECTION=mysql
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
echo "DB_LINK set: $([ -n "$DB_LINK" ] && echo 'yes' || echo 'no')"
echo "================="

# Run migrations
php artisan migrate --force

# Cache config from environment variables
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Start supervisor (nginx + php-fpm + queue worker + scheduler)
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
