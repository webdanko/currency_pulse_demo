# syntax=docker/dockerfile:1
FROM php:8.4-fpm-alpine

# System + PHP extensions the app actually uses (MySQL + Redis).
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && apk add --no-cache icu-dev git \
    && docker-php-ext-install pdo_mysql bcmath opcache pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . .

# Build-time install so the image is runnable on its own; the entrypoint
# re-installs if a bind mount overlays vendor during local development.
RUN composer install --no-interaction --prefer-dist --optimize-autoloader --no-scripts || true

COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]
