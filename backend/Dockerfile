FROM dunglas/frankenphp:1-php8.4 AS base

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

# Etap deweloperski: to on stoi za usluga backend w profilu dev.
FROM base AS dev

# pcov zamiast xdebuga - liczy wylacznie pokrycie i robi to wielokrotnie
# szybciej. Wylaczony domyslnie, zeby zwykly "composer test" nie placil za
# instrumentacje; wlacza go dopiero "composer coverage" przez -d.
RUN install-php-extensions pcov
RUN printf 'pcov.enabled=0\npcov.directory=/app/src\n' > "$PHP_INI_DIR/conf.d/99-pcov.ini"

# Etap produkcyjny: kod w obrazie, bo profil prod nie montuje ./backend.
FROM base AS prod

ENV APP_ENV=prod

COPY --link composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY --link . .

RUN composer dump-autoload --optimize --classmap-authoritative --no-dev \
    && php bin/console cache:warmup --env=prod

# Dopiero tutaj, nie w base: dev montuje ./backend, wiec tam sprawdzanie
# znacznikow czasu musi zostac wlaczone, inaczej zmiana w pliku wymagalaby
# restartu kontenera.
RUN printf 'opcache.validate_timestamps=0\n' > "$PHP_INI_DIR/conf.d/99-opcache-prod.ini"
