# 03 — Altyapı

## 1. Repo Yapısı
```
/
├── personel-service/        Laravel API + Dockerfile
├── egitim-portal/           Laravel API + Dockerfile
├── frontend/                Next.js + Dockerfile
├── docs/
├── .github/workflows/ci.yml
├── docker-compose.yml
├── .env.example
├── .gitattributes           *.sh text eol=lf
└── README.md
```

## 2. Kurulum
Her ortamda (Windows, macOS, Linux, Raspberry Pi) aynı adımlar uygulanır. Gereken tek şey Docker.
```
git clone <repo> && cd <repo>
cp .env.example .env
docker compose up -d --build
```
- **Panel:** http://localhost:3000. Giriş bilgileri `.env` dosyasındaki `ADMIN_EMAIL` ve `ADMIN_PASSWORD`.
- **Güncelleme:** `git pull && docker compose up -d --build`
- **Raspberry Pi:** `.env` dosyasında `PUBLIC_*` adreslerini Pi'nin adresiyle değiştir (§5) ve şifreleri değiştir. Pi kendi mimarisi için kendisi build eder. İlk build birkaç dakika sürer.
- `.env.example` olduğu gibi kopyalandığında sistem çalışmalı.
- **Alan adı + HTTPS (reverse proxy arkasında):** `docker-compose.proxy.yml` ana dosyanın üstüne bindirilir (`.env`'de `COMPOSE_FILE`, `PROXY_NETWORK`). Hiçbir servis host'a port açmaz. `frontend` ve `egitim-portal`, proxy ağına `ardic-frontend` / `ardic-egitim` alias'larıyla katılır. Proxy `/uploads/` ve `/media/` yollarını `egitim-portal`'a, geri kalanı `frontend`'e yönlendirir ve `X-Forwarded-Proto: https` gönderir. Eğitim Portalı yalnızca bu başlığa güvenir, `X-Forwarded-For`'a güvenmez. Ayrıntı: README → "Alan adıyla yayın".

## 3. Docker

| Servis | İmaj | Port | Volume |
|---|---|---|---|
| `personel-db` | `postgres:17-alpine` | `127.0.0.1:5433` | `ps-db-data` |
| `egitim-db` | `postgres:17-alpine` | `127.0.0.1:5434` | `ep-db-data` |
| `personel-service` | `dunglas/frankenphp` (PHP 8.4) | `127.0.0.1:8001` | `ps-storage` → `storage/` |
| `egitim-portal` | `dunglas/frankenphp` (PHP 8.4) | `${EDUCATION_PORT:-8002}` | `ep-storage` → `storage/` |
| `frontend` | `node:22-alpine`, `output: 'standalone'` | `${FRONTEND_PORT:-3000}` | — |

- **Ağa açık portlar:** Sadece `frontend` ve `egitim-portal`. Eğitim Portalı'nın açık olması gerekiyor, çünkü tarayıcı medyayı doğrudan ondan çeker. Diğer servisler sadece `127.0.0.1`'e bağlıdır.
- **İç adresler:** Servisler birbirine `http://<servis-adı>:8000` adresiyle erişir.
- **Başlatma sırası:** `depends_on: service_healthy` ile `db → backend → frontend`.
  - Veritabanı healthcheck'i: `pg_isready`. Laravel healthcheck'i: `/up`.
  - Tüm servislerde `restart: unless-stopped` var.
- **Dockerfile kuralları:**
  - Multi-stage build kullanılır.
  - Platform sabitlenmez (`--platform` yok), yalnızca amd64 için yayınlanan binary indirilmez. Böylece aynı Dockerfile PC'de ve Pi'de çalışır.
  - `.env` imaja girmez (`.dockerignore`). Bütün ayarlar çalışma zamanında env ile verilir.
- **Laravel imajı:** Eklentiler `pdo_pgsql`, `intl`, `zip`. Entrypoint sırasıyla:
  1. `APP_KEY` boşsa üretir ve `storage/app/.app_key` dosyasında saklar.
  2. `migrate --force`
  3. `db:seed --force` (seeder'lar idempotent)
  4. `config:cache`

## 4. Veritabanı
- **İki ayrı PostgreSQL container'ı var.** Her backend sadece kendi veritabanının bağlantı bilgisini bilir.
  ```
  DB_CONNECTION=pgsql  DB_HOST=personel-db|egitim-db  DB_PORT=5432
  DB_DATABASE=personel|egitim  DB_USERNAME=personel|egitim  DB_PASSWORD=${PS_DB_PASSWORD}|${EP_DB_PASSWORD}
  ```
  Postgres container'ları aynı değerleri `POSTGRES_DB`, `POSTGRES_USER`, `POSTGRES_PASSWORD` olarak alır.
- **Şema** sadece migration'larla değişir.
- **Testler:**
  - Yerelde bellekte SQLite ile çalışır (Laravel'in varsayılan `phpunit.xml` ayarı).
  - CI'da PostgreSQL ile çalışır. Env değerleri `phpunit.xml`'i ezer, dosya değiştirilmez.
  - Bu ikili yapı yüzünden veritabanına özgü SQL ve native enum kullanılmaz.
- **Yedek:** `docker compose exec personel-db pg_dump -U personel personel > yedek.sql`
- **Sıfırlama:** `docker compose down -v`

## 5. Env

**Kök `.env.example`:**
```
# Tarayıcının göreceği adresler. Pi'de: http://<pi-ip>:3000 ve http://<pi-ip>:8002
PUBLIC_FRONTEND_URL=http://localhost:3000
PUBLIC_EDUCATION_URL=http://localhost:8002
FRONTEND_PORT=3000
EDUCATION_PORT=8002
COOKIE_SECURE=false          # sadece HTTPS'te true

ADMIN_EMAIL=admin@example.com
ADMIN_PASSWORD=password

# Paylaşımlı ortamda (Pi) mutlaka değiştir
SERVICE_TOKEN=change-me-40-random-chars
PS_DB_PASSWORD=change-me
EP_DB_PASSWORD=change-me
PS_APP_KEY=                  # boşsa entrypoint üretir
EP_APP_KEY=
```

**Compose'un servislere verdiği değerler:**
- **`frontend`:**
  - `PERSONNEL_API_URL=http://personel-service:8000`, `EDUCATION_API_URL=http://egitim-portal:8000`
  - `PUBLIC_FRONTEND_URL`, `COOKIE_SECURE`
- **`egitim-portal`:**
  - `APP_URL=${PUBLIC_EDUCATION_URL}` (imzalı URL'ler bununla üretilir), `FRONTEND_URL=${PUBLIC_FRONTEND_URL}` (CORS)
  - `PERSONNEL_API_URL=http://personel-service:8000`, `PERSONNEL_API_TOKEN=${SERVICE_TOKEN}`, `MEDIA_DRIVER=local`
- **İki backend:** `ADMIN_EMAIL`, `ADMIN_PASSWORD`, `APP_KEY` ve §4'teki `DB_*` değerleri.

**Servis token'ı:** Personel Sistemi seed sırasında `service:token egitim-portal --token=$SERVICE_TOKEN` komutuyla bu token'ın hash'ini kaydeder (idempotent). Eğitim Portalı aynı değeri kullanır. Sanctum `|` içermeyen token'ı doğrudan hash'iyle aradığı için bu yöntem çalışır. Komut parametresiz verilirse rastgele bir token üretip ekrana yazar.

## 6. Git
- **`.gitignore`:** `.env`, `vendor/`, `node_modules/`, `.next/`, `*.sqlite`, `storage/` içeriği repoya girmez.
- **Branch:** `main` korunur. İşler `feat/…` ve `fix/…` branch'lerinde yapılır, PR'lar CI geçmeden merge edilmez.
- **Commit mesajları:** Conventional Commits (`feat:`, `fix:`, `chore:`, `docs:`, `ci:`, `test:`).
- **Push:** Ajanlar her fazın sonunda commit atar. Push sadece kullanıcı onay verirse yapılır.

## 7. CI (`.github/workflows/ci.yml`)
Her push ve PR'da çalışır. `concurrency` ile aynı branch'teki eski çalışmalar iptal edilir.

| Job | Adımlar |
|---|---|
| `backend` (matrix: iki Laravel uygulaması) | `postgres:17-alpine` servisi → `setup-php` 8.4 (`pdo_pgsql`) → `composer install` (cache) → `pint --test` → `php artisan test` (env: `DB_CONNECTION=pgsql`, `DB_HOST=127.0.0.1`, `DB_DATABASE=testing`, `DB_USERNAME=postgres`, `DB_PASSWORD=postgres`) |
| `frontend` | `setup-node` 22 (cache) → `npm ci` → `lint` → `tsc --noEmit` → `build` |
| `docker` | `needs` önceki iki job → `cp .env.example .env` → `docker compose up -d --build` → servislerin `healthy` olmasını bekle → `curl /login` 200 → `down -v` |

**Otomatik deploy yok.** Pi'de güncelleme §2'deki komutla elle yapılır.

## 8. Docker'sız Geliştirme (isteğe bağlı)
1. Sadece veritabanlarını başlat: `docker compose up -d personel-db egitim-db`
2. Laravel: `.env` dosyasında `DB_HOST=127.0.0.1`, `DB_PORT=5433` (Eğitim Portalı için `5434`) ayarla, yerel PHP'de `pdo_pgsql` açık olmalı. Sonra `composer install && php artisan migrate --seed && php artisan serve --port=8001` (Eğitim Portalı için `8002`).
3. Next: `npm install && npm run dev`. `.env.local` dosyasında backend adresleri `http://127.0.0.1:8001` ve `:8002`.
