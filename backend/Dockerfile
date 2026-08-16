FROM dunglas/frankenphp:1-php8.4

RUN install-php-extensions \
        pdo_pgsql \
        intl \
        zip \
        opcache \
        apcu

COPY --link conf.d/ $PHP_INI_DIR/conf.d/
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY --from=composer/composer:2-bin /composer /usr/bin/composer

ENV SERVER_NAME=:8000
ENV COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /app
