<?php
declare(strict_types=1);
require __DIR__ . '/iskelet.php';
tu_bas('Otomasyon', 'oto');
?>
<p class="kicker">TechTarget · What can be automated</p>
<h1>Test uygulaması siteyi tarayıcıdan dener</h1>
<p class="lead">Elle 15 senaryoyu her düzeltmede tekrar etmek yavaştır. Playwright, aynı girdileri V1 ve V2’ye otomatik yollar; beklenen metin ve adres ile gözlenen sonucu karşılaştırır.</p>
<div class="split">
  <section class="panel">
    <h3>Nasıl çalıştırılır?</h3>
    <ol>
      <li>XAMPP Apache açık olsun.</li>
      <li><code>kampus-kirtasiye\PLAYWRIGHT_AC.cmd</code> dosyasına çift tıkla.</li>
      <li>Veya <code>tests</code> klasöründe:</li>
    </ol>
    <pre>set BASE_URL=http://localhost/kampus-kirtasiye/v2/
npx playwright test --ui</pre>
    <p>V1 için adresi <code>.../v1/</code> yap. Aynı 15 test, iki sürüme de uygulanır.</p>
  </section>
  <section class="panel">
    <h3>Neden otomatik?</h3>
    <ul>
      <li>Aynı senaryo yüzlerce kez tekrarlanır (regresyon).</li>
      <li>Hata anında ekran görüntüsü ve trace kalır.</li>
      <li>Girdi / beklenen çıktı kodda kilitlidir; ezbere bağlı değildir.</li>
    </ul>
    <p><a class="btn" href="../v2/index.php?sayfa=giris">Sınanan siteyi aç</a></p>
  </section>
</div>
<section class="panel">
  <h3>Örnek otomatik adım (T12)</h3>
  <pre>await giris(page);
await page.getByTestId('cart-link').click();
await expect(page.getByTestId('cart-item')).toHaveCount(0);
await page.getByTestId('checkout').click();
await expect(page).toHaveURL(/sayfa=sepet/);
await expect(page.getByTestId('error'))
  .toHaveText('Sepetiniz boş. Teslimata geçmeden önce bir ürün ekleyin.');</pre>
</section>
<?php tu_son();
