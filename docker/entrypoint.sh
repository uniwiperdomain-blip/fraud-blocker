#!/bin/sh
set -e

# Map DB_LINK to DATABASE_URL for Laravel (Dokploy compatibility)
if [ -n "$DB_LINK" ]; then
    export DATABASE_URL="$DB_LINK"
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

# Run migrations
php artisan migrate --force

# Cache config and routes for production
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Start supervisor (nginx + php-fpm + queue + scheduler)
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
