# Kahve Durağı — Masa Rezervasyon Sistemi

**Ders:** Veritabanı  
**Proje:** Kafe masa rezervasyon uygulaması  
**Kafe adı:** Kahve Durağı  
**Çalışma saatleri:** 09:00–23:00  
**Teknoloji:** Saf PHP 8, PDO, MySQL, Bootstrap 5  

Bu rapor, XAMPP üzerinde çalışan ders projesini anlatır. Amaç bir “hazır CMS” göstermek değil; **üç tabloluk bir şema**, **saat aralığı çakışması** ve **güvenli yazma** (PDO, CSRF, oturum) konularını çalışan bir uygulamada toplamaktır.

Ekran görüntüleri (ZIP dışı teslim klasöründe): `ust-musteri.png`, `ust-admin.png`.

---

## 1. Amaç

Kahve Durağı’nda müşteri tarayıcıdan tarih, saat ve kişi sayısını seçer; müsait masalardan birini ayırtır. Kayıt oluşunca 8 karakterlik bir **rezervasyon kodu** verilir. Müşteri bu kodla (veya telefonuyla) kaydını sorgular, aynı gün onaylı kaydı iptal edebilir.

Personel tarafında kasa ofisi vardır: günlük özet, rezervasyon listesi (filtre + durum), masa ekleme/düzenleme. Üzerinde rezervasyon olan masa **silinmez**; yabancı anahtar `ON DELETE RESTRICT` olduğu için geçmiş bozulmasın diye masa **pasif** yapılır.

Yapılmayanlar (bilinçli sınır): online ödeme, SMS, Laravel/Symfony, ayrı menü tablosu. Ana sayfada imza içecek/tatlı şeridi vardır; bu statik görseldir, sipariş satılmaz.

---

## 2. Kullanılan teknolojiler

| Katman | Seçim | Neden |
| --- | --- | --- |
| Arayüz | HTML5, CSS3, Bootstrap 5, az miktar vanilla JS (`fetch`) | Ders iskeleti; tema Bootstrap’in üzerine yazılan `assets/css/style.css` |
| Sunucu | Saf PHP 8, framework yok | Ödev kuralı |
| Veri | MySQL 8 / MariaDB 10.4+ (phpMyAdmin) | `database.sql` tek dosyada şema + örnek veri |
| Erişim | PDO, `ATTR_EMULATE_PREPARES => false` | Gerçek prepared statement |
| Güvenlik | `e()`, CSRF, session, `password_hash` | Aşağıda ayrı bölüm |

Tüm sayfalar tek kapıdan girer: `require_once .../app/bootstrap.php`. Sayfa dosyasında `define('APP_INIT', true)` **yazılmaz**; sabiti bootstrap tanımlar. `app/` klasörü `.htaccess` ile tarayıcıya kapalıdır.

---

## 3. Veritabanı şeması (üç tablo)

Karakter seti `utf8mb4` / `utf8mb4_unicode_ci` (Türkçe diyakritik).

### 3.1 `yoneticiler`

Admin girişi. Şifre düz metin tutulmaz; `password_hash()` çıktısı (`sifre` VARCHAR 255). Kullanıcı adı `UNIQUE`.

Varsayılan hesap (yalnızca ders): `admin` / `admin123`.

### 3.2 `masalar`

Fiziksel masa: ad, kapasite (1–30, CHECK), konum `ENUM('ic','dis')`, durum `ENUM('aktif','pasif')`. Pasif masa müşteri listesinde çıkmaz; raporlarda adı durur.

### 3.3 `rezervasyonlar`

Bir kayıt = bir masa + bir tarih + bir saat aralığı + müşteri bilgisi + durum (`onaylandi` / `iptal` / `tamamlandi`).

- `rezervasyon_kodu CHAR(8)` benzersizdir. Ardışık `id` ile sorgulamak IDOR riski taşır; kod tahmin edilmesi daha zordur.
- `fk_rezervasyon_masa ... ON DELETE RESTRICT`: rezervasyonu olan masa `DELETE` edilemez (MySQL 1451). PHP tarafında `MasaRepository::sil()` kayıt varsa `pasif` yapar.
- Çakışma sorgusu için bileşik indeks: `(masa_id, tarih, durum, baslangic_saati)`.

Ayrıca dashboard için `v_gunluk_ozet` **view**’i vardır (tablo değildir): tarihe göre toplam / onaylı / iptal / tamamlanan ve onaylı misafir sayısı. View, o gün hiç kayıt yoksa satır döndürmez; panel bunu sıfır kabul eder.

---

## 4. Çakışma formülü

İki yarı açık aralık `[A_bas, A_bit)` ve `[B_bas, B_bit)` ancak şu koşulla kesişir:

```sql
durum = 'onaylandi'
AND baslangic_saati < :bitis
AND bitis_saati     > :baslangic
```

Bu tek satır, “tam aynı saat / içine gömülü / baştan taşan / sondan taşan / kapsayan” durumlarını ayrı `if`’lerle saymaya gerek bırakmaz. Sınırlarda `<` ve `>` kullanıldığı için **14:00’te biten** kayıttan sonra **14:00’te başlayan** kayıt kabul edilir (bitişik, çakışma yok).

Aynı koşul üç yerde aynı anlama gelir (kopya iş kuralı değil, aynı sorunun iki yüzü):

1. `MasaRepository::musaitMasalar()` — `NOT EXISTS` ile boş masaları listeler.  
2. `RezervasyonRepository::cakismaVarMi()` — tek masa için evet/hayır.  
3. `olustur()` içindeki kilitli SELECT — yazmadan hemen önce.

İptal ve tamamlandı kayıtları masayı kilitlemez. Admin eski bir iptali yeniden `onaylandi` yaparken `cakismaVarMi(..., $haricId)` çağrılır; `$haricId` kaydın kendisidir, yoksa kayıt kendi aralığıyla çakışır.

Dashboard’daki “akşam 19:00–21:00” sayısı da **yüzde değil**, aynı formülle o pencerede çakışan onaylı masa adedidir. Onay sayısını aktif masa sayısına bölüp “% doluluk” yazmak yanlış olur: aynı masa öğlen ve akşam ayrı satılır.

---

## 5. `FOR UPDATE` ve `olustur()`

“Önce bak, boşsa yaz” yetmez. İki kasiyer (veya iki tarayıcı) aynı milisaniyede `cakismaVarMi = false` görüp ikisi de INSERT ederse masa iki kez satılır.

`RezervasyonRepository::olustur()` bir **transaction** açar:

1. `SELECT ... FROM masalar WHERE id = :id FOR UPDATE` — masa satırı kilitlenir; aynı masa için ikinci istek bekler.  
2. Masa `aktif` mi, kapasite yeterli mi, taze okunur.  
3. Çakışma sorgusu **kilit altında** tekrarlanır (`FOR UPDATE` ile kilitli okuma; REPEATABLE READ anlık görüntüsüne güvenilmez).  
4. Boşsa INSERT, değilse hata dönülür; commit / rollback `Database::transaction()` içindedir.

Müşteri formundaki JavaScript yalnızca deneyim içindir: konsoldan veya `curl` ile atlanan her kural sunucuda yeniden doğrulanır.

---

## 6. Güvenlik

**PDO / SQL injection.** Kullanıcı değerleri sorgu metnine birleştirilmez; named parameter gider. Gerçek prepare açıktır (`EMULATE_PREPARES = false`). Aynı isimli parametreyi iki kez kullanmak HY093 verdiği için dashboard’da `:t1`, `:t2` gibi ayrı adlar vardır.

**XSS.** HTML’e basılan her metin `e()` → `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`. Masa adları JavaScript’te `innerHTML` ile birleştirilmez; `textContent` / `createElement` kullanılır.

**CSRF.** Değişiklik yapan her form (rezervasyon, iptal, admin durum, masa sil, çıkış) `csrf_field()` taşır; sunucu `hash_equals` ile bakar. GET iptal / çıkış yoktur: prefetch bir kaydı silmesin.

**PRG.** Başarılı yazmadan sonra `yonlendir(..., 303)` — F5 ikinci INSERT/UPDATE üretmez.

**Oturum (admin).** `session.use_strict_mode`, çerez adı `KAFESID`. `Auth::girisYap()` içinde `session_regenerate_id(true)` (session fixation). Kullanıcı yok / şifre yanlış tek mesajdır; kayıt yokken de sahte bcrypt üzerinde `password_verify` çalışır (zaman farkıyla kullanıcı listesi sızmasın). `Auth::kontrol()` yönlendirir ve `exit` eder; `header` deyip SQL’e devam edilmez. Çıkış POST + CSRF’dir; `session_destroy` yok ki müşteri formundaki CSRF token da gitmesin.

`app/config.php` doğrudan açılırsa `APP_INIT` yoksa 403 döner.

---

## 7. Müşteri ekranları

- **Ana sayfa (`index.php`):** kahraman görseli, imza menü şeridi (yerel JPEG), üç adımlı form (zaman → müsait masa → iletişim). Masa kartları `api/musait-masalar.php` JSON’undan dolar.  
- **Onay (`rezervasyon-onay.php`):** 303 ile gelir; kod session’daki `son_rezervasyon` özetindedir (adres çubuğuna yazılmaz). **Davetiyeyi yazdır** navbar/fotoğrafı gizler; kâğıtta kafe adı, adres, saatler, kod ve masa özeti kalır.  
- **Sorgula (`rezervasyon-sorgula.php`):** kod ile tek kayıt + iptal (modal, POST); telefon ile liste (iptal yok — numarayı bilen herkese yetki verilmez). Boş / bulunamadı durumları açıklayıcı kutudur.  
- **Hakkımızda:** kısa metin ve örnek iletişim (`KAFE_ADRES` gerçek işletme değildir).

Türkçe arayüz diyakritik kullanır (İ, ş, ğ, ü, ö, ç). PHP/SQL tanımlayıcıları ASCII’dir (`masa_id`, `cakismaVarMi`).

---

## 8. Admin ekranları

Adres: `http://localhost/kafe-rezervasyon/admin/login.php`

- **Giriş:** sidebar yok (yetki henüz yok).  
- **Günlük özet:** bugünün toplam / onaylı / kalan / iptal / tamamlanan sayıları; masa doluluğu ayrı sayılar (yüzde kartları “bugün kayıt olan masa / aktif masa” ve “şu an dolu” içindir, otel doluluğu değildir). Ek sayılar: şu an boş masa, 19:00–21:00 çakışan masa, önümüzdeki 7 gün onaylı adet; yaklaşan onaylılar; bugünün tablosu.  
- **Rezervasyonlar:** GET süzgeç (tarih, durum, masa), kayıt sayacı, yapışkan başlıklı tablo, müşteri notu rozeti. Onayla / Tamamlandı / İptal **POST + CSRF**. İptalde `confirm`.  
- **Masalar:** ekle / düzenle / pasif / sil. Silmede rezervasyon varsa pasif.  
- Boş listelerde “kayıt yok” satırı yerine yönlendiren boş durum kutusu vardır.

---

## 9. Kurulum (XAMPP)

1. ZIP’i açın. Kök klasör **`kafe-rezervasyon/`** olmalıdır (`database.sql`, `app/`, `admin/`, `index.php`, `RAPOR.md` bu klasörün içindedir).  
2. Klasörü `C:\xampp\htdocs\kafe-rezervasyon` altına kopyalayın. İç içe `kafe-rezervasyon\kafe-rezervasyon` yapmayın.  
3. XAMPP’te **Apache** ve **MySQL**’i başlatın.  
4. phpMyAdmin → **Import** → `C:\xampp\htdocs\kafe-rezervasyon\database.sql` → Go.  
   veya: `mysql -u root -p < C:\xampp\htdocs\kafe-rezervasyon\database.sql`  
5. Tarayıcı: `http://localhost/kafe-rezervasyon/`  
6. Panel: `http://localhost/kafe-rezervasyon/admin/login.php` — `admin` / `admin123`

VS Code’da **Dosya → Klasörü Aç** ile `htdocs` değil, `kafe-rezervasyon` klasörünün kendisi açılmalıdır.

Hoca RAR istiyorsa WinRAR ile aynı kök kuralı: arşivde tek üst klasör `kafe-rezervasyon/`, `.git` yok.

`app/config.php` varsayılanı XAMPP içindir: `127.0.0.1`, kullanıcı `root`, şifre boş. Canlı sunucuda bu kabul edilemez.

---

## 10. Klasör özeti

| Yol | Görev |
| --- | --- |
| `app/bootstrap.php` | Oturum, APP_INIT, autoload benzeri require sırası |
| `app/db.php` | PDO singleton |
| `app/helpers.php` | `e()`, CSRF, 303, tarih/saat biçimi |
| `app/Auth.php` | Giriş / çıkış / `kontrol()` |
| `app/MasaRepository.php` | Masa CRUD, müsait liste |
| `app/RezervasyonRepository.php` | Çakışma, `olustur()`, iptal, kod |
| `api/musait-masalar.php` | JSON masa listesi |
| `admin/` | Panel |
| `assets/` | Tema, yerel font ve JPEG |
| `database.sql` | Şema + örnek kayıt (`RZDEMO01` …) |
| `RAPOR.md` | Bu dosya |

Örnek rezervasyon koduyla deneme: sorgula sayfasına `RZDEMO01` yazılabilir (seed, bugünün tarihi).
