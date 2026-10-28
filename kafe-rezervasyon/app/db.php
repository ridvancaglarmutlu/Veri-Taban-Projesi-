<?php
/**
 * =====================================================================
 *  KAFE MASA REZERVASYON SISTEMI - VERITABANI KATMANI (PDO)
 * ---------------------------------------------------------------------
 *  Tum SQL trafigi bu dosyadan gecer. Amac tek satirda ozetlenebilir:
 *
 *      Kullanicidan gelen hicbir deger, SQL metninin icine yazilmaz.
 *
 *  Neden PDO, neden mysqli degil?
 *    - PDO 12'den fazla veritabani surucusu icin ayni arayuzu sunar.
 *      Yarin SQLite'a gecerseniz sadece DSN degisir.
 *    - Isimli parametre destekler (:masa_id). mysqli sadece sirali "?"
 *      kabul eder; 8 parametreli bir INSERT'te siralama hatasi yapmak
 *      cok kolaydir.
 *    - Hatalari istisna (exception) olarak firlatabilir; mysqli'de bu
 *      davranis surume gore degisir.
 *    - fetch modlari daha esnektir (FETCH_ASSOC, FETCH_OBJ, FETCH_CLASS).
 *
 *  Neden bir sinif (Database) ve neden "singleton"?
 *    Bir HTTP istegi icinde veritabanina birden fazla kez baglanmak
 *    gereksizdir: TCP el sikismasi + kimlik dogrulama her seferinde
 *    tekrarlanir. Singleton deseni "ilk isteyene baglantiyi kur, sonraki
 *    isteyenlere ayni nesneyi ver" demektir. Ayrica "lazy" (tembel)
 *    calisir: sayfa hic SQL calistirmayacaksa baglanti hic kurulmaz.
 * =====================================================================
 */

if (!defined('APP_INIT')) {
    http_response_code(403);
    die('Dogrudan erisim engellendi.');
}

final class Database
{
    /**
     * Tek ve paylasilan PDO nesnesi.
     * static -> nesneye degil SINIFA aittir, istek boyunca yasar.
     * ?PDO   -> baslangicta null, ilk kullanimda doldurulur.
     */
    private static ?PDO $pdo = null;

    /**
     * Yapici metot private: "new Database()" yazilamaz.
     * Boylece bu siniftan yanlislikla ikinci bir ornek uretilemez;
     * baglantiya ulasmanin tek yolu Database::baglanti() olur.
     */
    private function __construct()
    {
    }

    /**
     * PDO nesnesini dondurur; gerekiyorsa once baglantiyi kurar.
     */
    public static function baglanti(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;   // zaten bagli, tekrar baglanma
        }

        // -------------------------------------------------------------
        // DSN (Data Source Name) - baglantinin adresi
        // -------------------------------------------------------------
        // charset=utf8mb4 BURADA olmali. Alternatif olarak baglanti
        // kurulduktan sonra "SET NAMES utf8mb4" sorgusu calistirilabilir
        // ama bu YANLIS bir aliskanliktir:
        //   SET NAMES sadece SUNUCUYA karakter setini bildirir; PHP'nin
        //   icindeki MySQL istemcisi hala eski karakter setini kullandigini
        //   zanneder. Istemci ile sunucunun karakter seti hakkinda farkli
        //   seyler dusunmesi, asagida anlatilan "cok baytli kacis (escape)
        //   acigi" sinifinin tam olarak dogdugu yerdir.
        // DSN'e yazildiginda hem istemci hem sunucu ayni anda ayarlanir.
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            DB_HOST,
            DB_PORT,
            DB_NAME,
            DB_CHARSET
        );

        // -------------------------------------------------------------
        // PDO SECENEKLERI - bu dizinin her satiri bilincli bir tercihtir
        // -------------------------------------------------------------
        $secenekler = [

            // ---------------------------------------------------------
            // 1) ATTR_ERRMODE = ERRMODE_EXCEPTION
            // ---------------------------------------------------------
            // PDO'nun varsayilan davranisi ERRMODE_SILENT'tir: sorgu
            // basarisiz olur, hicbir sey soylemez, execute() sadece false
            // doner. Donus degerini kontrol etmezseniz kod "basarili"
            // gibi akmaya devam eder:
            //
            //   YANLIS (sessiz mod):
            //     $stmt = $pdo->prepare("INSERT INTO rezervasyonlar ...");
            //     $stmt->execute($veri);          // false donebilir
            //     echo "Rezervasyonunuz alindi!"; // ...ama alinmadi!
            //
            // EXCEPTION modunda ayni durumda PDOException firlatilir;
            // yakalanmazsa sayfa durur, log'a duser, musteriye yalan
            // soylenmis olmaz. Ayrica try/catch ile islem (transaction)
            // geri alinabilir.
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,

            // ---------------------------------------------------------
            // 2) ATTR_EMULATE_PREPARES = false
            //    *** BU DIZIDEKI EN KRITIK SATIR ***
            // ---------------------------------------------------------
            // PHP'de prepared statement IKI farkli sekilde calisabilir.
            //
            // (A) TAKLIT (emulation) -- PDO'nun MySQL'deki VARSAYILANI
            //     prepare() sunucuya hicbir sey gondermez. execute()
            //     cagrildiginda PDO, parametreleri PHP tarafinda
            //     PDO::quote() ile tirnaklayip SQL metninin icine
            //     YAPISTIRIR ve ortaya cikan TEK bir hazir SQL cumlesini
            //     sunucuya yollar:
            //
            //       Sablon : SELECT * FROM masalar WHERE masa_adi = ?
            //       Deger  : Masa 1
            //       Giden  : SELECT * FROM masalar WHERE masa_adi = 'Masa 1'
            //
            //     Yani guvenlik, PHP icindeki bir metin kacislama
            //     (escaping) fonksiyonunun dogruluguna bagimlidir.
            //
            // (B) GERCEK / SUNUCU TARAFLI prepared statement
            //     prepare() SQL SABLONUNU sunucuya gonderir. Sunucu onu
            //     hemen ayristirir (parse eder), sorgu planini olusturur
            //     ve "burada bir parametre var" seklinde bir yer tutucu
            //     birakir. execute() ise SADECE degerleri, ikili (binary)
            //     protokolle, ayri bir paket olarak gonderir.
            //
            //       PHP  --PREPARE--> SELECT * FROM masalar WHERE masa_adi = ?
            //       PHP  --EXECUTE--> ["' OR '1'='1"]
            //
            //     Kritik nokta: sorgu ZATEN ayristirilmistir. Sonradan
            //     gelen deger asla SQL olarak yorumlanamaz; yapabilecegi
            //     tek sey "masa_adi" kolonuyla karsilastirilacak bir metin
            //     olmaktir. Bu yuzden gercek prepared statement'ta SQL
            //     injection teorik olarak imkansizdir.
            //
            // --- Taklit acikken injection NASIL mumkun olabiliyor? ---
            //
            // PDO::quote() gelen metindeki tek tirnaklari ters bolu (\)
            // ile kacislar. Bu islem, baglantinin karakter setine gore
            // yapilir. Eger istemcinin bildigi karakter seti ile sunucunun
            // kullandigi karakter seti AYRISIRSA (klasik ornek: baglanti
            // GBK / Big5 / SJIS gibi cok baytli bir karakter setinde,
            // fakat "SET NAMES" ile degistirildigi icin istemci bunu
            // bilmiyor) su olur:
            //
            //   Saldirgan sunu gonderir  : 0xBF 0x27
            //     (0xBF = GBK'de bir karakterin ILK bayti, 0x27 = tek tirnak)
            //   PDO kacislar, araya \ koyar: 0xBF 0x5C 0x27
            //   Sunucu GBK olarak okur   : 0xBF5C = tek bir Cince karakter
            //                              ve geride 0x27 = KACISLANMAMIS
            //                              tek tirnak kalir!
            //
            // Ters bolu, cok baytli bir karakterin icine "yutulmus"tur;
            // tirnak serbest kalmistir ve sorgudan cikip SQL yazmaya
            // baslayabilirsiniz. Bu, teorik bir senaryo degil; MySQL/PHP
            // dunyasinda yillarca gercek acik olarak (CVE-2006-2753 ve
            // devami) somurulmustur.
            //
            // Gercek prepared statement'ta boyle bir kacislama ADIMI HIC
            // YOKTUR - deger, SQL metnine hicbir zaman girmez. Bu yuzden
            // "DSN'de charset + EMULATE_PREPARES=false" ikilisi birlikte
            // yazilir.
            //
            // --- Taklidin ikinci buyuk sorunu: yigilmis sorgular ---
            // Taklit acikken PDO, metin haline getirdigi sorguyu
            // mysqli_query benzeri bir kanaldan gonderir ve bazi
            // yapilandirmalarda noktali virgulle ayrilmis COKLU ifadeye
            // izin verir. Boylece kucuk bir acik
            //   '; DROP TABLE rezervasyonlar; --
            //   yazilarak yikici hale gelebilir.
            // Gercek prepared statement tek seferde tek ifade calistirir.
            //
            // --- Kapatmanin "maliyeti" nedir? ---
            // Sunucuya PREPARE ve EXECUTE olmak uzere iki gidis-dolus
            // olur (emulation'da tek). Yerel agda bu fark olculemeyecek
            // kadar kucuktur; guvenlik karsisinda tartisilmaz.
            //
            // --- Kapattiktan sonra dikkat edilecek tek konu ---
            // LIMIT/OFFSET gibi yerlerde MySQL sayi bekler, PDO ise
            // parametreleri varsayilan olarak METIN gonderir:
            //
            //   YANLIS: $stmt->execute([':limit' => $limit]);   // '10' metni -> sozdizimi hatasi
            //   DOGRU : $stmt->bindValue(':limit', (int) $limit, PDO::PARAM_INT);
            PDO::ATTR_EMULATE_PREPARES => false,

            // ---------------------------------------------------------
            // 3) ATTR_DEFAULT_FETCH_MODE = FETCH_ASSOC
            // ---------------------------------------------------------
            // Varsayilan FETCH_BOTH her satiri hem ['masa_adi'] hem [1]
            // olarak iki kez dondurur: bellek iki katina cikar ve
            // print_r ciktisi okunmaz hale gelir. FETCH_ASSOC sadece
            // kolon adlariyla calisir: $masa['masa_adi'].
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

            // ---------------------------------------------------------
            // 4) ATTR_STRINGIFY_FETCHES = false
            // ---------------------------------------------------------
            // PDO tarihsel olarak her seyi METIN dondurur: kapasite
            // kolonu INT olsa bile PHP'ye "4" (string) gelir. Bu sinsi
            // hatalara yol acar:
            //
            //   $masa['kapasite'] === 4     // false! cunku "4" === 4 degil
            //   json_encode($masa)          // {"kapasite":"4"} -> JS tarafinda
            //                               // "4" + 1 = "41" surprizi
            //
            // false yapinca (ve EMULATE_PREPARES de kapaliyken) INT
            // kolonlar PHP'ye gercek int, DECIMAL'lar float olarak gelir.
            // Not: Bu ayar ancak gercek prepared statement ile anlamlidir;
            // taklit acikken sunucu zaten her seyi metin olarak yollar.
            PDO::ATTR_STRINGIFY_FETCHES => false,

            // ---------------------------------------------------------
            // 5) ATTR_PERSISTENT = false
            // ---------------------------------------------------------
            // Kalici baglanti, istek bitince kapanmaz ve bir sonraki
            // istege devredilir. Hizlandirir ama yarim kalmis
            // transaction'lar ve kilitler baska bir kullaniciya miras
            // kalabilir. Ogrenme/gelistirme asamasinda kapali tutulur.
            PDO::ATTR_PERSISTENT => false,
        ];

        // -------------------------------------------------------------
        // BAGLANTI + HATA YONETIMI
        // -------------------------------------------------------------
        try {
            self::$pdo = new PDO($dsn, DB_USER, DB_PASS, $secenekler);
        } catch (PDOException $e) {
            // DIKKAT: PDOException mesaji baglanti ayrintilarini icerir;
            // bazi durumlarda yigin izinde (stack trace) sifre bile
            // gorunur:
            //   #0 /app/db.php(120): PDO->__construct('mysql:host=...', 'root', 'GizliSifre')
            // Bu yuzden hata mesajini kullaniciya gostermek SADECE
            // gelistirme ortaminda kabul edilebilir.
            if (APP_DEBUG) {
                self::hataEkrani(
                    'Veritabani baglantisi kurulamadi',
                    $e->getMessage()
                        . "\n\nKontrol listesi:"
                        . "\n  1) XAMPP Control Panel'de MySQL servisi calisiyor mu?"
                        . "\n  2) database.sql phpMyAdmin'den import edildi mi?"
                        . "\n  3) app/config.php icindeki DB_NAME / DB_USER dogru mu?"
                );
            }

            // Canli ortam: ayrinti sunucu log'una, kullaniciya genel mesaj.
            // error_log ciktisi Apache'nin error.log dosyasina duser;
            // boylece bilgi kaybolmaz ama disari sizmaz.
            error_log('[DB] Baglanti hatasi: ' . $e->getMessage());
            http_response_code(503); // 503 Service Unavailable
            die('Servis su anda kullanilamiyor. Lutfen birazdan tekrar deneyin.');
        }

        return self::$pdo;
    }

    // =================================================================
    // KISA YARDIMCILAR
    // -----------------------------------------------------------------
    // Asagidaki dort metot her seferinde prepare -> execute yazmaktan
    // kurtarir. Hepsi ISTISNASIZ prepared statement kullanir; bu sinifta
    // kullanicidan gelen degeri SQL metnine ekleyen tek bir satir yoktur.
    //
    // ONEMLI KURAL: $sql parametresi SABIT bir metin olmalidir.
    // Parametre yer tutuculari sadece DEGERLER icin kullanilabilir;
    // tablo ve kolon ADLARI parametre olamaz (SQL standardi boyle).
    //   YANLIS: "SELECT * FROM masalar ORDER BY :kolon"   -> calismaz
    //   DOGRU : izin verilen kolon adlarini beyaz listeden secip
    //           SQL'e oyle yazmak.
    // =================================================================

    /**
     * Sorguyu hazirlar, parametrelerle calistirir ve PDOStatement dondurur.
     *
     * @param string               $sql         Yer tutucu iceren SQL sablonu
     * @param array<string, mixed> $parametreler [':ad' => deger] veya [deger, ...]
     */
    public static function query(string $sql, array $parametreler = []): PDOStatement
    {
        $stmt = self::baglanti()->prepare($sql);
        $stmt->execute($parametreler);

        return $stmt;
    }

    /**
     * Tek satir dondurur. Kayit yoksa null.
     *
     * Neden false degil de null? PDO bos sonucta false dondurur ve
     *   if ($satir) { ... }
     * kontrolu 0 iceren bir satirla karisabilir. null dondurmek
     * "kayit yok" anlamini net hale getirir: $satir === null
     */
    public static function fetchOne(string $sql, array $parametreler = []): ?array
    {
        $satir = self::query($sql, $parametreler)->fetch();

        return $satir === false ? null : $satir;
    }

    /**
     * Tum satirlari dizi olarak dondurur. Kayit yoksa bos dizi.
     *
     * Bos dizi dondurmek, cagiran tarafta "null mu, dizi mi?" kontrolu
     * yapmayi gereksiz kilar; foreach bos dizide sorunsuz calisir.
     */
    public static function fetchAll(string $sql, array $parametreler = []): array
    {
        return self::query($sql, $parametreler)->fetchAll();
    }

    /**
     * Tek bir hucre degeri dondurur (COUNT(*), MAX(id) gibi sorgular icin).
     * Kayit yoksa null.
     */
    public static function fetchValue(string $sql, array $parametreler = []): mixed
    {
        $deger = self::query($sql, $parametreler)->fetchColumn();

        return $deger === false ? null : $deger;
    }

    /**
     * INSERT / UPDATE / DELETE icin. Etkilenen satir sayisini dondurur.
     *
     * Donen sayi ise yarar: "UPDATE ... WHERE rezervasyon_kodu = :kod"
     * sorgusu 0 donduruyorsa oyle bir kod yoktur; kullaniciya "Kayit
     * bulunamadi" demek icin ayrica SELECT yapmaya gerek kalmaz.
     */
    public static function execute(string $sql, array $parametreler = []): int
    {
        return self::query($sql, $parametreler)->rowCount();
    }

    /**
     * Son INSERT ile olusan otomatik id.
     * Bu deger BAGLANTIYA ozeldir; ayni anda baska bir kullanici kayit
     * atsa bile sizin baglantinizin urettigi id doner. Yaris durumu yoktur.
     */
    public static function sonId(): int
    {
        return (int) self::baglanti()->lastInsertId();
    }

    /**
     * Birden fazla yazma islemini tek parcada calistirmak icin.
     *
     * Ornek: rezervasyon kaydedilirken once cakisma kontrolu, sonra
     * INSERT yapilir. Arada baska biri ayni masayi kaparsa tutarsizlik
     * olusur; transaction + kilit bunu engeller. Callback icinde istisna
     * firlarsa tum degisiklikler geri alinir (rollback).
     */
    public static function transaction(callable $islem): mixed
    {
        $pdo = self::baglanti();
        $pdo->beginTransaction();

        try {
            $sonuc = $islem($pdo);
            $pdo->commit();

            return $sonuc;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;   // hatayi yutma: cagiran taraf haberdar olmali
        }
    }

    /**
     * Gelistirme ortaminda okunakli hata ekrani basar ve calismayi keser.
     * Mesaj htmlspecialchars'tan gecirilir: hata metninde HTML olsa bile
     * tarayicida calismasin (hata ekraninin kendisi XSS olmasin).
     */
    private static function hataEkrani(string $baslik, string $ayrinti): never
    {
        http_response_code(500);

        // CLI'da (komut satiri) HTML anlamsizdir; duz metin basalim.
        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, "\n[HATA] {$baslik}\n{$ayrinti}\n");
            exit(1);
        }

        $baslikGuvenli  = htmlspecialchars($baslik, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $ayrintiGuvenli = htmlspecialchars($ayrinti, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        echo '<!doctype html><html lang="tr"><meta charset="utf-8">'
            . '<title>Hata</title>'
            . '<div style="font-family:system-ui,sans-serif;max-width:720px;margin:48px auto;padding:24px;'
            . 'border:1px solid #f0b4b4;border-radius:12px;background:#fff6f6">'
            . '<h1 style="margin:0 0 12px;font-size:20px;color:#b02a2a">' . $baslikGuvenli . '</h1>'
            . '<pre style="white-space:pre-wrap;margin:0;font-size:13px;color:#444">' . $ayrintiGuvenli . '</pre>'
            . '<p style="margin:16px 0 0;font-size:12px;color:#888">'
            . 'Bu ayrintili ekran APP_DEBUG = true oldugu icin gorunuyor. '
            . 'Canli ortamda app/config.php icinde false yapin.</p></div>';

        exit(1);
    }
}
