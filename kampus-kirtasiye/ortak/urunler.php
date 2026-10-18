<?php

declare(strict_types=1);

function kirtasiye_urunler(): array
{
    return [
        1 => [
            'ad' => 'A4 Spiralli Defter',
            'etiket' => 'Ders notu',
            'kategori' => 'kirtasiye',
            'fiyat' => 5490,
            'ikon' => 'defter',
        ],
        2 => [
            'ad' => 'Uçlu Kalem 0.7',
            'etiket' => 'Yazım',
            'kategori' => 'kirtasiye',
            'fiyat' => 2990,
            'ikon' => 'kalem',
        ],
        3 => [
            'ad' => '24\'lü Fosforlu Set',
            'etiket' => 'İşaretleme',
            'kategori' => 'kirtasiye',
            'fiyat' => 11950,
            'ikon' => 'fosfor',
        ],
        4 => [
            'ad' => '64GB USB Bellek',
            'etiket' => 'Dosya',
            'kategori' => 'elektronik',
            'fiyat' => 32900,
            'ikon' => 'usb',
        ],
        5 => [
            'ad' => 'Bilimsel Hesap Makinesi',
            'etiket' => 'Sınav',
            'kategori' => 'elektronik',
            'fiyat' => 45990,
            'ikon' => 'hesap',
        ],
        6 => [
            'ad' => 'Klasör + Ayraç Seti',
            'etiket' => 'Düzen',
            'kategori' => 'kirtasiye',
            'fiyat' => 8490,
            'ikon' => 'klasor',
        ],
    ];
}

function kirtasiye_kullanicilar(): array
{
    return [
        'ridvan' => [
            'sifre' => 'Defter2026',
            'durum' => 'aktif',
        ],
        'mezun' => [
            'sifre' => 'Mezun2026',
            'durum' => 'kilitli',
        ],
    ];
}

function teslimat_ucreti_kurus(): int
{
    return 1490;
}
