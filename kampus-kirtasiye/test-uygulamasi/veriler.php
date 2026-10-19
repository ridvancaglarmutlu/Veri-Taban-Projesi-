<?php
declare(strict_types=1);

function test_senaryolari(): array
{
    return [
        ['id' => 'T01', 'grup' => 'Giriş', 'ad' => 'Boş giriş', 'girdi' => 'Kullanıcı adı ve şifre boş', 'beklenen' => 'Kullanıcı adı boş bırakılamaz.', 'teknik' => 'Negatif / kara kutu', 'v1' => 'geçti', 'v2' => 'geçti'],
        ['id' => 'T02', 'grup' => 'Giriş', 'ad' => 'Yanlış şifre', 'girdi' => 'ridvan / yanlis123', 'beklenen' => 'Kullanıcı adı veya şifre hatalı.', 'teknik' => 'Negatif / kara kutu', 'v1' => 'geçti', 'v2' => 'geçti'],
        ['id' => 'T03', 'grup' => 'Raf', 'ad' => 'Geçerli giriş', 'girdi' => 'ridvan / Defter2026', 'beklenen' => '6 ürün listelenir', 'teknik' => 'Pozitif / fonksiyonel', 'v1' => 'geçti', 'v2' => 'geçti'],
        ['id' => 'T04', 'grup' => 'Raf', 'ad' => 'Kırtasiye filtresi', 'girdi' => 'Kategori = kirtasiye', 'beklenen' => '4 ürün listelenir', 'teknik' => 'Fonksiyonel', 'v1' => 'kaldı', 'v2' => 'geçti'],
        ['id' => 'T05', 'grup' => 'Sepet', 'ad' => 'Defter ekleme', 'girdi' => 'Ürün 1 sepete eklenir', 'beklenen' => '1 adet, 54,90 TL', 'teknik' => 'Pozitif', 'v1' => 'geçti', 'v2' => 'geçti'],
        ['id' => 'T06', 'grup' => 'Sepet', 'ad' => 'Defteri kaldırma', 'girdi' => 'Sepetteki defter silinir', 'beklenen' => '0 ürün, 0,00 TL', 'teknik' => 'Fonksiyonel', 'v1' => 'geçti', 'v2' => 'geçti'],
        ['id' => 'T07', 'grup' => 'Teslimat', 'ad' => 'Boş form', 'girdi' => 'Ad, soyad, telefon boş', 'beklenen' => 'Ad alanı zorunludur.', 'teknik' => 'Negatif', 'v1' => 'geçti', 'v2' => 'geçti'],
        ['id' => 'T08', 'grup' => 'Giriş', 'ad' => 'Kilitli hesap', 'girdi' => 'mezun / Mezun2026', 'beklenen' => 'Hesap kilitlidir uyarısı', 'teknik' => 'Negatif', 'v1' => 'geçti', 'v2' => 'geçti'],
        ['id' => 'T09', 'grup' => 'Giriş', 'ad' => 'Boş şifre', 'girdi' => 'ridvan, şifre boş', 'beklenen' => 'Şifre alanı zorunludur.', 'teknik' => 'Negatif', 'v1' => 'geçti', 'v2' => 'geçti'],
        ['id' => 'T10', 'grup' => 'Sepet', 'ad' => 'İki ürün + kargo', 'girdi' => 'Defter + USB', 'beklenen' => '398,80 TL', 'teknik' => 'Pozitif / sınır para', 'v1' => 'geçti', 'v2' => 'geçti'],
        ['id' => 'T11', 'grup' => 'Teslimat', 'ad' => '10 haneli telefon', 'girdi' => '0555123456', 'beklenen' => 'Sipariş tamamlanır', 'teknik' => 'Pozitif / sınır', 'v1' => 'geçti', 'v2' => 'geçti'],
        ['id' => 'T12', 'grup' => 'Sepet', 'ad' => 'Boş sepet teslimatı', 'girdi' => '0 ürün, Teslimata geç', 'beklenen' => 'Sepette kalır, uyarı çıkar', 'teknik' => 'Negatif / güvenlik kuralı', 'v1' => 'kaldı', 'v2' => 'geçti'],
        ['id' => 'T13', 'grup' => 'Teslimat', 'ad' => '9 haneli telefon', 'girdi' => '055512345', 'beklenen' => '10 rakam kuralı reddeder', 'teknik' => 'Sınır komşusu', 'v1' => 'geçti', 'v2' => 'geçti'],
        ['id' => 'T14', 'grup' => 'Teslimat', 'ad' => '11 haneli telefon', 'girdi' => '05551234567', 'beklenen' => '10 rakam kuralı reddeder', 'teknik' => 'Sınır komşusu', 'v1' => 'geçti', 'v2' => 'geçti'],
        ['id' => 'T15', 'grup' => 'Oturum', 'ad' => 'Çıkış sonrası raf', 'girdi' => 'Çıkış, sonra /urunler', 'beklenen' => 'Giriş sayfasına düşer', 'teknik' => 'Fonksiyonel', 'v1' => 'geçti', 'v2' => 'geçti'],
    ];
}

function test_kapsam(): array
{
    return [
        'edilecek' => [
            'Öğrenci girişi ve kilitli hesap',
            'Ürün listesi ve kırtasiye filtresi',
            'Sepet adedi, ara toplam, 14,90 TL teslimat',
            'Teslimat formu ve 10 haneli telefon',
            'Boş sepetle siparişin engellenmesi',
            'Çıkış sonrası korumalı sayfa',
        ],
        'edilmeyecek' => [
            'Yük / performans (İTÜ: zorlanım testi yapılmadı)',
            'Kapsamlı güvenlik ve sızma testi (TechTarget)',
            'Mobil uyumluluk ve erişilebilirlik',
            'Gerçek ödeme ve kargo entegrasyonu',
            'Birim testi (kod içi JUnit benzeri test yok; kara kutu seçildi)',
        ],
        'bitirme' => [
            'V2 üzerinde 15/15 senaryo geçer',
            'V1 ile bulunan T04 ve T12 düzeltilmiş görünür',
            'Regresyon: eski 13 senaryo V2’de bozulmaz',
        ],
    ];
}
