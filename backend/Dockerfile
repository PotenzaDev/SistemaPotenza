FROM php:8.3-fpm
RUN apt-get update && apt-get install -y \
    git curl libpq-dev libzip-dev zip unzip \
    && docker-php-ext-install pdo pdo_pgsql zip \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /var/www
COPY composer.json composer.lock ./
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN composer install --no-dev --optimize-autoloader --no-scripts
COPY . .
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views \
    storage/logs \
    bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache
EXPOSE 9000
CMD ["php-fpm"]
