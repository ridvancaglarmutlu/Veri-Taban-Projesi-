# Kahve Durağı Masa Rezervasyon Sistemi

Veritabanı dersi proje raporu  
Hazırlayan: Rıdvan Çağlar Mutlu

Kafe adı: **Kahve Durağı**  
Çalışma saatleri: **09:00–23:00**  
Çalıştığım ortam: XAMPP (Apache + MySQL/MariaDB + PHP 8)  
Kod: saf PHP, PDO, Bootstrap 5. Framework kullanmadım, ödev de zaten öyle istiyordu.

Bu ödevde bir kafenin masalarını tarayıcıdan ayırtılabilir hale getirmeye çalıştım. Müşteri tarih/saat/kişi sayısını seçiyor, boş masalardan birini alıyor, 8 karakterlik bir rezervasyon kodu geliyor. O kodla (veya telefonla) kaydı sorguluyor; aynı gün onaylı kaydı iptal edebiliyor. Personel tarafında da günlük özet, rezervasyon listesi ve masa ekleme/düzenleme var.

Ana sayfada latte, brownie falan duruyor ama onlar statik fotoğraf. Sipariş satmıyorum, ayrı menü tablosu da yok. Online ödeme ve SMS de yok; ders için gerekmiyordu, vaktimi çakışma kontrolüne ve kaydın düzgün yazılmasına harcadım.

Ekran görüntüleri: `ust-musteri.png`, `ust-admin.png` (Word’un sonunda da var).

---

## Ne yaptım, kısaca

Müşteri `index.php` üzerinden rezervasyon bırakıyor. Kayıt oluşunca kod session’da duruyor, adres çubuğuna yazılmıyor; onay sayfasına 303 ile gidiliyor. Personel `admin/login.php` ile giriyor.

Üzerinde rezervasyon olan masayı silmiyorum. Veritabanında `ON DELETE RESTRICT` var zaten (MySQL 1451), PHP tarafında da `MasaRepository::sil()` kayıt varsa masayı **pasif** yapıyor. Geçmiş kayıtlar duruyor, müşteri listesinde pasif masa çıkmıyor.

---

## Kullandığım şeyler

Arayüz HTML + CSS + Bootstrap 5, biraz da vanilla JS (`fetch`). Tema `assets/css/style.css`. Sunucu tarafı saf PHP 8. Veri MySQL 8 / MariaDB (phpMyAdmin’den `database.sql` import). Sorgular PDO, `ATTR_EMULATE_PREPARES => false` — emüle prepare istemedim, gerçek prepared statement olsun diye.

Her sayfa `app/bootstrap.php` ile açılıyor. Sayfa dosyasına `define('APP_INIT', true)` yazmıyorum, sabiti bootstrap koyuyor. `app/` klasörü `.htaccess` ile tarayıcıya kapalı.

---

## Veritabanı: üç tablo

Karakter seti `utf8mb4` / `utf8mb4_unicode_ci`. Türkçe karakterler için.

**yoneticiler** — admin girişi. Şifre düz metin değil, `password_hash()` çıktısı (`sifre` VARCHAR 255). Kullanıcı adı UNIQUE. Ders için varsayılan hesap: `admin` / `admin123`.

**masalar** — masa adı, kapasite (1–30, CHECK), konum `ENUM('ic','dis')`, durum `ENUM('aktif','pasif')`.

**rezervasyonlar** — bir masa + bir tarih + bir saat aralığı + müşteri bilgisi + durum (`onaylandi` / `iptal` / `tamamlandi`).

Rezervasyon kodu `CHAR(8)` ve unique. Sorgulamada ardışık `id` kullanmadım; kod tahmin etmek biraz daha zor. Çakışma sorgusu için bileşik indeks: `(masa_id, tarih, durum, baslangic_saati)`.

Dashboard’da `v_gunluk_ozet` diye bir **view** var, dördüncü tablo değil. Tarihe göre toplam / onaylı / iptal / tamamlanan ve onaylı misafir sayısı. O gün hiç kayıt yoksa view satır döndürmüyor, panel sıfır kabul ediyor.

---

## Çakışma (en çok uğraştığım kısım)

İki rezervasyon aynı masada, aynı günde, saatleri kesişiyorsa ikisini birden kabul etmemek lazım. İlk başta “aynı saat mi” diye bakmayı düşündüm, yetmiyor. Biri 13:00–16:00, diğeri 14:00–15:00 olunca da çakışıyor.

Kullandığım koşul (A başlangıç < B bitiş **ve** A bitiş > B başlangıç):

```sql
durum = 'onaylandi'
AND baslangic_saati < :bitis
AND bitis_saati     > :baslangic
```

Yani `A_start < B_end AND A_end > B_start`. `<` ve `>` olduğu için 14:00’te biten kayıttan sonra 14:00’te başlayan kayıt geçiyor. Bitişik, çakışma yok. İptal ve tamamlandı kayıtları masayı tutmuyor.

Bu koşul üç yerde var:

1. `MasaRepository::musaitMasalar()` — `NOT EXISTS` ile boş masalar  
2. `RezervasyonRepository::cakismaVarMi()` — tek masa evet/hayır  
3. `olustur()` içindeki kilitli SELECT — INSERT’ten hemen önce

Admin eski bir iptali tekrar onaylarken `cakismaVarMi(..., $haricId)` çağırıyorum. `$haricId` kaydın kendisi; yoksa kayıt kendi saatiyle çakışmış görünüyor.

Paneldeki “akşam 19:00–21:00” sayısı yüzde değil. Aynı formülle o pencerede çakışan onaylı masa adedi. Onay sayısını aktif masa sayısına bölüp doluluk yazmak yanlış olurdu; aynı masa öğlen ve akşam ayrı satılıyor.

---

## FOR UPDATE — neden “önce bak sonra yaz” yetmedi

İki tarayıcı aynı anda boş görüp ikisi de INSERT ederse masa iki kez satılıyor. Bunu düşününce `olustur()` içine transaction koydum.

Sıra kabaca şöyle:

1. `SELECT ... FROM masalar WHERE id = :id FOR UPDATE` — masa satırı kilitleniyor, aynı masa için ikinci istek bekliyor.  
2. Masa hâlâ aktif mi, kapasite yetiyor mu, tekrar bakıyorum.  
3. Çakışmayı kilit altında bir kez daha soruyorum (yine `FOR UPDATE`). REPEATABLE READ snapshot’ına güvenmedim.  
4. Boşsa INSERT. Commit / rollback `Database::transaction()` içinde.

Formdaki JavaScript sadece kullanım kolaylığı. Konsoldan veya curl ile atlanan her kuralı sunucu tekrar kontrol ediyor.

---

## Güvenlik tarafı

SQL’e kullanıcı değerini string birleştirmiyorum, named parameter gidiyor. Dashboard’da aynı parametre adını iki kez kullanınca HY093 aldım, o yüzden `:t1`, `:t2` yaptım.

HTML’e basılan metin `e()` üzerinden gidiyor, o da `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`. JS tarafında masa adını `innerHTML` ile yapıştırmadım, `textContent` / `createElement`.

Rezervasyon, iptal, admin durum, masa sil, çıkış — hepsi POST ve `csrf_field()`. Sunucu `hash_equals` ile bakıyor. GET ile iptal/çıkış yok; tarayıcı prefetch silmesin diye.

Başarılı yazmadan sonra `yonlendir(..., 303)`. F5 basınca ikinci kayıt oluşmasın.

Admin oturumu: `session.use_strict_mode`, çerez adı `KAFESID`. Girişte `session_regenerate_id(true)`. Kullanıcı yok / şifre yanlış aynı mesaj. Kullanıcı yokken de sahte bcrypt üzerinde `password_verify` çalışıyor, süre farkından kullanıcı listesi sızmasın diye. `Auth::kontrol()` yönlendirip `exit` ediyor. Çıkış POST + CSRF; `session_destroy` yok çünkü müşteri formundaki CSRF token da gider.

`app/config.php` tek başına açılırsa `APP_INIT` yoksa 403.

---

## Müşteri sayfaları

Ana sayfa (`index.php`): üstte salon fotoğrafı, imza menü şeridi (yerel JPEG), form üç adım — zaman, müsait masa, iletişim. Masa kartları `api/musait-masalar.php` JSON’undan doluyor.

Onay (`rezervasyon-onay.php`): kod `son_rezervasyon` session özetinde. Yazdırınca navbar ve fotoğraflar gizleniyor, kâğıtta kafe adı, adres, saatler, kod ve masa kalıyor.

Sorgula (`rezervasyon-sorgula.php`): kod ile tek kayıt + iptal (modal, POST). Telefon ile liste geliyor ama iptal yok; numarayı bilen herkese yetki vermek istemedim. Seed’den denemek için `RZDEMO01` yazılabiliyor (bugünün tarihi).

Hakkımızda sayfasındaki adres örnek, gerçek işletme değil (`KAFE_ADRES`).

Arayüz Türkçe (İ, ş, ğ…). PHP/SQL isimleri ASCII: `masa_id`, `cakismaVarMi`.

---

## Admin

Adres: `http://localhost/kafe-rezervasyon/admin/login.php`  
Giriş: `admin` / `admin123`

Login’de sidebar yok, daha yetki yok. İçeri girince günlük özet: toplam, onaylı, kalan, iptal, tamamlanan. Masa doluluğu ayrı; yüzde kartları otel doluluğu değil, “bugün kayıt olan masa / aktif masa” ve “şu an dolu”. Bir de şu an boş masa, 19:00–21:00 çakışan masa, önümüzdeki 7 gün onaylı sayısı var.

Rezervasyonlar sayfasında GET filtre (tarih, durum, masa). Onayla / Tamamlandı / İptal POST + CSRF. İptalde `confirm`. Masalar: ekle, düzenle, pasif, sil. Liste boşsa tek satır “kayıt yok” yerine boş kutu çıkıyor.

---

## XAMPP’te ayağa kaldırma

ZIP’i açınca kök klasör `kafe-rezervasyon/` olmalı (`database.sql`, `app/`, `admin/`, `index.php`, `RAPOR.md` içinde). Bunu `C:\xampp\htdocs\kafe-rezervasyon` altına koydum. İç içe `kafe-rezervasyon\kafe-rezervasyon` olmasın.

Apache ve MySQL’i açıp phpMyAdmin’den `database.sql` import. Komut satırından da olur: `mysql -u root -p < C:\xampp\htdocs\kafe-rezervasyon\database.sql`

Site: `http://localhost/kafe-rezervasyon/`  
Panel: `http://localhost/kafe-rezervasyon/admin/login.php`

VS Code’da htdocs’u değil, `kafe-rezervasyon` klasörünün kendisini açmak daha rahat. Hoca RAR isterse arşivde tek üst klasör `kafe-rezervasyon/`, `.git` olmasın.

`app/config.php` varsayılanı XAMPP: `127.0.0.1`, kullanıcı `root`, şifre boş. Canlıya koyulacak bir ayar değil.

---

## Dosyalar (nerede ne var)

`app/bootstrap.php` — oturum, APP_INIT, require sırası  
`app/db.php` — PDO  
`app/helpers.php` — `e()`, CSRF, 303, tarih/saat  
`app/Auth.php` — giriş / çıkış / `kontrol()`  
`app/MasaRepository.php` — masa CRUD, müsait liste  
`app/RezervasyonRepository.php` — çakışma, `olustur()`, iptal, kod  
`api/musait-masalar.php` — JSON  
`admin/` — panel  
`assets/` — css, font, jpeg  
`database.sql` — şema + örnek kayıt (`RZDEMO01` …)  
`RAPOR.md` — bu dosya
