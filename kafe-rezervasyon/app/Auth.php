<?php
/**
 * =====================================================================
 *  KAFE MASA REZERVASYON SISTEMI - YONETICI KIMLIK DOGRULAMA
 * ---------------------------------------------------------------------
 *  Musteri tarafi (rezervasyon formu) OTURUM GEREKTIRMEZ: takip kodu
 *  zaten "parola yerine" gecer. Admin paneli ise masalari kapatabilir,
 *  rezervasyonu iptal edebilir; bu yuzden "kim oldugunu kanitla"
 *  adimi zorunludur.
 *
 *  Bu sinif uc isi tek yerde toplar:
 *    1. girisYap()  -> kullanici adi + sifre dogrula, oturumu kur
 *    2. cikisYap()  -> oturumdaki yonetici verisini sil
 *    3. kontrol()   -> giris yoksa login.php'ye yonlendir
 *
 *  Neden ayri bir sinif, neden her admin sayfasina kopyalanan if'ler
 *  degil? Yarin ikinci bir koruma kurali eklenirse (ornegin son 30
 *  dakikadir hareketsizse tekrar sifre sor) tek metot yeter; 5 ayri
 *  sayfada unutulan bir if, acik bir kapi birakir.
 * =====================================================================
 */

if (!defined('APP_INIT')) {
    http_response_code(403);
    die('Doğrudan erişim engellendi.');
}

final class Auth
{
    /**
     * Oturumda yonetici verisinin durdugu anahtar.
     *
     * MUSTERI oturumu 'flash' ve CSRF_SESSION_ANAHTARI ('csrf_token')
     * anahtarlarini kullanir. Ayni anahtara yazmak, musteri formundaki
     * bir flash mesajinin admin kimligini ezmesine (veya tersine) yol
     * acardi. Ayri bir kok anahtar, iki dunyayi birbirine karistirmayi
     * imkansiz kilar.
     */
    private const OTURUM_ANAHTARI = 'yonetici';

    /**
     * Kullanici adi + sifre ile giris dener.
     *
     * @return bool Basariliysa true. Basarisizlikte SEBEP DONULMEZ:
     *              "kullanici yok" ile "sifre yanlis"i ayirmak,
     *              saldirgana hangi hesaplarin var oldugunu soyler.
     */
    public static function girisYap(string $kullaniciAdi, string $sifre): bool
    {
        $kullaniciAdi = trim($kullaniciAdi);

        // Bos girdi icin veritabanina gitmiyoruz. Ayni "basarisiz"
        // cevabini vermek, zamanlama farkini da kucultur.
        if ($kullaniciAdi === '' || $sifre === '') {
            return false;
        }

        $yonetici = Database::fetchOne(
            'SELECT id, kullanici_adi, sifre, ad_soyad
               FROM yoneticiler
              WHERE kullanici_adi = :adi
              LIMIT 1',
            [':adi' => $kullaniciAdi]
        );

        // Kayit yoksa bile password_verify'i dummy bir hash uzerinde
        // calistiriyoruz. Neden? SELECT 0 satir dondugunde fonksiyon
        // hemen false donerse, var olan bir hesapta bcrypt'in ~100ms
        // surmesi ile "bu kullanici adi yok" cevabinin 1ms surmesi
        // olculebilir hale gelir (kullanici numaralandirma).
        $hash = is_array($yonetici) ? (string) $yonetici['sifre'] : self::sahteHash();

        if (!password_verify($sifre, $hash) || $yonetici === null) {
            return false;
        }

        // -----------------------------------------------------------------
        // session_regenerate_id(true)
        // -----------------------------------------------------------------
        // ENGELLEDIGI SALDIRI: Session Fixation'in ikinci yarisi.
        // bootstrap.php strict_mode ile saldirganin UYDURDUGU kimligi
        // reddeder; regenerate ise giris ONCESI (anonim) kimligi giris
        // SONRASINDA gecersiz kilar. true parametresi eski oturum
        // dosyasini siler: calinan eski cerez ile yeni yetkili oturuma
        // ulasilamaz.
        //
        // Siraya dikkat: once kimligi yenile, SONRA yonetici verisini
        // yaz. Tersi yapilirsa kisa bir pencerede eski kimlik yetkili
        // veriyi tasir.
        session_regenerate_id(true);

        $_SESSION[self::OTURUM_ANAHTARI] = [
            'id'            => (int) $yonetici['id'],
            'kullanici_adi' => (string) $yonetici['kullanici_adi'],
            'ad_soyad'      => (string) ($yonetici['ad_soyad'] ?? ''),
            'giris_at'      => date('Y-m-d H:i:s'),
        ];

        // son_giris_at raporlama icindir; basarisiz olsa bile oturum
        // zaten kurulmustur. Hatayi yutmak, girisi bozmamak icindir.
        try {
            Database::execute(
                'UPDATE yoneticiler
                    SET son_giris_at = :zaman
                  WHERE id = :id',
                [
                    ':zaman' => date('Y-m-d H:i:s'),
                    ':id'    => (int) $yonetici['id'],
                ]
            );
        } catch (Throwable $e) {
            error_log('[Auth] son_giris_at guncellenemedi: ' . $e->getMessage());
        }

        return true;
    }

    /**
     * Yonetici oturumunu kapatir. CSRF / flash gibi musteri anahtarlari
     * silinmez: cikis, musteri formundaki token'i da cop etmesin diye
     * SADECE yonetici kokunu kaldiriyoruz. Tam session_destroy() ayni
     * tarayicida acik olan musteri sekmesini de "oturum dustu" yapardi.
     */
    public static function cikisYap(): void
    {
        unset($_SESSION[self::OTURUM_ANAHTARI]);

        // Cikis sonrasi da kimligi yeniliyoruz: eski yetkili cerez
        // artik bos bir oturuma isaret eder, icerigi tasimaz.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    /**
     * Korunan admin sayfalarinin ILK satiridir.
     * Giris yoksa login.php'ye 303 ile gonderir ve betigi durdurur
     * (yonlendir() zaten exit yapar). header() deyip devam etmek,
     * alttaki SQL'in yetkisiz calismasina yol acardi.
     */
    public static function kontrol(): void
    {
        if (self::girisYapildiMi()) {
            return;
        }

        yonlendir('login.php', 303);
    }

    public static function girisYapildiMi(): bool
    {
        $veri = $_SESSION[self::OTURUM_ANAHTARI] ?? null;

        return is_array($veri) && isset($veri['id']) && (int) $veri['id'] > 0;
    }

    /**
     * Oturumdaki yonetici ozeti. Sifre HASH'i burada YOKTUR: session
     * dosyasi sunucuda duz metin durur; hash'i oraya kopyalamak ek
     * bir sizinti yuzeyi olur, ekranda da ise yaramaz.
     *
     * @return array{id:int, kullanici_adi:string, ad_soyad:string, giris_at:string}|null
     */
    public static function yonetici(): ?array
    {
        if (!self::girisYapildiMi()) {
            return null;
        }

        /** @var array{id:int, kullanici_adi:string, ad_soyad:string, giris_at:string} $veri */
        $veri = $_SESSION[self::OTURUM_ANAHTARI];

        return $veri;
    }

    /**
     * yonetici() ile ayni veri. Eski cagri adi (kullanici) bozulmasin
     * diye durur; yeni kod yonetici() kullanir.
     */
    public static function kullanici(): ?array
    {
        return self::yonetici();
    }

    /**
     * password_verify icin sabit maliyetli sahte hash.
     * Degeri onemli degil; islemin SURESI gercek dogrulamaya benzsin
     * diye duruyor. Her istekte password_hash uretmek (yeni tuz) sureyi
     * daha da uzatir ve CPU yer; tek bir gomulu bcrypt yeter.
     */
    private static function sahteHash(): string
    {
        return '$2y$10$usesomesillystringfore7u7s5Hy6gCSmx6bOjkpX2eKAdK4qK6';
    }
}
