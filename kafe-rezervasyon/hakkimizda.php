<?php
/**
 * =====================================================================
 *  KAFE MASA REZERVASYON SISTEMI - HAKKIMIZDA / ILETISIM
 * ---------------------------------------------------------------------
 *  Kisa marka metni ve ornek iletisim bilgileri. Adres / telefon /
 *  e-posta gercek bir isletmeye ait DEGILDIR; ders projesi icin
 *  yer tutucudur (config.php).
 * =====================================================================
 */

require_once __DIR__ . '/app/bootstrap.php';

$sayfaBasligi    = 'Hakkımızda';
$sayfaAciklamasi = SITE_ADI . ' — mahalle kahvesi, masa rezervasyonu.';
$aktifSayfa      = 'hakkimizda';
require __DIR__ . '/partials/header.php';
?>

<section class="kafe-hero kafe-hero-kisa">
    <div class="container">
        <span class="kafe-rozet mb-3">Mahalle kahvesi</span>
        <h1 class="kafe-hero-baslik">Masaların dolu olduğu sokak köşesi.</h1>
        <p class="kafe-hero-metin mb-0">
            <?= e(SITE_ADI) ?> öğrenci projesi için kurgulanmış bir mahalle kahvesidir.
            Gerçek bir işletme değildir; rezervasyon akışı ders kapsamında çalışır.
        </p>
    </div>
</section>

<div class="container my-5">
    <div class="row g-4">
        <div class="col-lg-7">
            <article class="kafe-kart kafe-metin-kart h-100">
                <p class="kafe-ust-etiket">Hikâye</p>
                <h2 class="h3 mb-3">Espresso, krema, pirinç.</h2>
                <p>
                    Sabah dokuzda ilk demlik, gece on birde son fincan.
                    İçeride ahşap masalar, bahçede ısıtıcılı köşe.
                    Kalabalık akşamlar için masayı önceden ayırmak yeterlidir.
                </p>
                <p class="mb-0">
                    Rezervasyon kodunuzla kaydınızı sorgulayabilir, gerekirse
                    aynı gün iptal edebilirsiniz. Ödeme veya üyelik yoktur;
                    kapıda sipariş.
                </p>
            </article>
        </div>
        <div class="col-lg-5">
            <aside class="kafe-kart kafe-iletisim-kart h-100">
                <p class="kafe-ust-etiket">İletişim</p>
                <h2 class="h4 mb-3">Bize yazın</h2>
                <dl class="kafe-ozet">
                    <dt>Adres</dt>
                    <dd><?= e(KAFE_ADRES) ?></dd>
                    <dt>Telefon</dt>
                    <dd><a href="tel:<?= e(preg_replace('/\s+/', '', KAFE_TELEFON_GOSTER)) ?>"><?= e(KAFE_TELEFON_GOSTER) ?></a></dd>
                    <dt>E-posta</dt>
                    <dd><a href="mailto:<?= e(KAFE_EPOSTA) ?>"><?= e(KAFE_EPOSTA) ?></a></dd>
                    <dt>Saatler</dt>
                    <dd><?= e(KAFE_ACILIS) ?> &ndash; <?= e(KAFE_KAPANIS) ?></dd>
                </dl>
                <p class="small text-muted mb-0 mt-3">
                    Adres ve iletişim satırları ödev için üretilmiş yer tutuculardır.
                </p>
            </aside>
        </div>
    </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
