<?php

declare(strict_types=1);

require __DIR__ . '/urunler.php';

if (!defined('APP_VERSION')) {
    define('APP_VERSION', 'v2');
}

define('HATA_KATEGORI', APP_VERSION === 'v1');
define('HATA_BOS_SEPET', APP_VERSION === 'v1');

session_name('kirtasiye_' . APP_VERSION);
session_start();
header('Content-Type: text/html; charset=utf-8');

function urunler(): array
{
    return kirtasiye_urunler();
}

function para(int $kurus): string
{
    $lira = intdiv($kurus, 100);
    $kusurat = $kurus % 100;
    return number_format($lira, 0, ',', '.') . ',' . str_pad((string) $kusurat, 2, '0', STR_PAD_LEFT) . ' TL';
}

function sepet(): array
{
    return $_SESSION['sepet'] ?? [];
}

function sepet_adet(): int
{
    return array_sum(sepet());
}

function sepet_ara_toplam(): int
{
    $toplam = 0;
    foreach (sepet() as $id => $adet) {
        $urun = urunler()[$id] ?? null;
        if ($urun) {
            $toplam += $urun['fiyat'] * $adet;
        }
    }
    return $toplam;
}

function sepet_genel_toplam(): int
{
    if (sepet_adet() === 0) {
        return 0;
    }
    return sepet_ara_toplam() + teslimat_ucreti_kurus();
}

function git(string $sayfa): never
{
    header('Location: index.php?sayfa=' . rawurlencode($sayfa));
    exit;
}

function flash(?string $mesaj = null, string $tur = 'error'): ?array
{
    if ($mesaj !== null) {
        $_SESSION['flash'] = ['mesaj' => $mesaj, 'tur' => $tur];
        return null;
    }
    $deger = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $deger;
}

function giris_gerekli(): void
{
    if (empty($_SESSION['kullanici'])) {
        flash('Bu sayfa için önce giriş yapmalısınız.');
        git('giris');
    }
}

function bos_sepet_engeli(string $sayfa): void
{
    if (HATA_BOS_SEPET) {
        return;
    }
    if (in_array($sayfa, ['teslimat', 'onay'], true) && sepet_adet() === 0) {
        flash('Sepetiniz boş. Teslimata geçmeden önce bir ürün ekleyin.');
        git('sepet');
    }
}

function urunleri_filtrele(?string $kategori): array
{
    $liste = urunler();
    if ($kategori === null || $kategori === '' || $kategori === 'hepsi') {
        return $liste;
    }
    return array_filter($liste, static fn(array $urun): bool => $urun['kategori'] === $kategori);
}

function ikon(string $ad): string
{
    $yollar = [
        'defter' => '<rect x="14" y="8" width="28" height="36" rx="3"/><path d="M20 8v36M14 16h28"/>',
        'kalem' => '<path d="M18 40 L36 14 L42 18 L24 44 Z"/><path d="M18 40 L14 46 L20 42"/>',
        'fosfor' => '<rect x="16" y="12" width="24" height="10" rx="2"/><path d="M20 22 v18 h16 v-18"/>',
        'usb' => '<rect x="22" y="10" width="12" height="10"/><rect x="16" y="20" width="24" height="26" rx="4"/><path d="M28 28 v10"/>',
        'hesap' => '<rect x="14" y="8" width="28" height="40" rx="3"/><rect x="18" y="13" width="20" height="8"/><path d="M20 28h4M28 28h4M36 28h-4M20 36h4M28 36h4M36 36h-4"/>',
        'klasor' => '<path d="M10 18 h16 l4 4 h24 v24 H10 Z"/>',
    ];
    $ic = $yollar[$ad] ?? $yollar['defter'];
    return '<svg viewBox="0 0 56 56" aria-hidden="true">' . $ic . '</svg>';
}

function istek_sayfa(): string
{
    $sayfa = $_GET['sayfa'] ?? (empty($_SESSION['kullanici']) ? 'giris' : 'urunler');
    $izinli = ['giris', 'urunler', 'sepet', 'teslimat', 'onay', 'tesekkur', 'cikis'];
    return in_array($sayfa, $izinli, true) ? $sayfa : 'giris';
}

function post_isle(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        return;
    }
    $islem = $_POST['islem'] ?? '';

    if ($islem === 'giris') {
        $ad = trim((string) ($_POST['kullanici'] ?? ''));
        $sifre = (string) ($_POST['sifre'] ?? '');
        if ($ad === '') {
            flash('Kullanıcı adı boş bırakılamaz.');
            git('giris');
        }
        if ($sifre === '') {
            flash('Şifre alanı zorunludur.');
            git('giris');
        }
        $hesaplar = kirtasiye_kullanicilar();
        if (!isset($hesaplar[$ad]) || $hesaplar[$ad]['sifre'] !== $sifre) {
            flash('Kullanıcı adı veya şifre hatalı.');
            git('giris');
        }
        if ($hesaplar[$ad]['durum'] === 'kilitli') {
            flash('Bu hesap kilitlidir. Öğrenci işlerine başvurun.');
            git('giris');
        }
        $_SESSION['kullanici'] = $ad;
        $_SESSION['sepet'] = $_SESSION['sepet'] ?? [];
        git('urunler');
    }

    if ($islem === 'ekle') {
        giris_gerekli();
        $id = (int) ($_POST['urun'] ?? 0);
        if (!isset(urunler()[$id])) {
            flash('Ürün bulunamadı.');
            git('urunler');
        }
        $_SESSION['sepet'][$id] = ($_SESSION['sepet'][$id] ?? 0) + 1;
        git('sepet');
    }

    if ($islem === 'sil') {
        giris_gerekli();
        $id = (int) ($_POST['urun'] ?? 0);
        unset($_SESSION['sepet'][$id]);
        git('sepet');
    }

    if ($islem === 'teslimata_gec') {
        giris_gerekli();
        if (!HATA_BOS_SEPET && sepet_adet() === 0) {
            flash('Sepetiniz boş. Teslimata geçmeden önce bir ürün ekleyin.');
            git('sepet');
        }
        git('teslimat');
    }

    if ($islem === 'teslimat') {
        giris_gerekli();
        if (!HATA_BOS_SEPET && sepet_adet() === 0) {
            flash('Sepetiniz boş. Teslimata geçmeden önce bir ürün ekleyin.');
            git('sepet');
        }
        $ad = trim((string) ($_POST['ad'] ?? ''));
        $soyad = trim((string) ($_POST['soyad'] ?? ''));
        $telefon = trim((string) ($_POST['telefon'] ?? ''));
        if ($ad === '') {
            flash('Ad alanı zorunludur.');
            git('teslimat');
        }
        if ($soyad === '') {
            flash('Soyad alanı zorunludur.');
            git('teslimat');
        }
        if (!preg_match('/^[0-9]{10}$/D', $telefon)) {
            flash('Telefon numarası 10 rakamdan oluşmalıdır.');
            git('teslimat');
        }
        $_SESSION['teslimat'] = compact('ad', 'soyad', 'telefon');
        git('onay');
    }

    if ($islem === 'siparis') {
        giris_gerekli();
        if (!HATA_BOS_SEPET && sepet_adet() === 0) {
            flash('Sepetiniz boş. Teslimata geçmeden önce bir ürün ekleyin.');
            git('sepet');
        }
        $_SESSION['son_siparis'] = [
            'no' => 'KK-' . date('ymd') . '-' . random_int(100, 999),
            'toplam' => sepet_genel_toplam(),
        ];
        $_SESSION['sepet'] = [];
        unset($_SESSION['teslimat']);
        git('tesekkur');
    }
}

function html_bas(string $baslik): void
{
    $adet = sepet_adet();
    $kullanici = $_SESSION['kullanici'] ?? null;
    echo '<!DOCTYPE html><html lang="tr"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . htmlspecialchars($baslik) . ' · Kampüs Kırtasiye</title>';
    echo '<link rel="stylesheet" href="../ortak/stil.css">';
    echo '</head><body><header class="top"><div class="wrap top-inner">';
    echo '<a class="brand" href="index.php?sayfa=' . ($kullanici ? 'urunler' : 'giris') . '">';
    echo '<span class="mark">kk</span><span>Kampüs Kırtasiye</span></a><nav class="nav">';
    if ($kullanici) {
        echo '<a href="index.php?sayfa=urunler">Raf</a>';
        echo '<a href="index.php?sayfa=sepet" data-testid="cart-link">Sepet<span class="badge" data-testid="cart-count">' . $adet . '</span></a>';
        echo '<a href="index.php?sayfa=cikis">Çıkış</a>';
    }
    echo '</nav></div></header><main class="wrap">';
}

function html_son(): void
{
    echo '</main><footer class="wrap foot"><span>Kampüs Kırtasiye · Yazılım Testi ders projesi · Rıdvan Çağlar Mutlu</span>';
    echo '<span data-testid="app-version">PHP + XAMPP · ' . htmlspecialchars(APP_VERSION) . '</span></footer></body></html>';
}

function flash_kutusu(): void
{
    $flash = flash();
    if (!$flash) {
        return;
    }
    $tur = $flash['tur'] === 'ok' ? 'ok' : 'error';
    echo '<div class="flash ' . $tur . '" data-testid="error">' . htmlspecialchars($flash['mesaj']) . '</div>';
}

function sayfa_giris(): void
{
    html_bas('Giriş');
    echo '<section class="hero"><div><p class="kicker">Öğrenci rafı</p>';
    echo '<h1>Defterden USB\'ye, kampüs içi kırtasiye.</h1>';
    echo '<p class="lead">Sınav haftası için temel malzemeler. Demo sipariş alınır, gerçek ödeme yoktur.</p></div>';
    echo '<div class="stamp">2026<br>KIRTASİYE<br>RAFI</div></section>';
    echo '<section class="auth panel"><h2>Öğrenci girişi</h2>';
    flash_kutusu();
    echo '<form method="post">';
    echo '<input type="hidden" name="islem" value="giris">';
    echo '<label for="kullanici">Kullanıcı adı</label>';
    echo '<input id="kullanici" name="kullanici" data-testid="user" autocomplete="username">';
    echo '<label for="sifre">Şifre</label>';
    echo '<input id="sifre" name="sifre" type="password" data-testid="pass" autocomplete="current-password">';
    echo '<div class="actions"><button class="solid" type="submit" data-testid="login">Rafa gir</button></div>';
    echo '<p class="help">Demo: ridvan / Defter2026</p></form></section>';
    html_son();
}

function sayfa_urunler(): void
{
    giris_gerekli();
    $secili = $_GET['kategori'] ?? 'hepsi';
    if (HATA_KATEGORI && $secili === 'kirtasiye') {
        $liste = urunler();
    } else {
        $liste = urunleri_filtrele($secili);
    }
    html_bas('Raf');
    echo '<section class="hero"><div><p class="kicker">6 ürün / iki kategori</p>';
    echo '<h1>Sınav masasına yetecek bir raf.</h1>';
    echo '<p class="lead">Kırtasiye ve küçük elektronik. Fiyatlar kuruş cinsinden tutulur, ekranda TL gösterilir.</p></div>';
    echo '<div class="stamp">KAMPÜS<br>İÇİ<br>TESLİMAT</div></section>';
    echo '<div class="toolbar"><div><h2>Rafı incele</h2><p class="hint" data-testid="product-count">' . count($liste) . ' ürün listeleniyor</p></div>';
    echo '<form method="get"><input type="hidden" name="sayfa" value="urunler">';
    echo '<label for="kategori">Kategori</label>';
    echo '<select id="kategori" name="kategori" data-testid="category" onchange="this.form.submit()">';
    $secenekler = ['hepsi' => 'Tüm raf', 'kirtasiye' => 'Yalnız kırtasiye', 'elektronik' => 'Yalnız elektronik'];
    foreach ($secenekler as $deger => $etiket) {
        if (HATA_KATEGORI && $deger === 'kirtasiye') {
            echo '</option value="kirtasiye">' . htmlspecialchars($etiket);
            continue;
        }
        $sel = $secili === $deger ? ' selected' : '';
        echo '<option value="' . $deger . '"' . $sel . '>' . htmlspecialchars($etiket) . '</option>';
    }
    echo '</select></form></div><div class="grid">';
    foreach ($liste as $id => $urun) {
        echo '<article class="card" data-testid="product" data-id="' . $id . '">';
        echo '<div class="swatch c-' . htmlspecialchars($urun['ikon']) . '">' . ikon($urun['ikon']) . '</div>';
        echo '<div class="card-body"><div class="etiket">' . htmlspecialchars($urun['etiket']) . '</div>';
        echo '<h3>' . htmlspecialchars($urun['ad']) . '</h3></div>';
        echo '<div class="row"><span class="price" data-testid="price">' . para($urun['fiyat']) . '</span>';
        echo '<form method="post"><input type="hidden" name="islem" value="ekle">';
        echo '<input type="hidden" name="urun" value="' . $id . '">';
        echo '<button class="ghost" data-testid="add-' . $id . '">Sepete ekle +</button></form></div></article>';
    }
    echo '</div>';
    html_son();
}

function sayfa_sepet(): void
{
    giris_gerekli();
    html_bas('Sepet');
    echo '<p class="kicker">Seçtiklerin</p><h1>Sepetin</h1>';
    echo '<p class="lead">Kampüs içi teslimat 14,90 TL olarak sepete eklenir.</p>';
    flash_kutusu();
    echo '<div class="split"><section class="panel">';
    if (sepet_adet() === 0) {
        echo '<div class="empty"><h3>Sepetin henüz boş</h3><p>Rafa dönüp bir defter veya kalem ekleyebilirsin.</p>';
        echo '<p><a class="ghost" href="index.php?sayfa=urunler">Rafı aç</a></p></div>';
    } else {
        foreach (sepet() as $id => $adet) {
            $urun = urunler()[$id];
            echo '<div class="cart-item" data-testid="cart-item" data-id="' . $id . '">';
            echo '<div><strong>' . htmlspecialchars($urun['ad']) . '</strong><div class="hint" data-testid="qty">Adet: ' . $adet . '</div></div>';
            echo '<div data-testid="line-total">' . para($urun['fiyat'] * $adet) . '</div>';
            echo '<form method="post"><input type="hidden" name="islem" value="sil"><input type="hidden" name="urun" value="' . $id . '">';
            echo '<button class="linkish" data-testid="remove-' . $id . '">Kaldır</button></form></div>';
        }
    }
    echo '</section><aside class="panel"><h3>Sipariş özeti</h3>';
    echo '<div class="sum-line"><span>Ürünler (' . sepet_adet() . ')</span><span data-testid="subtotal">' . para(sepet_ara_toplam()) . '</span></div>';
    echo '<div class="sum-line"><span>Teslimat</span><span data-testid="shipping">' . (sepet_adet() ? para(teslimat_ucreti_kurus()) : 'Ücretsiz') . '</span></div>';
    echo '<div class="sum-line total"><span>Toplam</span><span data-testid="total">' . para(sepet_genel_toplam()) . '</span></div>';
    echo '<form method="post"><input type="hidden" name="islem" value="teslimata_gec">';
    echo '<button class="solid wide terracotta" data-testid="checkout">Teslimata geç</button></form>';
    echo '<p class="help">Ders projesi. Gerçek ödeme alınmaz.</p></aside></div>';
    html_son();
}

function sayfa_teslimat(): void
{
    giris_gerekli();
    bos_sepet_engeli('teslimat');
    $eski = $_SESSION['teslimat'] ?? ['ad' => '', 'soyad' => '', 'telefon' => ''];
    html_bas('Teslimat');
    echo '<p class="kicker">Adım 1 / 2</p><h1>Teslimat bilgileri</h1>';
    echo '<p class="lead">Kampüs içi teslimat için ad, soyad ve 10 haneli telefon gerekir.</p>';
    flash_kutusu();
    echo '<form class="panel" method="post" style="margin:20px 0 40px">';
    echo '<input type="hidden" name="islem" value="teslimat">';
    echo '<div class="form-grid">';
    echo '<div><label for="ad">Ad</label><input id="ad" name="ad" value="' . htmlspecialchars($eski['ad']) . '" data-testid="first"></div>';
    echo '<div><label for="soyad">Soyad</label><input id="soyad" name="soyad" value="' . htmlspecialchars($eski['soyad']) . '" data-testid="last"></div>';
    echo '<div class="full"><label for="telefon">Telefon</label><input id="telefon" name="telefon" value="' . htmlspecialchars($eski['telefon']) . '" data-testid="phone">';
    echo '<p class="help">10 rakam gir. Örnek: 0555123456</p></div></div>';
    echo '<div class="actions"><a class="ghost" href="index.php?sayfa=sepet">Sepete dön</a>';
    echo '<button class="solid" data-testid="to-summary">Özeti gör</button></div></form>';
    html_son();
}

function sayfa_onay(): void
{
    giris_gerekli();
    bos_sepet_engeli('onay');
    $bilgi = $_SESSION['teslimat'] ?? null;
    if (!$bilgi) {
        git('teslimat');
    }
    html_bas('Onay');
    echo '<p class="kicker">Adım 2 / 2</p><h1>Sipariş özeti</h1>';
    flash_kutusu();
    echo '<div class="split"><section class="panel">';
    foreach (sepet() as $id => $adet) {
        $urun = urunler()[$id];
        echo '<div class="cart-item" data-testid="cart-item"><div>' . htmlspecialchars($urun['ad']) . ' × ' . $adet . '</div>';
        echo '<div>' . para($urun['fiyat'] * $adet) . '</div><div></div></div>';
    }
    echo '<p>Teslim: ' . htmlspecialchars($bilgi['ad'] . ' ' . $bilgi['soyad']) . ' · ' . htmlspecialchars($bilgi['telefon']) . '</p>';
    echo '</section><aside class="panel"><div class="sum-line total"><span>Toplam</span><span data-testid="total">' . para(sepet_genel_toplam()) . '</span></div>';
    echo '<form method="post"><input type="hidden" name="islem" value="siparis">';
    echo '<button class="solid wide terracotta" data-testid="place-order">Demo siparişi tamamla</button></form></aside></div>';
    html_son();
}

function sayfa_tesekkur(): void
{
    giris_gerekli();
    $siparis = $_SESSION['son_siparis'] ?? ['no' => 'KK-000', 'toplam' => 0];
    html_bas('Teşekkürler');
    echo '<section class="thanks"><p class="kicker">Sipariş alındı</p>';
    echo '<h1 data-testid="thanks">Kırtasiye siparişiniz kaydedildi.</h1>';
    echo '<p class="lead">Sipariş no: <strong data-testid="order-no">' . htmlspecialchars($siparis['no']) . '</strong></p>';
    echo '<p>Ödenen tutar (demo): <strong>' . para((int) $siparis['toplam']) . '</strong></p>';
    echo '<p><a class="solid" href="index.php?sayfa=urunler">Rafa dön</a></p></section>';
    html_son();
}

$sayfa = istek_sayfa();
if ($sayfa === 'cikis') {
    $_SESSION = [];
    session_destroy();
    header('Location: index.php?sayfa=giris');
    exit;
}

post_isle();

match ($sayfa) {
    'urunler' => sayfa_urunler(),
    'sepet' => sayfa_sepet(),
    'teslimat' => sayfa_teslimat(),
    'onay' => sayfa_onay(),
    'tesekkur' => sayfa_tesekkur(),
    default => sayfa_giris(),
};
