# Kafe Masa Rezervasyon Sistemi

Saf PHP (PDO) + MySQL + Bootstrap 5 ile geliştirilen, XAMPP üzerinde çalışan masa rezervasyon uygulaması. Kafe adı **Kahve Durağı**, çalışma saatleri **09:00–23:00**.

## Kullanılan Teknolojiler

| Katman | Teknoloji |
| --- | --- |
| Frontend | HTML5, CSS3, Vanilla JavaScript (fetch API), Bootstrap 5 |
| Backend | Saf PHP 8 (framework yok), PDO + prepared statements |
| Veritabanı | MySQL 8 / MariaDB 10.4+ (phpMyAdmin uyumlu) |
| Güvenlik | Prepared statements, `htmlspecialchars` (`e()`), CSRF token, session yönetimi |

## Klasör Mimarisi

```
kafe-rezervasyon/
├── app/                        # ÇEKİRDEK — tarayıcıdan erişilemez (.htaccess ile kapalı)
│   ├── .htaccess               # Require all denied
│   ├── config.php              # DB bilgileri, saat aralıkları gibi sabitler
│   ├── db.php                  # PDO bağlantısı (Database sınıfı, singleton)
│   ├── helpers.php             # e(), csrf_token(), flash(), yonlendir(), doğrulama
│   ├── bootstrap.php           # Her isteğin başında çağrılan tek giriş noktası
│   ├── Auth.php                # Admin giriş/çıkış ve oturum kontrolü
│   ├── MasaRepository.php      # Masa CRUD + müsait masa sorgusu
│   └── RezervasyonRepository.php # Çakışma kontrolü, kayıt, filtreleme
│
├── partials/                   # Tekrar kullanılan HTML parçaları
│   ├── header.php              # Müşteri tarafı <head> + navbar
│   ├── footer.php
│   ├── admin-header.php        # Admin kasa ofisi (sidebar + üst çubuk)
│   └── admin-footer.php
│
├── api/                        # JavaScript'in çağırdığı JSON uç noktaları
│   └── musait-masalar.php      # Tarih + saat + kişi sayısı → müsait masa listesi
│
├── admin/                      # Yönetim paneli (session ile korumalı)
│   ├── login.php               # Giriş formu
│   ├── logout.php              # POST + CSRF ile çıkış
│   ├── index.php               # Dashboard: günlük özet
│   ├── masalar.php             # Masa yönetimi (liste + ekle/düzenle/sil)
│   └── rezervasyonlar.php      # Rezervasyon listesi + filtre + durum
│
├── assets/
│   ├── css/style.css           # Kahve teması + admin sidebar
│   ├── img/favicon.svg         # Sekme ikonu
│   ├── js/app.js               # Dinamik masa listeleme (fetch)
│   └── js/admin.js             # Sil / iptal öncesi confirm()
│
├── index.php                   # Müşteri ana sayfası + rezervasyon formu
├── hakkimizda.php              # Kısa hikâye + yer tutucu iletişim
├── rezervasyon-onay.php        # Başarılı rezervasyon (kod + yazdır)
├── rezervasyon-sorgula.php     # Kod/telefon ile sorgulama ve iptal
└── database.sql                # Veritabanı şeması + örnek veriler
```

## Kurulum (XAMPP)

1. ZIP’i açın. İçinde **`kafe-rezervasyon/`** klasörü olmalıdır (`database.sql`, `app/`, `admin/`, `index.php` bu klasörün içindedir).
2. Klasörü `C:\xampp\htdocs\kafe-rezervasyon` altına kopyalayın.
   - Sonuç yolu: `C:\xampp\htdocs\kafe-rezervasyon\index.php`
   - Klasörü bir kez daha iç içe koymayın (`...\kafe-rezervasyon\kafe-rezervasyon\...` olmasın).
3. XAMPP Control Panel’den **Apache** ve **MySQL** servislerini başlatın.
4. Tarayıcıdan `http://localhost/phpmyadmin` açın.
5. Sol üstten **Import** (İçe Aktar) → **Choose File** → `C:\xampp\htdocs\kafe-rezervasyon\database.sql` seçin → **Go / İçe Aktar**.
   - phpMyAdmin “kafe_rezervasyon veritabanı oluşturuldu” benzeri bir başarı mesajı göstermelidir.
   - Alternatif (komut satırı): `mysql -u root -p < C:\xampp\htdocs\kafe-rezervasyon\database.sql`
6. Tarayıcıdan `http://localhost/kafe-rezervasyon/` adresini açın.

### VS Code’da klasörü Aç

Hocaya göndermeden önce projeyi düzenlemek veya ekran görüntüsü almak için:

1. Visual Studio Code’u açın.
2. **Dosya → Klasörü Aç…** (File → Open Folder…).
3. `C:\xampp\htdocs\kafe-rezervasyon` klasörünü seçin. **Üst klasörü (htdocs) değil, proje klasörünün kendisini** açın.
4. Sol gezginde `app/`, `admin/`, `database.sql` ve `README.md` yan yana görünmelidir.
5. PHP dosyalarını VS Code’dan “Run” ile çalıştırmayın; tarayıcı XAMPP Apache üzerinden açılır.

### Yönetim paneli adresleri

Oturum yoksa korunan sayfalar `login.php` adresine 303 ile gider.

| Sayfa | Adres |
| --- | --- |
| Müşteri formu | `http://localhost/kafe-rezervasyon/` |
| Hakkımızda | `http://localhost/kafe-rezervasyon/hakkimizda.php` |
| Rezervasyon onay | `http://localhost/kafe-rezervasyon/rezervasyon-onay.php` (kayıt sonrası 303) |
| Rezervasyon sorgula | `http://localhost/kafe-rezervasyon/rezervasyon-sorgula.php` |
| Giriş | `http://localhost/kafe-rezervasyon/admin/login.php` |
| Panel (dashboard) | `http://localhost/kafe-rezervasyon/admin/index.php` |
| Masalar | `http://localhost/kafe-rezervasyon/admin/masalar.php` |
| Rezervasyonlar | `http://localhost/kafe-rezervasyon/admin/rezervasyonlar.php` |
| Çıkış | `admin/logout.php` (yalnızca POST + CSRF; GET ile çıkış yapılmaz) |

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

Tüm sayfalar ilk satırda tek bir dosyayı çağırır (`APP_INIT` sayfa dosyasında **tanımlanmaz**):

```php
require_once __DIR__ . '/app/bootstrap.php';
```

Admin sayfalarında yol bir üst klasöre çıkar: `require_once __DIR__ . '/../app/bootstrap.php';`

| Fonksiyon / sınıf | Görevi |
| --- | --- |
| `Database::baglanti()` | Tek ve paylaşılan PDO nesnesi (lazy singleton) |
| `e($deger)` | HTML'e basılan her değer için XSS kaçışlaması |
| `csrf_token()` / `csrf_field()` / `csrf_dogrula()` | CSRF koruması (`random_bytes` + `hash_equals`) |
| `yonlendir($url)` | `header('Location')` + zorunlu `exit` (varsayılan 303 PRG) |
| `Auth::girisYap()` / `cikisYap()` / `kontrol()` | Session + `session_regenerate_id(true)` |
| `MasaRepository::musaitMasalar()` | `NOT EXISTS` + `baslangic < bitis AND bitis > baslangic` |
| `RezervasyonRepository::olustur()` | Transaction + masa satırı `FOR UPDATE` |
| `MasaRepository::sil()` | Rezervasyon varsa DELETE yok, `pasif` |

Kritik PDO ayarı: `PDO::ATTR_EMULATE_PREPARES => false` (gerçek prepared statement).

## Geliştirme Adımları

- [x] **Adım 1** — Klasör mimarisi ve `database.sql`
- [x] **Adım 2** — PDO bağlantı sınıfı (`db.php`), yapılandırma ve yardımcı fonksiyonlar
- [x] **Adım 3** — Müşteri arayüzü, müsait masa listeleme ve çakışma algoritması
- [x] **Adım 4** — Admin paneli: kimlik doğrulama, kasa ofisi (sidebar), dashboard, masa ve rezervasyon yönetimi
