<?php
declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Kampüs Kırtasiye · İki parça teslim</title>
  <link rel="stylesheet" href="giris-stil.css">
</head>
<body>
  <header class="top">
    <div class="wrap">
      <p class="kicker">Yazılım Testi dersi · 18 Ekim 2026</p>
      <h1>İki parça teslim</h1>
      <p class="lead">Önce test edilecek web sitesi yapılır. Sonra bu siteyi sınayan ayrı bir test uygulaması çalıştırılır. Süreç İTÜ BİDB ve TechTarget kaynaklarına göre kurulmuştur.</p>
    </div>
  </header>
  <main class="wrap">
    <div class="grid">
      <article class="card">
        <span class="no">01</span>
        <h2>Web sitesi</h2>
        <p>Kampüs Kırtasiye: öğrenci girişi, 6 ürün, sepet, teslimat ve demo sipariş. Teslim edilen sürüm V2’dir. V1, bulunan iki hatayı göstermek için durur.</p>
        <a class="btn" href="v2/index.php?sayfa=giris">Siteyi aç (V2)</a>
        <a class="btn alt" href="v1/index.php?sayfa=giris">Hatalı V1</a>
      </article>
      <article class="card">
        <span class="no">02</span>
        <h2>Test uygulaması</h2>
        <p>Hazırlık, kara kutu senaryoları, beklenen çıktılar, V1/V2 sonuçları ve Playwright otomasyonu. Siteyi tarayıcıdan otomatik dener.</p>
        <a class="btn" href="test-uygulamasi/index.php">Test uygulamasını aç</a>
      </article>
      <article class="card full">
        <h2>Kuramsal kaynaklar</h2>
        <p>
          <a href="https://bidb.itu.edu.tr/seyir-defteri/blog/2013/09/08/yaz%C4%B1l%C4%B1m-testi-ve-test-s%C3%BCre%C3%A7leri">İTÜ BİDB — Yazılım Testi ve Test Süreçleri</a>
          ·
          <a href="https://www.techtarget.com/whatis/definition/software-testing">TechTarget — Software testing</a>
        </p>
        <p class="note">Giriş: ridvan / Defter2026 · Kilitli hesap: mezun / Mezun2026 · Rıdvan Çağlar Mutlu · 2514216055</p>
      </article>
    </div>
  </main>
</body>
</html>
