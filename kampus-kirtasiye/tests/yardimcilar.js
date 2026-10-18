async function giris(page, kullanici = 'ridvan', sifre = 'Defter2026') {
  await page.goto('index.php?sayfa=giris');
  await page.getByTestId('user').fill(kullanici);
  await page.getByTestId('pass').fill(sifre);
  await page.getByTestId('login').click();
}

async function teslimatFormu(page) {
  await giris(page);
  await page.getByTestId('add-1').click();
  await page.getByTestId('checkout').click();
}

async function bilgileriGir(page, telefon, ad = 'R?dvan', soyad = 'Mutlu') {
  await page.getByTestId('first').fill(ad);
  await page.getByTestId('last').fill(soyad);
  await page.getByTestId('phone').fill(telefon);
  await page.getByTestId('to-summary').click();
}

module.exports = { giris, teslimatFormu, bilgileriGir };
