<?php
/**
 * =====================================================================
 *  KAFE MASA REZERVASYON SISTEMI - REZERVASYON DEPOSU (REPOSITORY)
 * ---------------------------------------------------------------------
 *  MasaRepository "hangi masalar var, hangileri musait?" sorusuna cevap
 *  verir. Bu sinif ise bir adim otesini yapar: REZERVASYON YAZAR.
 *
 *  Okumak ile yazmak arasindaki fark, bu dosyanin tamaminin sebebidir:
 *
 *      Yanlis OKUMA  -> kullanici hatali bir liste gorur, sayfayi
 *                       yeniler, duzelir.
 *      Yanlis YAZMA  -> ayni masa iki musteriye satilir. Aksam saat
 *                       19:00'da iki aile ayni masanin basinda durur ve
 *                       bunu geri almanin teknik bir yolu yoktur.
 *
 *  Bu yuzden asagidaki olustur() metodu, projenin en uzun yorumunu
 *  tasiyan yeridir: "once kontrol et, sonra yaz" yaklasimi TEK BASINA
 *  YETMEZ ve neden yetmedigini adim adim anlatiyoruz.
 *
 *  MasaRepository ile ayni tasarim kararlari gecerlidir:
 *    - Tum SQL burada toplanir; index.php veya admin sayfalari tek satir
 *      SQL yazmaz.
 *    - Metotlar static: sinifin saklayacagi bir durum (state) yok.
 *    - Metotlar ya dizi ya null doner; asla echo/die yapmaz. Kullaniciya
 *      ne gosterilecegi arayuzun (index.php) karari, deponun degil.
 *
 *  DONUS SOZLESMESI
 *  Yazma islemleri (olustur, iptalEt) istisna firlatmak yerine
 *  ['basarili' => bool, ..., 'hata' => ?string] seklinde dizi doner.
 *  Neden? Cunku "bu saat dolu" bir PROGRAM HATASI degil, normal bir is
 *  sonucudur. Istisna, beklenmeyen durumlar icindir (baglanti koptu,
 *  tablo yok). Normal is akisini istisna ile yonetmek, cagiran tarafi
 *  her cagriyi try/catch'e sarmaya zorlar ve gercek hatalari gizler.
 * =====================================================================
 */

if (!defined('APP_INIT')) {
    http_response_code(403);
    die('Doğrudan erişim engellendi.');
}

final class RezervasyonRepository
{
    /**
     * Rezervasyon kodu cakisirsa en fazla kac kez yeni kod denenecek.
     * Neden 5? Bir cakismanin olasiligi zaten milyarda birken, iki kez
     * ust uste cakisma pratikte imkansizdir. 5 rakami "sonsuz dongude
     * kilitlenme" riskini kesin olarak ortadan kaldirmak icindir; sinirsiz
     * bir while(true) dongusu, veritabani baska bir sebeple surekli 23000
     * dondurdugunde PHP surecini bitirene kadar doner.
     */
    private const KOD_DENEME_SINIRI = 5;

    /**
     * MySQL'in "yinelenen anahtar" (duplicate entry) hata kodu.
     * SQLSTATE 23000 "butunluk kisiti ihlali" demektir ve COK SEYI kapsar:
     * yabanci anahtar hatasi, NOT NULL ihlali, CHECK ihlali... Sadece
     * SQLSTATE'e bakip yeniden denemek, ornegin var olmayan bir masa_id
     * yuzunden patlayan bir INSERT'i de 5 kez tekrarlamak demek olurdu.
     * Bu yuzden surucu seviyesindeki 1062 kodunu de kontrol ediyoruz.
     */
    private const HATA_YINELENEN_ANAHTAR = 1062;


    // =================================================================
    //  1) CAKISMA KONTROLU
    // =================================================================

    /**
     * Belirtilen masa/tarih/saat araliginda ONAYLI bir rezervasyon var mi?
     *
     * Kosul, database.sql bolum 6 ve MasaRepository::musaitMasalar() ile
     * BIREBIR AYNIDIR:
     *
     *     mevcut.baslangic_saati < yeni.bitis
     *     mevcut.bitis_saati     > yeni.baslangic
     *
     * Ucuncu bir yerde ayni mantigin uceuncu bir kopyasini olusturmadigimiza
     * dikkat edin: musaitMasalar() LISTE uretir (hangi masalar bos?), bu
     * metot ise TEK MASA icin evet/hayir cevabi verir. Ayni kosulun iki
     * farkli soruya hizmet eden iki kullanimi var; kopya degil.
     *
     * ---------------------------------------------------------------------
     * NEDEN "SELECT 1 ... LIMIT 1", NEDEN "SELECT COUNT(*)" DEGIL?
     * COUNT(*) butun eslesen satirlari saymak zorundadir. Biz ise "en az
     * bir tane var mi?" diye soruyoruz; ilk satiri gorur gormez cevap
     * bellidir. LIMIT 1, MySQL'e "bulunca dur" der. Kucuk tabloda fark
     * olculemez ama niyeti dogru anlatan sorgu yazmak bir aliskanliktir.
     *
     * ---------------------------------------------------------------------
     * $haricId NE ISE YARAR?
     * Adim 4'te admin bir rezervasyonu DUZENLEYECEK. Diyelim 14:00-16:00
     * olan kayit 14:30-16:30 yapilacak. Cakisma kontrolu yapilirsa kayit
     * KENDISIYLE cakisir ve "bu saat dolu" hatasi alinir - musteri kendi
     * rezervasyonunu 30 dakika oteleyemez. $haricId ile "su id haric" deyip
     * kaydin kendisini kontrol disinda birakiyoruz.
     *
     * ---------------------------------------------------------------------
     * BU METOT TEK BASINA YETERLI DEGILDIR.
     * Donen "false" degeri sadece "SORGU ANINDA bos idi" demektir. Bir
     * milisaniye sonrasi icin hicbir sey soylemez. Gercek koruma
     * olustur() icindeki transaction + satir kilidi ile saglanir; ayrintili
     * aciklama orada. Bu metodu arayuzde "on kontrol" olarak kullanin
     * (kullaniciya erken ve anlasilir bir uyari gostermek icin), son karar
     * mercii olarak degil.
     */
    public static function cakismaVarMi(
        int $masaId,
        string $tarih,
        string $baslangic,
        string $bitis,
        ?int $haricId = null
    ): bool {
        $sql = "SELECT 1
                  FROM rezervasyonlar
                 WHERE masa_id = :masa_id
                   AND tarih   = :tarih
                   AND durum   = 'onaylandi'
                   AND baslangic_saati < :bitis
                   AND bitis_saati     > :baslangic";

        $parametreler = [
            ':masa_id'   => $masaId,
            ':tarih'     => $tarih,
            ':bitis'     => $bitis,
            ':baslangic' => $baslangic,
        ];

        // SQL metnini kosullu olarak buyutuyoruz. Dikkat: SQL'e eklenen
        // sey SABIT bir metin parcasi; kullanicidan gelen deger yine
        // parametre olarak gidiyor. "SQL'i string birlestirerek kurma"
        // yasagi DEGERLER icindir, sorgu iskeleti icin degil.
        if ($haricId !== null) {
            $sql .= ' AND id <> :haric';
            $parametreler[':haric'] = $haricId;
        }

        $sql .= ' LIMIT 1';

        return Database::fetchValue($sql, $parametreler) !== null;
    }


    // =================================================================
    //  2) REZERVASYON OLUSTURMA - PROJENIN EN KRITIK METODU
    // =================================================================

    /**
     * Yeni rezervasyon kaydi olusturur.
     *
     * @param array $veri Beklenen anahtarlar:
     *        masa_id, musteri_adi, musteri_telefon, musteri_email,
     *        tarih, baslangic_saati, bitis_saati, kisi_sayisi, musteri_notu
     *
     * @return array{basarili:bool, kod:?string, id:?int, hata:?string}
     *
     * =====================================================================
     *  YARIS KOSULU (RACE CONDITION) - BU METODUN VAR OLMA SEBEBI
     * =====================================================================
     *
     *  Akla ilk gelen, "dogru gorunen" ve YANLIS olan cozum sudur:
     *
     *      if (!self::cakismaVarMi($masaId, $tarih, $bas, $bit)) {
     *          Database::execute('INSERT INTO rezervasyonlar ...');
     *      }
     *
     *  Bu kod tek kullanicili testte kusursuz calisir. Sorun, bir web
     *  sunucusunun AYNI ANDA birden fazla istegi islemesidir. Apache her
     *  istek icin ayri bir surec/is parcacigi acar; PHP kodunuz ayni anda
     *  10 kopya halinde calisiyor olabilir.
     *
     *  --------------------------------------------------------------
     *  ZAMAN CIZELGESI: Ayse ve Mehmet ayni anda Masa 3'e 19:00-21:00
     *  --------------------------------------------------------------
     *
     *   t   AYSE'nin istegi                 MEHMET'in istegi
     *  ---  ----------------------------    ----------------------------
     *   1   SELECT ... cakisma var mi?
     *   2                                   SELECT ... cakisma var mi?
     *   3   sonuc: 0 satir (bos)
     *   4                                   sonuc: 0 satir (bos)
     *   5   if (!cakisma) -> DOGRU
     *   6                                   if (!cakisma) -> DOGRU
     *   7   INSERT ... (basarili)
     *   8                                   INSERT ... (basarili)
     *   9   "Rezervasyonunuz alindi"
     *  10                                   "Rezervasyonunuz alindi"
     *
     *  Iki kayit da yazildi. Iki musteriye de "tamam" dendi. Kodda tek
     *  satir hata yok; mantik hatasi da yok. Hata, "kontrol" ile "yazma"
     *  arasinda ACIK BIR PENCERE birakmis olmakta.
     *
     *  Bu pencere ne kadar kucuk? Tipik olarak 1-5 milisaniye. Gunde 20
     *  rezervasyon alan bir kafede yillarca hic olmayabilir. Sevgililer
     *  Gunu icin rezervasyonlarin acildigi dakikada ise kesin olur. Yaris
     *  kosullarinin en sinsi tarafi budur: TEST ORTAMINDA ASLA GORUNMEZ.
     *
     *  --------------------------------------------------------------
     *  COZUM: TRANSACTION + SATIR KILIDI (SELECT ... FOR UPDATE)
     *  --------------------------------------------------------------
     *  Transaction tek basina yetmez! Bircok kisinin sandiginin aksine
     *  BEGIN ... COMMIT sarmalamak yukaridaki cizelgeyi degistirmez:
     *  varsayilan REPEATABLE READ seviyesinde SELECT sorgulari birbirini
     *  BEKLETMEZ, ikisi de rahatca "bos" cevabini alir. Transaction'in
     *  verdigi garanti "ya hep ya hic" (atomiklik) ve geri alinabilirliktir;
     *  "sirayla calis" garantisi DEGILDIR.
     *
     *  Eksik olan parca KILIT'tir. SELECT'in sonuna FOR UPDATE eklendiginde
     *  InnoDB, okudugu satirlar uzerine YAZMA KILIDI (exclusive lock) koyar:
     *
     *    - Ayni satiri FOR UPDATE ile okumak isteyen ikinci transaction,
     *      birincisi COMMIT veya ROLLBACK yapana kadar BEKLER (bloke olur).
     *    - Kilit, ifade bitince degil TRANSACTION BITINCE birakilir. Bu
     *      cok onemli: kilidi 1. adimda alip 3. adimda INSERT yapana kadar
     *      elimizde tutabilmemizin sebebi budur.
     *    - Bekleme sonsuz degildir: innodb_lock_wait_timeout (varsayilan
     *      50 saniye) dolarsa bekleyen taraf hata alir.
     *
     *  Yeni zaman cizelgesi:
     *
     *   t   AYSE                              MEHMET
     *  ---  ------------------------------    ------------------------------
     *   1   BEGIN
     *   2   SELECT masalar WHERE id=3
     *       FOR UPDATE            [KILIT ALDI]
     *   3                                     BEGIN
     *   4                                     SELECT masalar WHERE id=3
     *                                         FOR UPDATE   ... B E K L I Y O R
     *   5   cakisma kontrolu -> bos
     *   6   INSERT ...
     *   7   COMMIT              [KILIT BIRAKILDI]
     *   8                                     ...uyandi, kilidi aldi
     *   9                                     cakisma kontrolu -> DOLU!
     *  10                                     COMMIT (hicbir sey yazmadan)
     *  11                                     "Bu saat az once dolduruldu"
     *
     *  Artik iki istek ayni masa icin SIRAYA girmis durumda.
     *
     *  --------------------------------------------------------------
     *  KILIDI NEREDEN ALMALI? (bu metodun en ince karari)
     *  --------------------------------------------------------------
     *  Iki secenek var:
     *
     *  (A) Kilidi dogrudan "cakisan rezervasyonlar" sorgusundan almak:
     *
     *        SELECT id FROM rezervasyonlar
     *         WHERE masa_id=3 AND tarih=... AND durum='onaylandi'
     *           AND baslangic_saati < ... AND bitis_saati > ...
     *         FOR UPDATE
     *
     *      Buradaki problem soyle: bu sorgu tam da istedigimiz durumda,
     *      yani masa BOSKEN, SIFIR SATIR dondurur. Var olmayan satira
     *      kilit konamaz. InnoDB bu durumda satir yerine BOSLUGU (gap)
     *      kilitler: indeks uzerinde "su iki degerin arasina kimse satir
     *      EKLEYEMESIN" der (gap lock / next-key lock). Teoride bizi korur,
     *      ama uc ciddi belirsizligi vardir:
     *        1. Gap kilitleri IZOLASYON SEVIYESINE baglidir. REPEATABLE
     *           READ'de calisir; READ COMMITTED'de InnoDB gap kilitlerini
     *           buyuk olcude devre disi birakir ve bos sonuc kumesi
     *           HICBIR SEYI kilitlemez. Yani korumaniz, kimsenin
     *           haberi olmadan degistirebilecegi bir sunucu ayarina
     *           bagimli hale gelir.
     *        2. Hangi araligin kilitlendigi, sorgunun hangi INDEKSI
     *           kullandigina gore degisir. Sorgu plani degisirse (tablo
     *           buyudu, indeks eklendi) kilit davranisi da sessizce
     *           degisir.
     *        3. Gap kilitleri genis ve orustusen araliklar urettigi icin
     *           KILITLENME (deadlock) uretmeye en yatkin kilit turudur.
     *
     *  (B) Kilidi, KESINLIKLE VAR OLAN bir satirdan almak: masalar tablosu.
     *
     *        SELECT id FROM masalar WHERE id = :masa_id FOR UPDATE
     *
     *      Bu satir her zaman vardir (yoksa zaten gecerli bir rezervasyon
     *      denemesi degildir). Dolayisiyla gercek bir SATIR KILIDI aliriz:
     *        - Izolasyon seviyesinden bagimsiz olarak ayni davranir.
     *        - Tek bir satir, tek bir kilit: kapsami net, deadlock riski
     *          minimum.
     *        - Kilit "masa 3" tanesine ozeldir; masa 4'e rezervasyon
     *          yapan baska bir istek hic beklemez. Yani tum tabloyu
     *          kilitlemek gibi bir performans cezasi yok.
     *      Bu desene "mutex satiri" ya da "kilit sahibi kaynak" denir:
     *      yaristigimiz kaynak MASANIN KENDISI oldugu icin, kilidi de
     *      masanin satirindan almak kavramsal olarak da dogrudur.
     *
     *  BIZ (B)'yi SECTIK. Ayrica cakisma sorgusunu da FOR UPDATE ile
     *  calistiriyoruz; sebebi kilit almak degil, TAZE VERI OKUMAK:
     *  REPEATABLE READ'de siradan bir SELECT, transaction'in ilk okumasinda
     *  olusan ANLIK GORUNTUYU (snapshot) okur ve az once commit edilmis
     *  satiri gormeyebilir. Kilitli okuma (locking read) ise her zaman en
     *  son commit edilmis veriyi okur. Iki satir maliyetiyle bu belirsizligi
     *  tamamen ortadan kaldiriyoruz.
     *
     *  --------------------------------------------------------------
     *  VE EN ONEMLI CUMLE: BU KOD SON SAVUNMA DEGILDIR
     *  --------------------------------------------------------------
     *  Uygulama seviyesindeki her kontrol, uygulamanin kendisi kadar
     *  guvenilirdir. Yarin:
     *      - admin paneli icin ikinci bir INSERT yolu yazilir,
     *      - bir cron betigi toplu kayit atar,
     *      - stajyer phpMyAdmin'den elle satir ekler,
     *      - uygulama iki sunucuda birden calistirilir
     *  ve bu metottaki kilit hicbirini baglamaz. Gercek ve nihai garanti
     *  VERITABANI KISITLARINDAN gelir; database.sql'de zaten var olanlar:
     *      - uq_rezervasyon_kodu UNIQUE  -> ayni kod iki kez yazilamaz
     *      - fk_rezervasyon_masa FOREIGN KEY -> olmayan masaya kayit olmaz
     *      - chk_saat_sirasi CHECK       -> bitis > baslangic
     *  "Saat araliklari cakismasin" kurali ne yazik ki MySQL'de tek bir
     *  kisitla ifade edilemez (PostgreSQL'de EXCLUDE USING gist ile
     *  dogrudan yazilabilir). Bu yuzden burada uygulama katmaninda
     *  kilitle cozuyoruz - ama bunun bir TERCIH degil ZORUNLULUK oldugunu,
     *  ve kural veritabaninda yazili olmadigi surece her yeni yazma
     *  yolunun ayni kilidi tekrar kurmak zorunda kaldigini bilerek.
     */
    public static function olustur(array $veri): array
    {
        // -------------------------------------------------------------
        // ADIM 1 - Girdileri normalize et
        // -------------------------------------------------------------
        // Normalizasyon dogrulamadan ONCE gelir. Aksi halde "0555 111 22 33"
        // telefonu "10 hane degil" diye reddedilir; oysa kullanicinin yazim
        // bicimi yanlis degil, sadece farkli.
        $masaId    = (int) ($veri['masa_id'] ?? 0);
        $ad        = trim((string) ($veri['musteri_adi'] ?? ''));
        $telefon   = telefon_normalize((string) ($veri['musteri_telefon'] ?? ''));
        $email     = trim((string) ($veri['musteri_email'] ?? ''));
        $tarih     = trim((string) ($veri['tarih'] ?? ''));
        $kisi      = (int) ($veri['kisi_sayisi'] ?? 0);
        $not       = trim((string) ($veri['musteri_notu'] ?? ''));

        // MySQL TIME kolonu '19:00:00' dondurur; forma geri basilip tekrar
        // gonderildiginde saat 8 karakter gelebilir. Ilk 5 karakteri alarak
        // her iki bicimi de ayni noktada bulusturuyoruz.
        $baslangic = substr(trim((string) ($veri['baslangic_saati'] ?? '')), 0, 5);
        $bitis     = substr(trim((string) ($veri['bitis_saati'] ?? '')), 0, 5);

        // Bos metin yerine NULL yaziyoruz. Kolonlar NULL kabul ediyor ve
        // "deger girilmedi" ile "bos metin girildi" farkli seylerdir:
        // ileride "e-postasi olanlara hatirlatma gonder" sorgusu
        // "WHERE musteri_email IS NOT NULL" ile dogru calissin diye.
        $emailDeger = $email === '' ? null : $email;
        $notDeger   = $not === '' ? null : $not;

        // -------------------------------------------------------------
        // ADIM 2 - Dogrulama
        // -------------------------------------------------------------
        // Buradaki kontroller index.php'deki form dogrulamasinin TEKRARIDIR
        // ve bu bilincli bir tekrardir: bu sinif yarin bir cron betiginden
        // ya da admin panelinden de cagrilacak. "Cagiran taraf dogrulamistir"
        // varsayimi, dogrulamayi hic yapmamakla aynidir.
        $hata = self::girdiDogrula($ad, $telefon, $emailDeger, $tarih, $baslangic, $bitis, $kisi, $notDeger);
        if ($hata !== null) {
            return self::basarisiz($hata);
        }

        // -------------------------------------------------------------
        // ADIM 3 - Kod uret, yaz; kod cakisirsa yeniden dene
        // -------------------------------------------------------------
        // Dongu transaction'in DISINDA. Neden? Cunku yinelenen anahtar
        // hatasi alan bir transaction'i icerden "kurtarmaya" calismak
        // kirilgandir: InnoDB bazi hatalarda sadece ifadeyi, bazilarinda
        // tum transaction'i geri alir. Her denemeyi TEMIZ bir transaction
        // olarak baslatmak hem daha basit hem daha dogrudur. Bedeli,
        // milyarda bir ihtimalle cakisma sorgusunu ikinci kez calistirmak.
        for ($deneme = 1; $deneme <= self::KOD_DENEME_SINIRI; $deneme++) {

            $kod = rezervasyon_kodu_uret();

            try {
                // Database::transaction() callback icinde istisna cikarsa
                // otomatik rollback yapar, cikmazsa commit eder.
                // ONEMLI: callback icinde Database::fetchOne / execute
                // cagirmak guvenlidir, cunku Database tekil (singleton) bir
                // PDO nesnesi tutar - yani ayni baglanti, ayni transaction.
                // Ayri bir baglanti acilsaydi kilitleri GORMEZ, hatta kendi
                // kilidimizi bekleyerek kendi kendimizi kilitlerdik.
                $sonuc = Database::transaction(static function () use (
                    $kod, $masaId, $ad, $telefon, $emailDeger,
                    $tarih, $baslangic, $bitis, $kisi, $notDeger
                ): array {

                    // --- 3.a) KILIT: masanin satirini kapat ---------------
                    // Bu satirdan itibaren, AYNI MASA icin calisan diger
                    // her istek bu noktada bekler. Transaction commit veya
                    // rollback olana kadar kilit bizde kalir.
                    //
                    // Kilidi almakla birlikte masanin GUNCEL halini de
                    // okuyoruz (durum, kapasite). Bu da bir kazanc: masa
                    // tam bu sirada 'pasif' yapildiysa taze veriyi goruruz.
                    $masa = Database::fetchOne(
                        'SELECT id, masa_adi, kapasite, durum
                           FROM masalar
                          WHERE id = :id
                          FOR UPDATE',
                        [':id' => $masaId]
                    );

                    // Masa yoksa kilit de alinamamistir; ama zaten yazacak
                    // bir sey yok. Erken cikiyoruz.
                    if ($masa === null) {
                        return ['id' => null, 'hata' => 'Seçilen masa bulunamadı.'];
                    }

                    if ($masa['durum'] !== 'aktif') {
                        return ['id' => null, 'hata' => 'Seçilen masa şu anda rezervasyona kapalı.'];
                    }

                    if ((int) $masa['kapasite'] < $kisi) {
                        return [
                            'id'   => null,
                            'hata' => sprintf(
                                '%s en fazla %d kişiliktir; %d kişi için uygun değil.',
                                $masa['masa_adi'],
                                (int) $masa['kapasite'],
                                $kisi
                            ),
                        ];
                    }

                    // --- 3.b) CAKISMA KONTROLU (kilit altinda) -----------
                    // Ayni kosulu cakismaVarMi() ile de yazabilirdik; burada
                    // FOR UPDATE ekleyebilmek icin sorguyu acik yaziyoruz.
                    // FOR UPDATE'in buradaki asil isi kilit almak degil,
                    // KILITLI OKUMA yapmaktir: REPEATABLE READ'de siradan
                    // SELECT anlik goruntuden okur ve az once commit edilmis
                    // satiri kacirabilir; kilitli okuma her zaman en guncel
                    // commit edilmis veriyi gorur.
                    $cakisan = Database::fetchOne(
                        "SELECT id, baslangic_saati, bitis_saati
                           FROM rezervasyonlar
                          WHERE masa_id = :masa_id
                            AND tarih   = :tarih
                            AND durum   = 'onaylandi'
                            AND baslangic_saati < :bitis
                            AND bitis_saati     > :baslangic
                          LIMIT 1
                          FOR UPDATE",
                        [
                            ':masa_id'   => $masaId,
                            ':tarih'     => $tarih,
                            ':bitis'     => $bitis,
                            ':baslangic' => $baslangic,
                        ]
                    );

                    if ($cakisan !== null) {
                        return [
                            'id'   => null,
                            'hata' => sprintf(
                                '%s bu tarihte %s-%s arasında dolu. Lütfen başka bir saat veya masa seçin.',
                                $masa['masa_adi'],
                                saat_goster((string) $cakisan['baslangic_saati']),
                                saat_goster((string) $cakisan['bitis_saati'])
                            ),
                        ];
                    }

                    // --- 3.c) YAZMA --------------------------------------
                    // durum kolonunu yazmiyoruz: varsayilani 'onaylandi'.
                    // created_at / updated_at de veritabani tarafindan
                    // dolduruluyor. Varsayilani olan kolonu elle yazmamak,
                    // "tek dogru kaynak" ilkesinin kucuk bir uygulamasidir.
                    Database::execute(
                        'INSERT INTO rezervasyonlar
                             (rezervasyon_kodu, masa_id, musteri_adi, musteri_telefon,
                              musteri_email, tarih, baslangic_saati, bitis_saati,
                              kisi_sayisi, musteri_notu)
                         VALUES
                             (:kod, :masa_id, :ad, :telefon,
                              :email, :tarih, :baslangic, :bitis,
                              :kisi, :not)',
                        [
                            ':kod'       => $kod,
                            ':masa_id'   => $masaId,
                            ':ad'        => $ad,
                            ':telefon'   => $telefon,
                            ':email'     => $emailDeger,
                            ':tarih'     => $tarih,
                            ':baslangic' => $baslangic,
                            ':bitis'     => $bitis,
                            ':kisi'      => $kisi,
                            ':not'       => $notDeger,
                        ]
                    );

                    return ['id' => Database::sonId(), 'hata' => null];
                });
            } catch (PDOException $e) {
                // Yinelenen rezervasyon kodu mu? Oyleyse yeni kod uretip
                // dongunun basina donuyoruz. Baska her hata (baglanti
                // koptu, kolon yok, yabanci anahtar ihlali) YUKARI FIRLAR:
                // bunlari yutmak, hatayi gizleyip sistemi anlasilmaz hale
                // getirmek olurdu.
                if (!self::kodCakismasiMi($e)) {
                    throw $e;
                }

                // Loga dusurelim: gercekten olursa bu satir cok kiymetli
                // bir kanittir (ve normalde asla gorulmemelidir).
                error_log("[Rezervasyon] Kod cakismasi ({$kod}), deneme {$deneme}.");
                continue;
            }

            if ($sonuc['hata'] !== null) {
                return self::basarisiz($sonuc['hata']);
            }

            return [
                'basarili' => true,
                'kod'      => $kod,
                'id'       => (int) $sonuc['id'],
                'hata'     => null,
            ];
        }

        // Buraya ulasmak, 5 kez ust uste kod cakismasi demektir. 32^8
        // olasilik icinde bunun gerceklesmesi, random_bytes() bozulmadikca
        // imkansizdir. Yine de sessizce basarili donmek yerine durumu
        // acikca bildiriyoruz.
        error_log('[Rezervasyon] Benzersiz kod üretilemedi; ' . self::KOD_DENEME_SINIRI . ' deneme tukendi.');

        return self::basarisiz('Rezervasyon kodu üretilemedi. Lütfen tekrar deneyin.');
    }


    // =================================================================
    //  3) SORGULAMA
    // =================================================================

    /**
     * Rezervasyon kodu ile tek kayit getirir; yoksa null.
     *
     * NEDEN ID ILE DEGIL, KOD ILE?
     * URL'de id kullansaydik (rezervasyon-sorgula.php?id=42) musteri
     * adres cubugunda 41 yazip BASKASININ rezervasyonunu gorurdu. Buna
     * IDOR (Insecure Direct Object Reference) denir ve web'deki en yaygin
     * yetkilendirme acigidir. Rastgele 8 karakterlik kod, 32^8 = 1.1
     * trilyon olasilik icinde tahmin edilemez oldugu icin ayni zamanda
     * basit bir "yetki belgesi" gorevi gorur.
     *
     * Kod BUYUK harfe cevriliyor: musteri e-postadan kopyalarken ya da
     * elle yazarken kucuk harf kullanabilir. Kolon utf8mb4_unicode_ci
     * harmanlamasi (collation) kullandigi icin MySQL zaten buyuk/kucuk
     * harf ayrimi yapmaz; ama PHP tarafinda da normalize etmek, ileride
     * harmanlama '_bin' olarak degistirilirse davranisin bozulmamasini
     * saglar. Kurala guvenmek yerine kurali kendimiz uygulamak.
     *
     * JOIN neden gerekli? Musteriye "Masa 3" demek yetmez; "Bahce 1,
     * dis mekan, 4 kisilik" bilgisini de gostermek isteriz. Bu bilgiyi
     * ayri bir MasaRepository::bul() cagrisiyla da alabilirdik, ama o
     * zaman her rezervasyon icin ikinci bir gidis-donus olurdu (N+1
     * sorgu problemi). INNER JOIN kullaniyoruz cunku fk_rezervasyon_masa
     * yabanci anahtari sayesinde masasi olmayan bir rezervasyon
     * VAR OLAMAZ; LEFT JOIN yazmak, imkansiz bir durumu varsayip sorguyu
     * gereksiz yere karmasiklastirmak olurdu.
     */
    public static function koduIleBul(string $kod): ?array
    {
        $kod = strtoupper(trim($kod));

        if ($kod === '') {
            return null;
        }

        return Database::fetchOne(
            'SELECT r.id, r.rezervasyon_kodu, r.masa_id, r.musteri_adi,
                    r.musteri_telefon, r.musteri_email, r.tarih,
                    r.baslangic_saati, r.bitis_saati, r.kisi_sayisi,
                    r.durum, r.musteri_notu, r.created_at, r.updated_at,
                    m.masa_adi, m.konum, m.kapasite
               FROM rezervasyonlar r
               INNER JOIN masalar m ON m.id = r.masa_id
              WHERE r.rezervasyon_kodu = :kod',
            [':kod' => $kod]
        );
    }

    /**
     * Bir telefon numarasina ait tum rezervasyonlar (en yeni tarih basta).
     *
     * Numara telefon_normalize() ile kanonik bicime cevrilir. Bu sart:
     * kayit sirasinda '5551112233' yazdik; musteri sorgularken
     * '0555 111 22 33' yazacak. Normalize etmezsek metin karsilastirmasi
     * tutmaz ve musteri "rezervasyonunuz bulunamadi" gorur.
     *
     * ---------------------------------------------------------------------
     * GUVENLIK NOTU (bilerek kabul edilen bir zayiflik)
     * Telefon numarasi 8 karakterlik rastgele kod kadar gizli degildir;
     * tahmin edilebilir. Yani biri baskasinin numarasini girip onun
     * rezervasyonlarini LISTELEYEBILIR. Bu yuzden bu metodun dondurdugu
     * kayitlar uzerinde IPTAL gibi yikici bir islem yapilmamalidir; iptal
     * icin kod zorunludur (bkz. iptalEt). Gercek bir sistemde bu ekran
     * SMS dogrulama kodu arkasina alinirdi. Odev kapsaminda kolaylik
     * ugruna acik biraktigimizi BILEREK yapiyoruz - fark etmeden birakmak
     * ile bilerek birakmak arasindaki fark, muhendislik ile sanstir.
     *
     * ORDER BY neden iki kolon? Sadece "tarih DESC" yazmak ayni gundeki
     * kayitlarin sirasini belirsiz birakir (bkz. MasaRepository::tumu
     * yorumu). Saat de eklenerek sira tamamen belirli hale getirilir.
     */
    public static function telefonIleBul(string $telefon): array
    {
        $normal = telefon_normalize($telefon);

        // Bos veya anlamsiz girdi icin veritabanina hic gitmiyoruz.
        // Bos metinle sorgu atmak butun kayitlari degil hicbirini dondururdu
        // ama bu yine de bosa giden bir gidis-donustur.
        if ($normal === '') {
            return [];
        }

        return Database::fetchAll(
            'SELECT r.id, r.rezervasyon_kodu, r.masa_id, r.musteri_adi,
                    r.musteri_telefon, r.musteri_email, r.tarih,
                    r.baslangic_saati, r.bitis_saati, r.kisi_sayisi,
                    r.durum, r.musteri_notu, r.created_at,
                    m.masa_adi, m.konum, m.kapasite
               FROM rezervasyonlar r
               INNER JOIN masalar m ON m.id = r.masa_id
              WHERE r.musteri_telefon = :telefon
              ORDER BY r.tarih DESC, r.baslangic_saati DESC',
            [':telefon' => $normal]
        );
    }


    // =================================================================
    //  4) IPTAL VE DURUM DEGISIKLIGI
    // =================================================================

    /**
     * Musterinin kendi rezervasyonunu iptal etmesi.
     *
     * @return array{basarili:bool, hata:?string}
     *
     * IS KURALLARI ve her biri icin AYRI mesaj:
     *   1. Kod bulunamadi           -> yanlis kod yazilmis olabilir.
     *   2. Zaten iptal edilmis      -> islem tekrarlandi (F5'e basildi).
     *   3. Tamamlanmis              -> misafir geldi gitti; gecmisi
     *                                  degistirmek muhasebeyi bozar.
     *   4. Gecmis tarihli           -> iptal edilecek bir sey kalmamis.
     *
     * NEDEN HER RET ICIN AYRI MESAJ?
     * Tek bir "Iptal edilemedi" mesaji kullaniciyi cikmaza sokar: ne
     * yapmasi gerektigini bilemez, telefonla kafeyi arar. Mesajin isi
     * kullaniciyi BIR SONRAKI ADIMA yoneltmektir.
     * (Karsi ornek: giris ekraninda "kullanici adi yanlis" ile "sifre
     * yanlis" mesajlarini AYIRMAK yanlistir, cunku saldirgana hangi
     * kullanici adlarinin var oldugunu soyler. Kural "her zaman ayrintili
     * mesaj ver" degil; "mesajin sizdirdigi bilgiyi dusun"dur. Burada
     * kullanici zaten kodu biliyor, yani kaydin sahibi sayiliyor.)
     *
     * NEDEN "sadece kodu bilen iptal edebilir"?
     * Kod, bu ekranda parola yerine geciyor. Bu yuzden helpers.php'de
     * kodu random_bytes ile urettik: mt_rand veya uniqid ile uretilseydi,
     * kendi kodunu bilen biri komsu kodlari hesaplayip baskalarinin
     * rezervasyonlarini iptal edebilirdi.
     */
    public static function iptalEt(string $kod): array
    {
        $rezervasyon = self::koduIleBul($kod);

        if ($rezervasyon === null) {
            return ['basarili' => false, 'hata' => 'Bu koda ait bir rezervasyon bulunamadı. Kodu kontrol edin.'];
        }

        if ($rezervasyon['durum'] === 'iptal') {
            return ['basarili' => false, 'hata' => 'Bu rezervasyon zaten iptal edilmiş.'];
        }

        if ($rezervasyon['durum'] === 'tamamlandi') {
            return ['basarili' => false, 'hata' => 'Tamamlanmış bir rezervasyon iptal edilemez.'];
        }

        // Tarih karsilastirmasi METIN uzerinden yapilabilir cunku 'Y-m-d'
        // bicimi sifir dolguludur: sozlukte siralama = takvimde siralama.
        // substr(): MySQL DATE kolonu '2026-09-30' dondurur, ama guvende
        // olmak icin ilk 10 karakteri aliyoruz.
        if (gecmis_tarih_mi(substr((string) $rezervasyon['tarih'], 0, 10))) {
            return ['basarili' => false, 'hata' => 'Geçmiş tarihli bir rezervasyon iptal edilemez.'];
        }

        // -------------------------------------------------------------
        // KOSULLU UPDATE - kucuk ama onemli bir ayrinti
        // -------------------------------------------------------------
        // WHERE'e "AND durum = 'onaylandi'" ekliyoruz. Yukarida durumu
        // zaten kontrol ettik; peki neden tekrar?
        //
        // Cunku SELECT ile UPDATE arasinda yine bir pencere var (aynen
        // olustur() icinde anlatilan yaris kosulu). Kullanici iptal
        // butonuna cift tiklarsa iki istek birden gelir; ikisi de SELECT
        // asamasinda 'onaylandi' gorur. Kosulu UPDATE'in WHERE'ine
        // koyunca, veritabani "karsilastir ve degistir" (compare-and-swap)
        // islemini TEK ATOMIK adimda yapar: sadece ilki 1 satir gunceller,
        // ikincisi 0 doner.
        //
        // Burada FOR UPDATE'li bir transaction kurmadik cunku tek bir
        // UPDATE ifadesi zaten kendi basina atomiktir. Transaction, BIRDEN
        // FAZLA ifadeyi birlikte guvenceye almak gerektiginde sarttir.
        $etkilenen = Database::execute(
            "UPDATE rezervasyonlar
                SET durum = 'iptal'
              WHERE rezervasyon_kodu = :kod
                AND durum = 'onaylandi'",
            [':kod' => strtoupper(trim($kod))]
        );

        if ($etkilenen === 0) {
            return ['basarili' => false, 'hata' => 'Rezervasyonun durumu az önce değişti. Sayfayı yenileyip tekrar bakın.'];
        }

        return ['basarili' => true, 'hata' => null];
    }

    /**
     * Admin panelinin kullanacagi genel durum degistirme metodu (Adim 4).
     *
     * @param string $durum onaylandi | iptal | tamamlandi
     *
     * NEDEN BEYAZ LISTE (match) VAR?
     * $durum degeri ENUM kolonuna gidiyor. Prepared statement SQL
     * injection'i zaten engelliyor; ama gecersiz bir deger ENUM'a
     * yazilmaya calisildiginda MySQL'in davranisi SQL MODE'a baglidir:
     * STRICT modda hata verir, strict olmayan modda kolona BOS METIN
     * yazip sadece uyari uretir. Ikinci durumda veritabaninda 'durum'
     * degeri bos bir satir olusur ve durum_etiketi() "Bilinmiyor" der.
     * Sunucu ayarina bagimli davranis istemiyoruz: kurali PHP'de acikca
     * yaziyoruz.
     *
     * NEDEN rowCount() > 0?
     * MySQL varsayilan olarak DEGISEN satir sayisini dondurur, ESLESEN'i
     * degil. Yani durum zaten 'iptal' iken tekrar 'iptal' yazarsaniz 0
     * doner ve bu metot false verir. Cagiran taraf icin dogru okuma
     * "bir sey degisti mi?"dir, "kayit var mi?" degil - kaydin varligini
     * kontrol etmek icin once koduIleBul()/bul() cagirin.
     */
    public static function durumGuncelle(int $id, string $durum): bool
    {
        $gecerli = match ($durum) {
            'onaylandi', 'iptal', 'tamamlandi' => true,
            default                            => false,
        };

        if (!$gecerli) {
            // Bu bir PROGRAMCI hatasidir (kullanici hatasi degil): kodda
            // yanlis bir sabit yazilmis demektir. Loga dusurup false
            // donuyoruz; sessizce yok saymak hatanin fark edilmemesine
            // yol acardi.
            error_log("[Rezervasyon] Gecersiz durum degeri: {$durum}");

            return false;
        }

        return Database::execute(
            'UPDATE rezervasyonlar
                SET durum = :durum
              WHERE id = :id',
            [':durum' => $durum, ':id' => $id]
        ) > 0;
    }


    // =================================================================
    //  5) OZEL (PRIVATE) YARDIMCILAR
    // =================================================================

    /**
     * olustur() icin tek noktadan girdi dogrulama.
     * Ilk buldugu hatayi dondurur, her sey yolundaysa null.
     *
     * Neden "ilk hata"? Butun hatalari birden toplamak (hata dizisi)
     * daha kullanici dostudur ve buyuk formlarda tercih edilir. Burada
     * tek bir metin dondurmeyi sectik cunku sozlesmemiz 'hata' => ?string.
     * Sozlesmeyi degistirmek, arayuzu yazan tarafin kodunu kirardi.
     */
    private static function girdiDogrula(
        string $ad,
        string $telefon,
        ?string $email,
        string $tarih,
        string $baslangic,
        string $bitis,
        int $kisi,
        ?string $not
    ): ?string {
        if (self::karakterSayisi($ad) < 3) {
            return 'Lütfen adınızı ve soyadınızı girin (en az 3 karakter).';
        }

        if (self::karakterSayisi($ad) > 100) {
            return 'Ad soyad en fazla 100 karakter olabilir.';
        }

        // gecerli_telefon() zaten normalize edip kontrol ediyor; buraya
        // gelen deger de normalize edilmis durumda, yani cift normalizasyon
        // zararsiz (fonksiyon idempotent: iki kez uygulamak sonucu
        // degistirmez).
        if (!gecerli_telefon($telefon)) {
            return 'Telefon numarası geçersiz. Örnek: 0555 111 22 33';
        }

        if ($email !== null && !gecerli_email($email)) {
            return 'E-posta adresi geçersiz.';
        }

        if (!gecerli_tarih($tarih)) {
            return 'Tarih geçersiz. Beklenen biçim: YYYY-AA-GG';
        }

        if (gecmis_tarih_mi($tarih)) {
            return 'Geçmiş bir tarihe rezervasyon yapılamaz.';
        }

        if (!rezervasyon_penceresinde_mi($tarih)) {
            return 'En fazla ' . MAX_ILERI_GUN . ' gun sonrasi icin rezervasyon yapilabilir.';
        }

        if (!gecerli_saat($baslangic) || !gecerli_saat($bitis)) {
            return 'Saat geçersiz. Beklenen biçim: SS:DD';
        }

        // Saatleri dakikaya cevirip karsilastiriyoruz. Metin
        // karsilastirmasi ('09:00' < '10:00') sifir dolgulu oldugu surece
        // calisir ama '9:00' gibi bir degerde sessizce bozulur.
        if (saati_dakikaya_cevir($bitis) <= saati_dakikaya_cevir($baslangic)) {
            return 'Bitiş saati başlangıç saatinden sonra olmalıdır.';
        }

        if (!saat_araliginda_mi($baslangic)) {
            return 'Başlangıç saati çalışma saatleri dışında (' . KAFE_ACILIS . ' - ' . KAFE_KAPANIS . ').';
        }

        // Bitis icin $bitisDahil = true: kapanis saati 23:00 ise 23:00'te
        // BITEN rezervasyon gecerlidir, 23:00'te BASLAYAN degil.
        if (!saat_araliginda_mi($bitis, KAFE_ACILIS, KAFE_KAPANIS, true)) {
            return 'Bitiş saati çalışma saatleri dışında (' . KAFE_ACILIS . ' - ' . KAFE_KAPANIS . ').';
        }

        if ($kisi < MIN_KISI_SAYISI || $kisi > MAX_KISI_SAYISI) {
            return 'Kişi sayısı ' . MIN_KISI_SAYISI . ' ile ' . MAX_KISI_SAYISI . ' arasinda olmalidir.';
        }

        // Notu KIRPMIYORUZ, REDDEDIYORUZ. Sessizce kirpmak, musterinin
        // "tekerlekli sandalye erisimi gerekiyor" notunun yarisini
        // ucurabilir ve kimse fark etmez. Veri kaybi her zaman gorunur
        // olmalidir.
        if ($not !== null && self::karakterSayisi($not) > 255) {
            return 'Notunuz en fazla 255 karakter olabilir.';
        }

        return null;
    }

    /**
     * UTF-8 karakter sayar.
     *
     * strlen() BAYT sayar: "Sisli Sube" gibi Turkce harf iceren bir metinde
     * her ozel harf 2 bayt tutar ve 100 karakterlik sinir 60 karakterde
     * dolmus gibi gorunur. mbstring eklentisi (mb_strlen) her PHP
     * kurulumunda ACIK DEGILDIR - bu gelistirme makinesinde de kapali.
     * preg_match_all('/./us') deseni ise PCRE'nin UTF-8 destegiyle
     * calisir ve ek bir eklenti gerektirmez: '.' bir karakteri eslestirir,
     * 'u' degistiricisi deseni ve konuyu UTF-8 olarak yorumlatir,
     * 's' ise '.' isaretinin satir sonlarini da saymasini saglar.
     */
    private static function karakterSayisi(string $metin): int
    {
        return (int) preg_match_all('/./us', $metin);
    }

    /**
     * Firlatilan PDOException, rezervasyon_kodu UNIQUE ihlali mi?
     *
     * errorInfo dizisi su yapidadir:
     *   [0] SQLSTATE            -> '23000' (butunluk kisiti ihlali)
     *   [1] Surucu hata kodu    -> 1062    (ER_DUP_ENTRY)
     *   [2] Surucu hata mesaji  -> "Duplicate entry 'RZ7K4M2Q' for key
     *                               'uq_rezervasyon_kodu'"
     *
     * Ucunu birden kontrol ediyoruz. Neden bu kadar titiz?
     *   - Sadece SQLSTATE'e bakmak: yabanci anahtar hatasi da 23000'dir;
     *     olmayan bir masa_id yuzunden patlayan INSERT'i 5 kez tekrar
     *     ederdik.
     *   - Sadece 1062'ye bakmak: ileride baska bir UNIQUE indeks eklenirse
     *     (ornegin "ayni telefon ayni saate iki kez kayit olamaz") onun
     *     ihlalini de kod cakismasi sanip sessizce yeniden denerdik; hata
     *     kullaniciya hic ulasmazdi.
     * Indeks adini mesajda aramak, niyeti kesinlestirir.
     */
    private static function kodCakismasiMi(PDOException $e): bool
    {
        $bilgi = $e->errorInfo ?? [];

        return ($bilgi[0] ?? '') === '23000'
            && (int) ($bilgi[1] ?? 0) === self::HATA_YINELENEN_ANAHTAR
            && str_contains((string) ($bilgi[2] ?? ''), 'uq_rezervasyon_kodu');
    }

    /**
     * olustur() icin standart basarisiz yaniti.
     * Sozlesmedeki dort anahtarin HEPSI her zaman bulunsun diye tek
     * yerden uretiyoruz: cagiran taraf $sonuc['kod'] yazdiginda
     * "Undefined array key" uyarisi almasin.
     */
    private static function basarisiz(string $mesaj): array
    {
        return [
            'basarili' => false,
            'kod'      => null,
            'id'       => null,
            'hata'     => $mesaj,
        ];
    }
}
