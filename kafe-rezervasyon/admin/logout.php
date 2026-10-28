<?php
/**
 * =====================================================================
 *  KAFE MASA REZERVASYON SISTEMI - YONETICI CIKISI
 * ---------------------------------------------------------------------
 *  Sadece POST kabul edilir. GET ile cikis:
 *    - CSRF token tasiyamaz (baglantiya yazmak token'i log'a dusurur)
 *    - Onizleme istekleri (e-posta istemcisi, prefetch) oturumu
 *      kullanicinin haberi olmadan kapatabilir
 *
 *  Basarili POST sonrasi 303 ile login.php: F5 cikis formunu tekrar
 *  gondermez.
 * =====================================================================
 */

require_once __DIR__ . '/../app/bootstrap.php';
require_once APP_DIZIN . '/Auth.php';

if (!post_istegi_mi()) {
    // GET gelirse sessizce uygun yere don: cikis YAPMA.
    if (Auth::girisYapildiMi()) {
        yonlendir('index.php', 303);
    }
    yonlendir('login.php', 303);
}

csrf_kontrol_et();
Auth::cikisYap();
flash_ekle('Oturumunuz kapatıldı.', 'info');
yonlendir('login.php', 303);
