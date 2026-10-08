<?php
/**
 * =====================================================================
 *  KAFE MASA REZERVASYON SISTEMI - REZERVASYON SORGULAMA VE IPTAL
 * ---------------------------------------------------------------------
 *  Musteri kendi kaydina iki yoldan ulasabilir:
 *
 *    1. REZERVASYON KODU ile -> tek kayit doner, IPTAL EDILEBILIR.
 *    2. TELEFON NUMARASI ile -> o numaraya ait kayitlar listelenir.
 *
 *  ---------------------------------------------------------------------
 *  NEDEN ARAMA "GET", IPTAL "POST"?
 *
 *  HTTP metotlarinin bir sozlesmesi vardir:
 *    GET  : GUVENLI (safe) ve yan etkisizdir. Sadece OKUR. Tarayici bu
 *           istegi onbellege alabilir, on yukleme yapabilir, kullanici
 *           adresi yer imine ekleyebilir veya paylasabilir.
 *    POST : Sunucuda bir sey DEGISTIRIR. Tarayici kendiliginden
 *           tekrarlamaz, onbellege almaz.
 *
 *  Iptali GET ile yapsaydik (ornegin <a href="?iptal=RZ7K4M2Q">) su
 *  somut felaketler olurdu:
 *    - Tarayicinin/antivirusun baglanti on yukleyicisi (prefetch) o
 *      adresi ziyaret eder ve rezervasyon KENDILIGINDEN iptal olur.
 *    - Bir forumda paylasilan baglantiya tiklayan herkes iptal eder.
 *    - CSRF korumasi zorlasir: <img src="..."> etiketi bile yeterlidir.
 *
 *  Arama ise gercekten okuma islemidir; GET olmasi sayesinde sonuc
 *  sayfasinin adresi paylasilabilir ve yenilenebilir.
 *
 *  Not: Telefonla arama adres cubuguna numara yazar. Gercek bir
 *  sistemde bu yontem SMS dogrulamasi ile korunmali ve hiz sinirina
 *  (rate limit) tabi olmalidir; numara tek basina zayif bir sirdir.
 * =====================================================================
 */

require_once __DIR__ . '/app/bootstrap.php';
require_once APP_DIZIN . '/RezervasyonRepository.php';


// =====================================================================
// 1) IPTAL ISLEMI  (POST)
// ---------------------------------------------------------------------
// Bu blok GORUNUM kodundan ONCE gelir. Sebebi teknik: yonlendir()
// icindeki header('Location') calisabilmesi icin ekrana HENUZ hicbir
// sey basilmamis olmalidir. Tek bir bosluk bile "headers already sent"
// hatasina yol acar.
// =====================================================================
if (post_istegi_mi()) {

    // CSRF once. Saldirgan baska bir siteden gizli bir form gonderip
    // kurbanin rezervasyonunu iptal ettirebilirdi.
    csrf_kontrol_et();

    $iptalKodu = strtoupper(post('kod'));

    // Kod bicimi kontrolu. Depo katmani zaten prepared statement
    // kullaniyor, yani SQL injection riski yok; buradaki kontrol
    // GEREKSIZ SORGUYU onlemek ve kullaniciya net mesaj vermek icin.
    if (!preg_match('/^[A-Z0-9]{8}$/', $iptalKodu)) {
        flash_ekle('Geçersiz rezervasyon kodu.', 'danger');
        yonlendir('rezervasyon-sorgula.php');
    }

    $sonuc = RezervasyonRepository::iptalEt($iptalKodu);

    if (!empty($sonuc['basarili'])) {
        flash_ekle('Rezervasyonunuz iptal edildi.', 'success');
    } else {
        flash_ekle($sonuc['hata'] ?? 'Rezervasyon iptal edilemedi.', 'danger');
    }

    // -----------------------------------------------------------------
    //  *** POST-REDIRECT-GET (burada da) ***
    // -----------------------------------------------------------------
    //  Iptalden sonra sonucu dogrudan ekrana basmiyoruz; kullaniciyi
    //  ayni kaydin GET adresine gonderiyoruz. Boylece:
    //    - F5'e basildiginda "formu yeniden gonder" kutusu cikmaz ve
    //      iptal ikinci kez denenmez.
    //    - Kullanici kaydin GUNCEL halini (durum: Iptal Edildi) gorur;
    //      veriyi PHP degiskeninden degil veritabanindan okumus oluruz.
    //      Bu, "ekranda iptal yaziyor ama veritabaninda duruyor" tipi
    //      yanilticiligi kokten engeller.
    //
    //  Kodu URL'e u() ile gomuyoruz: URL baglaminin kendi kacislamasi
    //  vardir, e() burada yetmez.
    // -----------------------------------------------------------------
    yonlendir('rezervasyon-sorgula.php?kod=' . u($iptalKodu));
}


// =====================================================================
// 2) ARAMA  (GET)
// =====================================================================

// strtoupper: musteri kodu kucuk harfle yazmis olabilir. Kod alfabesi
// (helpers.php) sadece buyuk harf icerdigi icin buyuk harfe cevirmek
// "kodu dogru yazdim ama bulunamadi" sikayetini ortadan kaldirir.
$aramaKodu     = strtoupper(get('kod'));
$aramaTelefonu = get('tel');

$kayit     = null;   // kod ile bulunan TEK kayit
$liste     = [];     // telefon ile bulunan kayitlar
$arandiMi  = false;  // form ilk kez mi aciliyor, yoksa arama yapildi mi?
$aramaHata = '';

if ($aramaKodu !== '') {
    $arandiMi = true;

    if (!preg_match('/^[A-Z0-9]{8}$/', $aramaKodu)) {
        // Kod 8 karakterlik sabit uzunluktadir (CHAR(8)). Bicim yanlissa
        // veritabanina hic gitmiyoruz.
        $aramaHata = 'Rezervasyon kodu 8 karakter olmalıdır. Örnek: RZDEMO01';
    } else {
        $kayit = RezervasyonRepository::koduIleBul($aramaKodu);

        if ($kayit === null) {
            $aramaHata = 'Bu koda ait bir rezervasyon bulunamadı.';
        }
    }

} elseif ($aramaTelefonu !== '') {
    $arandiMi = true;

    if (!gecerli_telefon($aramaTelefonu)) {
        $aramaHata = 'Telefon numarası 5 ile başlayan 10 haneli olmalıdır.';
    } else {
        // Depoya NORMALIZE edilmis numara gonderiyoruz. Veritabaninda
        // numaralar '5551112233' bicimindedir; kullanici '0555 111 22 33'
        // yazdiginda normalize etmezsek hicbir kayit eslesmez.
        $liste = RezervasyonRepository::telefonIleBul(telefon_normalize($aramaTelefonu));

        if ($liste === []) {
            $aramaHata = 'Bu numaraya ait rezervasyon bulunamadı.';
        }
    }
}

/**
 * Kayit su anda iptal edilebilir mi?
 *
 * Iki kosul:
 *   1. Durum 'onaylandi' olmali. Zaten iptal edilmis ya da tamamlanmis
 *      bir kaydi tekrar iptal etmek anlamsizdir.
 *   2. Rezervasyon gecmiste olmamali. Dunku bir rezervasyonu iptal
 *      etmek masayi bosaltmaz, sadece gecmis veriyi bozar.
 *
 * Bu SADECE butonu gizler. Asil kural depo katmanindadir; buton
 * gizlense de curl ile POST gonderilebilir.
 */
$iptalEdilebilir = static function (array $r): bool {
    if (($r['durum'] ?? '') !== 'onaylandi') {
        return false;
    }

    $tarih = substr((string) ($r['tarih'] ?? ''), 0, 10);

    return $tarih >= date('Y-m-d');
};


// =====================================================================
// 3) GORUNUM
// =====================================================================
$sayfaBasligi = 'Rezervasyon Sorgula';
$aktifSayfa   = 'sorgula';
require __DIR__ . '/partials/header.php';
?>

<section class="kafe-hero kafe-hero-kisa kafe-hero-sorgula">
    <div class="container kafe-hero-ic">
        <span class="kafe-rozet mb-3">Takip</span>
        <h1 class="kafe-hero-baslik">
            Defter kaydını<br>
            <span class="kafe-italik">kodunuzla açın.</span>
        </h1>
        <p class="kafe-hero-metin mb-0">
            Rezervasyon kodunuz ya da telefon numaranızla kaydınızı görüntüleyin;
            gerekirse aynı gün iptal edin.
        </p>
    </div>
</section>

<div class="container my-4 my-lg-5">

    <header class="kafe-bolum-baslik">
        <p class="kafe-ust-etiket">Defter kaydı</p>
        <h2 class="h3 mb-2">Kod veya telefon</h2>
        <p>Rezervasyon kodunuz ya da telefon numaranızla kaydınızı görüntüleyin.</p>
    </header>

    <!-- =============================================================
         ARAMA FORMU
         method="get" -> parametreler adres cubuguna yazilir, sonuc
         sayfasi yer imine eklenebilir. Okuma isleminde CSRF token'a
         gerek yoktur: token, DEGISIKLIK yapan istekleri korur.
         ============================================================= -->
    <div class="kafe-kart mb-4">
        <form method="get" action="rezervasyon-sorgula.php" class="row g-3 align-items-end">

            <div class="col-md-5">
                <label for="kod" class="form-label">Rezervasyon kodu</label>
                <input type="text" class="form-control kafe-kod-girdi"
                       id="kod" name="kod"
                       value="<?= e($aramaKodu) ?>"
                       maxlength="8" placeholder="RZDEMO01"
                       autocomplete="off" spellcheck="false">
                <div class="form-text">Rezervasyon sonrası verilen 8 karakterlik kod.</div>
            </div>

            <div class="col-md-5">
                <label for="tel" class="form-label">
                    veya telefon numarası
                </label>
                <input type="tel" inputmode="tel" class="form-control"
                       id="tel" name="tel"
                       value="<?= e($aramaTelefonu) ?>"
                       maxlength="20" placeholder="0555 111 22 33"
                       autocomplete="tel">
                <div class="form-text">Kodunuzu kaybettiyseniz numaranızla arayın.</div>
            </div>

            <div class="col-md-2 d-grid">
                <button type="submit" class="btn btn-kafe">Sorgula</button>
            </div>

        </form>
    </div>


    <?php if ($aramaHata !== ''): ?>
        <div class="alert alert-warning" role="alert"><?= e($aramaHata) ?></div>
    <?php endif; ?>


    <?php if ($kayit !== null): ?>
        <!-- =========================================================
             KOD ILE BULUNAN TEK KAYIT
             ========================================================= -->
        <section class="kafe-kart kafe-kayit-karti">

            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                <div>
                    <p class="kafe-kod-etiket mb-1">Rezervasyon kodu</p>
                    <p class="kafe-kod kafe-kod-kucuk mb-0"><?= e($kayit['rezervasyon_kodu']) ?></p>
                </div>

                <!--
                    durum_rengi() ve durum_etiketi() helpers.php'de
                    tanimli. Renk adini kullanicidan gelen degerden
                    degil, sabit bir match ifadesinden aliyoruz; boylece
                    "class enjeksiyonu" ile tasarim bozulamaz.
                -->
                <span class="badge bg-<?= e(durum_rengi($kayit['durum'])) ?> fs-6">
                    <?= e(durum_etiketi($kayit['durum'])) ?>
                </span>
            </div>

            <dl class="kafe-ozet">
                <dt>Ad Soyad</dt>
                <dd><?= e($kayit['musteri_adi']) ?></dd>

                <dt>Masa</dt>
                <dd>
                    <?= e($kayit['masa_adi']) ?>
                    <span class="badge kafe-konum-rozet">
                        <?= ($kayit['konum'] ?? '') === 'dis' ? 'Bahçe' : 'İç mekan' ?>
                    </span>
                    <span class="text-muted small">
                        (<?= e($kayit['kapasite']) ?> kişilik)
                    </span>
                </dd>

                <dt>Tarih</dt>
                <dd><?= e(tarih_goster($kayit['tarih'])) ?></dd>

                <dt>Saat</dt>
                <dd>
                    <!--
                        MySQL TIME kolonu '19:00:00' dondurur; saat_goster()
                        son iki haneyi kirpar. Bicimlendirmeyi SQL'de degil
                        PHP'de yapiyoruz: ayni veri admin panelinde farkli
                        bicimde gerekebilir, ham deger elimizde kalsin.
                    -->
                    <?= e(saat_goster($kayit['baslangic_saati'])) ?>
                    &ndash;
                    <?= e(saat_goster($kayit['bitis_saati'])) ?>
                </dd>

                <dt>Kişi</dt>
                <dd><?= e($kayit['kisi_sayisi']) ?> kişi</dd>

                <dt>Telefon</dt>
                <dd><?= e(telefon_goster($kayit['musteri_telefon'])) ?></dd>

                <?php if (!empty($kayit['musteri_notu'])): ?>
                    <dt>Notunuz</dt>
                    <dd><?= e($kayit['musteri_notu']) ?></dd>
                <?php endif; ?>
            </dl>

            <?php if ($iptalEdilebilir($kayit)): ?>
                <hr class="my-4">

                <!--
                    IPTAL BUTONU
                    data-bs-toggle="modal" ile once ONAY PENCERESI acilir.
                    Tek tikla geri alinamaz bir islem yapmak kotu bir
                    tasarimdir: kullanici yanlislikla dokunur ve masasini
                    kaybeder. Modal, kullaniciya "ne yapmak uzeresin"
                    sorusunu sorar.

                    Buton FORMUN DISINDA; gercek gonderim modal icindeki
                    formdan yapilir.
                -->
                <button type="button" class="btn btn-outline-danger"
                        data-bs-toggle="modal" data-bs-target="#iptalOnayModal">
                    Rezervasyonu İptal Et
                </button>

                <p class="form-text mt-2 mb-0">
                    İptal işlemi geri alınamaz. Yeniden rezervasyon yapmanız gerekir.
                </p>

                <!-- ONAY MODALI -->
                <div class="modal fade" id="iptalOnayModal" tabindex="-1"
                     aria-labelledby="iptalOnayBaslik" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">

                            <div class="modal-header">
                                <h2 class="modal-title h5" id="iptalOnayBaslik">İptali onaylayın</h2>
                                <button type="button" class="btn-close"
                                        data-bs-dismiss="modal" aria-label="Kapat"></button>
                            </div>

                            <div class="modal-body">
                                <p class="mb-2">
                                    <strong><?= e($kayit['rezervasyon_kodu']) ?></strong>
                                    kodlu rezervasyonunuz iptal edilecek:
                                </p>
                                <p class="mb-0 text-muted">
                                    <?= e($kayit['masa_adi']) ?> &middot;
                                    <?= e(tarih_goster($kayit['tarih'])) ?> &middot;
                                    <?= e(saat_goster($kayit['baslangic_saati'])) ?>
                                    &ndash;
                                    <?= e(saat_goster($kayit['bitis_saati'])) ?>
                                </p>
                            </div>

                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary"
                                        data-bs-dismiss="modal">Vazgeç</button>

                                <!--
                                    Asil istek burada. POST + CSRF token.
                                    Kodu gizli alanda tasiyoruz ki sunucu
                                    hangi kaydin iptal edilecegini bilsin.
                                -->
                                <form method="post" action="rezervasyon-sorgula.php" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="kod"
                                           value="<?= e($kayit['rezervasyon_kodu']) ?>">
                                    <button type="submit" class="btn btn-danger">
                                        Evet, iptal et
                                    </button>
                                </form>
                            </div>

                        </div>
                    </div>
                </div>

            <?php elseif (($kayit['durum'] ?? '') === 'onaylandi'): ?>
                <hr class="my-4">
                <p class="text-muted mb-0">
                    Bu rezervasyonun tarihi gectigi icin iptal edilemez.
                </p>
            <?php endif; ?>

        </section>
    <?php endif; ?>


    <?php if ($liste !== []): ?>
        <!-- =========================================================
             TELEFON ILE BULUNAN KAYITLAR
             ========================================================= -->
        <section>
            <h2 class="h5 mb-3"><?= e(count($liste)) ?> rezervasyon bulundu</h2>

            <!--
                table-responsive: Dar ekranda tabloyu yatay kaydirilabilir
                yapar. Olmazsa tablo sayfayi tasirip tum duzeni bozar -
                mobilde en sik karsilasilan Bootstrap hatasi budur.
            -->
            <div class="table-responsive kafe-kart p-0">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Kod</th>
                            <th scope="col">Masa</th>
                            <th scope="col">Tarih</th>
                            <th scope="col">Saat</th>
                            <th scope="col">Kişi</th>
                            <th scope="col">Durum</th>
                            <th scope="col" class="text-end">İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($liste as $satir): ?>
                            <tr>
                                <td class="fw-semibold font-monospace">
                                    <?= e($satir['rezervasyon_kodu']) ?>
                                </td>
                                <td><?= e($satir['masa_adi'] ?? '-') ?></td>
                                <td><?= e(tarih_goster($satir['tarih'])) ?></td>
                                <td class="text-nowrap">
                                    <?= e(saat_goster($satir['baslangic_saati'])) ?>
                                    &ndash;
                                    <?= e(saat_goster($satir['bitis_saati'])) ?>
                                </td>
                                <td><?= e($satir['kisi_sayisi']) ?></td>
                                <td>
                                    <span class="badge bg-<?= e(durum_rengi($satir['durum'])) ?>">
                                        <?= e(durum_etiketi($satir['durum'])) ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <!--
                                        Iptal butonunu BURADA gostermiyoruz.
                                        Iptal, kodu bilmeyi gerektiren bir
                                        yetkilendirme adimidir; telefon
                                        listesinden dogrudan iptal, numarayi
                                        bilen herkese bu yetkiyi verirdi.
                                        Kullanici once kaydin detayina gider.
                                    -->
                                    <a class="btn btn-sm btn-kafe-cizgi"
                                       href="rezervasyon-sorgula.php?kod=<?= u($satir['rezervasyon_kodu']) ?>">
                                        Detay
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>


    <?php if (!$arandiMi): ?>
        <div class="kafe-bos-durum">
            <span class="kafe-bos-ikon" aria-hidden="true"></span>
            <strong>Kodunuz hazır olduğunda</strong>
            <p class="mb-0">
                Henüz rezervasyonunuz yok mu?
                <a href="index.php#rezervasyon-formu">Hemen masa ayırtın.</a>
            </p>
        </div>
    <?php endif; ?>

</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
