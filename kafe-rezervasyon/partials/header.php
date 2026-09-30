<?php
/**
 * =====================================================================
 *  KAFE MASA REZERVASYON SISTEMI - MUSTERI TARAFI SAYFA BASLIGI
 * ---------------------------------------------------------------------
 *  Her musteri sayfasinin ustune giren ortak parca: <head> bolumu,
 *  navbar ve flash mesaj alani. Sayfalar sunu yazar:
 *
 *      $sayfaBasligi = 'Rezervasyon Sorgula';
 *      $aktifSayfa   = 'sorgula';
 *      require __DIR__ . '/partials/header.php';
 *
 *  Neden ayri dosya? Navbar'a yarin bir "Iletisim" baglantisi eklenecek.
 *  Parca kullanilmazsa bu degisikligi her sayfada tek tek yapmak gerekir
 *  ve birinde unutuldugunda kullanici sayfalar arasi gezerken menunun
 *  degistigini gorur.
 * =====================================================================
 */

if (!defined('APP_INIT')) {
    http_response_code(403);
    die('Doğrudan erişim engellendi.');
}

// ---------------------------------------------------------------------
// Sayfadan gelen degiskenler icin varsayilanlar.
//
// ?? (null birlestirme) operatoru, degisken TANIMSIZ olsa bile uyari
// vermeden calisir. isset() ile ayni kontrolu yapar ama tek satirdir.
// Bu sayede sayfa $sayfaBasligi tanimlamayi unutursa bile "Undefined
// variable" uyarisi almayiz; site adi yedek olarak kullanilir.
// ---------------------------------------------------------------------
$sayfaBasligi   = $sayfaBasligi   ?? SITE_ADI;
$sayfaAciklamasi = $sayfaAciklamasi ?? SITE_SLOGAN;
$aktifSayfa     = $aktifSayfa     ?? '';

// ---------------------------------------------------------------------
// VARLIK SURUMU (cache busting)
//
// Tarayici style.css dosyasini onbellege alir. CSS'i degistirdiginizde
// kullanici Ctrl+F5 yapmadan yeni halini GOREMEZ; "bende duzeldi ama
// hocada bozuk gorunuyor" sikayetinin en yaygin sebebi budur.
//
// filemtime() dosyanin son degistirilme zamanini (unix zaman damgasi)
// dondurur. Adresin sonuna ?v=1727700000 eklendiginde tarayici bunu
// FARKLI bir dosya sayar ve yeniden indirir. Dosya degismedigi surece
// sayi da degismez, yani onbellek avantaji kaybolmaz.
//
// @ (hata bastirma) ve ?: (kisa ternary): dosya henuz yoksa filemtime
// uyari verip false doner; o durumda anlik zamani kullaniyoruz.
// ---------------------------------------------------------------------
$cssSurumu = @filemtime(APP_KOK . '/assets/css/style.css') ?: time();
$jsSurumu  = @filemtime(APP_KOK . '/assets/js/app.js') ?: time();

/**
 * Navbar baglantisinin "active" sinifini uretir.
 * Aktif sayfayi isaretlemek gorsel suslemeden ibaret degildir:
 * aria-current="page" ekran okuyuculara "su an buradasiniz" der.
 */
$menuSinifi = static function (string $anahtar) use ($aktifSayfa): string {
    return $anahtar === $aktifSayfa ? ' active' : '';
};
?>
<!doctype html>
<!--
    lang="tr" SADECE bir etiket degildir:
      - Ekran okuyucular metni Turkce telaffuzla okur (Ingilizce
        okunan bir Turkce metin anlasilmaz hale gelir).
      - Tarayicinin yazim denetimi ve otomatik ceviri onerisi dogru
        calisir.
      - Arama motorlari sayfayi dogru dilde indeksler.
-->
<html lang="tr">
<head>
    <!--
        charset EN BASTA olmali (ilk 1024 bayt icinde). Tarayici
        karakter setini ogrenene kadar baytlari tahminle cozer; meta
        etiketi gec gelirse baslikta bozuk karakterler (UTF-8 baytlarin
        tek tek harfe cevrilmis hali) gorunur ve tarayici sayfayi
        bastan ayristirmak zorunda kalir.
    -->
    <meta charset="utf-8">

    <!--
        viewport olmadan mobil tarayici sayfayi 980px genisliginde bir
        masaustu sayfasi varsayar ve kucultur: yazilar okunmaz, Bootstrap'in
        duyarli (responsive) kirilma noktalari HIC devreye girmez.
        width=device-width  -> sayfa genisligi = cihaz genisligi
        initial-scale=1     -> acilista yakinlastirma yok
    -->
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <meta name="description" content="<?= e($sayfaAciklamasi) ?>">

    <!--
        Baslik bicimi "Sayfa - Site": tarayici sekmesi daraldiginda once
        sondaki site adi kirpilir, boylece kullanici hangi sayfada
        oldugunu gormeye devam eder.
    -->
    <title><?= e($sayfaBasligi) ?> &middot; <?= e(SITE_ADI) ?></title>

    <!--
        =============================================================
         BOOTSTRAP 5.3.3 - CDN + SRI
        =============================================================
        integrity="sha384-..." degeri SUBRESOURCE INTEGRITY (SRI) olarak
        adlandirilir ve dosyanin SHA-384 ozetidir.

        NEDEN GEREKLI?
        CDN bizim sunucumuz degildir. CDN ele gecirilirse (ya da araya
        giren biri trafigi degistirirse) bootstrap.min.css yerine
        icinde kotu kod olan bir dosya servis edilebilir. Tarayici
        indirdigi dosyanin ozetini hesaplar; buradaki deger ile
        eslesmezse dosyayi CALISTIRMAZ. Yani "dogru adresten geldi"
        yetmez, "dogru ICERIK geldi" de garanti altina alinir.

        crossorigin="anonymous": Baska bir kaynaktan (origin) gelen
        dosyanin butunlugunu dogrulayabilmek icin tarayicinin CORS ile
        istemesi gerekir. Bu oznitelik olmadan integrity kontrolu
        sessizce YAPILAMAZ ve dosya reddedilir.

        Bu ozetler uydurma degildir; dosyalar indirilip su komutla
        hesaplanmistir:
          openssl dgst -sha384 -binary bootstrap.min.css | openssl base64 -A

        NOT: Surum numarasini sabitlemek (5.3.3) de bir guvenlik
        tedbiridir. "latest" kullanilsaydi dosya her guncellemede
        degisir, ozet tutmaz ve site aniden stilsiz kalirdi.
    -->
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
        crossorigin="anonymous">

    <!--
        Kendi temamiz Bootstrap'ten SONRA yuklenir. CSS'te esit
        ozgullukteki (specificity) kurallarda SON yazilan kazanir;
        once yuklenseydi Bootstrap bizim renklerimizi ezerdi.
    -->
    <link rel="stylesheet" href="assets/css/style.css?v=<?= e($cssSurumu) ?>">
</head>
<body class="kafe-govde">

<!--
    "İçeriğe atla" baglantisi: klavyeyle gezen kullanici her sayfada
    once navbar baglantilarini tek tek gecmek zorunda kalmasin.
    Normalde gorunmez, TAB ile odaklandiginda ortaya cikar (style.css).
-->
<a class="icerige-atla" href="#ana-icerik">İçeriğe atla</a>

<!--
    navbar-expand-lg : Buyuk ekranda yatay menu, kucukte hamburger.
    Duyarli tasarimin buradaki karsiligi bu tek siniftir; menuyu elle
    medya sorgusuyla gizlemeye gerek yoktur.
-->
<nav class="navbar navbar-expand-lg kafe-navbar sticky-top">
    <div class="container">

        <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
            <!--
                Ikon olarak satir ici SVG kullaniyoruz. Ikon fontu
                (Font Awesome gibi) eklemek sayfaya yuz kilobayt ve
                fazladan bir CDN bagimliligi getirirdi; tek bir ikon
                icin bu maliyet gereksizdir.
                aria-hidden="true": susleme amacli, ekran okuyucu
                bunu okumasin (marka metni zaten yaninda).
            -->
            <svg class="kafe-logo" viewBox="0 0 24 24" width="26" height="26"
                 fill="none" stroke="currentColor" stroke-width="1.8"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M17 8h1a4 4 0 1 1 0 8h-1"></path>
                <path d="M3 8h14v9a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4Z"></path>
                <path d="M6 2v3M10 2v3M14 2v3"></path>
            </svg>
            <span class="fw-semibold"><?= e(SITE_ADI) ?></span>
        </a>

        <!--
            data-bs-toggle / data-bs-target: Bootstrap'in JavaScript'i
            bu ozniteliklere bakarak menuyu acip kapatir. Kendi JS'imizi
            yazmamiza gerek yoktur.
            aria-expanded ve aria-label erisilebilirlik icin zorunludur:
            ekran okuyucu menunun acik mi kapali mi oldugunu soyler.
        -->
        <button class="navbar-toggler" type="button"
                data-bs-toggle="collapse" data-bs-target="#anaMenu"
                aria-controls="anaMenu" aria-expanded="false"
                aria-label="Menüyü aç/kapat">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="anaMenu">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
                <li class="nav-item">
                    <a class="nav-link<?= $menuSinifi('ana') ?>"
                       <?= $aktifSayfa === 'ana' ? 'aria-current="page"' : '' ?>
                       href="index.php">Ana Sayfa</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?= $menuSinifi('sorgula') ?>"
                       <?= $aktifSayfa === 'sorgula' ? 'aria-current="page"' : '' ?>
                       href="rezervasyon-sorgula.php">Rezervasyon Sorgula</a>
                </li>
                <li class="nav-item ms-lg-2">
                    <a class="btn btn-kafe btn-sm px-3" href="index.php#rezervasyon-formu">
                        Masa Ayırt
                    </a>
                </li>
            </ul>
        </div>

    </div>
</nav>

<!--
    FLASH MESAJ ALANI
    Yonlendirme sonrasi tek seferlik bildirimler burada gosterilir.
    flash_goster() HAZIR HTML dondurur; icindeki kullanici metni
    fonksiyonun kendi icinde e() ile kacislanmistir. Bu yuzden burada
    tekrar e() UYGULAMIYORUZ - uygulasaydik etiketler ekranda
    "&lt;div&gt;" olarak gorunurdu.
-->
<?php $flashHtml = flash_goster(); ?>
<?php if ($flashHtml !== ''): ?>
    <div class="container mt-3" role="status" aria-live="polite">
        <?= $flashHtml ?>
    </div>
<?php endif; ?>

<main id="ana-icerik">
