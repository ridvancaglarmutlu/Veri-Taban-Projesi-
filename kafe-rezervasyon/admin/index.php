<?php
/**
 * =====================================================================
 *  KAFE MASA REZERVASYON SISTEMI - YONETIM PANELI (DASHBOARD)
 * ---------------------------------------------------------------------
 *  Bugunun ozeti: rezervasyon sayilari (view) + doluluk (masalar /
 *  rezervasyonlar). Sayilar TEK SEFERDE cekilir; masa listesini
 *  PHP'de donup her masa icin ayri SELECT atmak (N+1) 20 masalik
 *  bir kafede 21 gidis-donus demektir ve panel her acilista yavaslar.
 *
 *  v_gunluk_ozet view'i GROUP BY tarih yaptigi icin bugun hic kayit
 *  yoksa SATIR DONMEZ. Bunu "hata" sanmamak lazim: bos gun sifir
 *  demektir, fetchOne null gelir, asagida varsayilanlarla doldurulur.
 * =====================================================================
 */

require_once __DIR__ . '/../app/bootstrap.php';
require_once APP_DIZIN . '/Auth.php';

Auth::kontrol();

$bugun = date('Y-m-d');
$simdi = date('H:i:s');

$ozetSatir = Database::fetchOne(
    'SELECT tarih, toplam_rezervasyon, onayli_sayisi, iptal_sayisi,
            tamamlanan_sayisi, onayli_misafir
       FROM v_gunluk_ozet
      WHERE tarih = :tarih',
    [':tarih' => $bugun]
);

$ozet = [
    'toplam_rezervasyon' => (int) ($ozetSatir['toplam_rezervasyon'] ?? 0),
    'onayli_sayisi'      => (int) ($ozetSatir['onayli_sayisi'] ?? 0),
    'iptal_sayisi'       => (int) ($ozetSatir['iptal_sayisi'] ?? 0),
    'tamamlanan_sayisi'  => (int) ($ozetSatir['tamamlanan_sayisi'] ?? 0),
    'onayli_misafir'     => (int) ($ozetSatir['onayli_misafir'] ?? 0),
];

// Isimli parametreler gercek prepared statement'ta bir kez kullanilabilir
// (db.php, ATTR_EMULATE_PREPARES = false). Ayni tarihi iki yerde
// :tarih yazmak HY093 hatasi verir; bu yuzden :t1 / :t2 ayrildi.
$kapasite = Database::fetchOne(
    "SELECT
        (SELECT COUNT(*) FROM masalar) AS toplam_masa,
        (SELECT COUNT(*) FROM masalar WHERE durum = 'aktif') AS aktif_masa,
        (SELECT COUNT(*) FROM masalar WHERE durum = 'pasif') AS pasif_masa,
        (SELECT COALESCE(SUM(kapasite), 0) FROM masalar WHERE durum = 'aktif') AS aktif_kapasite,
        (SELECT COUNT(DISTINCT masa_id)
           FROM rezervasyonlar
          WHERE tarih = :t1 AND durum = 'onaylandi') AS bugun_dolu_masa,
        (SELECT COUNT(DISTINCT masa_id)
           FROM rezervasyonlar
          WHERE tarih = :t2
            AND durum = 'onaylandi'
            AND baslangic_saati <= :saat
            AND bitis_saati > :saat_bit) AS simdi_dolu_masa",
    [
        ':t1'        => $bugun,
        ':t2'        => $bugun,
        ':saat'      => $simdi,
        ':saat_bit'  => $simdi,
    ]
) ?? [
    'toplam_masa'     => 0,
    'aktif_masa'      => 0,
    'pasif_masa'      => 0,
    'aktif_kapasite'  => 0,
    'bugun_dolu_masa' => 0,
    'simdi_dolu_masa' => 0,
];

$aktifMasa     = (int) $kapasite['aktif_masa'];
$bugunDoluMasa = (int) $kapasite['bugun_dolu_masa'];
$simdiDoluMasa = (int) $kapasite['simdi_dolu_masa'];
$aktifKapasite = (int) $kapasite['aktif_kapasite'];

$gunlukDoluluk = $aktifMasa > 0
    ? (int) round(100 * $bugunDoluMasa / $aktifMasa)
    : 0;
$anlikDoluluk = $aktifMasa > 0
    ? (int) round(100 * $simdiDoluMasa / $aktifMasa)
    : 0;
$misafirDoluluk = $aktifKapasite > 0
    ? (int) round(100 * $ozet['onayli_misafir'] / $aktifKapasite)
    : 0;

$bugununKayitlari = Database::fetchAll(
    'SELECT r.rezervasyon_kodu, r.musteri_adi, r.baslangic_saati,
            r.bitis_saati, r.kisi_sayisi, r.durum, m.masa_adi
       FROM rezervasyonlar r
       INNER JOIN masalar m ON m.id = r.masa_id
      WHERE r.tarih = :tarih
      ORDER BY r.baslangic_saati, m.masa_adi',
    [':tarih' => $bugun]
);

$sayfaBasligi    = 'Yönetim paneli';
$sayfaAciklamasi = 'Günlük rezervasyon özeti';
$aktifSayfa      = 'dashboard';
require __DIR__ . '/../partials/admin-header.php';
?>

<div class="admin-sayfa">
    <div class="kafe-bolum-baslik d-flex flex-wrap justify-content-between align-items-end gap-2">
        <div>
            <h1 class="h3 mb-1">Günlük özet</h1>
            <p><?= e(tarih_goster($bugun)) ?> &middot; saat <?= e(saat_goster($simdi)) ?></p>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="admin-ozet-kart">
                <p class="admin-ozet-etiket">Toplam kayıt</p>
                <p class="admin-ozet-sayi"><?= e($ozet['toplam_rezervasyon']) ?></p>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="admin-ozet-kart admin-ozet-onay">
                <p class="admin-ozet-etiket">Onaylı</p>
                <p class="admin-ozet-sayi"><?= e($ozet['onayli_sayisi']) ?></p>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="admin-ozet-kart admin-ozet-iptal">
                <p class="admin-ozet-etiket">İptal</p>
                <p class="admin-ozet-sayi"><?= e($ozet['iptal_sayisi']) ?></p>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="admin-ozet-kart admin-ozet-tamam">
                <p class="admin-ozet-etiket">Tamamlanan</p>
                <p class="admin-ozet-sayi"><?= e($ozet['tamamlanan_sayisi']) ?></p>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="kafe-kart h-100">
                <p class="admin-ozet-etiket mb-1">Bugünkü masa doluluğu</p>
                <p class="admin-ozet-sayi mb-1"><?= e($gunlukDoluluk) ?>%</p>
                <p class="small text-muted mb-0">
                    <?= e($bugunDoluMasa) ?> / <?= e($aktifMasa) ?> aktif masada
                    bugün onaylı rezervasyon var.
                </p>
                <div class="admin-bar mt-3" aria-hidden="true">
                    <span style="width: <?= e(min(100, $gunlukDoluluk)) ?>%"></span>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="kafe-kart h-100">
                <p class="admin-ozet-etiket mb-1">Şu an dolu</p>
                <p class="admin-ozet-sayi mb-1"><?= e($anlikDoluluk) ?>%</p>
                <p class="small text-muted mb-0">
                    <?= e($simdiDoluMasa) ?> masa bu saatte onaylı rezervasyonda.
                </p>
                <div class="admin-bar mt-3" aria-hidden="true">
                    <span style="width: <?= e(min(100, $anlikDoluluk)) ?>%"></span>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="kafe-kart h-100">
                <p class="admin-ozet-etiket mb-1">Onaylı misafir</p>
                <p class="admin-ozet-sayi mb-1"><?= e($ozet['onayli_misafir']) ?></p>
                <p class="small text-muted mb-0">
                    Aktif kapasite <?= e($aktifKapasite) ?> kişi
                    (<?= e($misafirDoluluk) ?>%) &middot;
                    <?= e((int) $kapasite['pasif_masa']) ?> masa pasif.
                </p>
                <div class="admin-bar mt-3" aria-hidden="true">
                    <span style="width: <?= e(min(100, $misafirDoluluk)) ?>%"></span>
                </div>
            </div>
        </div>
    </div>

    <div class="kafe-kart">
        <h2 class="h5 mb-3">Bugünün rezervasyonları</h2>
        <?php if ($bugununKayitlari === []): ?>
            <p class="text-muted mb-0">Bugün henüz rezervasyon yok.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Kod</th>
                            <th>Saat</th>
                            <th>Masa</th>
                            <th>Müşteri</th>
                            <th>Kişi</th>
                            <th>Durum</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bugununKayitlari as $kayit): ?>
                            <tr>
                                <td><code><?= e($kayit['rezervasyon_kodu']) ?></code></td>
                                <td>
                                    <?= e(saat_goster((string) $kayit['baslangic_saati'])) ?>
                                    &ndash;
                                    <?= e(saat_goster((string) $kayit['bitis_saati'])) ?>
                                </td>
                                <td><?= e($kayit['masa_adi']) ?></td>
                                <td><?= e($kayit['musteri_adi']) ?></td>
                                <td><?= e($kayit['kisi_sayisi']) ?></td>
                                <td><?= durum_rozeti((string) $kayit['durum']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
require __DIR__ . '/../partials/admin-footer.php';
