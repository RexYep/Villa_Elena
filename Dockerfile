# --- Stage 1: build Vite/front-end assets -----------------------------
FROM node:20-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources resources
COPY vite.config.js ./
COPY public public
RUN npm run build

# --- Stage 2: PHP dependencies -----------------------------------------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist
COPY . .
RUN composer dump-autoload --optimize --no-dev

# --- Stage 3: runtime image (php-fpm + nginx + supervisord) ------------
FROM php:8.2-fpm-alpine

RUN apk add --no-cache nginx supervisor gettext libpng libjpeg-turbo libwebp \
        libzip icu-dev oniguruma-dev freetype libpng-dev libjpeg-turbo-dev libwebp-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) pdo_mysql mbstring bcmath exif pcntl gd zip intl \
    && apk del libpng-dev libjpeg-turbo-dev libwebp-dev icu-dev oniguruma-dev

WORKDIR /var/www/html

COPY --from=vendor /app /var/www/html
COPY --from=assets /app/public/build /var/www/html/public/build

COPY docker/nginx.conf.template /etc/nginx/http.d/default.conf.template
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

RUN mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 10000
CMD ["/usr/local/bin/start.sh"]
