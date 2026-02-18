FROM php:8.4-fpm-alpine AS base

RUN apk add --no-cache \
    nginx \
    supervisor \
    sqlite \
    sqlite-dev \
    libpng-dev \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    curl-dev \
    git \
    zip \
    unzip \
    nodejs \
    npm

RUN docker-php-ext-install \
    pdo_mysql \
    pdo_sqlite \
    mbstring \
    gd \
    zip \
    intl \
    bcmath \
    opcache \
    pcntl

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# -------------------------------------------------------------------
# Dependencies stage
# -------------------------------------------------------------------
FROM base AS dependencies

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

COPY package.json package-lock.json* ./
RUN npm ci

# -------------------------------------------------------------------
# Build stage (frontend assets)
# -------------------------------------------------------------------
FROM dependencies AS build

COPY . .
RUN composer dump-autoload --optimize
RUN npm run build

# -------------------------------------------------------------------
# Production image
# -------------------------------------------------------------------
FROM base AS production

# PHP production config
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY docker/php.ini /usr/local/etc/php/conf.d/99-app.ini
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

COPY --from=build /var/www/html /var/www/html

RUN mkdir -p /var/www/html/storage/logs \
    /var/www/html/storage/framework/sessions \
    /var/www/html/storage/framework/views \
    /var/www/html/storage/framework/cache \
    /var/www/html/bootstrap/cache \
    /var/www/html/database

RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database

RUN rm -rf /var/www/html/node_modules /var/www/html/.git

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
