<?php
/**
 * =====================================================================
 *  KAFE MASA REZERVASYON SISTEMI - YAPILANDIRMA
 * ---------------------------------------------------------------------
 *  Bu dosya uygulamanin TUM ayarlarini tek yerde toplar. Veritabani
 *  bilgisi, calisma saati, slot suresi gibi degerler kod icine dagitilmaz;
 *  cunku dagitilirsa "acilis saatini 08:00 yapalim" dendiginde 10 ayri
 *  dosyada arama yapmak gerekir ve bir tanesi mutlaka unutulur.
 *
 *  Buradaki degerler define() ile SABIT olarak tanimlanir. Sabitin
 *  degiskene gore avantaji: bir kez atanir, sonradan kazara degistirilemez
 *  ve global kapsamdadir (fonksiyon icinde "global" demeye gerek kalmaz).
 * =====================================================================
 */

// ---------------------------------------------------------------------
// GIRIS KONTROLU (APP_INIT guard)
// Bu dosya sadece bootstrap.php uzerinden yuklenmelidir. Tarayicidan
// dogrudan .../app/config.php cagrilirsa asagidaki satir calismayi
// keser. Ayrintili aciklama bootstrap.php icinde.
// ---------------------------------------------------------------------
if (!defined('APP_INIT')) {
    http_response_code(403);
    die('Doğrudan erişim engellendi.');
}


// =====================================================================
// 1) VERITABANI BAGLANTI BILGILERI
// =====================================================================

// Neden 'localhost' degil de '127.0.0.1'?
//   Windows/XAMPP'ta ikisi de ayni kapiya cikar (TCP 3306).
//   Linux'ta ise 'localhost' yazmak MySQL surucusunu TCP yerine Unix
//   soketi kullanmaya zorlar; soket yolu farkliysa "connection refused"
//   hatasi alirsiniz. '127.0.0.1' yazmak her iki isletim sisteminde de
//   ayni davranisi (TCP) garanti eder.
define('DB_HOST', '127.0.0.1');
define('DB_PORT', 3306);
define('DB_NAME', 'kafe_rezervasyon');

// XAMPP kurulumu MySQL'i "root" kullanicisi ve BOS sifre ile getirir.
//
// Bu neden kabul edilebilir?
//   Cunku XAMPP'taki MySQL sadece kendi bilgisayarinizi dinler
//   (bind-address = 127.0.0.1). Disaridan kimse baglanamaz, dolayisiyla
//   bos sifre yerel gelistirme icin pratik bir kolayliktir.
//
// Bu neden CANLI SUNUCUDA felakettir?
//   - Sunucu disari acik oldugu anda root/bos sifre demek, internetteki
//     herkesin veritabanina tam yetkiyle girmesi demektir.
//   - "root" kullanicisi DROP DATABASE dahil her seyi yapabilir. Uygulama
//     ise sadece SELECT/INSERT/UPDATE/DELETE'e ihtiyac duyar. En az yetki
//     ilkesi (least privilege) geregi canliya cikarken sunu yapmalisiniz:
//
//       CREATE USER 'kafe_app'@'localhost' IDENTIFIED BY 'uzun-rastgele-sifre';
//       GRANT SELECT, INSERT, UPDATE, DELETE
//         ON kafe_rezervasyon.* TO 'kafe_app'@'localhost';
//
//     Boylece SQL injection'a benzer bir acik bulunsa bile saldirgan
//     tablo silemez, baska veritabanlarini goremez.
//   - Gercek projelerde bu degerler dosyaya degil ortam degiskenine
//     (getenv) yazilir ki sifre git deposuna dusmesin.
define('DB_USER', 'root');
define('DB_PASS', '');

// utf8mb4: Turkce karakterler ve emoji dahil her seyi saklayabilen
// karakter seti. database.sql ile birebir ayni olmali; aksi halde
// Turkce karakter iceren masa adlari ve musteri isimleri veritabanina
// soru isareti olarak yazilir - ve bu kayip geri alinamaz.
define('DB_CHARSET', 'utf8mb4');


// =====================================================================
// 2) UYGULAMA SABITLERI
// =====================================================================

define('SITE_ADI', 'Kahve Durağı');
define('SITE_SLOGAN', 'Masanı ayırt, sıra bekleme.');

// --- Calisma saatleri -------------------------------------------------
// Rezervasyon formundaki saat secenekleri bu araliga gore uretilir ve
// sunucu tarafinda da bu araliga gore DOGRULANIR.
//
// Onemli: Sadece <select> icindeki secenekleri sinirlamak GUVENLIK
// DEGILDIR. Kullanici tarayici konsolundan ya da curl ile 03:00 degerini
// gonderebilir. HTML tarafi kullanici kolayligi, PHP tarafi ise asil
// kontroldur. (helpers.php > saat_araliginda_mi)
define('KAFE_ACILIS',  '09:00');
define('KAFE_KAPANIS', '23:00');

// Saat seceneklerinin kac dakikalik adimlarla uretilecegi.
// 30 -> 09:00, 09:30, 10:00 ... seklinde ilerler.
define('SLOT_DAKIKA', 30);

// Bir rezervasyonun varsayilan suresi. Musteri 19:00'i sectiginde
// bitis saati otomatik 21:00 olur. Cakisma kontrolu bu aralik uzerinden
// yapilir (bkz. database.sql, bolum 6).
define('REZERVASYON_SURESI_DAKIKA', 120);

// --- Kapasite ve tarih sinirlari --------------------------------------
// Veritabanindaki CHECK kisiti ile ayni deger (chk_kisi_sayisi).
// Ayni kurali iki katmanda tutmak bilincli bir tekrardir: veritabani son
// savunma hatti, PHP ise kullaniciya anlamli hata mesaji veren katmandir.
define('MAX_KISI_SAYISI', 30);
define('MIN_KISI_SAYISI', 1);

// Bugunden itibaren en fazla kac gun sonrasina rezervasyon alinabilir.
// Sinirsiz birakmak, birinin 2049 yilina 10.000 kayit atmasina izin verir.
define('MAX_ILERI_GUN', 60);

// --- CSRF ------------------------------------------------------------
// Token'in session icinde hangi anahtarla, formda hangi input adiyla
// tutulacagi. Tek yerde tanimli olmasi, isim degistirmek gerektiginde
// tum formlarin birlikte guncellenmesini saglar. (helpers.php)
define('CSRF_SESSION_ANAHTARI', 'csrf_token');
define('CSRF_ALAN_ADI',         '_token');

// Session cerezinin adi. Varsayilan "PHPSESSID" adini degistirmek
// saldirganin hangi teknolojiyi kullandiginizi tahmin etmesini bir nebze
// zorlastirir. Tek basina guvenlik saglamaz (security through obscurity),
// ama bedava bir ek katmandir.
define('SESSION_ADI', 'KAFESID');


// =====================================================================
// 3) HATA AYIKLAMA BAYRAGI
// ---------------------------------------------------------------------
// true  -> Gelistirme: hatalar ekrana yazilir, SQL mesajlari gorunur.
// false -> Canli ortam: kullaniciya genel mesaj, ayrinti sadece log'a.
//
// Canliya cikarken bu degeri false yapmayi UNUTMAYIN. Acik kalirsa bir
// PDO hata mesaji ekranda su satiri gosterebilir:
//   SQLSTATE[HY000] [1045] Access denied for user 'kafe_app'@'localhost'
// Bu satir bile saldirgana kullanici adini ve sunucu yapisini verir;
// bazi yapilandirmalarda yigin izi (stack trace) ile birlikte dosya
// yollari ve hatta baglanti sifresi sizabilir.
// =====================================================================
define('APP_DEBUG', true);
