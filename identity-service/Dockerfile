FROM php:8.4-cli-alpine

RUN apk add --no-cache \
    bash curl git icu-libs libzip libpq oniguruma \
    && apk add --no-cache --virtual .build-deps \
       $PHPIZE_DEPS icu-dev libzip-dev postgresql-dev oniguruma-dev \
    && docker-php-ext-install -j"$(nproc)" bcmath intl opcache pcntl pdo_pgsql zip \
    && apk del .build-deps \
    && php -r 'if (! defined("PASSWORD_ARGON2ID") || password_hash("exchange-image-check", PASSWORD_ARGON2ID) === false) { fwrite(STDERR, "PHP image must support Argon2id password hashing\n"); exit(1); }'

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-progress --no-scripts

COPY . .

RUN composer dump-autoload --optimize \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 8000

HEALTHCHECK --interval=15s --timeout=3s --start-period=20s --retries=3 \
    CMD wget -q -O /dev/null http://127.0.0.1:8000/up || exit 1

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
