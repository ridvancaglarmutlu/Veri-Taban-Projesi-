<?php
declare(strict_types=1);
require __DIR__ . '/iskelet.php';
$k = test_kapsam();
tu_bas('Test süreci', 'surec');
?>
<p class="kicker">İTÜ BİDB · Yazılım testi ve test süreçleri</p>
<h1>Test, beklenen ile gözlenen farkı arar</h1>
<p class="lead">İTÜ metnine göre test; sistemi elle veya otomatik deneyerek belirlenmiş gereksinimleri doğrulama veya beklenen ile gözlenen sonuçlar arasındaki farkı bulma sürecidir. TechTarget aynı işi “işlevi değerlendir, hata ve boşluk ara, sonuç beklentiyle örtüşüyor mu bak” diye tanımlar.</p>

<section class="steps">
  <article>
    <h2>1. Test hazırlık</h2>
    <p>Analiz incelendi. Test edilecek / edilmeyecek modüller yazıldı. Bitirme ölçütü 15/15 olarak kilitlendi. Her senaryoya girdi ve beklenen çıktı verildi.</p>
  </article>
  <article>
    <h2>2. Dinamik test</h2>
    <p>Kod çalışırken kara kutu + fonksiyonel senaryolar Playwright ile koşuldu. V1’de iki fark bulundu. Düzeltme V2’de yapıldı.</p>
  </article>
  <article>
    <h2>3. Testin sonlandırılması</h2>
    <p>Bitirme ölçütü sağlandı: V2’de 15 geçti, 0 kaldı. Regresyon eski işlevleri korudu. Kapsam dışı testler raporda yazılıdır.</p>
  </article>
</section>

<div class="split">
  <section class="panel">
    <h3>Test edilecekler</h3>
    <ul><?php foreach ($k['edilecek'] as $x) echo '<li>' . htmlspecialchars($x) . '</li>'; ?></ul>
  </section>
  <section class="panel">
    <h3>Bu derste edilmeyecekler</h3>
    <ul><?php foreach ($k['edilmeyecek'] as $x) echo '<li>' . htmlspecialchars($x) . '</li>'; ?></ul>
  </section>
</div>

<section class="panel">
  <h3>Sonlandırma kriterleri</h3>
  <ul><?php foreach ($k['bitirme'] as $x) echo '<li>' . htmlspecialchars($x) . '</li>'; ?></ul>
  <p><a class="btn" href="senaryolar.php">15 senaryoyu gör</a> <a class="btn alt" href="sonuclar.php">V1 / V2 sonuçları</a></p>
</section>
<?php tu_son();
