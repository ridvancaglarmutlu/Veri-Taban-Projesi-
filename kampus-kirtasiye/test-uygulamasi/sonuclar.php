<?php
declare(strict_types=1);
require __DIR__ . '/iskelet.php';
tu_bas('Sonuçlar', 'sonuc');
$gecen1 = count(array_filter(test_senaryolari(), fn($s) => $s['v1'] === 'geçti'));
$gecen2 = count(array_filter(test_senaryolari(), fn($s) => $s['v2'] === 'geçti'));
?>
<p class="kicker">Dinamik test + regresyon</p>
<h1>Önce fark bulundu, sonra aynı test yeniden koşuldu</h1>
<p class="lead">TechTarget: yazılım testi hataları canlıya çıkmadan bulur. İTÜ: regresyon, düzeltmeden sonra eski sorunların kapandığını ve yeni hata oluşmadığını doğrular.</p>
<div class="kpi">
  <div><strong>V1</strong><span><?= $gecen1 ?> / 15 geçti</span><small>T04 ve T12 kaldı · 33,9 sn</small></div>
  <div><strong>V2</strong><span><?= $gecen2 ?> / 15 geçti</span><small>0 kaldı · 7,8 sn</small></div>
</div>
<table>
  <thead><tr><th>ID</th><th>Senaryo</th><th>V1</th><th>V2</th></tr></thead>
  <tbody>
  <?php foreach (test_senaryolari() as $s): ?>
    <tr>
      <td><?= htmlspecialchars($s['id']) ?></td>
      <td><?= htmlspecialchars($s['ad']) ?></td>
      <td class="<?= $s['v1'] === 'geçti' ? 'ok' : 'bad' ?>"><?= htmlspecialchars($s['v1']) ?></td>
      <td class="<?= $s['v2'] === 'geçti' ? 'ok' : 'bad' ?>"><?= htmlspecialchars($s['v2']) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<section class="panel">
  <h3>Bulunan farklar</h3>
  <p><strong>H01 / T04:</strong> <code>kirtasiye</code> seçeneği bozuk <code>option</code> yüzünden listede yoktu. Beklenen 4 ürün, gözlenen seçenek yok.</p>
  <p><strong>H02 / T12:</strong> Boş sepet teslimat formunu açtı. Beklenen uyarı, gözlenen teslimat sayfası.</p>
</section>
<?php tu_son();
