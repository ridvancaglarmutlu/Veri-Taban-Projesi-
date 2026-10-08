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

<section class="kafe-hero kafe-hero-kisa kafe-hero-hakkinda">
    <div class="container kafe-hero-ic">
        <span class="kafe-rozet mb-3">Salon defteri</span>
        <h1 class="kafe-hero-baslik">
            Işıklar kısık,<br>
            <span class="kafe-italik">masalar numaralı.</span>
        </h1>
        <p class="kafe-hero-metin mb-0">
            <?= e(SITE_ADI) ?> ders projesi için kurgulanmış bir salondur.
            Gerçek bir işletme değildir; rezervasyon akışı öğretim kapsamında çalışır.
        </p>
    </div>
</section>

<div class="container my-5">
    <div class="row g-4 align-items-stretch">
        <div class="col-lg-6">
            <figure class="kafe-foto-cerceve mb-0 h-100">
                <img src="assets/img/fincan.jpg"
                     alt="Fincanlarda demlenmiş kahve"
                     width="900" height="600" loading="lazy">
                <figcaption>Yerel stok · salon fincanı</figcaption>
            </figure>
        </div>
        <div class="col-lg-6">
            <article class="kafe-kart kafe-metin-kart h-100">
                <p class="kafe-ust-etiket">Hikâye</p>
                <blockquote class="kafe-alinti">
                    Sabah dokuzda ilk demlik; gece on birde son fincan.
                </blockquote>
                <h2 class="h3 mb-3">Ahşap salon, pirinç çizgi.</h2>
                <p>
                    İçeride düşük ışık ve numaralı masalar; bahçede ısıtıcılı köşe.
                    Kalabalık akşamlar için yer, deftere önceden yazılır.
                    Ödeme veya üyelik yoktur — sipariş kapıdadır.
                </p>
                <p class="mb-0">
                    Rezervasyon kodunuz davetiye gibidir: onunla kaydı görür,
                    gerekirse aynı gün iptal edersiniz.
                </p>
            </article>
        </div>
        <div class="col-12">
            <figure class="kafe-foto-cerceve mb-0">
                <img src="assets/img/salon-vitrin.jpg"
                     alt="Vitrin ve iç mekan masaları"
                     width="1000" height="686" loading="lazy">
                <figcaption>İç salon vitrini · yerel stok</figcaption>
            </figure>
        </div>
        <div class="col-lg-7">
            <aside class="kafe-kart kafe-saat-cizelge h-100">
                <p class="kafe-ust-etiket">Açılış saatleri</p>
                <h2 class="h4 mb-3">Her gün aynı ritim</h2>
                <dl class="kafe-saat-liste">
                    <div>
                        <dt>Hafta içi</dt>
                        <dd><?= e(KAFE_ACILIS) ?> &ndash; <?= e(KAFE_KAPANIS) ?></dd>
                    </div>
                    <div>
                        <dt>Hafta sonu</dt>
                        <dd><?= e(KAFE_ACILIS) ?> &ndash; <?= e(KAFE_KAPANIS) ?></dd>
                    </div>
                    <div>
                        <dt>Mutfak</dt>
                        <dd>Kapanıştan yarım saat önce son sipariş</dd>
                    </div>
                </dl>
            </aside>
        </div>
        <div class="col-lg-5">
            <aside class="kafe-kart kafe-iletisim-kart h-100">
                <p class="kafe-ust-etiket">İletişim</p>
                <h2 class="h4 mb-3">Yer tutucu kart</h2>
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
