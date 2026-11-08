#!/bin/sh
set -eu
cd /app

# Volume ilk mount'ta bos olabilir: storage iskeletini yeniden olustur.
mkdir -p storage/app/private storage/app/public \
    storage/framework/cache/data storage/framework/sessions storage/framework/views \
    storage/logs bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache

# 1. APP_KEY bossa uret ve storage/app/.app_key icinde sakla.
KEY_FILE=storage/app/.app_key
if [ -z "${APP_KEY:-}" ]; then
    if [ -s "$KEY_FILE" ]; then
        APP_KEY="$(cat "$KEY_FILE")"
    else
        APP_KEY="base64:$(head -c 32 /dev/urandom | base64)"
        umask 077
        printf '%s' "$APP_KEY" > "$KEY_FILE"
    fi
    export APP_KEY
fi

# Eski config cache'i kalmasin (restart sonrasi env degismis olabilir).
rm -f bootstrap/cache/config.php

# 2-3. Sema ve seed (seeder'lar idempotent).
php artisan migrate --force
php artisan db:seed --force

# 4. Config cache.
php artisan config:cache

exec "$@"
