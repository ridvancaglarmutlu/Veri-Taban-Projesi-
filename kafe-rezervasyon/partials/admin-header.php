<?php
/**
 * =====================================================================
 *  KAFE MASA REZERVASYON SISTEMI - ADMIN SAYFA BASLIGI
 * ---------------------------------------------------------------------
 *  Musteri header.php'den AYRI bir dosya. Neden kopya degil, neden
 *  tek header'da if (admin) degil?
 *
 *    - Musteri menusu "Masa Ayirt" cagirir; admin menusu masalari
 *      yonetir. Tek dosyada iki menu, her degisiklikte yanlis baglanti
 *      kirma riski tasir.
 *    - CSS yolu farklidir: admin sayfalari /admin altindadir, stiller
 *      ../assets/css/style.css ile bir ust klasore cikmak zorundadir.
 *    - Cikis bir GET baglantisi DEGILDIR (asagida form). Musteri
 *      navbar'inda boyle bir form yoktur.
 *    - Giris yapilmissa sol kenarda sabit bir kasa/ofis (sidebar)
 *      durur; giris sayfasinda sidebar YOKTUR (henuz yetki yok).
 *
 *  Kullanim (korunan sayfa):
 *      Auth::kontrol();
 *      $sayfaBasligi = 'Panel';
 *      $aktifSayfa   = 'dashboard';
 *      require __DIR__ . '/../partials/admin-header.php';
 * =====================================================================
 */

if (!defined('APP_INIT')) {
    http_response_code(403);
    die('Doğrudan erişim engellendi.');
}

$sayfaBasligi    = $sayfaBasligi    ?? 'Yönetim';
$sayfaAciklamasi = $sayfaAciklamasi ?? 'Yönetim paneli';
$aktifSayfa      = $aktifSayfa      ?? '';
$yonetici        = class_exists('Auth') ? Auth::yonetici() : null;
$panelIci        = is_array($yonetici);

$cssSurumu     = @filemtime(APP_KOK . '/assets/css/style.css') ?: time();
$adminJsSurumu = @filemtime(APP_KOK . '/assets/js/admin.js') ?: time();

$menuSinifi = static function (string $anahtar) use ($aktifSayfa): string {
    return $anahtar === $aktifSayfa ? ' active' : '';
};
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= e($sayfaAciklamasi) ?>">
    <meta property="og:title" content="<?= e($sayfaBasligi) ?> · <?= e(SITE_ADI) ?>">
    <meta property="og:description" content="<?= e($sayfaAciklamasi) ?>">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="tr_TR">
    <link rel="icon" href="../assets/img/favicon.svg" type="image/svg+xml">
    <title><?= e($sayfaBasligi) ?> &middot; <?= e(SITE_ADI) ?></title>
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
        crossorigin="anonymous">
    <!--
        Yol ../assets/... : bu parca admin/*.php tarafindan cagrilir.
        "assets/css/style.css" yazmak /admin/assets aratirdi ve tema
        yuklenmezdi. Musteri header'ini kopyalayip yolu unutmak, admin
        panelinin "stilsiz" gorunmesinin klasik sebebidir.
    -->
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= e($cssSurumu) ?>">
</head>
<body class="kafe-govde admin-govde<?= $panelIci ? ' admin-govde-panel' : ' admin-govde-giris' ?>">

<a class="icerige-atla" href="#ana-icerik">İçeriğe atla</a>

<?php if ($panelIci): ?>
<aside class="admin-yan" aria-label="Yönetim menüsü">
    <a class="admin-yan-marka" href="index.php">
        <svg class="kafe-logo" viewBox="0 0 24 24" width="28" height="28"
             fill="none" stroke="currentColor" stroke-width="1.8"
             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M17 8h1a4 4 0 1 1 0 8h-1"></path>
            <path d="M3 8h14v9a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4Z"></path>
            <path d="M6 2v3M10 2v3M14 2v3"></path>
        </svg>
        <span>
            <strong><?= e(SITE_ADI) ?></strong>
            <small>Kasa ofisi</small>
        </span>
    </a>

    <nav class="admin-yan-nav">
        <p class="admin-yan-grup">Günlük iş</p>
        <a class="admin-yan-link<?= $menuSinifi('dashboard') ?>"
           <?= $aktifSayfa === 'dashboard' ? 'aria-current="page"' : '' ?>
           href="index.php">Günlük özet</a>
        <a class="admin-yan-link<?= $menuSinifi('rezervasyonlar') ?>"
           <?= $aktifSayfa === 'rezervasyonlar' ? 'aria-current="page"' : '' ?>
           href="rezervasyonlar.php">Rezervasyonlar</a>

        <p class="admin-yan-grup">Salon</p>
        <a class="admin-yan-link<?= $menuSinifi('masalar') ?>"
           <?= $aktifSayfa === 'masalar' ? 'aria-current="page"' : '' ?>
           href="masalar.php">Masalar</a>
    </nav>

    <div class="admin-yan-alt">
        <p class="admin-yan-kullanici">
            <?= e($yonetici['ad_soyad'] !== '' ? $yonetici['ad_soyad'] : $yonetici['kullanici_adi']) ?>
        </p>
        <!--
            Cikis GET ile yapILMAZ. <a href="logout.php"> tarayici
            onbellekleri, e-posta istemcileri ve <img src> ile
            tetiklenebilir; CSRF token tasiyamaz. POST + csrf_field
            hem "yanlislikla cikis"i hem siteler arasi cikis
            istegini engeller.
        -->
        <form method="post" action="logout.php" class="m-0">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-sm btn-outline-light w-100">Çıkış</button>
        </form>
        <a class="admin-yan-musteri" href="../index.php">Müşteri sitesi</a>
    </div>
</aside>
<?php endif; ?>

<div class="admin-cerceve">
    <header class="admin-ust">
        <?php if ($panelIci): ?>
            <button class="admin-yan-ac navbar-toggler d-lg-none" type="button"
                    data-bs-toggle="offcanvas" data-bs-target="#adminMobilMenu"
                    aria-controls="adminMobilMenu" aria-label="Menüyü aç">
                <span class="navbar-toggler-icon"></span>
            </button>
            <h1 class="admin-ust-baslik h5 mb-0"><?= e($sayfaBasligi) ?></h1>
            <span class="admin-ust-saat d-none d-md-inline">
                <?= e(tarih_goster(date('Y-m-d'))) ?>
                &middot;
                <?= e(KAFE_ACILIS) ?>–<?= e(KAFE_KAPANIS) ?>
            </span>
        <?php else: ?>
            <a class="navbar-brand d-flex align-items-center gap-2 admin-giris-marka" href="login.php">
                <svg class="kafe-logo" viewBox="0 0 24 24" width="26" height="26"
                     fill="none" stroke="currentColor" stroke-width="1.8"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M17 8h1a4 4 0 1 1 0 8h-1"></path>
                    <path d="M3 8h14v9a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4Z"></path>
                    <path d="M6 2v3M10 2v3M14 2v3"></path>
                </svg>
                <span class="fw-semibold"><?= e(SITE_ADI) ?></span>
                <span class="admin-rozet">Yönetim</span>
            </a>
            <a class="small admin-ust-musteri" href="../index.php">Müşteri sitesi</a>
        <?php endif; ?>
    </header>

<?php if ($panelIci): ?>
<div class="offcanvas offcanvas-start admin-mobil-menu d-lg-none" tabindex="-1" id="adminMobilMenu">
    <div class="offcanvas-header">
        <h2 class="offcanvas-title h6 mb-0"><?= e(SITE_ADI) ?></h2>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Kapat"></button>
    </div>
    <div class="offcanvas-body">
        <a class="admin-yan-link<?= $menuSinifi('dashboard') ?>" href="index.php">Günlük özet</a>
        <a class="admin-yan-link<?= $menuSinifi('rezervasyonlar') ?>" href="rezervasyonlar.php">Rezervasyonlar</a>
        <a class="admin-yan-link<?= $menuSinifi('masalar') ?>" href="masalar.php">Masalar</a>
        <form method="post" action="logout.php" class="mt-3">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-sm btn-outline-light">Çıkış</button>
        </form>
    </div>
</div>
<?php endif; ?>

<?php $flashHtml = flash_goster(); ?>
<?php if ($flashHtml !== ''): ?>
    <div class="admin-flash" role="status" aria-live="polite">
        <?= $flashHtml ?>
    </div>
<?php endif; ?>

<main id="ana-icerik" class="admin-icerik">
    <div class="admin-icerik-ic">
