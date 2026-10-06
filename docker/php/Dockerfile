# Shared image for the "api" (PHP-FPM) and "queue-worker" containers.
FROM php:8.5-fpm-alpine

RUN apk add --no-cache icu-libs libpq libzip postgresql-client \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev libpq-dev libzip-dev linux-headers \
    && docker-php-ext-install -j"$(nproc)" bcmath intl pcntl pdo_pgsql zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www/backend

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
