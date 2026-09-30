<?php
/**
 * =====================================================================
 *  ADMIN PANELI - SAYFA BASLIGI
 * ---------------------------------------------------------------------
 *  STUB (Adim 4 - masa/rezervasyon CRUD iscisi)
 *  Kardes isci tam kabugu (sidebar, cikis, dashboard linki) yazabilir.
 *  Bu parca o dosya gelene kadar masalar/rezervasyonlar sayfalarinin
 *  Bootstrap 5 ile acilmasini saglar.
 *
 *  Sayfalar:
 *      $sayfaBasligi = 'Masalar';
 *      $aktifSayfa   = 'masalar';
 *      require APP_KOK . '/partials/admin-header.php';
 * =====================================================================
 */

if (!defined('APP_INIT')) {
    http_response_code(403);
    die('Doğrudan erişim engellendi.');
}

$sayfaBasligi = $sayfaBasligi ?? 'Yönetim';
$aktifSayfa   = $aktifSayfa ?? '';
$yonetici     = class_exists('Auth', false) ? Auth::kullanici() : null;
$yoneticiAd   = is_array($yonetici)
    ? (string) ($yonetici['ad_soyad'] ?? $yonetici['kullanici_adi'] ?? 'Yönetici')
    : 'Yönetici';

$cssSurumu   = @filemtime(APP_KOK . '/assets/css/style.css') ?: time();
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
    <title><?= e($sayfaBasligi) ?> &middot; Yönetim &middot; <?= e(SITE_ADI) ?></title>
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
        crossorigin="anonymous">
    <!-- Admin sayfalari /admin altinda oldugu icin CSS bir ust klasorden gelir. -->
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= e($cssSurumu) ?>">
</head>
<body class="kafe-govde">

<nav class="navbar navbar-expand-lg kafe-navbar sticky-top">
    <div class="container">
        <a class="navbar-brand fw-semibold" href="index.php"><?= e(SITE_ADI) ?> Yönetim</a>
        <button class="navbar-toggler" type="button"
                data-bs-toggle="collapse" data-bs-target="#adminMenu"
                aria-controls="adminMenu" aria-expanded="false"
                aria-label="Menüyü aç/kapat">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="adminMenu">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
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
            <span class="navbar-text small me-3"><?= e($yoneticiAd) ?></span>
            <a class="btn btn-kafe-cizgi btn-sm" href="../index.php">Müşteri sitesi</a>
        </div>
    </div>
</nav>

<?php $flashHtml = flash_goster(); ?>
<?php if ($flashHtml !== ''): ?>
    <div class="container mt-3" role="status" aria-live="polite">
        <?= $flashHtml ?>
    </div>
<?php endif; ?>

<main id="ana-icerik" class="container my-4">
