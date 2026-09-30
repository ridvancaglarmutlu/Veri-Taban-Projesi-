<?php
/**
 * =====================================================================
 *  KAFE MASA REZERVASYON SISTEMI - MUSTERI ANA SAYFASI
 * ---------------------------------------------------------------------
 *  Bu dosya iki isi birden yapar:
 *
 *    GET  -> Karsilama bolumu + rezervasyon formunu gosterir.
 *    POST -> Formu dogrular, rezervasyonu kaydeder ve YONLENDIRIR.
 *
 *  Tek dosyada toplamanin sebebi, hata durumunda formu kullanicinin
 *  girdikleriyle birlikte yeniden gosterebilmektir. Ayri bir
 *  "kaydet.php" yazilsaydi, hatali girdileri geri tasimak icin
 *  session'a yazip index.php'de okumak gerekirdi; bu olcekte
 *  gereksiz karmasiklik olurdu.
 *
 *  DOSYANIN AKISI (yukaridan asagiya okunabilir olmasi icin bilincli
 *  olarak bu sirada yazildi):
 *    1. Cekirdek yukleme
 *    2. Sabitler ve secenek listeleri
 *    3. Basari ekrani verisinin okunmasi (tek seferlik)
 *    4. POST islemesi + SUNUCU TARAFI DOGRULAMA
 *    5. POST-Redirect-GET
 *    6. HTML ciktisi
 * =====================================================================
 */

// ---------------------------------------------------------------------
// 1) CEKIRDEK
// bootstrap.php; APP_INIT sabitini tanimlar, config + db + helpers
// dosyalarini yukler ve oturumu baslatir. Depo siniflari bootstrap'in
// PARCASI DEGILDIR: her sayfa sadece ihtiyaci olani yukler, boylece
// ornegin sadece masa listeleyen bir sayfa rezervasyon SQL'ini bellege
// almaz.
// ---------------------------------------------------------------------
require_once __DIR__ . '/app/bootstrap.php';
require_once APP_DIZIN . '/MasaRepository.php';
require_once APP_DIZIN . '/RezervasyonRepository.php';


// =====================================================================
// 2) SECENEK LISTELERI
// =====================================================================

// Saat <select>'ini dolduracak liste. saat_secenekleri() son secenegin
// uzerine rezervasyon suresi eklendiginde kapanisi asmamasini saglar.
$saatSecenekleri = saat_secenekleri();

// -----------------------------------------------------------------
// SURE SECENEKLERI - AYNI ZAMANDA BEYAZ LISTE
//
// Bu dizi hem <select>'i doldurur hem de dogrulamada kullanilir:
//     isset($sureSecenekleri[$sure])  ->  gecerli mi?
//
// Boylece "izin verilen degerler" tek yerde tanimli olur. Ayri bir
// dogrulama dizisi yazsaydik, birine 240 eklenip digerine eklenmedigi
// gun sistem sessizce tutarsiz hale gelirdi.
//
// Anahtarlarin int olmasi onemli: POST'tan gelen deger (int) ile
// donusturulup karsilastirilir, boylece "120abc" gibi girdiler 120'ye
// inmez, once is_numeric kontrolunden gecer (asagida).
// -----------------------------------------------------------------
$sureSecenekleri = [
    60  => '1 saat',
    90  => '1,5 saat',
    120 => '2 saat',
    180 => '3 saat',
];

// Kişi sayısı <select>'inin ust siniri. En buyuk AKTIF masa 6 kisilikse
// kullaniciya 30 secenegi sunmanin tek sonucu "uygun masa bulunamadi"
// ekranidir. Iyi arayuz, imkansiz secimi bastan gostermez.
$enBuyukKapasite = min(MasaRepository::maksimumKapasite(), MAX_KISI_SAYISI);
$enBuyukKapasite = max($enBuyukKapasite, MIN_KISI_SAYISI);

// Tarih alaninin alt/ust sinirlari. HTML'deki min/max SADECE kolaylik
// saglar (tarayici secicide gri gosterir); asil kontrol asagida PHP ile
// tekrar yapilir.
$bugun     = date('Y-m-d');
$sonTarih  = date('Y-m-d', strtotime('+' . MAX_ILERI_GUN . ' days'));


// =====================================================================
// 3) BASARI EKRANI VERISI  (tek seferlik okuma)
// ---------------------------------------------------------------------
// Rezervasyon kaydedildikten sonra kullaniciya kodunu gostermemiz
// gerekiyor. Ama araya bir YONLENDIRME giriyor (bkz. bolum 5), yani
// PHP degiskenleri kayboluyor.
//
// Veriyi tasimanin iki yolu var:
//
//   (a) URL:      index.php?kod=RZ7K4M2Q
//       Basit ama sakincali: kod tarayici gecmisine, sunucu erisim
//       log'larina ve tiklanan dis baglantilarin Referer basligina
//       duser. Kod bu sistemde parola gorevi goruyor (kodu bilen
//       rezervasyonu IPTAL EDEBILIR), yani adres cubugunda tasinmamali.
//
//   (b) SESSION:  tek seferlik ("flash") veri     <- SECTIGIMIZ
//       Veri sunucuda kalir, adres temiz olur. Okur okumaz siliyoruz;
//       aksi halde kullanici saatler sonra ana sayfaya girdiginde eski
//       basari ekranini tekrar gorurdu.
// =====================================================================
$sonRezervasyon = $_SESSION['son_rezervasyon'] ?? null;
unset($_SESSION['son_rezervasyon']);


// =====================================================================
// 4) POST ISLEME + SUNUCU TARAFI DOGRULAMA
// =====================================================================

// Hata mesajlari. Alan adi => mesaj seklinde tutuyoruz; boylece ilgili
// <input>'un altina dogru mesaji basabiliyoruz.
$hatalar = [];

// Formun yeniden doldurulacagi degerler. Varsayilanlari GET istegi icin
// de kullaniyoruz, bu yuzden bastan tanimliyoruz.
$eski = [
    'tarih'           => '',
    'baslangic_saati' => '',
    'sure'            => (string) REZERVASYON_SURESI_DAKIKA,
    'kisi_sayisi'     => '2',
    'masa_id'         => '',
    'musteri_adi'     => '',
    'musteri_telefon' => '',
    'musteri_email'   => '',
    'musteri_notu'    => '',
];

if (post_istegi_mi()) {

    // -----------------------------------------------------------------
    // 4.a) CSRF - HER SEYDEN ONCE
    // -----------------------------------------------------------------
    // Token gecersizse istek buradan ILERI GITMEZ (fonksiyon die eder).
    // Kontrolu en basa koymak bilincli: dogrulama kodunun tamami
    // calistiktan sonra token'a bakmak, saldirgana hangi girdinin
    // gecerli oldugunu deneme firsati verirdi (hata mesajlari uzerinden
    // bilgi sizdirma).
    csrf_kontrol_et();

    // -----------------------------------------------------------------
    // 4.b) GIRDILERI OKU
    // post() bas/son bosluklari temizler ve alan hic gonderilmemisse
    // bos metin dondurur (Undefined array key uyarisi olusmaz).
    // -----------------------------------------------------------------
    $eski['tarih']           = post('tarih');
    $eski['baslangic_saati'] = post('baslangic_saati');
    $eski['sure']            = post('sure');
    $eski['kisi_sayisi']     = post('kisi_sayisi');
    $eski['masa_id']         = post('masa_id');
    $eski['musteri_adi']     = post('musteri_adi');
    $eski['musteri_telefon'] = post('musteri_telefon');
    $eski['musteri_email']   = post('musteri_email');
    $eski['musteri_notu']    = post('musteri_notu');

    // =================================================================
    //  *** SUNUCU TARAFI DOGRULAMA ***
    // -----------------------------------------------------------------
    //  Asagidaki kontrollerin tamami, formdaki HTML kisitlariyla
    //  (required, min, max, type="date", <select>) BIREBIR AYNI islere
    //  bakiyor. Bu bir tekrar DEGILDIR:
    //
    //    - HTML kisitlari KULLANICI KOLAYLIGIDIR. Kullaniciyi sunucuya
    //      gitmeden uyarirlar.
    //    - Ama tarayicidan gelen istek uzerinde hicbir kontrolumuz yok.
    //      Su komut formu tamamen atlar:
    //
    //        curl -X POST http://localhost/kafe-rezervasyon/index.php \
    //             -d "tarih=2020-01-01&baslangic_saati=03:00&kisi_sayisi=999&masa_id=8"
    //
    //      Ayni sonuc tarayici konsolundan <input> degerini degistirerek
    //      ya da DevTools > Network > "Edit and Resend" ile de alinir.
    //
    //  KURAL: Istemci dogrulamasi arayuzdur, sunucu dogrulamasi
    //  guvenliktir. Ikisi birbirinin yerine gecmez.
    // =================================================================

    // --- TARIH -------------------------------------------------------
    // gecerli_tarih() sadece bicime degil TAKVIME de bakar: '2026-02-30'
    // bicim olarak dogrudur ama boyle bir gun yoktur (helpers.php).
    if (!gecerli_tarih($eski['tarih'])) {
        $hatalar['tarih'] = 'Geçerli bir tarih seçin.';
    } elseif (gecmis_tarih_mi($eski['tarih'])) {
        $hatalar['tarih'] = 'Geçmiş bir tarihe rezervasyon yapılamaz.';
    } elseif (!rezervasyon_penceresinde_mi($eski['tarih'])) {
        $hatalar['tarih'] = 'En fazla ' . MAX_ILERI_GUN . ' gun sonrasina rezervasyon alabiliyoruz.';
    }

    // --- BASLANGIC SAATI ---------------------------------------------
    // Iki asamali kontrol:
    //   1. saat_araliginda_mi() -> calisma saatleri disini reddeder.
    //   2. in_array(...) BEYAZ LISTE -> 09:17 gibi ara degerleri de
    //      reddeder. Tek basina birincisi 09:17'yi kabul ederdi; slot
    //      disi baslangiclar takvimi delik desik eder.
    // Karsilastirmada ucuncu parametre true (siki kontrol): PHP'nin
    // gevsek karsilastirmasinda "09:00" ile 9 esit sayilabilirdi.
    if (!gecerli_saat($eski['baslangic_saati'])) {
        $hatalar['baslangic_saati'] = 'Geçerli bir saat seçin.';
    } elseif (!in_array($eski['baslangic_saati'], $saatSecenekleri, true)) {
        $hatalar['baslangic_saati'] = 'Bu saat rezervasyona açık değil.';
    }

    // --- SURE ---------------------------------------------------------
    // Beyaz liste kontrolu. is_numeric olmadan (int) donusumu "abc"
    // degerini 0 yapar; 0 dizide yoksa zaten yakalanirdi ama niyeti
    // acikca yazmak, ileride 0 anahtarli bir secenek eklendiginde
    // olusacak sessiz hatayi onler.
    $sureDakika = 0;
    if (!is_numeric($eski['sure']) || !isset($sureSecenekleri[(int) $eski['sure']])) {
        $hatalar['sure'] = 'Geçerli bir süre seçin.';
    } else {
        $sureDakika = (int) $eski['sure'];
    }

    // --- BITIS SAATI (TUREV DEGER) ------------------------------------
    // ONEMLI TASARIM KARARI: Bitis saatini kullanicidan ALMIYORUZ,
    // baslangic + sure ile KENDIMIZ HESAPLIYORUZ.
    //
    // Neden? Kullanicidan alinan her alan ayrica dogrulanmasi gereken
    // bir yuzeydir. Bitis saati gizli bir <input> ile gelseydi,
    // saldirgan "baslangic 19:00, bitis 19:01" gonderip masayi bir
    // dakikaligina rezerve edebilir ya da tam tersi "23:59" yazip
    // bir masayi tum gun bloklayabilirdi.
    //
    // Turetilebilen veriyi istemciden almamak genel bir ilkedir
    // (ayni sebeple sepet toplami da istemciden alinmaz).
    $bitisSaati = '';
    if (!isset($hatalar['baslangic_saati']) && $sureDakika > 0) {
        $bitisSaati = dakikayi_saate_cevir(
            saati_dakikaya_cevir($eski['baslangic_saati']) + $sureDakika
        );

        // Kapanis saati DAHIL kontrol edilir ($bitisDahil = true):
        // 23:00'te BITEN rezervasyon gecerlidir, 23:00'te BASLAYAN degil.
        if (!saat_araliginda_mi($bitisSaati, KAFE_ACILIS, KAFE_KAPANIS, true)) {
            $hatalar['sure'] = 'Bu süre kapanış saatini (' . KAFE_KAPANIS
                . ') aşıyor. Daha kısa bir süre veya daha erken bir saat seçin.';
        }
    }

    // --- KISI SAYISI ---------------------------------------------------
    if (!ctype_digit($eski['kisi_sayisi'])) {
        // ctype_digit sadece rakamlardan olusan METNI kabul eder.
        // "3.5", "-2", " 3" ve "" burada elenir. (int) ile dogrudan
        // donusturseydik "-2" sessizce -2 olurdu.
        $hatalar['kisi_sayisi'] = 'Kişi sayısı geçerli değil.';
    } else {
        $kisiSayisi = (int) $eski['kisi_sayisi'];
        if ($kisiSayisi < MIN_KISI_SAYISI || $kisiSayisi > MAX_KISI_SAYISI) {
            $hatalar['kisi_sayisi'] = 'Kişi sayısı ' . MIN_KISI_SAYISI
                . ' ile ' . MAX_KISI_SAYISI . ' arasinda olmalidir.';
        }
    }

    // --- MASA -----------------------------------------------------------
    // Masa <select> degil, JavaScript'in urettigi radio listesinden gelir.
    // Yani istemci tarafinda uretilmis bir degerdir ve TAMAMEN sifirdan
    // dogrulanmalidir:
    //   1. Sayi mi?
    //   2. Veritabaninda var mi?          -> MasaRepository::bul()
    //   3. Aktif mi?                      -> pasif masa satilamaz
    //   4. Kapasitesi yeterli mi?         -> 8 kisi 2'lik masaya oturamaz
    //
    // 4. maddeyi atlamak klasik bir hatadir: JavaScript zaten yeterli
    // kapasitedeki masalari listeliyor, "nasilsa yanlis gelmez" denir.
    // Oysa listeyi olusturan istektir; kisi sayisini 2 secip masayi
    // aldiktan sonra kisi sayisini 8 yapip gondermek saniyeler alir.
    $masa = null;
    if (!ctype_digit($eski['masa_id'])) {
        $hatalar['masa_id'] = 'Lütfen bir masa seçin.';
    } else {
        $masa = MasaRepository::bul((int) $eski['masa_id']);

        if ($masa === null) {
            $hatalar['masa_id'] = 'Seçilen masa bulunamadı.';
        } elseif ($masa['durum'] !== 'aktif') {
            $hatalar['masa_id'] = 'Seçilen masa şu anda rezervasyona kapalı.';
        } elseif (isset($kisiSayisi) && $kisiSayisi > (int) $masa['kapasite']) {
            $hatalar['masa_id'] = $masa['masa_adi'] . ' en fazla '
                . $masa['kapasite'] . ' kişiliktir.';
        }
    }

    // --- AD SOYAD --------------------------------------------------------
    // mb_strlen kullaniyoruz, strlen DEGIL. strlen BAYT sayar; UTF-8'de
    // Turkce harfler 2 bayt tutar. Icinde 5 Turkce harf olan 20 harflik
    // bir isim strlen'e gore 25'tir; 100 karakterlik alan 80 harfte dolar
    // ve kullanici sebebini anlayamadigi bir hata alir.
    //
    // mb_strlen "mbstring" eklentisiyle gelir. XAMPP'ta varsayilan olarak
    // ACIKTIR; kendi kurdugunuz bir PHP'de eksikse php.ini icinde
    // "extension=mbstring" satirini etkinlestirmeniz gerekir.
    $adUzunlugu = mb_strlen($eski['musteri_adi'], 'UTF-8');
    if ($adUzunlugu < 3) {
        $hatalar['musteri_adi'] = 'Ad soyad en az 3 karakter olmalıdır.';
    } elseif ($adUzunlugu > 100) {
        // Kolon VARCHAR(100). Kontrol etmezsek MySQL kati modda hata
        // firlatir (ya da gevsek modda veriyi SESSIZCE kirpar).
        $hatalar['musteri_adi'] = 'Ad soyad en fazla 100 karakter olabilir.';
    }

    // --- TELEFON ----------------------------------------------------------
    if (!gecerli_telefon($eski['musteri_telefon'])) {
        $hatalar['musteri_telefon'] = 'Telefon 5 ile başlayan 10 haneli olmalıdır. Örnek: 0555 111 22 33';
    }

    // --- E-POSTA (opsiyonel) ----------------------------------------------
    // Bos birakilabilir; girildiyse bicimi dogru olmali. "Opsiyonel ama
    // girildiyse gecerli" kalibi, bos degeri once elemekle kurulur.
    if ($eski['musteri_email'] !== '' && !gecerli_email($eski['musteri_email'])) {
        $hatalar['musteri_email'] = 'E-posta adresi geçerli görünmüyor.';
    }

    // --- NOT (opsiyonel) ---------------------------------------------------
    if (mb_strlen($eski['musteri_notu'], 'UTF-8') > 255) {
        $hatalar['musteri_notu'] = 'Not en fazla 255 karakter olabilir.';
    }


    // =================================================================
    // 4.c) KAYIT
    // -----------------------------------------------------------------
    // Butun dogrulamalar gectiyse deposunu cagiriyoruz. Cakisma kontrolu
    // ve kod uretimi RezervasyonRepository'nin isidir; bu sayfa SQL
    // gormez. Boylece ayni kural Adim 4'teki admin panelinde de birebir
    // gecerli olur.
    // =================================================================
    if ($hatalar === []) {

        $sonuc = RezervasyonRepository::olustur([
            'masa_id'         => (int) $eski['masa_id'],
            'musteri_adi'     => $eski['musteri_adi'],
            // Telefonu KANONIK bicimde sakliyoruz: '0555 111 22 33' ve
            // '+90 555 111 22 33' ayni satira donusur. Aksi halde musteri
            // telefonuyla sorgulama yaptiginda kendi kaydini bulamaz.
            'musteri_telefon' => telefon_normalize($eski['musteri_telefon']),
            // Bos e-posta yerine NULL: kolon NULL kabul ediyor ve bos
            // metin ile NULL'u karistirmak raporlamada "1 kisi e-posta
            // birakmis" gibi yanlis sayimlara yol acar.
            'musteri_email'   => $eski['musteri_email'] !== '' ? $eski['musteri_email'] : null,
            'tarih'           => $eski['tarih'],
            'baslangic_saati' => $eski['baslangic_saati'],
            'bitis_saati'     => $bitisSaati,
            'kisi_sayisi'     => (int) $eski['kisi_sayisi'],
            'musteri_notu'    => $eski['musteri_notu'] !== '' ? $eski['musteri_notu'] : null,
        ]);

        if (!empty($sonuc['basarili'])) {

            // =====================================================
            //  *** POST - REDIRECT - GET (PRG) DESENI ***
            // -----------------------------------------------------
            //  Kayit basarili. Simdi ekrana "Rezervasyonunuz alindi"
            //  yazip DURSAYDIK ne olurdu?
            //
            //  Tarayicinin adres cubugunda hala POST istegi durur.
            //  Kullanici F5'e bastiginda ya da geri/ileri yaptiginda
            //  tarayici su kutuyu gosterir:
            //
            //     "Formu yeniden gondermek icin onay verin"
            //
            //  Kullanicilarin cogu bu kutuyu okumadan "Devam"a basar
            //  ve AYNI POST ikinci kez gonderilir. Sonuc: ayni masaya
            //  ayni saate ikinci bir rezervasyon (ya da en iyi ihtimalle
            //  kafa karistirici bir "cakisma" hatasi). Ayni sey mobilde
            //  sayfayi asagi cekip yenilemekle de olur.
            //
            //  COZUM: POST'u bir YONLENDIRME ile bitirmek.
            //    1. POST gelir, kayit yapilir.
            //    2. Sunucu 303 See Other ile "index.php'ye GET ile git" der.
            //    3. Tarayici GET yapar; adres cubugunda artik GET durur.
            //  Bundan sonra F5 sadece sayfayi tekrar OKUR, kayit tekrar
            //  ETMEZ. Uyari kutusu da cikmaz.
            //
            //  303 kodunu ozellikle seciyoruz (yonlendir() varsayilani):
            //  302 bazi eski istemcilerde POST'u POST olarak tekrarlar;
            //  303 "yeni istegi kesinlikle GET yap" demektir.
            //
            //  Basari verisini session'a birakiyoruz cunku yonlendirme
            //  sonrasi PHP degiskenleri sifirlanir (bkz. bolum 3).
            // =====================================================
            $_SESSION['son_rezervasyon'] = [
                'kod'         => $sonuc['kod'],
                'masa_adi'    => $masa['masa_adi'],
                'konum'       => $masa['konum'],
                'tarih'       => $eski['tarih'],
                'baslangic'   => $eski['baslangic_saati'],
                'bitis'       => $bitisSaati,
                'kisi_sayisi' => (int) $eski['kisi_sayisi'],
                'ad'          => $eski['musteri_adi'],
            ];

            flash_ekle('Rezervasyonunuz oluşturuldu. Kodunuzu aşağıda bulabilirsiniz.', 'success');

            // Yonlendirmeden sonra exit ZORUNLUDUR; yonlendir() bunu
            // kendi icinde yapiyor (donus tipi "never").
            yonlendir('index.php');
        }

        // Depo basarisiz dondu. En yaygin sebep, form doldurulurken
        // baska birinin ayni masayi ayni saate almasidir (yaris durumu).
        // Bu yuzden cakisma kontrolunu formu gosterirken degil, KAYIT
        // ANINDA yapmak sarttir.
        $hatalar['genel'] = $sonuc['hata'] ?? 'Rezervasyon oluşturulamadı. Lütfen tekrar deneyin.';
    }
}


// =====================================================================
// 5) GORUNUM
// =====================================================================
$sayfaBasligi = 'Masa Rezervasyonu';
$aktifSayfa   = 'ana';
require __DIR__ . '/partials/header.php';
?>

<!-- =================================================================
     KARSILAMA (HERO)
     ================================================================= -->
<section class="kafe-hero">
    <div class="container">
        <div class="row align-items-center gy-4">

            <div class="col-lg-7">
                <span class="kafe-rozet mb-3"><?= e(KAFE_ACILIS) ?> &ndash; <?= e(KAFE_KAPANIS) ?> arası açıktır</span>

                <h1 class="kafe-hero-baslik">
                    Masanı ayırt,<br>kahven hazır olsun.
                </h1>

                <p class="kafe-hero-metin">
                    <?= e(SITE_ADI) ?>'nda iç mekan ve bahçe masalarını birkaç saniyede
                    rezerve edebilirsiniz. Tarih ve saati seçin, o aralıkta boş olan
                    masaları anında görün.
                </p>

                <div class="d-flex flex-wrap gap-2">
                    <a href="#rezervasyon-formu" class="btn btn-kafe px-4">Hemen Rezervasyon Yap</a>
                    <a href="rezervasyon-sorgula.php" class="btn btn-kafe-cizgi px-4">Rezervasyonumu Sorgula</a>
                </div>
            </div>

            <div class="col-lg-5">
                <ul class="kafe-ozellik-liste">
                    <li>
                        <strong>Anında müsaitlik</strong>
                        Seçtiğiniz saat aralığında gerçekten boş olan masalar listelenir.
                    </li>
                    <li>
                        <strong>Takip kodu</strong>
                        Her rezervasyon için kısa bir kod üretilir; sorgulama ve iptal bu kodla yapılır.
                    </li>
                    <li>
                        <strong>İç mekan / bahçe</strong>
                        Masanın konumunu ve kapasitesini seçmeden önce görürsünüz.
                    </li>
                </ul>
            </div>

        </div>
    </div>
</section>


<div class="container my-5">

    <?php if ($sonRezervasyon !== null): ?>
        <!-- =========================================================
             BASARI EKRANI
             Sadece yonlendirmeden hemen sonraki GET istegiyle gorunur.
             ========================================================= -->
        <section class="kafe-basari-kart mb-5" id="rezervasyon-sonucu">
            <div class="row gy-4 align-items-center">

                <div class="col-lg-5 text-center">
                    <p class="kafe-kod-etiket">Rezervasyon Kodunuz</p>

                    <!--
                        Kod GORSEL OLARAK en bariz oge olmali: musteri bu
                        ekrandan sadece bunu almali. Harfler arasi bosluk
                        (letter-spacing) ve monospace font, kodu telefonda
                        okurken/yazarken hata yapilmasini azaltir.
                    -->
                    <p class="kafe-kod"><?= e($sonRezervasyon['kod']) ?></p>

                    <div class="alert alert-warning small mb-0 text-start">
                        <strong>Bu kodu saklayın.</strong>
                        Rezervasyonunuzu sorgulamak ve iptal etmek icin
                        bu kod gereklidir. Kodu baskasiyla paylasmayin.
                    </div>
                </div>

                <div class="col-lg-7">
                    <h2 class="h4 mb-3">Masanız ayrıldı</h2>

                    <dl class="kafe-ozet">
                        <dt>Ad Soyad</dt>
                        <dd><?= e($sonRezervasyon['ad']) ?></dd>

                        <dt>Masa</dt>
                        <dd>
                            <?= e($sonRezervasyon['masa_adi']) ?>
                            <span class="badge kafe-konum-rozet">
                                <?= $sonRezervasyon['konum'] === 'dis' ? 'Bahçe' : 'İç mekan' ?>
                            </span>
                        </dd>

                        <dt>Tarih</dt>
                        <dd><?= e(tarih_goster($sonRezervasyon['tarih'])) ?></dd>

                        <dt>Saat</dt>
                        <dd><?= e($sonRezervasyon['baslangic']) ?> &ndash; <?= e($sonRezervasyon['bitis']) ?></dd>

                        <dt>Kişi</dt>
                        <dd><?= e($sonRezervasyon['kisi_sayisi']) ?> kişi</dd>
                    </dl>

                    <!--
                        Koda u() (rawurlencode) uyguluyoruz. Kod sadece
                        [A-Z2-9] karakterlerinden olustugu icin pratikte
                        degismez; ama "URL'e giden her deger URL kacislamasi
                        ister" kuralini istisnasiz uygulamak, bir gun kod
                        bicimi degistiginde sessiz hatayi onler.
                    -->
                    <a class="btn btn-kafe mt-2"
                       href="rezervasyon-sorgula.php?kod=<?= u($sonRezervasyon['kod']) ?>">
                        Rezervasyonu Goruntule
                    </a>
                </div>

            </div>
        </section>
    <?php endif; ?>


    <!-- =============================================================
         REZERVASYON FORMU
         ============================================================= -->
    <section id="rezervasyon-formu">

        <header class="kafe-bolum-baslik">
            <h2>Rezervasyon Formu</h2>
            <p>Önce tarih ve saati seçin, ardından listelenen müsait masalardan birini işaretleyin.</p>
        </header>

        <?php if (isset($hatalar['genel'])): ?>
            <!--
                Genel hata (ornegin cakisma) formun EN USTUNDE gosterilir.
                Alan bazli hatalar ilgili kutunun altinda durur; ikisini
                karistirmamak kullanicinin neyi duzeltecegini bilmesini saglar.
            -->
            <div class="alert alert-danger" role="alert">
                <strong>Rezervasyon oluşturulamadı.</strong><br>
                <?= e($hatalar['genel']) ?>
            </div>
        <?php endif; ?>

        <!--
            novalidate: Tarayicinin kendi balon uyarilarini kapatir.
            Sebep tutarlilik: bazi alanlari (masa secimi gibi) zaten
            JavaScript ile kontrol ediyoruz; iki farkli uyari bicimi
            karisik gorunur. Sunucu dogrulamasi degismedigi icin guvenlik
            acisindan bir kayip yoktur.

            data-* oznitelikleri JavaScript'e baslangic durumunu tasir.
            Global degisken ya da <script> icine PHP gomerek deger
            gecirmek yerine data- kullanmak daha guvenlidir: degerler
            HTML attribute kacislamasindan (e()) gecer, JavaScript
            baglamina enjeksiyon riski olusmaz.
        -->
        <form method="post" action="index.php" class="kafe-form" novalidate
              id="rezervasyonFormu"
              data-api="api/musait-masalar.php"
              data-secili-masa="<?= e($eski['masa_id']) ?>">

            <?= csrf_field() ?>

            <!-- ---------- 1. ADIM: ZAMAN VE KISI ---------- -->
            <div class="kafe-kart mb-4">
                <h3 class="kafe-adim-baslik"><span>1</span> Zaman ve kişi sayısı</h3>

                <div class="row g-3">

                    <div class="col-sm-6 col-lg-3">
                        <label for="tarih" class="form-label">Tarih</label>
                        <!--
                            type="date" mobilde takvim secici acar.
                            min/max degerleri gecmis ve cok ileri tarihleri
                            secicide gri yapar - ama bu sadece kolayliktir,
                            asil kontrol PHP'dedir (bolum 4).
                        -->
                        <input type="date"
                               class="form-control <?= isset($hatalar['tarih']) ? 'is-invalid' : '' ?>"
                               id="tarih" name="tarih"
                               value="<?= e($eski['tarih']) ?>"
                               min="<?= e($bugun) ?>" max="<?= e($sonTarih) ?>" required>
                        <?php if (isset($hatalar['tarih'])): ?>
                            <div class="invalid-feedback"><?= e($hatalar['tarih']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-sm-6 col-lg-3">
                        <label for="baslangic_saati" class="form-label">Başlangıç saati</label>
                        <select class="form-select <?= isset($hatalar['baslangic_saati']) ? 'is-invalid' : '' ?>"
                                id="baslangic_saati" name="baslangic_saati" required>
                            <option value="">Saat seçin</option>
                            <?php foreach ($saatSecenekleri as $saat): ?>
                                <!--
                                    selected durumunu SIKI karsilastirma ile
                                    belirliyoruz. PHP'de "09:00" == 0 gecmiste
                                    true donerdi; === bu tur surprizleri keser.
                                -->
                                <option value="<?= e($saat) ?>"
                                    <?= $eski['baslangic_saati'] === $saat ? 'selected' : '' ?>>
                                    <?= e($saat) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($hatalar['baslangic_saati'])): ?>
                            <div class="invalid-feedback"><?= e($hatalar['baslangic_saati']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-sm-6 col-lg-3">
                        <label for="sure" class="form-label">Süre</label>
                        <select class="form-select <?= isset($hatalar['sure']) ? 'is-invalid' : '' ?>"
                                id="sure" name="sure" required>
                            <?php foreach ($sureSecenekleri as $dakika => $etiket): ?>
                                <option value="<?= e($dakika) ?>"
                                    <?= (int) $eski['sure'] === $dakika ? 'selected' : '' ?>>
                                    <?= e($etiket) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($hatalar['sure'])): ?>
                            <div class="invalid-feedback"><?= e($hatalar['sure']) ?></div>
                        <?php else: ?>
                            <!--
                                Bitis saatini JavaScript hesaplayip buraya
                                yazar. Sadece BILGILENDIRME amaclidir; forma
                                gonderilmez, sunucu kendi hesabini yapar.
                            -->
                            <div class="form-text" id="bitisBilgisi"></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-sm-6 col-lg-3">
                        <label for="kisi_sayisi" class="form-label">Kişi sayısı</label>
                        <select class="form-select <?= isset($hatalar['kisi_sayisi']) ? 'is-invalid' : '' ?>"
                                id="kisi_sayisi" name="kisi_sayisi" required>
                            <?php for ($i = MIN_KISI_SAYISI; $i <= $enBuyukKapasite; $i++): ?>
                                <option value="<?= e($i) ?>"
                                    <?= (int) $eski['kisi_sayisi'] === $i ? 'selected' : '' ?>>
                                    <?= e($i) ?> kişi
                                </option>
                            <?php endfor; ?>
                        </select>
                        <?php if (isset($hatalar['kisi_sayisi'])): ?>
                            <div class="invalid-feedback"><?= e($hatalar['kisi_sayisi']) ?></div>
                        <?php else: ?>
                            <div class="form-text">En büyük masamız <?= e($enBuyukKapasite) ?> kişiliktir.</div>
                        <?php endif; ?>
                    </div>

                </div>
            </div>


            <!-- ---------- 2. ADIM: MASA SECIMI ---------- -->
            <div class="kafe-kart mb-4">
                <h3 class="kafe-adim-baslik"><span>2</span> Müsait masayı seçin</h3>

                <?php if (isset($hatalar['masa_id'])): ?>
                    <div class="alert alert-danger py-2 small" role="alert">
                        <?= e($hatalar['masa_id']) ?>
                    </div>
                <?php endif; ?>

                <!--
                    Bu kutuyu JavaScript doldurur.
                    aria-live="polite": Icerik degistiginde ekran okuyucu
                    kullaniciyi bolmeden haber verir ("3 masa bulundu").
                    Dinamik listelerde bu oznitelik olmazsa gorme engelli
                    kullanici listenin geldigini hic fark etmez.
                -->
                <div id="masaListesi" class="kafe-masa-listesi" aria-live="polite">
                    <p class="kafe-bos-durum">
                        Müsait masaları görmek için yukarıdan tarih, saat ve kişi sayısı seçin.
                    </p>
                </div>

                <!--
                    JavaScript kapaliysa sayfa tamamen kullanilamaz hale
                    gelmesin diye kullaniciyi bilgilendiriyoruz. Gercek bir
                    projede burada JS'siz calisan bir yedek akis (formu
                    gonderip masalari sunucuda listelemek) sunulurdu.
                -->
                <noscript>
                    <div class="alert alert-warning mb-0">
                        Masa listesi JavaScript ile yüklenir. Tarayıcınızda JavaScript kapalı
                        görünüyor; lütfen açın veya bizi telefonla arayın.
                    </div>
                </noscript>

                <!--
                    Secilen masanin id'si buraya yazilir. Kullanici kart
                    uzerindeki radio'yu isaretledikce JavaScript gunceller.
                -->
                <input type="hidden" id="masaId" name="masa_id" value="<?= e($eski['masa_id']) ?>">
            </div>


            <!-- ---------- 3. ADIM: ILETISIM ---------- -->
            <div class="kafe-kart mb-4">
                <h3 class="kafe-adim-baslik"><span>3</span> İletişim bilgileriniz</h3>

                <div class="row g-3">

                    <div class="col-md-6">
                        <label for="musteri_adi" class="form-label">Ad Soyad</label>
                        <input type="text"
                               class="form-control <?= isset($hatalar['musteri_adi']) ? 'is-invalid' : '' ?>"
                               id="musteri_adi" name="musteri_adi"
                               value="<?= e($eski['musteri_adi']) ?>"
                               maxlength="100" autocomplete="name" required>
                        <?php if (isset($hatalar['musteri_adi'])): ?>
                            <div class="invalid-feedback"><?= e($hatalar['musteri_adi']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-6">
                        <label for="musteri_telefon" class="form-label">Telefon</label>
                        <!--
                            inputmode="tel" mobilde rakam klavyesini acar.
                            type="tel" kullanmak da mumkun; ama type="tel"
                            tarayicida hicbir dogrulama yapmaz, sadece
                            klavyeyi degistirir. Asil kontrol yine PHP'de.
                        -->
                        <input type="tel" inputmode="tel"
                               class="form-control <?= isset($hatalar['musteri_telefon']) ? 'is-invalid' : '' ?>"
                               id="musteri_telefon" name="musteri_telefon"
                               value="<?= e($eski['musteri_telefon']) ?>"
                               placeholder="0555 111 22 33" maxlength="20"
                               autocomplete="tel" required>
                        <?php if (isset($hatalar['musteri_telefon'])): ?>
                            <div class="invalid-feedback"><?= e($hatalar['musteri_telefon']) ?></div>
                        <?php else: ?>
                            <div class="form-text">Rezervasyonunuzu telefonla da sorgulayabilirsiniz.</div>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-6">
                        <label for="musteri_email" class="form-label">
                            E-posta <span class="text-muted fw-normal">(isteğe bağlı)</span>
                        </label>
                        <input type="email"
                               class="form-control <?= isset($hatalar['musteri_email']) ? 'is-invalid' : '' ?>"
                               id="musteri_email" name="musteri_email"
                               value="<?= e($eski['musteri_email']) ?>"
                               maxlength="120" autocomplete="email">
                        <?php if (isset($hatalar['musteri_email'])): ?>
                            <div class="invalid-feedback"><?= e($hatalar['musteri_email']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-6">
                        <label for="musteri_notu" class="form-label">
                            Notunuz <span class="text-muted fw-normal">(isteğe bağlı)</span>
                        </label>
                        <input type="text"
                               class="form-control <?= isset($hatalar['musteri_notu']) ? 'is-invalid' : '' ?>"
                               id="musteri_notu" name="musteri_notu"
                               value="<?= e($eski['musteri_notu']) ?>"
                               maxlength="255"
                               placeholder="Doğum günü, bebek sandalyesi...">
                        <?php if (isset($hatalar['musteri_notu'])): ?>
                            <div class="invalid-feedback"><?= e($hatalar['musteri_notu']) ?></div>
                        <?php endif; ?>
                    </div>

                </div>

                <div class="d-flex flex-wrap align-items-center gap-3 mt-4">
                    <button type="submit" class="btn btn-kafe btn-lg px-4" id="gonderButonu">
                        Rezervasyonu Tamamla
                    </button>
                    <span class="small text-muted" id="formUyari" role="alert"></span>
                </div>
            </div>

        </form>
    </section>

</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
