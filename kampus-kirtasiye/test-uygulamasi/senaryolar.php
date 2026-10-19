<?php
declare(strict_types=1);
require __DIR__ . '/iskelet.php';
tu_bas('Senaryolar', 'senaryo');
?>
<p class="kicker">Test case · girdi / beklenen çıktı</p>
<h1>15 kara kutu senaryosu</h1>
<p class="lead">İTÜ hazırlık maddesi: “belirli girdiler verildiğinde hangi çıkışların alınması gerektiğini bildiren test case senaryoları.” TechTarget bunu fonksiyonel / kara kutu test diye adlandırır.</p>
<table>
  <thead>
    <tr><th>ID</th><th>Grup</th><th>Senaryo</th><th>Girdi</th><th>Beklenen çıktı</th><th>Teknik</th></tr>
  </thead>
  <tbody>
  <?php foreach (test_senaryolari() as $s): ?>
    <tr>
      <td><?= htmlspecialchars($s['id']) ?></td>
      <td><?= htmlspecialchars($s['grup']) ?></td>
      <td><?= htmlspecialchars($s['ad']) ?></td>
      <td><?= htmlspecialchars($s['girdi']) ?></td>
      <td><?= htmlspecialchars($s['beklenen']) ?></td>
      <td><?= htmlspecialchars($s['teknik']) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<p class="note">Otomasyon dosyası: <code>tests/kirtasiye.spec.js</code>. Her satır bir Playwright testi ile aynı beklenen metne bağlıdır.</p>
<?php tu_son();
