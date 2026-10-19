# Kampüs Kırtasiye — Yazılım Testi ders projesi

Rıdvan Çağlar Mutlu · 2514216055

Teslim **iki parçadır**:

1. **Web sitesi** — Kampüs Kırtasiye (öğrenci rafı)
2. **Test uygulaması** — bu siteyi İTÜ BİDB + TechTarget sürecine göre sınar

Kaynaklar:

- https://bidb.itu.edu.tr/seyir-defteri/blog/2013/09/08/yazılım-testi-ve-test-süreçleri
- https://www.techtarget.com/whatis/definition/software-testing

## XAMPP

Klasör: `C:\xampp\htdocs\kampus-kirtasiye`

- Kapı: http://localhost/kampus-kirtasiye/
- Site V2: http://localhost/kampus-kirtasiye/v2/
- Site V1: http://localhost/kampus-kirtasiye/v1/
- Test uygulaması: http://localhost/kampus-kirtasiye/test-uygulamasi/

Giriş: `ridvan` / `Defter2026`

## Otomasyon

`PLAYWRIGHT_AC.cmd` veya `tests` klasöründe:

```bat
set BASE_URL=http://localhost/kampus-kirtasiye/v2/
npx playwright test --ui
```

V2: 15/15 geçer. V1: T04 ve T12 kalır.
