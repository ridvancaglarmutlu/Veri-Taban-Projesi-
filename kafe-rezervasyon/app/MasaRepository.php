<?php
/**
 * =====================================================================
 *  KAFE MASA REZERVASYON SISTEMI - MASA DEPOSU (REPOSITORY)
 * ---------------------------------------------------------------------
 *  "Repository" (depo) deseni tek bir soruya cevap verir:
 *
 *      Masalar tablosuna ait SQL nerede duruyor?
 *
 *  Cevap: SADECE burada. index.php, api/musait-masalar.php ve Adim 4'teki
 *  admin sayfalari tek satirlik SQL bile yazmaz; bu sinifin metotlarini
 *  cagirir.
 *
 *  Neden bu kadar katiyiz?
 *    - Cakisma sorgusu (asagidaki musaitMasalar) projenin en kritik
 *      mantigidir. Ayni sorgu iki ayri sayfaya kopyalanirsa, birinde
 *      "durum = 'onaylandi'" filtresini duzeltip digerinde unutmak
 *      kacinilmazdir. O an iptal edilmis bir rezervasyon masayi bloklamaya
 *      baslar ve hata haftalarca fark edilmez.
 *    - Indeks ve sorgu optimizasyonu tek yerde yapilir.
 *    - Test etmek kolaydir: metodu cagirip donen diziye bakmak yeterli.
 *
 *  Neden metotlar "static"?
 *    Bu sinifin saklayacagi bir DURUMU (state) yok; sadece SQL calistirip
 *    dizi donduruyor. Nesne uretmek (new MasaRepository()) hicbir sey
 *    kazandirmazdi. Buyuk projelerde bagimlilik enjeksiyonu (dependency
 *    injection) icin nesne tercih edilir; bu olcekte static daha okunakli.
 * =====================================================================
 */

if (!defined('APP_INIT')) {
    http_response_code(403);
    die('Doğrudan erişim engellendi.');
}

final class MasaRepository
{
    /**
     * Tum masalar (aktif + pasif). Admin panelindeki masa yonetimi listesi
     * icin gerekir; musteri tarafinda KULLANILMAZ, cunku musteriye pasif
     * masa gosterilmemeli.
     *
     * ORDER BY neden var? MySQL, ORDER BY yazilmadiginda satirlarin hangi
     * sirada donecegine dair HICBIR GARANTI vermez. Kucuk tablolarda
     * genelde ekleme sirasi gibi gorunur, ama indeks degisince ya da
     * sorgu plani degisince sira bozulur. "Bende calisiyordu" hatalarinin
     * klasik kaynagidir; sira onemliyse acikca yazilir.
     */
    public static function tumu(): array
    {
        return Database::fetchAll(
            'SELECT id, masa_adi, kapasite, konum, durum, created_at
               FROM masalar
              ORDER BY masa_adi'
        );
    }

    /**
     * Tek masayi id ile getirir. Kayit yoksa null.
     *
     * Bu metot sadece "veri cekmek" icin degil, DOGRULAMA icin de kullanilir:
     * musteri formda masa_id = 999 gonderirse (form <input>'unu tarayici
     * konsolundan degistirmek saniyeler alir) burada null donecek ve
     * index.php "Gecersiz masa" hatasi verecek. Boylece var olmayan bir
     * masaya rezervasyon denemesi veritabaninin foreign key hatasina
     * kadar ilerlemez; kullaniciya anlamli mesaj gosterebiliriz.
     *
     * Parametre tipi int: kullanicidan gelen "3 OR 1=1" gibi bir deger
     * PHP tarafinda (int) donusumu ile 3'e iner. Prepared statement zaten
     * koruyor, ama tip belirtmek niyeti de belgelendirir.
     */
    public static function bul(int $id): ?array
    {
        return Database::fetchOne(
            'SELECT id, masa_adi, kapasite, konum, durum, created_at
               FROM masalar
              WHERE id = :id',
            [':id' => $id]
        );
    }

    /**
     * Sadece rezervasyona acik (aktif) masalar.
     *
     * Not: Bu liste "musait" DEGILDIR. Aktif = "masa fiziksel olarak
     * hizmette". Musait = "aktif VE istenen saat araliginda bos".
     * Ikisini karistirmak, dolu masayi musteriye onermeye yol acar.
     * Musaitlik icin musaitMasalar() metodunu kullanin.
     */
    public static function aktifler(): array
    {
        return Database::fetchAll(
            "SELECT id, masa_adi, kapasite, konum
               FROM masalar
              WHERE durum = 'aktif'
              ORDER BY kapasite, masa_adi"
        );
    }

    // =================================================================
    //  MUSAIT MASA SORGUSU - PROJENIN KALBI
    // =================================================================

    /**
     * Verilen tarih + saat araliginda rezerve edilebilecek masalari dondurur.
     *
     * ---------------------------------------------------------------------
     * 1) CAKISMA MATEMATIGI
     * ---------------------------------------------------------------------
     * Iki zaman araligi [A_bas, A_bit) ve [B_bas, B_bit) SADECE VE ANCAK
     *
     *          A_bas < B_bit   VE   A_bit > B_bas
     *
     * oldugunda kesisir. (database.sql, bolum 6 ile birebir ayni kosul.)
     *
     * Bu TEK satir, akla gelebilecek butun yerlesim ihtimallerini kapsar.
     * Musteri 14:00-16:00 istiyor olsun; mevcut kayit:
     *
     *   12:00-14:00 (bitisik, once)  -> 12:00 < 16:00 DOGRU
     *                                   14:00 > 14:00 YANLIS  => cakisma YOK
     *   13:00-15:00 (bastan tasan)   -> 13:00 < 16:00 DOGRU
     *                                   15:00 > 14:00 DOGRU   => CAKISIR
     *   14:30-15:00 (tam icinde)     -> her iki kosul DOGRU    => CAKISIR
     *   13:00-17:00 (kapsayan)       -> her iki kosul DOGRU    => CAKISIR
     *   14:00-16:00 (birebir ayni)   -> her iki kosul DOGRU    => CAKISIR
     *   15:30-18:00 (sondan tasan)   -> her iki kosul DOGRU    => CAKISIR
     *   16:00-18:00 (bitisik, sonra) -> 16:00 < 16:00 YANLIS   => cakisma YOK
     *
     * Ogrencilerin en sik yaptigi hata bu kosul yerine dort ayri "if"
     * yazmaktir (tam ayni / icinde / bastan tasan / sondan tasan). Dort
     * ifin biri mutlaka eksik kalir, genelde "kapsayan" durumu.
     *
     * Sinirlarda neden "<" ve ">", neden "<=" ve ">=" degil?
     * Cunku aralik yarim aciktir: [14:00, 16:00) demek "16:00 dahil degil".
     * 14:00'te biten bir rezervasyonun ardindan 14:00'te baslayan yeni
     * rezervasyon tamamen gecerlidir. ">=" yazilsa bitisik rezervasyonlar
     * bosuna reddedilir ve kafe her gun ciro kaybeder.
     *
     * ---------------------------------------------------------------------
     * 2) NEDEN "NOT EXISTS", NEDEN "LEFT JOIN ... IS NULL" DEGIL?
     * ---------------------------------------------------------------------
     * Ayni sonucu uc yolla alabiliriz:
     *
     *   (a) NOT EXISTS (alt sorgu)         <- SECTIGIMIZ
     *   (b) LEFT JOIN ... WHERE r.id IS NULL
     *   (c) NOT IN (SELECT masa_id ...)    <- TEHLIKELI
     *
     * (a) NOT EXISTS'in avantajlari:
     *     - NIYETI DOGRUDAN ANLATIR. "Bu masa icin cakisan bir rezervasyon
     *       YOKSA listeye al." Kodu alti ay sonra okuyan kisi (siz) tek
     *       bakista anlar.
     *     - YARI-BIRLESIM (semi-join) olarak calisir: MySQL ilk eslesen
     *       satiri bulur bulmaz o masa icin aramayi BIRAKIR. Masa 3'te o
     *       gun 40 rezervasyon olsa bile ilkini gorup durur.
     *     - SATIR COGALTMAZ. LEFT JOIN, bir masa icin birden cok cakisan
     *       kayit oldugunda ayni masayi birden cok satir olarak dondurur;
     *       IS NULL filtresi bu ornekte temizler ama benzer sorgularda
     *       DISTINCT eklemeyi unutmak klasik bir hatadir.
     *
     * (b) LEFT JOIN ... IS NULL ("anti-join") performansta genelde
     *     esdegerdir; modern MySQL/MariaDB optimizer'i ikisini de benzer
     *     plana cevirir. Tercih okunabilirlikte NOT EXISTS'e gider.
     *
     * (c) NOT IN'den KACININ: alt sorgu tek bir NULL dondurdugu anda
     *     SQL'in uc-degerli mantigi geregi TUM sonuc bos gelir
     *     ("x NOT IN (1, NULL)" asla DOGRU olamaz, UNKNOWN olur).
     *     Sessizce bos liste donduren, hata vermeyen bir sorgu en kotu
     *     hata turudur.
     *
     * ---------------------------------------------------------------------
     * 3) INDEKS KULLANIMI
     * ---------------------------------------------------------------------
     * database.sql'deki iki indeks bu sorgu icin acilmistir:
     *   idx_masa_durum_kapasite (durum, kapasite)         -> dis sorgu
     *   idx_cakisma (masa_id, tarih, durum, baslangic_saati) -> alt sorgu
     *
     * Bilesik indeks soldan saga kesintisiz kullanilir. Alt sorguda once
     * uc esitlik (masa_id, tarih, durum), sonra aralik (baslangic_saati)
     * kontrolu var; indeks sirasi da tam boyle. EXPLAIN ile dogrulayin:
     *
     *   EXPLAIN SELECT ... ;   ->  alt sorgu satirinda key: idx_cakisma
     *
     * ---------------------------------------------------------------------
     * 4) AYNI PARAMETREYI IKI KEZ KULLANMA TUZAGI
     * ---------------------------------------------------------------------
     * Asagida tarih iki yerde geciyor ama parametre adlari FARKLI
     * (:tarih ve :tarih_doluluk). Sebebi db.php'deki
     * PDO::ATTR_EMULATE_PREPARES => false ayaridir: GERCEK (sunucu tarafli)
     * prepared statement'ta bir isimli parametre SQL icinde yalnizca BIR
     * kez gecebilir. Iki kez yazarsaniz su hatayi alirsiniz:
     *
     *   SQLSTATE[HY093]: Invalid parameter number
     *
     * Taklit (emulation) modunda ayni isim tekrar kullanilabilir; bu yuzden
     * internetteki cok ornek calisir gorunur. Guvenlik icin taklidi
     * kapattigimiza gore bedeli, tekrarlanan degere ikinci bir ad vermek.
     *
     * @param string $tarih       'YYYY-AA-GG'
     * @param string $baslangic   'HH:MM'
     * @param string $bitis       'HH:MM'
     * @param int    $kisiSayisi  Masanin kapasitesi bundan kucuk olmamali
     *
     * @return array<int, array{id:int, masa_adi:string, kapasite:int,
     *                          konum:string, dolu_araliklar:?string}>
     */
    public static function musaitMasalar(
        string $tarih,
        string $baslangic,
        string $bitis,
        int $kisiSayisi
    ): array {
        $sql = "
            SELECT
                m.id,
                m.masa_adi,
                m.kapasite,
                m.konum,

                -- DOLULUK BILGISI (arayuzde 'bugun 12:00-14:00 arasi dolu'
                -- notunu gosterebilmek icin).
                --
                -- Bu, masa basina BIR kez calisan iliskili (correlated) bir
                -- alt sorgudur: m.id'yi dis sorgudan alir. GROUP_CONCAT
                -- birden cok satiri tek metne birlestirir:
                --     '12:00-14:00, 19:00-21:00'
                --
                -- Neden ikinci bir sorgu yerine boyle yaptik? Cunku 'N+1
                -- sorgu problemi'nden kacinmak istiyoruz: PHP'de masalari
                -- donup her biri icin ayri SELECT atmak, 20 masali bir
                -- kafede 1 + 20 = 21 gidis-donus demektir. Tek sorgu,
                -- veritabani ile tek konusma.
                --
                -- DIKKAT: GROUP_CONCAT'in ciktisi varsayilan olarak 1024
                -- bayt ile sinirlidir (group_concat_max_len). Gunde 80+
                -- rezervasyonlu bir masada metin sessizce kirpilir. Burada
                -- bilgi sadece ipucu amacli oldugu icin sorun degil; kritik
                -- veride GROUP_CONCAT'e guvenilmez.
                (
                    SELECT GROUP_CONCAT(
                               CONCAT(
                                   TIME_FORMAT(d.baslangic_saati, '%H:%i'),
                                   '-',
                                   TIME_FORMAT(d.bitis_saati, '%H:%i')
                               )
                               ORDER BY d.baslangic_saati
                               SEPARATOR ', '
                           )
                      FROM rezervasyonlar d
                     WHERE d.masa_id = m.id
                       AND d.tarih   = :tarih_doluluk
                       AND d.durum   = 'onaylandi'
                ) AS dolu_araliklar

              FROM masalar m

             -- 1. SART: masa hizmette olmali.
             --    Pasif masa (tadilatta, kaldirilmis) hic listelenmez.
             WHERE m.durum = 'aktif'

               -- 2. SART: kapasite yeterli olmali.
               --    '>=' kullaniyoruz: 4 kisilik masa 2 kisiye de satilir.
               --    Not: Bu sorgu ISTENEN kapasiteden buyuk masalari da
               --    dondurur ve ORDER BY ile en kucugu one alir; boylece
               --    2 kisi 10'luk masayi kapatip kafeyi zarara sokmaz,
               --    ama 10'luk masa yine de secilebilir durumda kalir.
               AND m.kapasite >= :kisi_sayisi

               -- 3. SART: istenen aralikta ONAYLI rezervasyonu olmamali.
               AND NOT EXISTS (
                     SELECT 1                 -- deger onemli degil, VARLIK onemli.
                                              -- 'SELECT *' yazmak da calisir ama
                                              -- niyeti bulandirir; MySQL her iki
                                              -- durumda da kolon okumaz.
                       FROM rezervasyonlar r
                      WHERE r.masa_id = m.id  -- iliski: bu masanin kayitlari

                        AND r.tarih   = :tarih

                        -- SADECE 'onaylandi' masayi bloklar.
                        -- 'iptal'      -> musteri vazgecti, masa yeniden satilir.
                        -- 'tamamlandi' -> misafir geldi, yedi, gitti; gecmis kayit.
                        -- Bu filtreyi unutmak en sinsi hatadir: sistem calisir
                        -- gorunur, sadece masalar sebepsiz 'dolu' cikar ve
                        -- kimse neden oldugunu anlamaz.
                        AND r.durum   = 'onaylandi'

                        -- *** CAKISMA KOSULU ***
                        -- mevcut.baslangic < yeni.bitis  VE
                        -- mevcut.bitis     > yeni.baslangic
                        AND r.baslangic_saati < :bitis_saati
                        AND r.bitis_saati     > :baslangic_saati
                   )

             -- Once en kucuk uygun masa: 2 kisilik grup once 2'lik masayi
             -- gorsun, 6'lik masa buyuk gruplara kalsin. Bu bir IS KURALI
             -- (yield management), teknik zorunluluk degil.
             ORDER BY m.kapasite ASC, m.masa_adi ASC
        ";

        return Database::fetchAll($sql, [
            ':tarih_doluluk'   => $tarih,
            ':kisi_sayisi'     => $kisiSayisi,
            ':tarih'           => $tarih,
            ':bitis_saati'     => $bitis,
            ':baslangic_saati' => $baslangic,
        ]);
    }

    /**
     * Bir masanin belirli gundeki ONAYLI rezervasyon araliklari.
     *
     * musaitMasalar() zaten 'dolu_araliklar' alanini metin olarak
     * donduruyor; bu metot ayni bilgiyi YAPISAL dizi olarak verir.
     * Metin bir arayuz kolayligi, dizi ise uzerinde hesap yapilabilen veri:
     * Adim 4'teki admin gun planlamasi bunu kullanacak.
     *
     * Ders notu: "ayni veriyi iki bicimde dondurmek tekrar mi?" Hayir -
     * biri gorunum (presentation), digeri veri (data). Gorunum metnini
     * parse edip is mantigi kurmak (explode(', ') gibi) kirilgan bir
     * yaklasimdir; ayri metot yazmak dogrusudur.
     */
    public static function doluAraliklar(int $masaId, string $tarih): array
    {
        return Database::fetchAll(
            "SELECT baslangic_saati, bitis_saati, kisi_sayisi
               FROM rezervasyonlar
              WHERE masa_id = :masa_id
                AND tarih   = :tarih
                AND durum   = 'onaylandi'
              ORDER BY baslangic_saati",
            [':masa_id' => $masaId, ':tarih' => $tarih]
        );
    }

    /**
     * Sadece aktif masalarin en buyuk kapasitesi.
     *
     * Formda kisi sayisi <select>'ini bu degerle sinirlamak icin kullanilir:
     * en buyuk masa 6 kisilikse 10 kisi secenegini gostermek, kullaniciyi
     * "hicbir masa bulunamadi" ekranina goturmekten baska ise yaramaz.
     * Iyi arayuz, imkansiz secimi bastan sunmaz.
     *
     * COALESCE: tabloda hic aktif masa yoksa MAX(kapasite) NULL doner;
     * NULL'u MIN_KISI_SAYISI'na cevirip cagiran tarafi null kontrolunden
     * kurtariyoruz.
     */
    public static function maksimumKapasite(): int
    {
        $deger = Database::fetchValue(
            "SELECT COALESCE(MAX(kapasite), :varsayilan)
               FROM masalar
              WHERE durum = 'aktif'",
            [':varsayilan' => MIN_KISI_SAYISI]
        );

        return (int) $deger;
    }
}
