<?php
/**
 * =====================================================================
 *  ADMIN PANELI - SAYFA ALTI
 * ---------------------------------------------------------------------
 *  STUB (Adim 4 - masa/rezervasyon CRUD iscisi)
 *  Bootstrap JS + yalnizca onay diyaloglari icin admin.js.
 * =====================================================================
 */

if (!defined('APP_INIT')) {
    http_response_code(403);
    die('Doğrudan erişim engellendi.');
}

$adminJsSurumu = $adminJsSurumu ?? (@filemtime(APP_KOK . '/assets/js/admin.js') ?: time());
?>
</main>

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
    crossorigin="anonymous"></script>
<script src="../assets/js/admin.js?v=<?= e($adminJsSurumu) ?>"></script>
</body>
</html>
