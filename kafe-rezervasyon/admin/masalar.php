<?php
/**
 * =====================================================================
 *  ADMIN - MASA YONETIMI (CRUD)
 * ---------------------------------------------------------------------
 *  Liste + ekle / duzenle / sil (veya pasif yap).
 *
 *  Musteri sayfalarina (index.php) dokunulmaz. Pasif masa musait
 *  listesine zaten girmez (MasaRepository::musaitMasalar WHERE durum=aktif).
 * =====================================================================
 */

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once APP_DIZIN . '/Auth.php';
require_once APP_DIZIN . '/MasaRepository.php';

Auth::kontrol();

$konumEtiket = [
    'ic'  => 'İç mekan',
    'dis' => 'Bahçe',
];

$form = [
    'id'       => 0,
    'masa_adi' => '',
    'kapasite' => 2,
    'konum'    => 'ic',
    'durum'    => 'aktif',
];
$formHatalar = [];
$duzenleme   = false;


// =====================================================================
// POST: CSRF -> islem -> PRG (303)
// ---------------------------------------------------------------------
// Kayit/silme sonrasi ayni POST'u gostermek F5 ile cift kayit/cift silme
// uretir. yonlendir() 303 See Other ile tarayiciya "bundan sonra GET yap"
// der; sonraki yenileme sadece listeyi okur.
// =====================================================================
if (post_istegi_mi()) {
    csrf_kontrol_et();

    $islem = post('islem');

    if ($islem === 'kaydet') {
        $form['id']       = post_int('id', 0, 0);
        $form['masa_adi'] = post('masa_adi');
        $form['kapasite'] = post_int('kapasite', 2, 1, 30);
        $form['konum']    = post('konum');
        $form['durum']    = post('durum') !== '' ? post('durum') : 'aktif';

        if ($form['id'] > 0) {
            $sonuc = MasaRepository::guncelle(
                $form['id'],
                $form['masa_adi'],
                $form['kapasite'],
                $form['konum'],
                $form['durum']
            );
        } else {
            $sonuc = MasaRepository::ekle(
                $form['masa_adi'],
                $form['kapasite'],
                $form['konum']
            );
        }

        if (!empty($sonuc['basarili'])) {
            flash_ekle(
                $form['id'] > 0 ? 'Masa güncellendi.' : 'Yeni masa eklendi.',
                'success'
            );
            yonlendir('masalar.php');
        }

        $formHatalar['genel'] = $sonuc['hata'] ?? 'Kayıt yapılamadı.';
        $duzenleme = $form['id'] > 0;
    } elseif ($islem === 'sil') {
        $id = post_int('id', 0, 0);

        $sonuc = MasaRepository::sil($id);

        if (empty($sonuc['basarili'])) {
            flash_ekle($sonuc['hata'] ?? 'Masa silinemedi.', 'danger');
        } elseif ($sonuc['islem'] === 'pasif') {
            // ON DELETE RESTRICT: gecmis rezervasyonlarin masa satiri
            // durmak zorunda. Kullaniciya "silinmedi" demek dogru;
            // sessizce pasife cekmek "neden listede duruyor?" sorusu dogurur.
            flash_ekle(
                'Bu masanın geçmiş rezervasyonları var; silinmedi, rezervasyona kapatıldı (pasif).',
                'warning'
            );
        } else {
            flash_ekle('Masa silindi.', 'success');
        }

        yonlendir('masalar.php');
    } elseif ($islem === 'pasif') {
        $id = post_int('id', 0, 0);

        if (MasaRepository::pasifYap($id)) {
            flash_ekle('Masa rezervasyona kapatıldı (pasif).', 'success');
        } else {
            flash_ekle('Masa güncellenemedi.', 'danger');
        }

        yonlendir('masalar.php');
    } else {
        flash_ekle('Geçersiz işlem.', 'danger');
        yonlendir('masalar.php');
    }
}


// GET ?duzenle=ID  -> formu dolu goster (PRG sonrasi id URL'de kalir)
$duzenleId = get('duzenle');
if ($duzenleId !== '' && ctype_digit($duzenleId) && $formHatalar === []) {
    $kayit = MasaRepository::bul((int) $duzenleId);
    if ($kayit === null) {
        flash_ekle('Düzenlenecek masa bulunamadı.', 'danger');
        yonlendir('masalar.php');
    }
    $form = [
        'id'       => (int) $kayit['id'],
        'masa_adi' => (string) $kayit['masa_adi'],
        'kapasite' => (int) $kayit['kapasite'],
        'konum'    => (string) $kayit['konum'],
        'durum'    => (string) $kayit['durum'],
    ];
    $duzenleme = true;
}

$masalar = MasaRepository::tumu();

$sayfaBasligi = 'Masalar';
$aktifSayfa   = 'masalar';
require APP_KOK . '/partials/admin-header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h3 mb-1">Masalar</h1>
        <p class="text-muted mb-0">Kapasite 1–30. Üzerinde rezervasyon olan masa silinemez; pasif yapılır.</p>
    </div>
</div>

<div class="row g-4">

    <div class="col-lg-4">
        <div class="kafe-kart">
            <h2 class="h5 mb-3"><?= $duzenleme ? 'Masayı düzenle' : 'Yeni masa ekle' ?></h2>

            <?php if (isset($formHatalar['genel'])): ?>
                <div class="alert alert-danger"><?= e($formHatalar['genel']) ?></div>
            <?php endif; ?>

            <form method="post" action="masalar.php" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="islem" value="kaydet">
                <input type="hidden" name="id" value="<?= e($form['id']) ?>">

                <div class="mb-3">
                    <label for="masa_adi" class="form-label">Masa adı</label>
                    <input type="text" class="form-control" id="masa_adi" name="masa_adi"
                           value="<?= e($form['masa_adi']) ?>" maxlength="50" required
                           placeholder="Örn. Masa 5, Bahçe 3">
                </div>

                <div class="mb-3">
                    <label for="kapasite" class="form-label">Kapasite</label>
                    <input type="number" class="form-control" id="kapasite" name="kapasite"
                           value="<?= e($form['kapasite']) ?>" min="1" max="30" required>
                    <div class="form-text">En az 1, en fazla 30 kişi.</div>
                </div>

                <div class="mb-3">
                    <label for="konum" class="form-label">Konum</label>
                    <select class="form-select" id="konum" name="konum" required>
                        <option value="ic" <?= $form['konum'] === 'ic' ? 'selected' : '' ?>>İç mekan</option>
                        <option value="dis" <?= $form['konum'] === 'dis' ? 'selected' : '' ?>>Bahçe</option>
                    </select>
                </div>

                <?php if ($duzenleme): ?>
                    <div class="mb-3">
                        <label for="durum" class="form-label">Durum</label>
                        <select class="form-select" id="durum" name="durum">
                            <option value="aktif" <?= $form['durum'] === 'aktif' ? 'selected' : '' ?>>Aktif</option>
                            <option value="pasif" <?= $form['durum'] === 'pasif' ? 'selected' : '' ?>>Pasif</option>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-kafe">
                        <?= $duzenleme ? 'Güncelle' : 'Ekle' ?>
                    </button>
                    <?php if ($duzenleme): ?>
                        <a class="btn btn-outline-secondary" href="masalar.php">Vazgeç</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="table-responsive kafe-kart p-0">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Ad</th>
                        <th>Kapasite</th>
                        <th>Konum</th>
                        <th>Durum</th>
                        <th>Rezervasyon</th>
                        <th class="text-end">İşlem</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($masalar === []): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">Henüz masa yok.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($masalar as $masa): ?>
                        <?php
                        $masaId = (int) $masa['id'];
                        $rezSayisi = MasaRepository::rezervasyonSayisi($masaId);
                        ?>
                        <tr>
                            <td><?= e($masa['masa_adi']) ?></td>
                            <td><?= e($masa['kapasite']) ?></td>
                            <td><?= e($konumEtiket[$masa['konum']] ?? $masa['konum']) ?></td>
                            <td>
                                <?php if ($masa['durum'] === 'aktif'): ?>
                                    <span class="badge bg-success">Aktif</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Pasif</span>
                                <?php endif; ?>
                            </td>
                            <td><?= e($rezSayisi) ?></td>
                            <td class="text-end">
                                <div class="d-inline-flex flex-wrap gap-1 justify-content-end">
                                    <a class="btn btn-sm btn-outline-secondary"
                                       href="masalar.php?duzenle=<?= e($masaId) ?>">Düzenle</a>

                                    <?php if ($masa['durum'] === 'aktif'): ?>
                                        <form method="post" action="masalar.php" class="d-inline"
                                              data-onay="Bu masayı rezervasyona kapatmak (pasif) istediğinize emin misiniz?">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="islem" value="pasif">
                                            <input type="hidden" name="id" value="<?= e($masaId) ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-warning">Pasif yap</button>
                                        </form>
                                    <?php endif; ?>

                                    <form method="post" action="masalar.php" class="d-inline"
                                          data-onay="<?= $rezSayisi > 0
                                              ? 'Bu masanın rezervasyonları var. Silinemez; pasif yapılacak. Devam edilsin mi?'
                                              : 'Bu masayı kalıcı olarak silmek istediğinize emin misiniz?' ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="islem" value="sil">
                                        <input type="hidden" name="id" value="<?= e($masaId) ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <?= $rezSayisi > 0 ? 'Sil / pasif' : 'Sil' ?>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<?php require APP_KOK . '/partials/admin-footer.php'; ?>
