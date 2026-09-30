# Kafe Masa Rezervasyon Sistemi

Saf PHP (PDO) + MySQL + Bootstrap 5 ile geliştirilen, XAMPP üzerinde çalışan masa rezervasyon uygulaması.

## Kullanılan Teknolojiler

| Katman | Teknoloji |
| --- | --- |
| Frontend | HTML5, CSS3, Vanilla JavaScript (fetch API), Bootstrap 5 |
| Backend | Saf PHP 8 (framework yok), PDO + prepared statements |
| Veritabanı | MySQL 8 / MariaDB 10.4+ (phpMyAdmin uyumlu) |
| Güvenlik | Prepared statements, `htmlspecialchars`, CSRF token, session yönetimi |

## Klasör Mimarisi

```
kafe-rezervasyon/
├── app/                        # ÇEKİRDEK — tarayıcıdan erişilemez (.htaccess ile kapalı)
│   ├── .htaccess               # Require all denied
│   ├── config.php              # DB bilgileri, saat aralıkları gibi sabitler        ✔ hazır
│   ├── db.php                  # PDO bağlantısı (Database sınıfı, singleton)        ✔ hazır
│   ├── helpers.php             # e(), csrf_token(), flash(), redirect(), validasyon ✔ hazır
│   ├── bootstrap.php           # Her isteğin başında çağrılan tek giriş noktası     ✔ hazır
│   ├── Auth.php                # Admin giriş/çıkış ve oturum kontrolü               ✔ hazır
│   ├── MasaRepository.php      # Masa CRUD + müsait masa sorgusu                    ✔ hazır
│   └── RezervasyonRepository.php # Çakışma kontrolü, kayıt, filtreleme              ✔ hazır
│
├── partials/                   # Tekrar kullanılan HTML parçaları
│   ├── header.php              # Müşteri tarafı <head> + navbar                     ✔ hazır
│   ├── footer.php
│   ├── admin-header.php        # Admin paneli kabuğu (navbar)                       ✔ hazır
│   └── admin-footer.php        # Admin sayfa altı                                   ✔ hazır
│
├── api/                        # JavaScript'in çağırdığı JSON uç noktaları
│   └── musait-masalar.php      # Tarih + saat + kişi sayısı → müsait masa listesi   ✔ hazır
│
├── admin/                      # Yönetim paneli (session ile korumalı)
│   ├── login.php               # Giriş formu                                        ✔ hazır
│   ├── logout.php              # POST + CSRF ile çıkış                              ✔ hazır
│   ├── index.php               # Dashboard: günlük özet, doluluk oranı              ✔ hazır
│   ├── masalar.php             # Masa yönetimi (liste + ekle/düzenle/sil)           [Adım 4]
│   └── rezervasyonlar.php      # Rezervasyon listesi + filtre + durum butonları     [Adım 4]
│
├── assets/                     # Statik dosyalar
│   ├── css/style.css           # Bootstrap üzerine özel tema                        ✔ hazır
│   └── js/app.js               # Dinamik masa listeleme (fetch)                     ✔ hazır
│
├── index.php                   # Müşteri ana sayfası + rezervasyon formu            ✔ hazır
├── rezervasyon-sorgula.php     # Kod/telefon ile sorgulama ve iptal                 ✔ hazır
└── database.sql                # Veritabanı şeması + örnek veriler                  ✔ hazır
```

### Bu mimari neden böyle?

- **`app/` web kökünün dışında mantığı tutar.** Veritabanı şifresi içeren dosyanın tarayıcıdan
  okunabilmesi klasik bir güvenlik hatasıdır. PHP dosyaları normalde yorumlanıp çalıştığı için
  içeriği görünmez; ancak PHP modülü devre dışı kaldığında (yanlış yapılandırma, `.php.bak`
  uzantılı yedekler) düz metin olarak sunulur. `.htaccess` + `bootstrap.php` sabiti kontrolü
  iki ayrı savunma katmanı sağlar.
- **Repository sınıfları SQL'i tek yerde toplar.** Çakışma kontrolü gibi kritik sorgu tek bir
  metotta durur; sayfaların içine dağılmış SQL'de bir yeri düzeltip diğerini unutma riski olur.
- **`api/` klasörü HTML üreten sayfalardan ayrıdır.** JSON dönen uç noktalar ile sayfa render
  eden dosyaları karıştırmamak, ileride mobil uygulama eklenirse aynı uçları kullanmayı sağlar.
- **`partials/` tekrarı önler.** Navbar'ı 8 dosyada ayrı ayrı güncellemek istemeyiz.

## Kurulum (XAMPP)

1. Bu klasörü `C:\xampp\htdocs\kafe-rezervasyon` altına kopyalayın.
2. XAMPP Control Panel'den **Apache** ve **MySQL** servislerini başlatın.
3. `http://localhost/phpmyadmin` → **Import** → `database.sql` dosyasını seçip çalıştırın.
   (Alternatif: `mysql -u root -p < database.sql`)
4. Tarayıcıdan `http://localhost/kafe-rezervasyon/` adresini açın.

### Yönetim paneli adresleri

Müşteri sayfalarından ayrı durur (`admin/` klasörü). Oturum yoksa paneli açmak `login.php` sayfasına yönlendirir.

| Sayfa | Adres |
| --- | --- |
| Giriş | `http://localhost/kafe-rezervasyon/admin/login.php` |
| Panel (dashboard) | `http://localhost/kafe-rezervasyon/admin/index.php` |
| Çıkış | `admin/logout.php` (yalnızca POST + CSRF; GET ile çıkış yapılmaz) |
| Masalar | `http://localhost/kafe-rezervasyon/admin/masalar.php` |
| Rezervasyonlar | `http://localhost/kafe-rezervasyon/admin/rezervasyonlar.php` |

### Varsayılan admin hesabı

| Kullanıcı adı | Şifre |
| --- | --- |
| `admin` | `admin123` |

> Şifre veritabanında `password_hash()` ile üretilmiş bcrypt hash olarak saklanır. Gerçek
> kullanımda ilk girişten sonra mutlaka değiştirin.

## Veritabanı Şeması

| Tablo | Amaç |
| --- | --- |
| `yoneticiler` | Admin paneli kullanıcıları (`password_hash` ile saklanan şifre) |
| `masalar` | Fiziksel masalar: ad, kapasite, konum (iç/dış), durum (aktif/pasif) |
| `rezervasyonlar` | Masa + tarih + saat aralığı + müşteri bilgileri + durum |
| `v_gunluk_ozet` | Dashboard için günlük toplam/onaylı/iptal sayılarını veren view |

`rezervasyonlar` tablosu `masalar` tablosuna `ON DELETE RESTRICT` ile bağlıdır: üzerinde
rezervasyon bulunan masa silinemez, bunun yerine durumu `pasif` yapılır.

## Çekirdek Katman (`app/`)

Tüm sayfalar ilk satırda tek bir dosyayı çağırır:

```php
require_once __DIR__ . '/app/bootstrap.php';
```

`bootstrap.php` sırasıyla `APP_INIT` sabitini tanımlar, `config.php` sabitlerini yükler,
hata gösterimini `APP_DEBUG` bayrağına göre ayarlar, zaman dilimini `Europe/Istanbul` yapar,
oturumu güvenli çerez bayraklarıyla (`httponly`, `samesite=Lax`, HTTPS varsa `secure`)
başlatır ve `db.php` + `helpers.php` dosyalarını yükler.

| Fonksiyon / sınıf | Görevi |
| --- | --- |
| `Database::baglanti()` | Tek ve paylaşılan PDO nesnesi (lazy singleton) |
| `Database::query/fetchOne/fetchAll/fetchValue/execute` | Her zaman prepared statement üzerinden sorgu |
| `e($deger)` | HTML'e basılan her değer için XSS kaçışlaması |
| `csrf_token()` / `csrf_field()` / `csrf_dogrula()` | CSRF koruması (`random_bytes` + `hash_equals`) |
| `flash_ekle()` / `flash_goster()` | Yönlendirme sonrası tek seferlik bildirim |
| `yonlendir($url)` | `header('Location')` + zorunlu `exit` |
| `telefon_normalize()`, `gecerli_tarih()`, `gecerli_saat()`, `saat_araliginda_mi()` | Sunucu tarafı doğrulama |
| `rezervasyon_kodu_uret()` | `random_bytes` tabanlı 8 karakterlik takip kodu |
| `durum_etiketi()` / `durum_rengi()` | ENUM değerini Bootstrap rozetine çevirir |

Kritik PDO ayarları `db.php` içinde tek bir dizide toplanmıştır; en önemlisi
`PDO::ATTR_EMULATE_PREPARES => false`, yani gerçek (sunucu taraflı) prepared statement.

## Geliştirme Adımları

- [x] **Adım 1** — Klasör mimarisi ve `database.sql`
- [x] **Adım 2** — PDO bağlantı sınıfı (`db.php`), yapılandırma ve yardımcı fonksiyonlar
- [x] **Adım 3** — Müşteri arayüzü, müsait masa listeleme ve çakışma algoritması
- [x] **Adım 4 (kısmi)** — Admin girişi, kabuk (navbar) ve günlük dashboard
- [ ] **Adım 4 (devam)** — Masa CRUD ve rezervasyon listesi / durum yönetimi
