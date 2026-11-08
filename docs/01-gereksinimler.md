# 01 — Gereksinimler

> Mühendisler ve AI ajanları için yazıldı. Okuma sırası: bu dosya, [02-mimari.md](02-mimari.md), [03-altyapi.md](03-altyapi.md).

## Amaç
Personel bilgileri ayrı bir sistemde tutuluyor. Bu bilgilerle çalışanlara eğitim atanacak ve eğitim durumu takip edilecek.

| Parça | Teknoloji | Görevi |
|---|---|---|
| Personel Sistemi | Laravel API | Personel ve departman verisinin sahibi |
| Eğitim Portalı | Laravel API | Eğitim, içerik ve atamaların sahibi. Personeli Personel Sistemi API'sinden okur |
| Yönetim Paneli | Next.js | Tek arayüz. Backend'lerle sunucu tarafında konuşur (BFF) |

## Kapsam
- **Faz 1 (zorunlu):** admin girişi, personel/departman yönetimi, eğitim tanımlama, atama, tamamlanma takibi, Docker ve CI.
- **Faz 2 (bonus, Faz 1 bitmeden başlanmaz):** eğitime video, PDF ve link içerik ekleme, öğrenme linki, otomatik tamamlanma.
- **Kapsam dışı:** sınav, sertifika, personel hesabı, rol bazlı yetki, e-posta, SSO, bulut depolamanın kod olarak yazılması (sadece tasarlanır), otomatik deploy.

**Kısıtlar**
- İki backend aynı veritabanını kullanamaz.
- Repoyu klonlayan herkes sistemi **sadece Docker** ile, tek komutla çalıştırabilmeli. Proje sahibi aynı adımlarla Raspberry Pi'de (64-bit) çalıştıracak.
- Kod public bir GitHub reposunda olacak, her push'ta CI çalışacak.

## Gereksinimler
| ID | Gereksinim |
|---|---|
| AUTH-1 | Admin e-posta ve şifreyle giriş yapar |
| AUTH-2 | Token'lar tarayıcıya inmez, httpOnly cookie'de tutulur |
| AUTH-3 | Oturum süresi dolunca giriş sayfasına yönlendirilir. Çıkışta token'lar iptal edilir |
| PS-1 | Departman ve personel (ad, soyad, e-posta, departman) için CRUD |
| PS-2 | Personel listesinde arama, departman filtresi, sayfalama |
| PS-3 | Eğitim Portalı'na salt-okunur bir servis API'si |
| EP-1 | Eğitim CRUD (ad, tür, açıklama) |
| EP-2 | Bir eğitime tek tek ya da departman bazında toplu atama |
| EP-3 | Atama durumu: Atandı / Devam Ediyor / Tamamlandı |
| EP-4 | Eğitim bazında ve personel bazında ilerleme (sayılar ve yüzde) |
| EP-5 | Atamayı kaldırma |
| FE-1 | Tüm ekranlar tek panelde, Türkçe ve mobil uyumlu |
| FE-2 | Form hataları alan bazında, işlem sonuçları toast ile gösterilir |
| FE-3 | Personel Sistemi kapalıyken eğitim ekranları açılmaya devam eder ve uyarı gösterir |
| CNT-1 (F2) | Eğitime sıralı içerik eklenir: video, PDF ya da link. Her içerik zorunlu veya opsiyonel olabilir |
| CNT-2 (F2) | Video ve PDF yüklenir, yükleme sırasında ilerleme gösterilir. Depolama şimdilik local |
| CNT-3 (F2) | Her atama için süreli ve iptal edilebilir bir öğrenme linki üretilir |
| CNT-4 (F2) | Personel link ile, giriş yapmadan sadece kendi eğitimini görür. Video kaldığı yerden devam eder |

## İş Kuralları
| ID | Kural |
|---|---|
| BR-1 | Bir kişi aynı eğitime yalnızca bir kez atanır (unique kısıt) |
| BR-2 | İçinde personel olan departman silinemez (409) |
| BR-3 | Atamada personel, Personel Sistemi'nden canlı olarak doğrulanır. Sistem kapalıysa atama yapılmaz (503) |
| BR-4 | Personel silinse bile atama geçmişi korunur. Atama anındaki ad, e-posta ve departman saklanır (snapshot) |
| BR-5 | Durum *Tamamlandı* olunca `completed_at` dolar, durum geri alınırsa temizlenir |
| BR-6 (F2) | Tamamlanma koşulları: video için en az %90'ı izlenmiş olmalı, PDF açılmış olmalı, link tıklanmış olmalı |
| BR-7 (F2) | İlk ilerlemede durum *Devam Ediyor*, tüm zorunlu içerikler bitince *Tamamlandı* olur. Otomatik geçiş sadece ileri yönde. Admin durumu her yönde elle değiştirebilir |
| BR-8 (F2) | Video ileri sarılarak tamamlanamaz. Sunucu, gerçekte geçen süreden fazla ilerlemeyi kabul etmez |
| BR-9 (F2) | Bir atamanın tek bir geçerli linki olur. Yeni link üretilince eskisi geçersizleşir |

## Fonksiyonel Olmayan Gereksinimler
- **Güvenlik:** Token'lar yetkiyle sınırlı ve hash'li saklanır, rate limit uygulanır. Gizli bilgiler sadece `.env` dosyasında tutulur.
- **Bağımsızlık:** Her backend'in kendi veritabanı ve kendi kullanıcıları vardır.
- **Genişletilebilirlik:** Yeni bir içerik türü ya da depolama sağlayıcısı eklemek mevcut koda dokunmadan yapılabilmeli.
- **Kalite:** Backend'de Feature testler yazılır. Frontend'de lint, typecheck ve build hatasız geçer. CI yeşil kalır.

## Varsayımlar
1. Tek bir admin rolü var. Aynı admin bilgisi iki backend'e de seed edilir.
2. Öğrenme linkini personele admin iletir.
3. Video en fazla 200 MB olabilir, mp4 veya webm formatında. Video süresini oynatıcı tarayıcıdan bildirir.

## Kabul Kriterleri
**Faz 1**
- [ ] Temiz bir klasöre klonlanan repo, README'deki adımlarla ve sadece Docker kullanarak çalışır. Panel http://localhost:3000 adresinde açılır.
- [ ] Giriş yapmadan açılmaya çalışılan sayfalar login sayfasına yönlendirir. Tarayıcıda hiçbir token görünmez.
- [ ] Servis token'ıyla yazma isteği 403, token olmadan yapılan istek 401 döner.
- [ ] Bir departmanın tüm personeli tek seferde atanabilir. Aynı kişi ikinci kez atanamaz.
- [ ] Personel Sistemi durdurulunca eğitim ekranları açılmaya devam eder ve uyarı gösterir. Atama yapılamaz.
- [ ] `.env` dosyasındaki adresler bilgisayarın yerel IP'siyle değiştirildiğinde, aynı ağdaki başka bir cihazdan panele girilebilir (Pi senaryosu).
- [ ] GitHub Actions'taki tüm job'lar yeşildir.

**Faz 2**
- [ ] Bir eğitime video, PDF ve link içerik eklenip sıralanabilir.
- [ ] Geçersiz ya da süresi dolmuş bir öğrenme linki açılınca "link geçersiz" sayfası gösterilir.
- [ ] Videonun %90'ı izlenince ve diğer zorunlu içerikler de tamamlanınca atama kendiliğinden *Tamamlandı* olur. Video ileri sarılarak bu sağlanamaz.
