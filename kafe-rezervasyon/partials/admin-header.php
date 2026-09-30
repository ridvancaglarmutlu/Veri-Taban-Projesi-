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

$cssSurumu = @filemtime(APP_KOK . '/assets/css/style.css') ?: time();

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
<body class="kafe-govde admin-govde">

<a class="icerige-atla" href="#ana-icerik">İçeriğe atla</a>

<nav class="navbar navbar-expand-lg kafe-navbar admin-navbar sticky-top">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="<?= $yonetici ? 'index.php' : 'login.php' ?>">
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

        <button class="navbar-toggler" type="button"
                data-bs-toggle="collapse" data-bs-target="#adminMenu"
                aria-controls="adminMenu" aria-expanded="false"
                aria-label="Menüyü aç/kapat">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="adminMenu">
            <?php if ($yonetici): ?>
                <ul class="navbar-nav me-auto align-items-lg-center gap-lg-1">
                    <li class="nav-item">
                        <a class="nav-link<?= $menuSinifi('dashboard') ?>"
                           <?= $aktifSayfa === 'dashboard' ? 'aria-current="page"' : '' ?>
                           href="index.php">Panel</a>
                    </li>
                    <li class="nav-item">
                        <!--
                            masalar.php / rezervasyonlar.php bu adimda
                            URETILMEZ; baska bir gelistirici ekler. Linkler
                            yine de durur ki kabuk (shell) tamamlanmis olsun
                            ve o sayfalar geldiginde navbar'i yeniden
                            yazmak gerekmesin.
                        -->
                        <a class="nav-link<?= $menuSinifi('masalar') ?>"
                           <?= $aktifSayfa === 'masalar' ? 'aria-current="page"' : '' ?>
                           href="masalar.php">Masalar</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link<?= $menuSinifi('rezervasyonlar') ?>"
                           <?= $aktifSayfa === 'rezervasyonlar' ? 'aria-current="page"' : '' ?>
                           href="rezervasyonlar.php">Rezervasyonlar</a>
                    </li>
                </ul>

                <div class="d-flex flex-wrap align-items-center gap-2 ms-lg-auto">
                    <span class="admin-kullanici small">
                        <?= e($yonetici['ad_soyad'] !== '' ? $yonetici['ad_soyad'] : $yonetici['kullanici_adi']) ?>
                    </span>
                    <!--
                        Cikis GET ile yapILMAZ. <a href="logout.php"> tarayici
                        onbellekleri, e-posta istemcileri ve <img src> ile
                        tetiklenebilir; CSRF token tasiyamaz. POST + csrf_field
                        hem "yanlislikla cikis"i hem siteler arasi cikis
                        istegini engeller.
                    -->
                    <form method="post" action="logout.php" class="m-0">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-sm btn-outline-light">Çıkış</button>
                    </form>
                </div>
            <?php else: ?>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../index.php">Müşteri sitesi</a>
                    </li>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</nav>

<?php $flashHtml = flash_goster(); ?>
<?php if ($flashHtml !== ''): ?>
    <div class="container mt-3" role="status" aria-live="polite">
        <?= $flashHtml ?>
    </div>
<?php endif; ?>

<main id="ana-icerik" class="admin-icerik">
