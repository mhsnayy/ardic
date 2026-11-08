# Personel Sistemi + Eğitim Portalı

[![CI](https://github.com/mhsnayy/ardinc/actions/workflows/ci.yml/badge.svg)](https://github.com/mhsnayy/ardinc/actions/workflows/ci.yml)


Şirket personelini yöneten bir **Personel Sistemi** ile bu personele eğitim atayıp ilerlemeyi izleyen bir **Eğitim Portalı**. İkisi de ayrı Laravel API'sidir ve her birinin kendi PostgreSQL veritabanı vardır. Yöneticiler her ikisini tek bir **Next.js paneli** üzerinden kullanır. Hepsi Docker ile ayağa kalkar.

| Servis | Teknoloji | Adres |
|---|---|---|
| `frontend` | Next.js (panel, BFF) | http://localhost:3000 |
| `egitim-portal` | Laravel 13 API + PostgreSQL | http://localhost:8002 |
| `personel-service` | Laravel 13 API + PostgreSQL | http://127.0.0.1:8001 (sadece yerel makine) |
| `personel-db`, `egitim-db` | PostgreSQL 17 | 127.0.0.1:5433 ve 127.0.0.1:5434 |

## Kurulum

Gereken tek şey [Docker](https://docs.docker.com/get-docker/) (Compose v2 ile). Windows, macOS, Linux ve Raspberry Pi'de adımlar aynıdır.

```bash
git clone <repo-adresi> && cd <repo-klasoru>
cp .env.example .env
docker compose up -d --build
```

İlk build birkaç dakika sürer. Servisler sırayla (veritabanı, API'ler, panel) başlar. Hepsi hazır olunca `docker compose ps` çıktısında beş servis de `healthy` görünür.

Panel: http://localhost:3000. Giriş bilgileri `.env` dosyasındaki `ADMIN_EMAIL` ve `ADMIN_PASSWORD` değerleridir (varsayılan: `admin@example.com` / `password`). Örnek departman ve personel verisi yalnızca ilk kurulumda (Personel Sistemi veritabanı boşken) yüklenir; silinen ya da değiştirilen örnek kayıtlar yeniden başlatmada geri gelmez. Admin hesabı ve servis token'ı ise her açılışta `.env` ile senkronlanır.

## Raspberry Pi

Pi kendi mimarisi için imajları kendisi build eder (64-bit işletim sistemi önerilir). `.env` dosyasında şunları değiştirin:

- `PUBLIC_FRONTEND_URL=http://<pi-ip>:3000` ve `PUBLIC_EDUCATION_URL=http://<pi-ip>:8002`. Tarayıcı bu adresleri kullanır. Değişiklikten sonra `docker compose up -d` yeterlidir, yeniden build gerekmez.
- `ADMIN_PASSWORD`, `PS_DB_PASSWORD`, `EP_DB_PASSWORD` ve `SERVICE_TOKEN` (en az 40 rastgele karakter, örn. `openssl rand -hex 20`).
- İsterseniz `FRONTEND_PORT` ve `EDUCATION_PORT`.

> **Uyarı: `COOKIE_SECURE`** yalnızca site HTTPS ile sunuluyorsa `true` olmalıdır. Düz HTTP'de (localhost, yerel ağdaki Pi) `false` kalmalıdır. Aksi halde tarayıcı oturum cookie'sini kaydetmez ve giriş yapılamaz.

> Seed her açılışta **tek** admin hesabını `.env`'deki `ADMIN_EMAIL` / `ADMIN_PASSWORD` ile günceller. Şifreyi değiştirmek için `.env`'i düzenleyip `docker compose up -d` yapmak yeterlidir. E-posta değiştirilirse eski admin hesabı ve token'ları silinir.

## Güncelleme, yedek, sıfırlama

```bash
# Güncelleme
git pull && docker compose up -d --build

# Yedek (Personel Sistemi; Eğitim Portalı için egitim-db / -U egitim egitim)
docker compose exec personel-db pg_dump -U personel personel > yedek.sql
docker compose exec egitim-db pg_dump -U egitim egitim > yedek-egitim.sql

# Loglar ve durum
docker compose ps
docker compose logs -f personel-service

# Sıfırlama (TÜM veriyi, volume'lar dahil siler)
docker compose down -v
```

## Mimari ve güvenlik

```
Tarayıcı ──► Next.js :3000 (BFF, veritabanı yok)
                │ admin token          │ admin token
                ▼                      ▼
     Personel Sistemi        ◄──  Eğitim Portalı :8002
     Laravel API         servis    Laravel API
          │              token'ı        │
     personel-db                   egitim-db
```

- **Tarayıcı yalnızca Next ile konuşur.** Next, backend çağrılarını sunucu tarafında yapar. Backend adresleri ve token'lar tarayıcıya inmez.
- **Kimlik doğrulama:** Sanctum token'ları. Admin token'ı 8 saatliktir ve `httpOnly` cookie'de tutulur. Giriş denemesi dakikada 5 ile sınırlıdır (anahtar: e-posta + IP). Panel üzerinden gelen istekler backend'e panel container'ının IP'siyle ulaştığı için sınır fiilen e-posta başına uygulanır. `X-Forwarded-For` başlığına bilinçli olarak güvenilmez, çünkü istemci tarafından sahtelenebilir.
- **Servisler arası erişim:** Eğitim Portalı, Personel Sistemi'ne yalnızca okuma yetkili bir servis token'ıyla (`SERVICE_TOKEN`) bağlanır. Personel verisini değiştiremez, atama anında bir kopya (snapshot) saklar.
- **Ağ:** Dışarıya yalnızca `frontend` ve `egitim-portal` açıktır. Veritabanları ve Personel Sistemi `127.0.0.1`'e bağlıdır, Docker iç ağında `http://<servis-adı>:8000` ile konuşulur.
- **Gizli bilgi:** `.env` imaja girmez, git'e girmez. Tüm ayarlar çalışma zamanında env ile verilir.
- **`APP_KEY`:** `PS_APP_KEY` / `EP_APP_KEY` boşsa entrypoint üretir ve `storage` volume'unda saklar. `docker compose down -v` anahtarı da siler.

Ayrıntılar: [docs/02-mimari.md](docs/02-mimari.md), [docs/03-altyapi.md](docs/03-altyapi.md).

## Docker'sız geliştirme

Yerelde PHP 8.4 (`pdo_pgsql` açık), Composer ve Node 22 gerekir.

```bash
# 1. Sadece veritabanları
docker compose up -d personel-db egitim-db

# 2. Personel Sistemi (egitim-portal için klasörü, DB_PORT=5434 ve --port=8002 olarak değiştirin)
cd personel-service
composer install
cp .env.example .env && php artisan key:generate
# .env içinde: DB_CONNECTION=pgsql, DB_HOST=127.0.0.1, DB_PORT=5433,
# DB_DATABASE=personel, DB_USERNAME=personel, DB_PASSWORD=<PS_DB_PASSWORD>,
# SERVICE_TOKEN=<en az 40 karakter, örn. openssl rand -hex 20>
php artisan migrate --seed && php artisan serve --port=8001

# 3. Panel
cd frontend
npm install && npm run dev
```

Yerelde iki tarafın servis token'ı eşleşmelidir: `personel-service/.env` içindeki `SERVICE_TOKEN=<değer>` (seed sırasında kaydedilir) ile `egitim-portal/.env` içindeki `PERSONNEL_API_TOKEN=<aynı değer>`. Eğitim Portalı için ayrıca `PERSONNEL_API_URL=http://127.0.0.1:8001` verin.

`frontend/.env.local` içinde backend adresleri: `PERSONNEL_API_URL=http://127.0.0.1:8001`, `EDUCATION_API_URL=http://127.0.0.1:8002`.

## Testler

```bash
# Laravel (yerelde bellekte SQLite; CI'da PostgreSQL)
cd personel-service && php artisan test && vendor/bin/pint --test
cd egitim-portal    && php artisan test && vendor/bin/pint --test

# Panel
cd frontend && npm run lint && npx tsc --noEmit && npm run build
```

CI (`.github/workflows/ci.yml`) her push ve PR'da aynı kontrolleri çalıştırır, ardından tüm yığını `docker compose up --build` ile kaldırıp `/login` sayfasını doğrular.

## API özeti

Tüm yanıtlar JSON'dur. Hatalar `{message}`, doğrulama hataları `{message, errors}` biçimindedir. Sayfalı yanıtlar `{data, meta}`.

**Personel Sistemi** (`:8001`)

```
POST /api/auth/login · POST /api/auth/logout · GET /api/auth/me
/api/admin/personnel      GET (?search, department_id, page) POST · GET PUT DELETE /{id}
/api/admin/departments    GET POST · PUT DELETE /{id}   (personeli varsa DELETE → 409)
/api/service/personnel    GET (?department_id) · GET /{id}   servis token'ı, dakikada 60 istek
/api/service/departments  GET
```

**Eğitim Portalı** (`:8002`)

```
/api/auth/login | logout | me
/api/educations                    GET (ilerleme sayılarıyla) POST · GET PUT DELETE /{id}
/api/educations/{id}/assignments   GET · POST {personnel_ids[]}
/api/assignments/{id}              PATCH {status} · DELETE
/api/personnel                     GET (Personel Sistemi verisi + kişi başı eğitim sayıları)
/api/personnel/{id}/assignments    GET
/api/departments                   GET
```

Sağlık kontrolü: her iki Laravel servisinde `GET /up`.
