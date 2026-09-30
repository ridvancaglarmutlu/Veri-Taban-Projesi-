<?php
/**
 * =====================================================================
 *  ADMIN - REZERVASYON LISTESI
 * ---------------------------------------------------------------------
 *  Filtre (tarih, durum, masa) + durum butonlari.
 *
 *  Durum degisikligi RezervasyonRepository::durumGuncelle() ile yapilir.
 *  Cakisma SQL'i burada YENIDEN YAZILMAZ; onay'a cekerken mevcut
 *  cakismaVarMi(..., $haricId) cagirilir. Kosul depoda durur:
 *      baslangic_saati < :bitis AND bitis_saati > :baslangic
 *      AND durum = 'onaylandi'
 *
 *  Musteri tarafinda rezervasyon OLUSTURMA formu yoktur; asil is
 *  liste + filtre + durumdur.
 * =====================================================================
 */

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once APP_DIZIN . '/Auth.php';
require_once APP_DIZIN . '/MasaRepository.php';
require_once APP_DIZIN . '/RezervasyonRepository.php';

Auth::kontrol();

$izinliDurumlar = ['onaylandi', 'iptal', 'tamamlandi'];


function admin_filtre_url(array $filtre, array $ek = []): string
{
    $sorgu = array_filter(
        array_merge($filtre, $ek),
        static fn ($v) => $v !== '' && $v !== null
    );

    $qs = http_build_query($sorgu);

    return $qs === '' ? 'rezervasyonlar.php' : 'rezervasyonlar.php?' . $qs;
}


// GET filtreleri: paylasilabilir URL. CSRF GET'te gerekmez (yan etki yok).
$filtre = [
    'tarih'   => get('tarih'),
    'durum'   => get('durum'),
    'masa_id' => get('masa_id'),
];

if ($filtre['tarih'] !== '' && !gecerli_tarih($filtre['tarih'])) {
    $filtre['tarih'] = '';
}

if ($filtre['durum'] !== '' && !in_array($filtre['durum'], $izinliDurumlar, true)) {
    $filtre['durum'] = '';
}

if ($filtre['masa_id'] !== '' && !ctype_digit($filtre['masa_id'])) {
    $filtre['masa_id'] = '';
}


if (post_istegi_mi()) {
    csrf_kontrol_et();

    $id    = post_int('id', 0, 0);
    $durum = post('durum');

    // Filtreleri POST'tan gizlenmis alanlarla geri tasiyoruz ki
    // durum degisince "bugunun onaylilari" listesi sifirlanmasin.
    $geri = [
        'tarih'   => post('filtre_tarih'),
        'durum'   => post('filtre_durum'),
        'masa_id' => post('filtre_masa_id'),
    ];

    if ($id < 1 || !in_array($durum, $izinliDurumlar, true)) {
        flash_ekle('Geçersiz durum güncellemesi.', 'danger');
        yonlendir(admin_filtre_url($geri));
    }

    $kayit = Database::fetchOne(
        'SELECT id, masa_id, tarih, baslangic_saati, bitis_saati, durum
           FROM rezervasyonlar
          WHERE id = :id',
        [':id' => $id]
    );

    if ($kayit === null) {
        flash_ekle('Rezervasyon bulunamadı.', 'danger');
        yonlendir(admin_filtre_url($geri));
    }

    // Iptal edilmis/tamamlanmis kaydi yeniden "onaylandi" yapmak, o saatte
    // baska bir onayli kayit varsa cift satisa yol acar. Cakisma kontrolunu
    // depodaki tek metottan cagiriyoruz; haricId = bu kaydin kendisi
    // (kendi araligiyla "cakisiyor" diye reddedilmesin).
    if ($durum === 'onaylandi') {
        $cakisiyor = RezervasyonRepository::cakismaVarMi(
            (int) $kayit['masa_id'],
            substr((string) $kayit['tarih'], 0, 10),
            saat_goster((string) $kayit['baslangic_saati']),
            saat_goster((string) $kayit['bitis_saati']),
            (int) $kayit['id']
        );

        if ($cakisiyor) {
            flash_ekle(
                'Bu saat aralığında masada başka onaylı rezervasyon var. Onaylanamadı.',
                'danger'
            );
            yonlendir(admin_filtre_url($geri));
        }
    }

    if (RezervasyonRepository::durumGuncelle($id, $durum)) {
        flash_ekle('Rezervasyon durumu güncellendi: ' . durum_etiketi($durum) . '.', 'success');
    } else {
        flash_ekle('Durum değişmedi (kayıt zaten bu durumdaydı veya bulunamadı).', 'warning');
    }

    yonlendir(admin_filtre_url($geri));
}


// Liste sorgusu sayfada: filtre kolonlari beyaz listeden eklenir,
// degerler prepared statement parametresidir. Cakisma SQL'i kopyalanmaz.
$sql = 'SELECT r.id, r.rezervasyon_kodu, r.masa_id, r.musteri_adi,
               r.musteri_telefon, r.tarih, r.baslangic_saati, r.bitis_saati,
               r.kisi_sayisi, r.durum, r.musteri_notu,
               m.masa_adi, m.konum
          FROM rezervasyonlar r
          INNER JOIN masalar m ON m.id = r.masa_id
         WHERE 1 = 1';

$parametreler = [];

if ($filtre['tarih'] !== '') {
    $sql .= ' AND r.tarih = :tarih';
    $parametreler[':tarih'] = $filtre['tarih'];
}

if ($filtre['durum'] !== '') {
    $sql .= ' AND r.durum = :durum';
    $parametreler[':durum'] = $filtre['durum'];
}

if ($filtre['masa_id'] !== '') {
    $sql .= ' AND r.masa_id = :masa_id';
    $parametreler[':masa_id'] = (int) $filtre['masa_id'];
}

$sql .= ' ORDER BY r.tarih DESC, r.baslangic_saati DESC, r.id DESC';

$kayitlar = Database::fetchAll($sql, $parametreler);
$masalar  = MasaRepository::tumu();

$sayfaBasligi = 'Rezervasyonlar';
$aktifSayfa   = 'rezervasyonlar';
require APP_KOK . '/partials/admin-header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h3 mb-1">Rezervasyonlar</h1>
        <p class="text-muted mb-0">Tarih, durum ve masaya göre süzün. İptal öncesi onay istenir.</p>
    </div>
</div>

<form method="get" action="rezervasyonlar.php" class="kafe-kart mb-4">
    <div class="row g-3 align-items-end">
        <div class="col-sm-6 col-lg-3">
            <label for="tarih" class="form-label">Tarih</label>
            <input type="date" class="form-control" id="tarih" name="tarih"
                   value="<?= e($filtre['tarih']) ?>">
        </div>
        <div class="col-sm-6 col-lg-3">
            <label for="durum" class="form-label">Durum</label>
            <select class="form-select" id="durum" name="durum">
                <option value="">Tümü</option>
                <?php foreach ($izinliDurumlar as $d): ?>
                    <option value="<?= e($d) ?>" <?= $filtre['durum'] === $d ? 'selected' : '' ?>>
                        <?= e(durum_etiketi($d)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-sm-6 col-lg-3">
            <label for="masa_id" class="form-label">Masa</label>
            <select class="form-select" id="masa_id" name="masa_id">
                <option value="">Tümü</option>
                <?php foreach ($masalar as $masa): ?>
                    <option value="<?= e($masa['id']) ?>"
                        <?= $filtre['masa_id'] === (string) $masa['id'] ? 'selected' : '' ?>>
                        <?= e($masa['masa_adi']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-sm-6 col-lg-3 d-flex flex-wrap gap-2">
            <button type="submit" class="btn btn-kafe">Filtrele</button>
            <a class="btn btn-outline-secondary" href="rezervasyonlar.php">Temizle</a>
        </div>
    </div>
</form>

<div class="table-responsive kafe-kart p-0">
    <table class="table table-hover align-middle mb-0">
        <thead>
            <tr>
                <th>Kod</th>
                <th>Tarih / saat</th>
                <th>Masa</th>
                <th>Müşteri</th>
                <th>Kişi</th>
                <th>Durum</th>
                <th class="text-end">İşlem</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($kayitlar === []): ?>
            <tr>
                <td colspan="7" class="text-center text-muted py-4">Bu filtreye uyan rezervasyon yok.</td>
            </tr>
        <?php else: ?>
            <?php foreach ($kayitlar as $r): ?>
                <?php
                $rid = (int) $r['id'];
                $mevcut = (string) $r['durum'];
                ?>
                <tr>
                    <td><code><?= e($r['rezervasyon_kodu']) ?></code></td>
                    <td>
                        <?= e(tarih_goster((string) $r['tarih'])) ?><br>
                        <span class="small text-muted">
                            <?= e(saat_goster((string) $r['baslangic_saati'])) ?>
                            &ndash;
                            <?= e(saat_goster((string) $r['bitis_saati'])) ?>
                        </span>
                    </td>
                    <td>
                        <?= e($r['masa_adi']) ?>
                        <span class="badge kafe-konum-rozet">
                            <?= $r['konum'] === 'dis' ? 'Bahçe' : 'İç mekan' ?>
                        </span>
                    </td>
                    <td>
                        <?= e($r['musteri_adi']) ?><br>
                        <span class="small text-muted"><?= e(telefon_goster((string) $r['musteri_telefon'])) ?></span>
                    </td>
                    <td><?= e($r['kisi_sayisi']) ?></td>
                    <td><?= durum_rozeti($mevcut) ?></td>
                    <td class="text-end">
                        <div class="d-inline-flex flex-wrap gap-1 justify-content-end">
                            <?php if ($mevcut !== 'onaylandi'): ?>
                                <form method="post" action="rezervasyonlar.php" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= e($rid) ?>">
                                    <input type="hidden" name="durum" value="onaylandi">
                                    <input type="hidden" name="filtre_tarih" value="<?= e($filtre['tarih']) ?>">
                                    <input type="hidden" name="filtre_durum" value="<?= e($filtre['durum']) ?>">
                                    <input type="hidden" name="filtre_masa_id" value="<?= e($filtre['masa_id']) ?>">
                                    <button type="submit" class="btn btn-sm btn-success">Onayla</button>
                                </form>
                            <?php endif; ?>

                            <?php if ($mevcut !== 'tamamlandi'): ?>
                                <form method="post" action="rezervasyonlar.php" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= e($rid) ?>">
                                    <input type="hidden" name="durum" value="tamamlandi">
                                    <input type="hidden" name="filtre_tarih" value="<?= e($filtre['tarih']) ?>">
                                    <input type="hidden" name="filtre_durum" value="<?= e($filtre['durum']) ?>">
                                    <input type="hidden" name="filtre_masa_id" value="<?= e($filtre['masa_id']) ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-secondary">Tamamlandı</button>
                                </form>
                            <?php endif; ?>

                            <?php if ($mevcut !== 'iptal'): ?>
                                <form method="post" action="rezervasyonlar.php" class="d-inline"
                                      data-onay="Bu rezervasyonu iptal etmek istediğinize emin misiniz?">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= e($rid) ?>">
                                    <input type="hidden" name="durum" value="iptal">
                                    <input type="hidden" name="filtre_tarih" value="<?= e($filtre['tarih']) ?>">
                                    <input type="hidden" name="filtre_durum" value="<?= e($filtre['durum']) ?>">
                                    <input type="hidden" name="filtre_masa_id" value="<?= e($filtre['masa_id']) ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">İptal et</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require APP_KOK . '/partials/admin-footer.php'; ?>
