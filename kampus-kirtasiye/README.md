# Kampüs Kırtasiye — Yazılım Testi ders projesi

Rıdvan Çağlar Mutlu · 2514216055 · Bilgisayar Programcılığı 2. sınıf

Kampüs Sepeti’nden farklı bir uygulama: öğrenci kırtasiye rafı. PHP + oturum sepeti, XAMPP üzerinde çalışır. Playwright ile 15 senaryo V1 (hatalı) ve V2 (düzeltilmiş) sürümlerinde koşulur.

## XAMPP kurulumu

1. Klasörü `C:\\xampp\\htdocs\\kampus-kirtasiye` altına kopyalayın.
2. Apache’yi başlatın.
3. V1: `http://localhost/kampus-kirtasiye/v1/`
4. V2: `http://localhost/kampus-kirtasiye/v2/`

## Demo hesaplar

| Kullanıcı | Şifre | Durum |
| --- | --- | --- |
| `ridvan` | `Defter2026` | Aktif |
| `mezun` | `Mezun2026` | Kilitli |

## Ürünler (kuruş)

| Ürün | Kategori | Fiyat |
| --- | --- | --- |
| A4 Spiralli Defter | kırtasiye | 54,90 TL |
| Uçlu Kalem 0.7 | kırtasiye | 29,90 TL |
| 24’lü Fosforlu Set | kırtasiye | 119,50 TL |
| 64GB USB Bellek | elektronik | 329,00 TL |
| Bilimsel Hesap Makinesi | elektronik | 459,90 TL |
| Klasör + Ayraç Seti | kırtasiye | 84,90 TL |

Kampüs içi teslimat: **14,90 TL**. Örnek: defter + USB = 383,90 + 14,90 = **398,80 TL**.

## V1’de bilinen hatalar

- **H01:** `kirtasiye` seçeneği bozuk `option` etiketi yüzünden listede oluşmaz (T04).
- **H02:** Boş sepetle teslimat formu açılır (T12). Sunucu tarafı kontrolü yoktur.

V2 bu iki hatayı kapatır. Diğer 13 senaryo her iki sürümde de geçer.

## Playwright

```bat
kampus-kirtasiye\\PLAYWRIGHT_AC.cmd
```

veya `tests` klasöründe:

```bat
set BASE_URL=http://localhost/kampus-kirtasiye/v2/
npx playwright test
```

Yerel PHP sunucusu (XAMPP yoksa):

```bat
php -S 127.0.0.1:8080 -t .
```

Sonra V2: `http://127.0.0.1:8080/v2/`
