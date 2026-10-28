<?php
/**
 * =====================================================================
 *  KAFE MASA REZERVASYON SISTEMI - REZERVASYON ONAY SAYFASI
 * ---------------------------------------------------------------------
 *  Kayit index.php'de 303 ile buraya gelir. Ozet session'daki
 *  son_rezervasyon anahtarindan okunur (adres cubuguna kod yazilmaz).
 *
 *  Bu sayfada session SILINMEZ: yazdir / yenile ayni ozeti korur.
 *  Ana sayfa (index.php) flash-tarzi okuyup silmeye devam eder.
 * =====================================================================
 */

require_once __DIR__ . '/app/bootstrap.php';

$sonRezervasyon = $_SESSION['son_rezervasyon'] ?? null;

if (!is_array($sonRezervasyon) || empty($sonRezervasyon['kod'])) {
    flash_ekle('Gösterilecek yeni rezervasyon yok. Kodunuzla sorgulayabilirsiniz.', 'info');
    yonlendir('rezervasyon-sorgula.php');
}

$sayfaBasligi    = 'Rezervasyon onaylandı';
$sayfaAciklamasi = 'Masanız ayrıldı. Rezervasyon kodunuzu saklayın.';
$aktifSayfa      = 'ana';
require __DIR__ . '/partials/header.php';
?>

<div class="container my-4 my-lg-5">
    <section class="kafe-basari-kart kafe-onay-sayfa kafe-fis" id="rezervasyon-sonucu">
        <header class="kafe-fis-ust">
            <p class="kafe-ust-etiket mb-1"><?= e(SITE_ADI) ?></p>
            <h1 class="h3 mb-1">Masa davetiyesi</h1>
            <p class="kafe-fis-adres mb-0">
                <?= e(KAFE_ADRES) ?>
                &middot;
                <?= e(KAFE_ACILIS) ?>–<?= e(KAFE_KAPANIS) ?>
            </p>
        </header>
        <div class="row gy-4 align-items-center">
            <div class="col-lg-5 text-center kafe-onay-kod">
                <figure class="kafe-onay-foto no-print">
                    <img src="assets/img/salon-masa.jpg"
                         alt="Ayrılmış masa"
                         width="1000" height="667">
                </figure>
                <p class="kafe-kod-etiket">Davetiye kodu</p>
                <p class="kafe-kod"><?= e($sonRezervasyon['kod']) ?></p>
                <div class="alert alert-warning small mb-0 text-start no-print">
                    <strong>Bu kodu saklayın.</strong>
                    Sorgulama ve iptal için bu kod gereklidir. Başkasıyla paylaşmayın.
                </div>
            </div>
            <div class="col-lg-7">
                <p class="kafe-ust-etiket mb-2 no-print"><?= e(SITE_ADI) ?></p>
                <h2 class="h3 mb-3 no-print">Masanız deftere işlendi</h2>
                <dl class="kafe-ozet">
                    <dt>Ad Soyad</dt>
                    <dd><?= e($sonRezervasyon['ad']) ?></dd>
                    <dt>Masa</dt>
                    <dd>
                        <?= e($sonRezervasyon['masa_adi']) ?>
                        <span class="badge kafe-konum-rozet">
                            <?= ($sonRezervasyon['konum'] ?? '') === 'dis' ? 'Bahçe' : 'İç mekan' ?>
                        </span>
                    </dd>
                    <dt>Tarih</dt>
                    <dd><?= e(tarih_goster($sonRezervasyon['tarih'])) ?></dd>
                    <dt>Saat</dt>
                    <dd><?= e($sonRezervasyon['baslangic']) ?> &ndash; <?= e($sonRezervasyon['bitis']) ?></dd>
                    <dt>Kişi</dt>
                    <dd><?= e($sonRezervasyon['kisi_sayisi']) ?> kişi</dd>
                </dl>
                <p class="kafe-fis-dip d-none d-print-block mb-0">
                    Kapıda bu kodu gösterin. Ödeme bu projede yoktur; sipariş salonda alınır.
                </p>
                <div class="d-flex flex-wrap gap-2 mt-4 no-print">
                    <button type="button" class="btn btn-kafe" onclick="window.print()">Davetiyeyi yazdır</button>
                    <a class="btn btn-kafe-cizgi"
                       href="rezervasyon-sorgula.php?kod=<?= u($sonRezervasyon['kod']) ?>">
                        Rezervasyonu görüntüle
                    </a>
                    <a class="btn btn-kafe-cizgi" href="index.php">Yeni rezervasyon</a>
                </div>
                <p class="form-text mt-3 mb-0 no-print">
                    Yazdırınca menü ve fotoğraf gizlenir; kâğıtta yalnızca kafe adı, kod ve masa özeti kalır.
                </p>
            </div>
        </div>
    </section>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
