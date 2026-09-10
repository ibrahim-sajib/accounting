FROM php:8.3-fpm-alpine

# System dependencies
RUN apk add --no-cache \
    oniguruma-dev \
    libzip-dev \
    unzip \
    curl \
    && docker-php-ext-install \
        pdo_mysql \
        mbstring \
        opcache \
        bcmath \
        fileinfo \
        zip

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy application
WORKDIR /var/www/html

COPY --chown=www-data:www-data . /var/www/html

RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

COPY docker/app/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

USER www-data

EXPOSE 9000

ENTRYPOINT ["entrypoint"]