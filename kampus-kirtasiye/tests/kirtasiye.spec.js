const { test, expect } = require('@playwright/test');
const { giris, teslimatFormu, bilgileriGir } = require('./yardimcilar');

test.beforeEach(async ({ page }) => {
  await page.goto('index.php?sayfa=giris');
});

test('T01 Bos giris kullanici adi uyarisi verir', async ({ page }) => {
  await page.getByTestId('login').click();
  await expect(page.getByTestId('error')).toHaveText('Kullanıcı adı boş bırakılamaz.');
  await expect(page).toHaveURL(/sayfa=giris/);
});

test('T02 Yanlis sifre reddedilir', async ({ page }) => {
  await giris(page, 'ridvan', 'yanlis123');
  await expect(page.getByTestId('error')).toHaveText('Kullanıcı adı veya şifre hatalı.');
  await expect(page).toHaveURL(/sayfa=giris/);
});

test('T03 Gecerli giris alti urun gosterir', async ({ page }) => {
  await giris(page);
  await expect(page.getByTestId('product')).toHaveCount(6);
  await expect(page.getByTestId('product-count')).toHaveText('6 ürün listeleniyor');
});

test('T04 Kirtasiye filtresi dort urun gosterir', async ({ page }) => {
  await giris(page);
  await page.getByLabel('Kategori').selectOption('kirtasiye');
  await expect(page).toHaveURL(/kategori=kirtasiye/);
  await expect(page.getByTestId('product')).toHaveCount(4);
  await expect(page.getByTestId('product-count')).toHaveText('4 ürün listeleniyor');
});

test('T05 Defter eklenince 1 adet ve 54,90 TL gorunur', async ({ page }) => {
  await giris(page);
  await page.getByTestId('add-1').click();
  await expect(page.getByTestId('cart-item')).toHaveCount(1);
  await expect(page.getByTestId('qty')).toHaveText('Adet: 1');
  await expect(page.getByTestId('line-total')).toHaveText('54,90 TL');
  await expect(page.getByTestId('subtotal')).toHaveText('54,90 TL');
});

test('T06 Defter kaldirilinca sepet bosalir', async ({ page }) => {
  await giris(page);
  await page.getByTestId('add-1').click();
  await page.getByTestId('remove-1').click();
  await expect(page.getByTestId('cart-item')).toHaveCount(0);
  await expect(page.getByTestId('total')).toHaveText('0,00 TL');
});

test('T07 Bos teslimat formu ad uyarisi verir', async ({ page }) => {
  await teslimatFormu(page);
  await page.getByTestId('to-summary').click();
  await expect(page.getByTestId('error')).toHaveText('Ad alanı zorunludur.');
  await expect(page).toHaveURL(/sayfa=teslimat/);
});

test('T08 Kilitli hesap girisi reddedilir', async ({ page }) => {
  await giris(page, 'mezun', 'Mezun2026');
  await expect(page.getByTestId('error')).toHaveText('Bu hesap kilitlidir. Öğrenci işlerine başvurun.');
  await expect(page).toHaveURL(/sayfa=giris/);
});

test('T09 Bos sifre uyarisi verir', async ({ page }) => {
  await page.getByTestId('user').fill('ridvan');
  await page.getByTestId('login').click();
  await expect(page.getByTestId('error')).toHaveText('Şifre alanı zorunludur.');
});

test('T10 Iki urunun toplami teslimat ile 398,80 TL olur', async ({ page }) => {
  await giris(page);
  await page.getByTestId('add-1').click();
  await page.goto('index.php?sayfa=urunler');
  await page.getByTestId('add-4').click();
  await expect(page.getByTestId('subtotal')).toHaveText('383,90 TL');
  await expect(page.getByTestId('shipping')).toHaveText('14,90 TL');
  await expect(page.getByTestId('total')).toHaveText('398,80 TL');
});

test('T11 On haneli telefon ile siparis tamamlanir', async ({ page }) => {
  await teslimatFormu(page);
  await bilgileriGir(page, '0555123456');
  await expect(page).toHaveURL(/sayfa=onay/);
  await page.getByTestId('place-order').click();
  await expect(page.getByTestId('thanks')).toHaveText('Kırtasiye siparişiniz kaydedildi.');
  await expect(page.getByTestId('order-no')).toHaveText(/KK-/);
});

test('T12 Bos sepet teslimata gecemez', async ({ page }) => {
  await giris(page);
  await page.getByTestId('cart-link').click();
  await expect(page.getByTestId('cart-item')).toHaveCount(0);
  await page.getByTestId('checkout').click();
  await expect(page).toHaveURL(/sayfa=sepet/);
  await expect(page.getByTestId('error')).toHaveText(
    'Sepetiniz boş. Teslimata geçmeden önce bir ürün ekleyin.'
  );
});

test('T13 Dokuz haneli telefon reddedilir', async ({ page }) => {
  await teslimatFormu(page);
  await bilgileriGir(page, '055512345');
  await expect(page.getByTestId('error')).toHaveText('Telefon numarası 10 rakamdan oluşmalıdır.');
  await expect(page).toHaveURL(/sayfa=teslimat/);
});

test('T14 On bir haneli telefon reddedilir', async ({ page }) => {
  await teslimatFormu(page);
  await bilgileriGir(page, '05551234567');
  await expect(page.getByTestId('error')).toHaveText('Telefon numarası 10 rakamdan oluşmalıdır.');
  await expect(page).toHaveURL(/sayfa=teslimat/);
});

test('T15 Cikis sonrasi urunler girise duser', async ({ page }) => {
  await giris(page);
  await page.getByRole('link', { name: 'Çıkış' }).click();
  await page.goto('index.php?sayfa=urunler');
  await expect(page).toHaveURL(/sayfa=giris/);
  await expect(page.getByTestId('error')).toHaveText('Bu sayfa için önce giriş yapmalısınız.');
});
