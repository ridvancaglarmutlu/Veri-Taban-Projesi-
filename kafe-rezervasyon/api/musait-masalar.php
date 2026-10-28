<?php
/**
 * =====================================================================
 *  KAFE MASA REZERVASYON SISTEMI - MUSAIT MASA JSON UC NOKTASI (API)
 * ---------------------------------------------------------------------
 *  Bu dosya HTML uretmez. Tarayicidaki JavaScript, kullanici tarih ve
 *  saat sectigi anda buraya fetch() ile istek atar; biz de o aralikta
 *  bos olan masalari JSON olarak doneriz. Sayfa yenilenmeden masa
 *  listesinin guncellenmesini saglayan parca budur.
 *
 *  SOZLESME (kontrat)
 *  ------------------
 *  Istek : GET api/musait-masalar.php
 *          ?tarih=YYYY-AA-GG&baslangic=SS:DD&bitis=SS:DD&kisi_sayisi=N
 *
 *  Basarili (HTTP 200):
 *    {"basarili":true,"masalar":[
 *        {"id":3,"masa_adi":"Masa 3","kapasite":4,
 *         "konum":"ic","dolu_araliklar":"12:00-14:00"}
 *    ]}
 *
 *  Hatali (HTTP 400):
 *    {"basarili":false,"hata":"Turkce aciklama"}
 *
 *  Sozlesme neden bu kadar net yazilmali? Cunku bu dosyayi PHP yazan
 *  kisi ile onu tuketen JavaScript'i yazan kisi farkli olabilir (bu
 *  projede oyle). Iki taraf da ayni belgeye bakip birbirini beklemeden
 *  calisabilsin diye anahtar adlari ve HTTP kodlari onceden sabitlenir.
 *  Alan adini sonradan 'masalar' yerine 'data' yapmak, calisan bir
 *  arayuzu sessizce bozar.
 *
 *  HER YANITTA 'basarili' ALANI VAR - neden?
 *  JavaScript tarafi HTTP kodunu de kontrol etmeli, ama pratikte cogu
 *  fetch kodu dogrudan response.json() cagirir. Yanitin ILK alani
 *  sonucu soyluyorsa istemci kodu tek bir if ile dogru dallanir:
 *      if (!veri.basarili) { hatayiGoster(veri.hata); return; }
 *  Basarili ve hatali yanitlarin farkli SEKILLERI olmasi (biri dizi,
 *  digeri nesne gibi) istemci tarafinda en sik hata kaynagidir.
 * =====================================================================
 */

// ---------------------------------------------------------------------
// ONYUKLEME
// ---------------------------------------------------------------------
// DIKKAT - COK SIK YAPILAN HATA:
// Bu dosya app/ klasorunun DISINDA oldugu icin "APP_INIT sabitini once
// ben tanimlayayim" demek mantikli gorunur:
//
//     define('APP_INIT', true);                 // <-- YANLIS
//     require_once __DIR__ . '/../app/bootstrap.php';
//
// Bu kod sessizce coker. Cunku bootstrap.php'nin ilk satirlari sunlar:
//
//     if (defined('APP_INIT')) { return; }
//
// Bu kalkan, bootstrap'in iki kez yuklenmesini engellemek icindir. Biz
// sabiti onceden tanimlarsak bootstrap KENDISINI zaten yuklenmis sanip
// hemen geri doner: config.php okunmaz, oturum baslamaz, db.php ve
// helpers.php yuklenmez. Sonuc "Call to undefined function e()" gibi,
// sebebi ilk bakista anlasilmayan olumcul bir hatadir.
//
// DOGRUSU: APP_INIT'i bootstrap'in kendisi tanimlar. Bize dusen sadece
// onu require etmektir - tipki index.php gibi.
require_once __DIR__ . '/../app/bootstrap.php';

// bootstrap yalnizca cekirdek dosyalari (db, helpers) yukler; depo
// siniflari kullanan her sayfa kendi ihtiyacini acikca belirtir.
// Boylece admin sayfalari, ihtiyac duymadiklari siniflari bosuna
// ayristirmak (parse) zorunda kalmaz.
require_once APP_DIZIN . '/MasaRepository.php';


// ---------------------------------------------------------------------
// YANIT BASLIKLARI
// ---------------------------------------------------------------------
// Content-Type: Tarayiciya "bu HTML degil, JSON" diyoruz. Yazilmazsa
// PHP varsayilan olarak text/html gonderir; fetch().json() yine calisir
// ama adres cubugunda acildiginda tarayici icerigi HTML olarak
// yorumlamaya calisir ve bir gun icerikte '<' gecerse sorun cikar.
// charset=utf-8 ise Turkce karakterlerin dogru gorunmesi icin sarttir.
header('Content-Type: application/json; charset=utf-8');

// nosniff: Bazi tarayicilar Content-Type'i yok sayip icerige bakarak
// tur tahmini yapar (MIME sniffing). Saldirgan, bir metin alanina HTML
// yazip bu JSON'un tarayicida sayfa olarak calistirilmasini saglayabilir.
// Bu baslik tahmini kapatir.
header('X-Content-Type-Options: nosniff');

// Musaitlik saniyeler icinde degisir. Tarayici veya bir ara vekil
// sunucu (proxy) bu yaniti onbellege alirsa, kullaniciya 5 dakika once
// bos olan bir masa "musait" diye gosterilir.
header('Cache-Control: no-store');


/**
 * JSON yanitini basar ve betigi durdurur.
 *
 * NEDEN JSON_UNESCAPED_UNICODE?
 * Varsayilan olarak json_encode Turkce harfleri \u kacislariyla yazar:
 *     {"hata":"Ge\u00e7ersiz tarih"}
 * Bu teknik olarak gecerli JSON'dur ve JavaScript dogru cozer. Ama
 * yaniti curl ile veya tarayicinin Network sekmesinde incelerken okunmaz
 * hale gelir; hata ayiklamayi zorlastirir. Cikti da yaklasik %50 buyur.
 *
 * NEDEN 'never' donus tipi?
 * Fonksiyon her zaman exit ile bitiyor. 'never' yazmak hem okuyucuya
 * "buradan sonrasi calismaz" der hem de statik analiz araclarinin
 * (PHPStan gibi) sonraki satirlari olu kod olarak isaretlemesini saglar.
 */
function json_yanit(array $govde, int $kod = 200): never
{
    http_response_code($kod);
    echo json_encode($govde, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Standart hata yaniti. Varsayilan HTTP kodu 400 (Bad Request):
 * "istek hatali, sunucuda bir sorun yok". 500 dondurmek yanlis olurdu -
 * o kod "sunucu coktu" demektir ve izleme sistemleri alarm uretir.
 */
function json_hata(string $mesaj, int $kod = 400): never
{
    json_yanit(['basarili' => false, 'hata' => $mesaj], $kod);
}


// =====================================================================
//  GIRDILERI OKU
// =====================================================================
// get() helpers.php'den gelir: $_GET'te anahtar yoksa bos metin doner
// ve trim uygular. Boylece "Undefined array key" uyarisi olusmaz.
$tarih     = get('tarih');
$baslangic = get('baslangic');
$bitis     = get('bitis');
$kisiHam   = get('kisi_sayisi');


// =====================================================================
//  SUNUCU TARAFI DOGRULAMA
// ---------------------------------------------------------------------
//  "Formda zaten <input type=date> var, saatler <select> ile sinirli,
//   ayrica JavaScript kontrol ediyor. Burada tekrar dogrulamak fazlalik
//   degil mi?"
//
//  HAYIR. Istemci tarafi dogrulama bir KULLANICI KOLAYLIGIDIR; guvenlik
//  degeri SIFIRDIR. Sebebi basit: JavaScript kullanicinin BILGISAYARINDA
//  calisir, yani tamamen onun kontrolundedir. Bu uc noktaya su yollarla
//  istek atilabilir:
//
//    1. Tarayici konsolundan elle fetch cagirmak:
//         fetch('api/musait-masalar.php?tarih=1900-01-01&baslangic=03:00'
//               + '&bitis=05:00&kisi_sayisi=9999')
//    2. Komut satirindan curl / wget:
//         curl 'http://localhost/kafe-rezervasyon/api/musait-masalar.php?...'
//    3. Postman, Insomnia gibi API araclari.
//    4. Tarayicinin gelistirici araclarinda "Edit and Resend".
//    5. Sayfayi kaydedip JavaScript'i silerek yeniden acmak.
//    6. Bir vekil sunucu (Burp Suite) ile istegi yolda degistirmek -
//       bu durumda JavaScript dogru degeri gondermistir bile, degisiklik
//       tarayiciyi terk ettikten SONRA yapilir.
//
//  6. madde ozellikle onemlidir: "ama benim JavaScript'im dogru deger
//  gonderiyor" savunmasi bile gecersizdir. Sunucuya ULASAN veri, sizin
//  gonderdiginizi sandiginiz veri degildir.
//
//  DOGRU ZIHIN MODELI:
//    - Istemci dogrulamasi: kullaniciya ANINDA geri bildirim (hizli, hos).
//    - Sunucu dogrulamasi : GERCEK kural (yavas ama baglayici).
//  Ikisi ayni kurali ifade eder; ama biri kapatilabilir, digeri hayir.
//  Kurallarin TEK DOGRU KAYNAGI config.php'deki sabitlerdir; hem bu
//  dosya hem form ayni sabitleri kullanir, boylece ikisi ayrisamaz.
//
//  Ayrica bu dogrulamalar sadece guvenlik degil, VERI KALITESI de saglar:
//  bozuk bir tarih dogrudan SQL'e giderse MySQL '0000-00-00' gibi
//  anlamsiz bir deger uretebilir ya da sorgu sessizce bos doner ve
//  kullanici "hicbir masa yok" sanir.
// =====================================================================

// --- Tarih ------------------------------------------------------------
// gecerli_tarih() sadece bicime bakmaz, TAKVIMI de kontrol eder:
// '2026-02-30' bicim olarak dogrudur ama boyle bir gun yoktur.
if (!gecerli_tarih($tarih)) {
    json_hata('Geçerli bir tarih seçin. Beklenen biçim: YYYY-AA-GG');
}

if (gecmis_tarih_mi($tarih)) {
    json_hata('Geçmiş bir tarih için müsaitlik sorgulanamaz.');
}

// Ust sinir: birinin 2049 yilina rezervasyon yapmasini engeller.
// gecmis_tarih_mi zaten yukarida kontrol edildigi icin burada fiilen
// sadece ileri tarih penceresi denetlenmis olur.
if (!rezervasyon_penceresinde_mi($tarih)) {
    json_hata('En fazla ' . MAX_ILERI_GUN . ' gun sonrasi icin rezervasyon yapilabilir.');
}

// --- Saatler ----------------------------------------------------------
if (!gecerli_saat($baslangic) || !gecerli_saat($bitis)) {
    json_hata('Geçerli bir saat seçin. Beklenen biçim: SS:DD');
}

// Saatleri METIN olarak degil, dakikaya cevirip karsilastiriyoruz.
// '09:00' < '10:00' metin karsilastirmasi sifir dolgulu degerlerde
// calisir; ama istemci '9:00' gonderirse ('0' olmadan) siralama bozulur.
if (saati_dakikaya_cevir($bitis) <= saati_dakikaya_cevir($baslangic)) {
    json_hata('Bitiş saati başlangıç saatinden sonra olmalıdır.');
}

// Calisma saatleri. KAFE_ACILIS / KAFE_KAPANIS config.php'de tanimli;
// saat degerlerini buraya elle yazmak, acilis saati degistiginde bu
// dosyanin unutulmasi demek olurdu.
if (!saat_araliginda_mi($baslangic)) {
    json_hata('Başlangıç saati çalışma saatleri dışında (' . KAFE_ACILIS . ' - ' . KAFE_KAPANIS . ').');
}

// $bitisDahil = true: kapanis 23:00 ise 23:00'te BITEN rezervasyon
// gecerlidir; 23:00'te BASLAYAN degil. Bu yuzden baslangic ve bitis
// ayni fonksiyonu FARKLI parametreyle cagirir.
if (!saat_araliginda_mi($bitis, KAFE_ACILIS, KAFE_KAPANIS, true)) {
    json_hata('Bitiş saati çalışma saatleri dışında (' . KAFE_ACILIS . ' - ' . KAFE_KAPANIS . ').');
}

// --- Kişi sayısı ------------------------------------------------------
// Burada bilincli olarak post_int() benzeri bir "sinira cekme" (clamp)
// yapmiyoruz. Formda clamp mantikli olabilir; bir API'de DEGILDIR:
// 9999 gonderen istemciye sessizce 30 kisilik sonuc dondurmek, onun
// hatali oldugunu gizler ve hata ayiklamayi imkansiz hale getirir.
// API'ler sessizce duzeltmez, ACIKCA reddeder.
//
// ctype_digit: '4' -> true, '4.5' / '-1' / 'abc' / '' -> false.
// Neden (int) donusumu yetmez? Cunku (int)'abc' = 0 ve (int)'4kisi' = 4;
// yani anlamsiz girdi sessizce gecerli bir sayiya donusurdu.
if (!ctype_digit($kisiHam)) {
    json_hata('Kişi sayısı bir tam sayı olmalıdır.');
}

$kisiSayisi = (int) $kisiHam;

if ($kisiSayisi < MIN_KISI_SAYISI || $kisiSayisi > MAX_KISI_SAYISI) {
    json_hata('Kişi sayısı ' . MIN_KISI_SAYISI . ' ile ' . MAX_KISI_SAYISI . ' arasinda olmalidir.');
}


// =====================================================================
//  VERIYI CEK VE DON
// =====================================================================
// Tum SQL MasaRepository'nin icinde. Bu dosyada tek satir SQL olmamasi
// bilincli bir kuraldir: cakisma kosulu tek yerde yasar, boylece bir
// gun duzeltildiginde her yerde duzelmis olur.
//
// try/catch neden var? Buraya kadar gelen istek gecerli; yine de
// veritabani duserse PDOException firlar. Yakalamazsak PHP varsayilan
// hata ciktisini basar: APP_DEBUG acikken HTML bir yigin izi (stack
// trace), yani JSON bekleyen istemci icin cozulemez bir yanit ve
// sunucu dosya yollarinin sizmasi. Yakalayip duzgun bicimli bir JSON
// dondurmek hem istemciyi hem guvenligi korur.
try {
    $masalar = MasaRepository::musaitMasalar($tarih, $baslangic, $bitis, $kisiSayisi);
} catch (Throwable $e) {
    // Ayrinti log'a (kaybolmasin), kullaniciya genel mesaj (sizmasin).
    error_log('[API musait-masalar] ' . $e->getMessage());

    // 503 Service Unavailable: "istegin degil, sunucunun sorunu; birazdan
    // tekrar dene". 400 dondurmek istemciyi bosuna parametrelerini
    // duzeltmeye ugrastirirdi.
    json_hata('Müsaitlik bilgisi şu anda alınamıyor. Lütfen tekrar deneyin.', 503);
}

// Bos liste bir HATA DEGILDIR. "O saatte hic masa yok" tamamen normal
// bir is sonucudur; basarili=true ve bos dizi doneriz. 404 dondurmek
// yaygin bir yanlistir: 404 "boyle bir UC NOKTA yok" demektir, "sonuc
// bulunamadi" degil. Istemci tarafi masalar.length === 0 kontrolu ile
// "Bu saatte uygun masa kalmadi" mesajini gosterir.
json_yanit([
    'basarili' => true,
    'masalar'  => $masalar,
]);
