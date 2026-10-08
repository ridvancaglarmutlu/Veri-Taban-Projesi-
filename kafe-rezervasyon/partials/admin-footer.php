<?php
/**
 * =====================================================================
 *  KAFE MASA REZERVASYON SISTEMI - ADMIN SAYFA ALTI
 * ---------------------------------------------------------------------
 *  Musteri footer'indaki app.js BURAYA YUKLENMEZ. O dosya rezervasyon
 *  formunu arar; form yoksa zararsizca cikar ama admin sayfasina
 *  musteri betigi tasimak, ileride "neden admin konsolu API cagiriyor?"
 *  kafasini karistirir.
 *
 *  Bootstrap JS hamburger / offcanvas icin gerekir.
 *  admin.js yalnizca data-onay="..." formlarinda confirm() sorar.
 * =====================================================================
 */

if (!defined('APP_INIT')) {
    http_response_code(403);
    die('Doğrudan erişim engellendi.');
}

$adminJsSurumu = $adminJsSurumu ?? (@filemtime(APP_KOK . '/assets/js/admin.js') ?: time());
?>
    </div>
</main>

<footer class="kafe-footer admin-footer">
    <div class="admin-icerik-ic py-3">
        <div class="d-flex flex-wrap justify-content-between gap-2 small opacity-75">
            <span><?= e(SITE_ADI) ?> kasa ofisi</span>
            <span>&copy; <?= e(date('Y')) ?></span>
        </div>
    </div>
</footer>
</div><!-- .admin-cerceve -->

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
    crossorigin="anonymous"></script>
<script src="../assets/js/admin.js?v=<?= e($adminJsSurumu) ?>"></script>
</body>
</html>
