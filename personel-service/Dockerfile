# syntax=docker/dockerfile:1

# ---- base: PHP 8.4 + FrankenPHP + eklentiler (platform sabitlenmez) ----
FROM dunglas/frankenphp:1-php8.4 AS base
RUN install-php-extensions pdo_pgsql intl zip opcache
WORKDIR /app

# ---- vendor: sadece uretim bagimliliklari ----
FROM base AS vendor
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction
COPY . .
RUN mkdir -p bootstrap/cache storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs storage/app/private storage/app/public \
    && composer dump-autoload --no-dev --optimize --no-interaction

# ---- runtime ----
FROM base AS runtime
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php.ini "$PHP_INI_DIR/conf.d/zz-app.ini"
COPY --from=vendor /app /app
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh \
    && mkdir -p bootstrap/cache storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs storage/app/private storage/app/public \
    && chmod -R ug+rwX storage bootstrap/cache

# Gizli bilgi yok: tum ayarlar calisma zamaninda env ile gelir.
ENV SERVER_NAME=:8000 \
    APP_ENV=production \
    APP_DEBUG=false
EXPOSE 8000

HEALTHCHECK --interval=10s --timeout=5s --start-period=90s --retries=6 \
    CMD php -r 'exit(@file_get_contents("http://127.0.0.1:8000/up") === false ? 1 : 0);'

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile", "--adapter", "caddyfile"]
