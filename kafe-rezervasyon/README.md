# Kafe Masa Rezervasyon Sistemi

Saf PHP (PDO) + MySQL + Bootstrap 5 ile geli?tirilen, XAMPP üzerinde çal??an masa rezervasyon uygulamas?.

## Kullan?lan Teknolojiler

| Katman | Teknoloji |
| --- | --- |
| Frontend | HTML5, CSS3, Vanilla JavaScript (fetch API), Bootstrap 5 |
| Backend | Saf PHP 8 (framework yok), PDO + prepared statements |
| Veritaban? | MySQL 8 / MariaDB 10.4+ (phpMyAdmin uyumlu) |
| Güvenlik | Prepared statements, `htmlspecialchars`, CSRF token, session yönetimi |

## Klasör Mimarisi

```
kafe-rezervasyon/
??? app/                        # ÇEK?RDEK — taray?c?dan eri?ilemez (.htaccess ile kapal?)
?   ??? .htaccess               # Require all denied
?   ??? config.php              # DB bilgileri, saat aral?klar? gibi sabitler        [Ad?m 2]
?   ??? db.php                  # PDO ba?lant?s? (Database s?n?f?, singleton)        [Ad?m 2]
?   ??? helpers.php             # e(), csrf_token(), flash(), redirect(), validasyon [Ad?m 2]
?   ??? bootstrap.php           # Her iste?in ba??nda ça?r?lan tek giri? noktas?     [Ad?m 2]
?   ??? Auth.php                # Admin giri?/ç?k?? ve oturum kontrolü               [Ad?m 4]
?   ??? MasaRepository.php      # Masa CRUD + müsait masa sorgusu                   [Ad?m 3]
?   ??? RezervasyonRepository.php # Çak??ma kontrolü, kay?t, filtreleme             [Ad?m 3]
?
??? partials/                   # Tekrar kullan?lan HTML parçalar?
?   ??? header.php              # Mü?teri taraf? <head> + navbar                     [Ad?m 3]
?   ??? footer.php
?   ??? admin-header.php        # Admin paneli kabu?u (sidebar + navbar)             [Ad?m 4]
?   ??? admin-footer.php
?
??? api/                        # JavaScript'in ça??rd??? JSON uç noktalar?
?   ??? musait-masalar.php      # Tarih + saat + ki?i say?s? ? müsait masa listesi   [Ad?m 3]
?
??? admin/                      # Yönetim paneli (session ile korumal?)
?   ??? login.php               # Giri? formu                                        [Ad?m 4]
?   ??? logout.php
?   ??? index.php               # Dashboard: günlük özet, doluluk oran?              [Ad?m 4]
?   ??? masalar.php             # Masa yönetimi (liste + ekle/düzenle/sil)           [Ad?m 4]
?   ??? rezervasyonlar.php      # Rezervasyon listesi + filtre + durum butonlar?     [Ad?m 4]
?
??? assets/                     # Statik dosyalar
?   ??? css/style.css           # Bootstrap üzerine özel tema                        [Ad?m 3]
?   ??? js/app.js               # Dinamik masa listeleme (fetch)                     [Ad?m 3]
?
??? index.php                   # Mü?teri ana sayfas? + rezervasyon formu            [Ad?m 3]
??? rezervasyon-sorgula.php     # Kod/telefon ile sorgulama ve iptal                 [Ad?m 3]
??? database.sql                # Veritaban? ?emas? + örnek veriler                  [Ad?m 1 ?]
```

### Bu mimari neden böyle?

- **`app/` web kökünün d???nda mant??? tutar.** Veritaban? ?ifresi içeren dosyan?n taray?c?dan
  okunabilmesi klasik bir güvenlik hatas?d?r. PHP dosyalar? normalde yorumlan?p çal??t??? için
  içeri?i görünmez; ancak PHP modülü devre d??? kald???nda (yanl?? yap?land?rma, `.php.bak`
  uzant?l? yedekler) düz metin olarak sunulur. `.htaccess` + `bootstrap.php` sabiti kontrolü
  iki ayr? savunma katman? sa?lar.
- **Repository s?n?flar? SQL'i tek yerde toplar.** Çak??ma kontrolü gibi kritik sorgu tek bir
  metotta durur; sayfalar?n içine da??lm?? SQL'de bir yeri düzeltip di?erini unutma riski olur.
- **`api/` klasörü HTML üreten sayfalardan ayr?d?r.** JSON dönen uç noktalar ile sayfa render
  eden dosyalar? kar??t?rmamak, ileride mobil uygulama eklenirse ayn? uçlar? kullanmay? sa?lar.
- **`partials/` tekrar? önler.** Navbar'? 8 dosyada ayr? ayr? güncellemek istemeyiz.

## Kurulum (XAMPP)

1. Bu klasörü `C:\xampp\htdocs\kafe-rezervasyon` alt?na kopyalay?n.
2. XAMPP Control Panel'den **Apache** ve **MySQL** servislerini ba?lat?n.
3. `http://localhost/phpmyadmin` ? **Import** ? `database.sql` dosyas?n? seçip çal??t?r?n.
   (Alternatif: `mysql -u root -p < database.sql`)
4. Taray?c?dan `http://localhost/kafe-rezervasyon/` adresini aç?n.

### Varsay?lan admin hesab?

| Kullan?c? ad? | ?ifre |
| --- | --- |
| `admin` | `admin123` |

> ?ifre veritaban?nda `password_hash()` ile üretilmi? bcrypt hash olarak saklan?r. Gerçek
> kullan?mda ilk giri?ten sonra mutlaka de?i?tirin.

## Veritaban? ?emas?

| Tablo | Amaç |
| --- | --- |
| `yoneticiler` | Admin paneli kullan?c?lar? (`password_hash` ile saklanan ?ifre) |
| `masalar` | Fiziksel masalar: ad, kapasite, konum (iç/d??), durum (aktif/pasif) |
| `rezervasyonlar` | Masa + tarih + saat aral??? + mü?teri bilgileri + durum |
| `v_gunluk_ozet` | Dashboard için günlük toplam/onayl?/iptal say?lar?n? veren view |

`rezervasyonlar` tablosu `masalar` tablosuna `ON DELETE RESTRICT` ile ba?l?d?r: üzerinde
rezervasyon bulunan masa silinemez, bunun yerine durumu `pasif` yap?l?r.

## Geli?tirme Ad?mlar?

- [x] **Ad?m 1** — Klasör mimarisi ve `database.sql`
- [ ] **Ad?m 2** — PDO ba?lant? s?n?f? (`db.php`), yap?land?rma ve yard?mc? fonksiyonlar
- [ ] **Ad?m 3** — Mü?teri arayüzü, müsait masa listeleme ve çak??ma algoritmas?
- [ ] **Ad?m 4** — Admin paneli: kimlik do?rulama, dashboard, masa ve rezervasyon yönetimi
