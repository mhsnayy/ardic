#!/bin/sh
set -eu
cd /app

# Volume ilk mount'ta bos olabilir: storage iskeletini yeniden olustur.
mkdir -p storage/app/private storage/app/public storage/app/media \
    storage/framework/cache/data storage/framework/sessions storage/framework/views \
    storage/logs bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache

# Buyuk yuklemeler (200 MB ham PUT): govde limiti ~210 MB, yavas baglantilar
# icin govde okuma zaman asimi 15 dk. Varsayilan Caddyfile'da limit/zaman asimi
# yoktur; sinirsiz birakmamak icin acikca ayarlanir.
NL='
'
export CADDY_GLOBAL_OPTIONS="servers {${NL}timeouts {${NL}read_body 15m${NL}}${NL}}"
export CADDY_SERVER_EXTRA_DIRECTIVES="request_body {${NL}max_size 210MB${NL}}"

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

# 5. Baglanmamis eski yuklemeleri temizle (idempotent); hata acilisi engellemez.
php artisan media:prune || echo "UYARI: media:prune basarisiz, devam ediliyor" >&2

# 6. Container acikken periyodik temizlik (varsayilan saatte bir). Dongu arka
# planda calisir; FrankenPHP exec ile PID 1 olur, dongu container ile birlikte olur.
PRUNE_INTERVAL="${MEDIA_PRUNE_INTERVAL:-3600}"
(
    while true; do
        sleep "$PRUNE_INTERVAL"
        php artisan media:prune || echo "UYARI: media:prune basarisiz" >&2
    done
) &

exec "$@"
