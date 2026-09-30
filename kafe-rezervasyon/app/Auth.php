<?php
/**
 * =====================================================================
 *  KAFE MASA REZERVASYON SISTEMI - OTURUM KONTROLU
 * ---------------------------------------------------------------------
 *  STUB (Adim 4 - masa/rezervasyon CRUD iscisi)
 *
 *  Asil Auth.php (giris, cikis, session_regenerate_id) kardes isci
 *  tarafindan yazilir. Bu dosya, o dosya henuz yokken admin/masalar.php
 *  ve admin/rezervasyonlar.php sayfalarinin calisabilmesi icindir.
 *
 *  Hedef API (degistirmeyin; asil dosya da ayni imzayi tasiyacak):
 *      Auth::kontrol()     -> giris yoksa login sayfasina yonlendirir
 *      Auth::kullanici()   -> oturumdaki yonetici dizisi veya null
 *
 *  STUB davranisi: login.php yoksa yonlendirme dongusune dusmemek icin
 *  oturuma gecici bir yonetici yazar. Canliya cikmadan ONCE bu dosya
 *  gercek Auth ile degistirilmelidir.
 * =====================================================================
 */

if (!defined('APP_INIT')) {
    http_response_code(403);
    die('Doğrudan erişim engellendi.');
}

final class Auth
{
    /**
     * Admin sayfalarinin ilk satiri. Giris yoksa iceri almaz.
     *
     * Neden her sorguda tekrar? Cunku oturum cerezi tarayicidadir;
     * kullanici URL'yi ezberleyip dogrudan masalar.php acabilir.
     * "Navbar'da link gizledik" yetki kontrolu degildir.
     */
    public static function kontrol(): void
    {
        if (self::kullanici() !== null) {
            return;
        }

        // STUB: gercek giris akisi henuz yoksa sayfalar yine acilsin.
        // login.php hazir oldugunda asil Auth bu blogu yonlendirme ile
        // degistirir; asagidaki sahte oturum KALMAMALIDIR.
        if (!is_file(APP_KOK . '/admin/login.php')) {
            $_SESSION['yonetici'] = [
                'id'            => 0,
                'kullanici_adi' => 'stub',
                'ad_soyad'      => 'STUB Yönetici',
            ];

            return;
        }

        yonlendir('login.php');
    }

    /**
     * Oturumdaki yonetici. Yoksa null.
     */
    public static function kullanici(): ?array
    {
        $kullanici = $_SESSION['yonetici'] ?? null;

        return is_array($kullanici) ? $kullanici : null;
    }
}
