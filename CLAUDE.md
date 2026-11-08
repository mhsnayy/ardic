# Personel Sistemi + Eğitim Portalı

İki Laravel API (`personel-service/`, `egitim-portal/`) ve bir Next.js paneli (`frontend/`, BFF) var. Her backend'in kendi PostgreSQL container'ı bulunur. Tüm yapı tek komutla Docker'da çalışır: PC'de ve Raspberry Pi'de aynı adımlarla. Kod public bir GitHub reposunda tutulur, CI GitHub Actions ile çalışır.

## Önce oku
1. [docs/01-gereksinimler.md](docs/01-gereksinimler.md): ne yapılacak
2. [docs/02-mimari.md](docs/02-mimari.md): nasıl yapılacak
3. [docs/03-altyapi.md](docs/03-altyapi.md): kurulum, Docker, veritabanı, Git, CI

Dokümanla çelişen bir şey yapma; gerekirse önce sor. Raporlarda gereksinim ID'lerini an (ör. BR-3).

## Ajanlar
`backend` (uygulama başına bir tane) · `frontend` · `devops` · `reviewer` (salt-okunur)
Tüm akış: `/build-project`. Faz 2 için: `/build-project faz2`.

## Kurallar
- Faz 1 bitmeden Faz 2'ye başlama.
- Gizli bilgi commit etme. Push için onay al.
- Windows notları:
  - `.env` dosyalarını Bash'te `sed -i` ile düzenle. PowerShell 5 dosyaya BOM ekler.
  - curl ile Türkçe veri gönderirken veriyi dosyadan ver (`--data-urlencode "alan@dosya.txt"`).
