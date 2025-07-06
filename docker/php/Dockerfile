FROM php:8.4-cli-alpine

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

RUN apk --no-cache add \
    git \
    linux-headers \
    > /dev/null

RUN docker-php-ext-install pcntl sockets

COPY composer.* /app/
WORKDIR /app
RUN composer install --prefer-dist --no-interaction

ADD . /app
