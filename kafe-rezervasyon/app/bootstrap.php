<?php
/**
 * =====================================================================
 *  KAFE MASA REZERVASYON SISTEMI - ONYUKLEME (BOOTSTRAP)
 * ---------------------------------------------------------------------
 *  Uygulamanin TEK giris noktasi. Tarayiciya acilan her PHP dosyasi
 *  (index.php, admin/login.php, api/musait-masalar.php ...) ilk satirda
 *  sadece sunu yazar:
 *
 *      require_once __DIR__ . '/app/bootstrap.php';
 *
 *  Bu dosya cagrildiginda su sira isler:
 *      1. APP_INIT sabiti tanimlanir  (dogrudan erisim kalkani)
 *      2. config.php yuklenir         (sabitler)
 *      3. Hata gosterimi ayarlanir    (APP_DEBUG'a gore)
 *      4. Zaman dilimi sabitlenir     (Europe/Istanbul)
 *      5. Oturum guvenli sekilde baslatilir
 *      6. db.php ve helpers.php yuklenir
 *
 *  SIRA ONEMLIDIR: config.php'den once hata ayarini yapamayiz (APP_DEBUG
 *  orada tanimli), oturumu baslatmadan once cerez ayarlarini vermeliyiz.
 * =====================================================================
 */

// ---------------------------------------------------------------------
// Bu dosya iki kez cagrilirsa (ornegin bir sayfa hem bootstrap'i hem de
// bootstrap cagiran baska bir dosyayi require ederse) sabit yeniden
// tanimlanmaya calisilir ve PHP uyari verir. Guard bunu engeller.
// ---------------------------------------------------------------------
if (defined('APP_INIT')) {
    return;
}

// =====================================================================
// 1) APP_INIT - DOGRUDAN ERISIM KALKANI
// ---------------------------------------------------------------------
// app/ klasorundeki her dosyanin basinda su kontrol vardir:
//
//     if (!defined('APP_INIT')) { http_response_code(403); die(...); }
//
// Yani config.php, db.php ve helpers.php ancak BU dosya uzerinden
// yuklendiklerinde calisirlar. Tarayicidan
//     http://localhost/kafe-rezervasyon/app/config.php
// adresi acilirsa APP_INIT tanimli olmadigi icin betik hemen durur.
//
// ---------------------------------------------------------------------
// ".htaccess zaten app/ klasorunu kapatiyor, bu kontrol fazlalik degil mi?"
//
// Hayir. .htaccess SADECE su sartlarda calisir:
//   - Sunucu Apache ise. Nginx .htaccess dosyasini OKUMAZ BILE; dosya
//     orada durur ama hicbir etkisi yoktur. Projenizi bir VPS'e tasidiginizda
//     ya da bir hosting Nginx'e gectiginde koruma sessizce kaybolur.
//   - Apache yapilandirmasinda "AllowOverride All" (veya en az AllowOverride
//     Limit) tanimli ise. Performans icin "AllowOverride None" yazan
//     sunucularda .htaccess tamamen yok sayilir - yine hata vermez,
//     sadece calismaz.
//   - PHP-FPM/CGI kurulumlarinda bazi yapilandirmalar dosyayi Apache'ye
//     ugratmadan dogrudan calistirabilir.
//
// Guvenlikte tek katmana guvenilmez (defense in depth / savunma
// derinligi). .htaccess birinci kapidir; APP_INIT ikinci kapidir. Birinci
// kapi sessizce devre disi kalsa bile ikincisi ayaktadir.
//
// Kalkanin engelledigi somut senaryo: dosya dogrudan calistirilirsa
// config.php icindeki define() cagrilari calisir ve bir hata mesaji
// icinde DB_PASS degeri ekrana dusebilir.
// =====================================================================
define('APP_INIT', true);

// APP_KOK: proje kok klasorunun mutlak yolu. __DIR__ her zaman ICINDE
// BULUNULAN dosyanin klasorunu verir; calisma dizinine (getcwd) bagimli
// olmadigi icin include yollari nereden cagrilirsa cagrilsin dogru calisir.
define('APP_KOK', dirname(__DIR__));
define('APP_DIZIN', __DIR__);


// =====================================================================
// 2) YAPILANDIRMA
// =====================================================================
require_once APP_DIZIN . '/config.php';


// =====================================================================
// 3) HATA GOSTERIMI
// ---------------------------------------------------------------------
// display_errors = On, canli sunucuda ciddi bir bilgi sizintisidir.
// Tipik bir PHP hata mesaji sunlari ele verir:
//   - Sunucudaki mutlak dosya yolu (C:\xampp\htdocs\... veya /var/www/...)
//   - Kullanilan kutuphane ve surumler
//   - Yigin izinde fonksiyonlara gecirilen ARGUMANLAR (sifreler dahil)
// Saldirgan icin bu, sistemin haritasini cikarmak demektir.
//
// Canli ortamda hatalari EKRANA degil LOG'a yaziyoruz: bilgi kaybolmuyor,
// sadece disari sizmiyor.
// =====================================================================
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
} else {
    error_reporting(E_ALL);      // hatalari yine de TOPLA...
    ini_set('display_errors', '0');  // ...ama kullaniciya GOSTERME
    ini_set('log_errors', '1');      // sunucu log dosyasina yaz
}


// =====================================================================
// 4) ZAMAN DILIMI
// ---------------------------------------------------------------------
// Bu sistemin tamami saat karsilastirmasi uzerine kuruludur:
//   - "Gecmis tarihe rezervasyon alinamaz"   -> date('Y-m-d') ile karsilastirma
//   - "Bugunun rezervasyonlari"              -> CURDATE() ile eslesme
//   - "Kafe su anda acik mi?"                -> date('H:i') kontrolu
//
// PHP'nin zaman dilimi ayarlanmazsa varsayilan UTC'dir. Turkiye UTC+3
// oldugu icin aradaki fark 3 saattir ve su somut hatalar olusur:
//   - Saat 01:00'de (Istanbul) yapilan bir rezervasyon, sunucu icin hala
//     ONCEKI GUN 22:00'dir. Musteri bugune kayit yaptirir, admin panelinde
//     dunku listede gorunur.
//   - "Gecmis tarih" kontrolu gece yarisi ile sabah 03:00 arasinda yanlis
//     karar verir; bugune rezervasyon almayi reddeder.
//
// Ayrica MySQL'in kendi zaman dilimi de ayridir. NOW() ve CURDATE()
// sunucunun ayarini kullanir. Bu yuzden PHP ile MySQL'in ayni gunu
// gordugunden emin olmak gerekir; en saglam yontem tarih/saat degerlerini
// PHP'de uretip parametre olarak gondermektir (Adim 3'te boyle yapacagiz).
// =====================================================================
date_default_timezone_set('Europe/Istanbul');


// =====================================================================
// 5) OTURUM (SESSION) - GUVENLI BASLATMA
// =====================================================================
if (session_status() === PHP_SESSION_NONE) {

    // -----------------------------------------------------------------
    // 5.a) session.use_strict_mode = 1
    // -----------------------------------------------------------------
    // ENGELLEDIGI SALDIRI: Session Fixation (oturum sabitleme)
    //
    // Saldirgan kurbana su baglantiyi gonderir:
    //     http://site/index.php?PHPSESSID=ABC123
    // ya da bir XSS ile cereze ABC123 yazar. Strict mode KAPALIYKEN PHP,
    // daha once hic uretmedigi bu kimligi kabul eder ve "ABC123" adiyla
    // YENI bir oturum acar. Kurban giris yapinca oturum admin yetkisi
    // kazanir - ve saldirgan da ayni ABC123 kimligini bildigi icin artik
    // admin olarak icerdedir.
    //
    // Strict mode ACIKKEN PHP, sunucuda karsiligi olmayan bir oturum
    // kimligini reddeder ve kendi urettigi yeni bir kimlik atar.
    // Saldirganin dayattigi deger kullanilmaz.
    //
    // NOT: Giris basarili olduktan sonra Adim 4'te ayrica
    // session_regenerate_id(true) cagiracagiz. Ikisi birlikte, "giris
    // oncesi kimlik giris sonrasinda da gecerli olmasin" garantisini verir.
    ini_set('session.use_strict_mode', '1');

    // Oturum kimligini SADECE cerezde tasi, URL'e yazma.
    // trans_sid acik olsaydi adres su hale gelirdi:
    //     index.php?PHPSESSID=9f2c...
    // Kullanici bu adresi kopyalayip arkadasina gonderdiginde oturumunu
    // da gondermis olurdu. Ayrica adres, tiklanan dis sitelerin Referer
    // basliginda ve sunucu erisim log'larinda gorunur hale gelirdi.
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');

    // Cerez adini varsayilan PHPSESSID'den degistiriyoruz (config.php).
    session_name(SESSION_ADI);

    // HTTPS uzerinden mi geliyoruz? XAMPP'ta genelde hayir (http://localhost),
    // canlida evet. Kontrolu otomatik yapip "secure" bayragini ona gore
    // ayarliyoruz; elle true yazilsaydi localhost'ta oturum hic acilmazdi.
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? null) == 443)
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_set_cookie_params([
        // 0 = "tarayici kapaninca sil" (oturum cerezi). Diske yazilmaz;
        // ortak kullanilan bilgisayarlarda oturumun asili kalmasini onler.
        'lifetime' => 0,

        // Cerezin gonderilecegi yol. '/' tum site.
        'path'     => '/',

        // domain bos: cerez sadece tam olarak bu alan adina gider,
        // alt alan adlarina (baska.site.com) sizmaz.
        'domain'   => '',

        // ---------------------------------------------------------
        // secure: Cerez SADECE HTTPS uzerinden gonderilir.
        // ENGELLEDIGI SALDIRI: ayni Wi-Fi agindaki birinin trafigi
        // dinleyip (sniffing) oturum cerezini duz metin olarak okumasi.
        // Cerez bir kez calindiginda saldirgan sifreyi bilmeden oturumu
        // devralir (session hijacking).
        // ---------------------------------------------------------
        'secure'   => $https,

        // ---------------------------------------------------------
        // httponly: Cerez JavaScript'e GORUNMEZ. document.cookie
        // ciktisinda yer almaz.
        // ENGELLEDIGI SALDIRI: XSS ile oturum calma. Sayfaya kotu bir
        // script enjekte edilse bile cerezi okuyup disari yollayamaz.
        // Bu, e() fonksiyonunun (XSS'i onlemek) yanindaki IKINCI
        // savunma katmanidir: birinci katman delinse bile oturum durur.
        // ---------------------------------------------------------
        'httponly' => true,

        // ---------------------------------------------------------
        // samesite: Cerezin BASKA bir siteden tetiklenen isteklere
        // eklenip eklenmeyecegini belirler.
        //   'Strict' : baska siteden gelen HICBIR istege eklenmez.
        //              Cok guvenli ama kullanici bir e-postadaki
        //              baglantiyla siteye geldiginde "cikis yapmis"
        //              gorunur - rahatsiz edicidir.
        //   'Lax'    : Ust duzey GEZINME (link tiklama, adres cubugu)
        //              ile gelen GET isteklerine eklenir; ama baska bir
        //              sitenin gonderdigi POST isteklerine EKLENMEZ.
        //   'None'   : Her zaman eklenir (secure zorunlu).
        //
        // 'Lax' secmemizin sebebi: yukaridaki CSRF senaryosunda
        // (helpers.php, bolum 2) saldirganin gizli formu POST gonderir;
        // SameSite=Lax bu isteme cerezi eklemez ve saldiri daha sunucuya
        // ulasmadan coker.
        //
        // Yine de CSRF token'ini KALDIRMIYORUZ: SameSite bir TARAYICI
        // ozelligidir. Eski tarayicilar bunu desteklemez, ayrica saldiri
        // tarayici disi araclarla (ornegin kullanicinin bilgisayarindaki
        // bir program) yapilabilir. Token sunucu tarafli kesin kontroldur.
        // ---------------------------------------------------------
        'samesite' => 'Lax',
    ]);

    // Oturum verisini 30 dakika hareketsizlikten sonra cope at.
    // Uzun omurlu oturum, calinan bir cerezin daha uzun sure ise
    // yaramasi demektir.
    ini_set('session.gc_maxlifetime', '1800');

    // Komut satirinda (CLI) cerez gonderilemez; session_start uyari
    // verebilir. Test betikleri icin @ ile bastiriyoruz, web tarafinda
    // zaten uyari olusmaz.
    if (PHP_SAPI === 'cli') {
        @session_start();
    } else {
        session_start();
    }
}


// =====================================================================
// 6) CEKIRDEK DOSYALAR
// ---------------------------------------------------------------------
// require_once kullaniyoruz:
//   - require (include degil): dosya bulunamazsa olumcul hata verip
//     durur. include sadece uyari verir ve kod eksik tanimlarla calismaya
//     devam eder; "Call to undefined function e()" gibi anlasilmasi zor
//     hatalara yol acar.
//   - _once: ayni dosya iki kez yuklenirse "Cannot redeclare function"
//     olumcul hatasi olusur.
// =====================================================================
require_once APP_DIZIN . '/db.php';
require_once APP_DIZIN . '/helpers.php';
