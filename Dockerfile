# syntax=docker/dockerfile:1
FROM composer:2 AS dependencies
WORKDIR /app
COPY composer.json composer.lock ./
RUN --mount=type=secret,id=cloud_ca if [ -f /run/secrets/cloud_ca ]; then export COMPOSER_CAFILE=/run/secrets/cloud_ca; fi; composer install --no-interaction --prefer-dist
FROM php:8.3-cli
RUN docker-php-ext-install pdo_mysql
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app
COPY --from=dependencies /app/vendor ./vendor
COPY . .
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]
