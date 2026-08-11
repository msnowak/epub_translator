FROM dunglas/frankenphp:1-php8.4

RUN install-php-extensions \
        pdo_pgsql \
        intl \
        zip \
        opcache \
        apcu

COPY --from=composer/composer:2-bin /composer /usr/bin/composer

ENV SERVER_NAME=:8000
ENV COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /app
