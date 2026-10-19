<?php
declare(strict_types=1);
require_once __DIR__ . '/veriler.php';

function tu_bas(string $baslik, string $aktif = 'surec'): void
{
    header('Content-Type: text/html; charset=utf-8');
    $baglar = [
        'surec' => ['index.php', 'Süreç'],
        'senaryo' => ['senaryolar.php', 'Senaryolar'],
        'sonuc' => ['sonuclar.php', 'Sonuçlar'],
        'oto' => ['otomasyon.php', 'Otomasyon'],
    ];
    echo '<!DOCTYPE html><html lang="tr"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . htmlspecialchars($baslik) . ' · Test uygulaması</title>';
    echo '<link rel="stylesheet" href="stil.css"></head><body>';
    echo '<header class="bar"><div class="wrap row">';
    echo '<a class="brand" href="index.php"><span>TU</span> Kampüs Kırtasiye test uygulaması</a><nav>';
    foreach ($baglar as $k => [$href, $ad]) {
        $cls = $k === $aktif ? ' class="on"' : '';
        echo '<a' . $cls . ' href="' . $href . '">' . $ad . '</a>';
    }
    echo '<a href="../index.php">Teslim</a>';
    echo '</nav></div></header><main class="wrap">';
}

function tu_son(): void
{
    echo '</main><footer class="wrap foot">İTÜ BİDB test süreci + TechTarget yazılım testi tanımı · Rıdvan Çağlar Mutlu</footer></body></html>';
}
