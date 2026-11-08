---
name: build-project
description: docs/ klasöründeki tasarıma göre projeyi subagent'larla sıfırdan geliştirir. Kullanıcı /build-project yazınca çalışır.
disable-model-invocation: true
argument-hint: "[faz1 | faz2]  (boşsa faz1)"
---

# Projeyi subagent'larla geliştir

Sen orkestra şefisin. Kodu ajanlar yazar. Ortam, iskelet, entegrasyon ve doğrulama sende kalır.

**Başlangıç**
- Klasörde sadece `CLAUDE.md`, `docs/` ve `.claude/` vardır. Kod yoktur, her şey sıfırdan yazılır.
- Ajanlar: `backend`, `frontend`, `devops`, `reviewer`. Dokümanlar: `01-gereksinimler.md`, `02-mimari.md`, `03-altyapi.md`.
- Bunlardan farklı ajan ya da doküman adları görürsen eski bir sürüm kopyalanmıştır. Dur ve kullanıcıya bildir.

**Kurallar**
- Üç dokümanı oku, kısa bir plan göster ve onay al.
- Her ajana doküman yollarını ve net bir görev ver. Ajanlar konuşmayı görmez.
- Her fazın başında kullanıcıya tek satırla nerede olduğunu söyle.
- Ajan raporlarına güvenme, testleri ve build'i kendin de çalıştır.
- Her fazın sonunda commit at. Push sadece kullanıcı onay verirse yapılır.

## Faz 1

**0. Ortam** (sen)
- `php -v`, `composer -V`, `node -v`, `docker info` komutlarıyla araçları kontrol et. Eksik olanı kullanıcıya bildir.
- PHP'de `pdo_sqlite`, `openssl`, `mbstring`, `curl`, `fileinfo`, `zip` eklentileri açık olmalı. Yerel testler için PostgreSQL gerekmez.
- Araçlar bulunamazsa önce PATH'i yenile (VS Code, araçlar kurulmadan önce açılmış olabilir).
- `git init -b main`

**1. İskelet** (sen; tüm komutlar etkileşimsiz çalışmalı)
- `composer create-project laravel/laravel personel-service --no-interaction`, aynısı `egitim-portal` için. İkisinde de `php artisan install:api --without-migration-prompt`.
- `npx create-next-app@latest frontend --ts --tailwind --eslint --app --no-src-dir --import-alias "@/*" --use-npm --yes`, ardından `frontend/` içinde `npx shadcn@latest init -d`.
- İskeletlerden gelen `ExampleTest` dosyalarını ve varsa `CLAUDE.md` / `AGENTS.md` dosyalarını sil.
- Commit: `chore: scaffold apps`

**2. Uygulamalar** (3 ajan, tek mesajda paralel)
- `backend` → personel-service. Mimari §2–4. Admin seed'i ve `service:token --token=` komutu (03 §5).
- `backend` → egitim-portal. Mimari §2–4 ve §7. `PersonnelApiClient`.
- `frontend` → panel. AUTH, FE, PS ve EP gereksinimleri.
- Bittiğinde testleri ve build'i kendin çalıştır. Commit: `feat: core apps`

**3. Altyapı** (`devops`)
- Commit: `ci: docker, compose and github actions`

**4. Entegrasyon** (sen)
- `cp .env.example .env && docker compose up -d --build` çalıştır, beş servisin de `healthy` olduğunu doğrula.
- Faz 1 kabul kriterlerini tek tek dene: backend için curl, panel için login → eğitim oluşturma → departman bazlı atama.
- `docker compose stop personel-service` ile Personel Sistemi kapalıyken davranışı test et.
- `docker compose restart` sonrasında veri korunmalı ve tekrarlanmamalı.
- Pi testi: `PUBLIC_*` adreslerini yerel IP ile değiştir → `up -d` → o IP'den giriş yap → değerleri geri al.
- Temiz kurulum testi: diğer stack'i `down` ile durdur, repoyu geçici bir klasöre klonla, README adımlarını harfiyen uygula.
- Hata varsa ilgili ajana çıktıyla birlikte düzelttir.

**5. İnceleme** (`reviewer`)
- Yüksek ve Orta bulguları doğrula, düzelttir, testleri tekrar çalıştır. Commit: `fix: review findings`

**6. Bitiş**
- Kullanıcıya raporla: adresler, test/build sonuçları, kabul kriterleri (✓/✗), düzeltilen bulgular.
- Push için onay ve remote adresi iste.

## Faz 2 (sadece Faz 1 tamamsa)
Sırayla ilerle, her adımda `backend` ya da `frontend` ajanını kullan ve commit at:
1. İçerik modelleri, morph map ve `ContentType` (mimari §4–5)
2. `MediaSource`, local driver ve yükleme akışı (§5–6)
3. Paneldeki içerik yönetimi
4. Öğrenme linki ve `/ogren/[token]` sayfası
5. İlerleme kaydı ve otomatik tamamlanma (BR-6…8)

Sonunda Faz 2 kabul kriterlerini dene ve `reviewer` ajanını tekrar çalıştır.
