<?php
/**
 * =====================================================================
 *  KAFE MASA REZERVASYON SISTEMI - YONETICI GIRISI
 * ---------------------------------------------------------------------
 *  Bu sayfa Auth::kontrol() CAGIRMAZ: cagirirsa giris yapmamis kullanici
 *  kendini surekli bu sayfaya yonlendiren bir donguye girer.
 *
 *  Akis:
 *    GET  -> formu goster
 *    POST -> CSRF + girisYap(); basariliysa 303 ile panele git
 * =====================================================================
 */

require_once __DIR__ . '/../app/bootstrap.php';
require_once APP_DIZIN . '/Auth.php';

// Zaten icerdeyse formu tekrar gostermek yaniltir; panele al.
if (Auth::girisYapildiMi()) {
    yonlendir('index.php', 303);
}

if (post_istegi_mi()) {
    csrf_kontrol_et();

    $kullaniciAdi = post('kullanici_adi');
    $sifre        = post('sifre');

    if (Auth::girisYap($kullaniciAdi, $sifre)) {
        flash_ekle('Hoş geldiniz. Oturumunuz açıldı.', 'success');
        yonlendir('index.php', 303);
    }

    // Ayni mesaj: kullanici adi mi sifre mi yanlis, soylemiyoruz.
    flash_ekle('Kullanıcı adı veya şifre hatalı.', 'danger');
    yonlendir('login.php', 303);
}

$sayfaBasligi    = 'Yönetici girişi';
$sayfaAciklamasi = 'Yönetim paneli girişi';
$aktifSayfa      = 'giris';
require __DIR__ . '/../partials/admin-header.php';
?>

<div class="admin-giris-sahne">
    <div class="admin-giris-pano">
        <p class="kafe-rozet mb-3"><?= e(SITE_ADI) ?> · <?= e(KAFE_ACILIS) ?>–<?= e(KAFE_KAPANIS) ?></p>
        <h1 class="h2 mb-3">Kasa ofisi</h1>
        <p class="kafe-alinti kafe-alinti-karanlik mb-3">
            Günlük defter, salon planı, yalnızca yetkili oturum.
        </p>
        <p class="mb-0">Masa düzeni ve rezervasyon durumu buradan yönetilir. Müşteri formu bu oturumu kullanmaz.</p>
    </div>
    <div class="kafe-kart admin-giris-kart">
        <div class="kafe-bolum-baslik mb-4">
            <p class="kafe-ust-etiket">Personel</p>
            <h2 class="h4">Yönetici girişi</h2>
            <p>Panel yalnızca yetkili hesaplarla açılır.</p>
        </div>

        <form method="post" action="login.php" class="kafe-form" novalidate>
            <?= csrf_field() ?>

            <div class="mb-3">
                <label class="form-label" for="kullanici_adi">Kullanıcı adı</label>
                <input class="form-control" type="text" id="kullanici_adi"
                       name="kullanici_adi" autocomplete="username"
                       required autofocus>
            </div>

            <div class="mb-4">
                <label class="form-label" for="sifre">Şifre</label>
                <input class="form-control" type="password" id="sifre"
                       name="sifre" autocomplete="current-password" required>
            </div>

            <button type="submit" class="btn btn-kafe w-100">Giriş yap</button>
        </form>
    </div>
</div>

<?php
require __DIR__ . '/../partials/admin-footer.php';
