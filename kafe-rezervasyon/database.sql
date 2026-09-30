-- =====================================================================
--  KAFE MASA REZERVASYON SISTEMI - VERITABANI SEMASI
--  Hedef ortam: XAMPP (MySQL 8.x / MariaDB 10.4+) - phpMyAdmin uyumlu
--  Kullanim: phpMyAdmin > Import > bu dosyayi sec  VEYA
--            mysql -u root -p < database.sql
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1) VERITABANI
-- utf8mb4: Turkce karakterler (c, g, i, o, s, u harflerinin aksanli halleri)
-- sorunsuz saklanir. Eski "utf8" karakter seti 4 byte'lik karakterleri
-- desteklemedigi icin MySQL'de artik utf8mb4 tercih edilir.
-- ---------------------------------------------------------------------
CREATE DATABASE IF NOT EXISTS `kafe_rezervasyon`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `kafe_rezervasyon`;

-- Tablolari bagimlilik sirasina gore siliyoruz: rezervasyonlar tablosu
-- masalar tablosuna foreign key ile bagli oldugu icin ONCE o silinmeli.
DROP TABLE IF EXISTS `rezervasyonlar`;
DROP TABLE IF EXISTS `masalar`;
DROP TABLE IF EXISTS `yoneticiler`;


-- ---------------------------------------------------------------------
-- 2) YONETICILER
-- Admin paneline giris yapacak kullanicilar.
-- DIKKAT: "sifre" alaninda ASLA duz metin sifre tutulmaz. PHP tarafinda
-- password_hash() ile uretilen hash saklanir, dogrulama password_verify()
-- ile yapilir. bcrypt hash'i 60 karakterdir; ileride Argon2id'ye gecmek
-- isteyebilecegimiz icin alani 255 birakiyoruz (Argon2 hash'i daha uzun).
-- ---------------------------------------------------------------------
CREATE TABLE `yoneticiler` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `kullanici_adi` VARCHAR(50)  NOT NULL,
    `sifre`         VARCHAR(255) NOT NULL COMMENT 'password_hash() cikti degeri',
    `ad_soyad`      VARCHAR(100)     NULL,
    `son_giris_at`  DATETIME         NULL COMMENT 'Basarili son giris zamani',
    `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    -- Ayni kullanici adiyla ikinci bir hesap acilmasini veritabani
    -- seviyesinde engelliyoruz. Sadece PHP kontrolune guvenmek yetmez:
    -- iki istek ayni anda gelirse (race condition) PHP kontrolu atlatilabilir.
    UNIQUE KEY `uq_yonetici_kullanici_adi` (`kullanici_adi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 3) MASALAR
-- Kafedeki fiziksel masalar.
--   konum : ENUM('ic','dis')      -> ic mekan / dis mekan (bahce, teras)
--   durum : ENUM('aktif','pasif') -> pasif masalar rezervasyona kapalidir
--
-- Neden ENUM? Bu alanlar sadece birkac sabit deger alabilir. ENUM hem
-- yerden tasarruf saglar hem de "?c", "Ic", "disi" gibi yazim hatalarini
-- veritabani seviyesinde imkansiz kilar. (Alternatif: ayri lookup tablosu.)
-- ---------------------------------------------------------------------
CREATE TABLE `masalar` (
    `id`         INT UNSIGNED         NOT NULL AUTO_INCREMENT,
    `masa_adi`   VARCHAR(50)          NOT NULL COMMENT 'Ornek: Masa 1, Bahce 3, Pencere Kenari',
    `kapasite`   TINYINT UNSIGNED     NOT NULL DEFAULT 2 COMMENT 'Masanin alabilecegi maksimum kisi',
    `konum`      ENUM('ic','dis')     NOT NULL DEFAULT 'ic',
    `durum`      ENUM('aktif','pasif') NOT NULL DEFAULT 'aktif',
    `created_at` TIMESTAMP            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_masa_adi` (`masa_adi`),
    -- Musteri arayuzunde "aktif VE kapasitesi >= kisi_sayisi olan masalar"
    -- sorgusunu sik calistiracagiz; bu bilesik indeks o sorguyu hizlandirir.
    KEY `idx_masa_durum_kapasite` (`durum`, `kapasite`),
    -- CHECK kisiti: MySQL 8.0.16+ ve MariaDB 10.2+ bunu gercekten uygular.
    -- Daha eski surumlerde soz dizimi kabul edilir ama yok sayilir; bu yuzden
    -- ayni dogrulamayi PHP tarafinda da tekrar yapacagiz (savunma katmanlari).
    CONSTRAINT `chk_masa_kapasite` CHECK (`kapasite` BETWEEN 1 AND 30)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 4) REZERVASYONLAR
-- Sistemin kalbi. Bir rezervasyon = bir masa + bir tarih + bir saat araligi.
--
--   rezervasyon_kodu : Musterinin kendi kaydini sorgulamasi/iptal etmesi icin
--                      uretilen kisa kod (orn. RZ7K4M2Q). ID yerine kod
--                      kullanmak guvenlidir: ardisik ID'ler tahmin edilebilir
--                      oldugu icin baskasinin rezervasyonu gorunebilirdi
--                      (IDOR zafiyeti).
--   durum            : onaylandi | iptal | tamamlandi
--                      Silme yerine durum degistiriyoruz -> gecmis kayitlar
--                      raporlama icin korunur (soft delete mantigi).
-- ---------------------------------------------------------------------
CREATE TABLE `rezervasyonlar` (
    `id`               INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `rezervasyon_kodu` CHAR(8)          NOT NULL COMMENT 'Musteriye verilen takip kodu',
    `masa_id`          INT UNSIGNED     NOT NULL,
    `musteri_adi`      VARCHAR(100)     NOT NULL,
    `musteri_telefon`  VARCHAR(20)      NOT NULL COMMENT 'Sadece rakam olarak normalize edilip saklanir',
    `musteri_email`    VARCHAR(120)         NULL,
    `tarih`            DATE             NOT NULL,
    `baslangic_saati`  TIME             NOT NULL,
    `bitis_saati`      TIME             NOT NULL,
    `kisi_sayisi`      TINYINT UNSIGNED NOT NULL DEFAULT 2,
    `durum`            ENUM('onaylandi','iptal','tamamlandi') NOT NULL DEFAULT 'onaylandi',
    `musteri_notu`     VARCHAR(255)         NULL COMMENT 'Ozel istek (dogum gunu, bebek sandalyesi vb.)',
    `created_at`       TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_rezervasyon_kodu` (`rezervasyon_kodu`),

    -- ***** CAKISMA SORGUSUNUN INDEKSI *****
    -- Her yeni rezervasyon kaydindan once "bu masa, bu tarihte, bu saat
    -- araliginda dolu mu?" sorgusunu calistiracagiz. Sorgunun WHERE
    -- kosulundaki kolon sirasi ile indeks sirasini ayni tutuyoruz:
    -- once esitlik kontrolleri (masa_id, tarih, durum), sonra aralik
    -- kontrolu (baslangic_saati). MySQL bilesik indeksi ancak soldan saga
    -- kesintisiz kullanabilir, bu yuzden sira onemlidir.
    KEY `idx_cakisma` (`masa_id`, `tarih`, `durum`, `baslangic_saati`),

    -- Admin panelindeki "tarihe gore" ve "duruma gore" filtreler icin
    KEY `idx_tarih_durum` (`tarih`, `durum`),
    -- Musterinin telefon numarasi ile kendi rezervasyonlarini sorgulamasi icin
    KEY `idx_musteri_telefon` (`musteri_telefon`),

    -- Referans butunlugu: var olmayan bir masa_id ile kayit atilamaz.
    -- ON DELETE RESTRICT -> uzerinde rezervasyon bulunan masa silinemez.
    -- Bu bilincli bir tercih: gecmis rezervasyonlarin masa bilgisi kaybolmasin.
    -- Admin panelinde masayi "silmek" yerine durumunu 'pasif' yapacagiz.
    CONSTRAINT `fk_rezervasyon_masa`
        FOREIGN KEY (`masa_id`) REFERENCES `masalar` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,

    -- Mantiksal tutarlilik kisitlari
    CONSTRAINT `chk_saat_sirasi`  CHECK (`bitis_saati` > `baslangic_saati`),
    CONSTRAINT `chk_kisi_sayisi`  CHECK (`kisi_sayisi` BETWEEN 1 AND 30)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
-- 5) ORNEK VERILER (SEED)
-- =====================================================================

-- Admin hesabi
--   Kullanici adi : admin
--   Sifre         : admin123
-- Asagidaki deger password_hash('admin123', PASSWORD_DEFAULT) ile uretilmis
-- gercek bir bcrypt hash'idir. Canli ortama gecerken bu hesabin sifresini
-- mutlaka degistirin.
INSERT INTO `yoneticiler` (`kullanici_adi`, `sifre`, `ad_soyad`) VALUES
('admin', '$2a$10$E80aFHWhcNYMMnRvxUSNtuFwz7hql0/4IW4OazFFw0Drs5PlEfbXG', 'Sistem Yoneticisi');

-- Masalar: farkli kapasite ve konumlarda 8 masa
INSERT INTO `masalar` (`masa_adi`, `kapasite`, `konum`, `durum`) VALUES
('Masa 1',            2,  'ic',  'aktif'),
('Masa 2',            2,  'ic',  'aktif'),
('Masa 3',            4,  'ic',  'aktif'),
('Masa 4',            4,  'ic',  'aktif'),
('Pencere Kenari',    6,  'ic',  'aktif'),
('Bahce 1',           4,  'dis', 'aktif'),
('Bahce 2',           6,  'dis', 'aktif'),
('Teras Loca',        10, 'dis', 'pasif');  -- tadilatta: rezervasyona kapali

-- Bugune ait ornek rezervasyonlar (CURDATE() sayesinde dosya her gun calisir)
INSERT INTO `rezervasyonlar`
    (`rezervasyon_kodu`, `masa_id`, `musteri_adi`, `musteri_telefon`, `musteri_email`,
     `tarih`, `baslangic_saati`, `bitis_saati`, `kisi_sayisi`, `durum`) VALUES
('RZDEMO01', 3, 'Ayse Yilmaz',  '5551112233', 'ayse@example.com',  CURDATE(), '12:00:00', '14:00:00', 4, 'onaylandi'),
('RZDEMO02', 6, 'Mehmet Demir', '5554445566', NULL,                CURDATE(), '19:00:00', '21:00:00', 3, 'onaylandi'),
('RZDEMO03', 1, 'Zeynep Kaya',  '5557778899', 'zeynep@example.com', CURDATE(), '10:00:00', '11:30:00', 2, 'tamamlandi'),
('RZDEMO04', 5, 'Can Ozturk',   '5552223344', NULL,                 CURDATE(), '18:00:00', '20:00:00', 5, 'iptal');


-- =====================================================================
-- 6) KRITIK MANTIK: SAAT ARALIGI CAKISMA KONTROLU
-- ---------------------------------------------------------------------
-- Iki zaman araligi [A_bas, A_bit) ve [B_bas, B_bit) SADECE ve ANCAK
-- su iki kosul birlikte saglandiginda KESISIR:
--
--        A_bas < B_bit   VE   A_bit > B_bas
--
-- Bu tek satirlik kosul, akla gelen tum durumlari (tam ayni saat, ice
-- gomulu, bastan tasan, sondan tasan, kapsayan) kendiliginden kapsar.
-- Tek tek "if" yazmaya gerek yoktur.
--
-- Yeni rezervasyon 14:00-16:00 icin su ornekleri dusunun:
--   Mevcut 12:00-14:00  -> 14:00 < 14:00 YANLIS  => cakisma YOK (bitisik)
--   Mevcut 13:00-15:00  -> 13:00 < 16:00 DOGRU ve 15:00 > 14:00 DOGRU => CAKISIR
--   Mevcut 14:30-15:00  -> gomulu, her iki kosul DOGRU => CAKISIR
--   Mevcut 16:00-18:00  -> 18:00 > 14:00 DOGRU ama 16:00 < 16:00 YANLIS => cakisma YOK
--
-- Sinirlarda "<" ve ">" (kesin kucuk/buyuk) kullanmak, saat 14:00'te biten
-- rezervasyonun ardindan 14:00'te baslayan yeni rezervasyona izin verir.
-- ">=" kullanilsa bitisik rezervasyonlar bosuna reddedilirdi.
--
-- PHP tarafinda (Adim 3) kullanacagimiz hazir sorgu:
--
--   SELECT COUNT(*) FROM rezervasyonlar
--    WHERE masa_id = :masa_id
--      AND tarih   = :tarih
--      AND durum   = 'onaylandi'        -- iptal/tamamlandi kayitlar masayi bloklamaz
--      AND baslangic_saati < :bitis_saati
--      AND bitis_saati     > :baslangic_saati;
--
-- Sonuc 0'dan buyukse masa o aralikta doludur.
--
-- Asagidaki sorgu ile ornek verilerde kendiniz deneyebilirsiniz
-- (masa 3'te 12:00-14:00 dolu oldugu icin 13:00-15:00 cakisma verir):
--
--   SELECT * FROM rezervasyonlar
--    WHERE masa_id = 3 AND tarih = CURDATE() AND durum = 'onaylandi'
--      AND baslangic_saati < '15:00:00' AND bitis_saati > '13:00:00';
-- =====================================================================


-- =====================================================================
-- 7) YARDIMCI VIEW (opsiyonel) - Admin dashboard icin gunluk ozet
-- View = kaydedilmis SELECT sorgusu. Dashboard'da ayni sorguyu tekrar
-- tekrar yazmak yerine "SELECT * FROM v_gunluk_ozet" diyebiliriz.
-- =====================================================================
CREATE OR REPLACE VIEW `v_gunluk_ozet` AS
SELECT
    r.`tarih`,
    COUNT(*)                                                   AS toplam_rezervasyon,
    SUM(CASE WHEN r.`durum` = 'onaylandi'  THEN 1 ELSE 0 END)  AS onayli_sayisi,
    SUM(CASE WHEN r.`durum` = 'iptal'      THEN 1 ELSE 0 END)  AS iptal_sayisi,
    SUM(CASE WHEN r.`durum` = 'tamamlandi' THEN 1 ELSE 0 END)  AS tamamlanan_sayisi,
    -- Onayli rezervasyonlarin toplam misafir sayisi
    SUM(CASE WHEN r.`durum` = 'onaylandi' THEN r.`kisi_sayisi` ELSE 0 END) AS onayli_misafir
FROM `rezervasyonlar` r
GROUP BY r.`tarih`;
