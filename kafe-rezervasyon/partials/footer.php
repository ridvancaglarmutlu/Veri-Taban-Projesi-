<?php
/**
 * =====================================================================
 *  KAFE MASA REZERVASYON SISTEMI - MUSTERI TARAFI SAYFA ALTI
 * ---------------------------------------------------------------------
 *  header.php'de acilan <main>, <body> ve <html> etiketleri burada
 *  kapanir. Bir parcanin etiket acip digerinin kapatmasi ilk bakista
 *  tuhaf gorunur; ama bu sayede sayfa dosyalari SADECE kendi icerigini
 *  yazar ve HTML iskeleti tek yerde durur.
 * =====================================================================
 */

if (!defined('APP_INIT')) {
    http_response_code(403);
    die('Doğrudan erişim engellendi.');
}

// header.php icinde hesaplanmisti; sayfa dogrudan footer'i cagirirsa
// diye burada da yedegi var.
$jsSurumu = $jsSurumu ?? (@filemtime(APP_KOK . '/assets/js/app.js') ?: time());
?>
</main>

<footer class="kafe-footer mt-5">
    <div class="container py-4">
        <div class="row gy-3 align-items-center">

            <div class="col-md-6">
                <p class="mb-1 fw-semibold"><?= e(SITE_ADI) ?></p>
                <p class="mb-0 small opacity-75"><?= e(SITE_SLOGAN) ?></p>
            </div>

            <div class="col-md-6 text-md-end small opacity-75">
                <p class="mb-1">
                    Çalışma saatleri:
                    <?= e(KAFE_ACILIS) ?> &ndash; <?= e(KAFE_KAPANIS) ?>
                </p>
                <p class="mb-0">
                    <!--
                        date('Y') her yil elle guncelleme derdini bitirir.
                        Zaman dilimi bootstrap.php'de Europe/Istanbul olarak
                        sabitlendigi icin yil donumunde de dogru sonuc verir.
                    -->
                    &copy; <?= e(date('Y')) ?> &middot; Veritabanı dersi projesi
                </p>
            </div>

        </div>
    </div>
</footer>

<!--
    =================================================================
     JAVASCRIPT - NEDEN SAYFANIN SONUNDA?
    =================================================================
    Tarayici <script> etiketiyle karsilastiginda HTML ayristirmasini
    DURDURUR, dosyayi indirir ve calistirir. Script <head> icinde olsaydi
    kullanici bu sure boyunca BOS SAYFA gorurdu.

    Ikinci ve daha somut sebep: app.js sayfadaki form elemanlarini
    document.getElementById ile ariyor. Script <head> icinde calissaydi o
    elemanlar HENUZ OLUSMAMIS olurdu ve her arama null donerdi
    ("Cannot read properties of null" hatasi). Sonda calistirmak,
    DOMContentLoaded beklemeye bile gerek birakmaz.

    Alternatif: <head> icinde "defer" ozniteligi kullanmak. Sonuc
    benzerdir; burada daha acik oldugu icin klasik yontemi sectik.
-->
<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
    crossorigin="anonymous"></script>

<!--
    bundle = bootstrap.js + Popper.js birlikte. Popper, tooltip ve
    dropdown'larin konumlandirmasini yapar; ayri dosya olarak
    yuklemeyi unutmak "dropdown aciliyor ama yanlis yerde duruyor"
    hatasinin klasik sebebidir.
-->
<script src="assets/js/app.js?v=<?= e($jsSurumu) ?>"></script>

</body>
</html>
