#!/usr/bin/env python3
from pathlib import Path

from pptx import Presentation
from pptx.dml.color import RGBColor
from pptx.enum.shapes import MSO_SHAPE
from pptx.enum.text import PP_ALIGN
from pptx.util import Inches, Pt

NAVY = RGBColor(0x16, 0x25, 0x44)
NAVY2 = RGBColor(0x24, 0x3B, 0x6B)
TERR = RGBColor(0xC4, 0x5C, 0x26)
PAPER = RGBColor(0xF4, 0xEF, 0xE4)
WHITE = RGBColor(0xFF, 0xFF, 0xFF)
INK = RGBColor(0x1B, 0x24, 0x30)
MUTED = RGBColor(0x5C, 0x55, 0x4C)
OK = RGBColor(0x2F, 0x6B, 0x4F)
BAD = RGBColor(0x8A, 0x1F, 0x1F)
CODE_BG = RGBColor(0x1B, 0x24, 0x30)

W = Inches(13.333)
H = Inches(7.5)
HERE = Path(__file__).resolve().parent
SHOTS = HERE / "gorseller"
OUT = HERE / "Kampus_Kirtasiye_Yazilim_Testi_Sunumu.pptx"


def set_run(run, size=18, bold=False, color=INK, font="Calibri"):
    run.font.size = Pt(size)
    run.font.bold = bold
    run.font.color.rgb = color
    run.font.name = font


def add_box(slide, l, t, w, h, fill, line=None):
    sh = slide.shapes.add_shape(MSO_SHAPE.RECTANGLE, l, t, w, h)
    sh.line.fill.background()
    if line is None:
        sh.line.fill.background()
    sh.fill.solid()
    sh.fill.fore_color.rgb = fill
    return sh


def add_round(slide, l, t, w, h, fill):
    sh = slide.shapes.add_shape(MSO_SHAPE.ROUNDED_RECTANGLE, l, t, w, h)
    sh.adjustments[0] = 0.08
    sh.line.fill.background()
    sh.fill.solid()
    sh.fill.fore_color.rgb = fill
    return sh


def add_text(slide, l, t, w, h, text, size=18, bold=False, color=INK, align=PP_ALIGN.LEFT, font="Calibri"):
    box = slide.shapes.add_textbox(l, t, w, h)
    tf = box.text_frame
    tf.word_wrap = True
    p = tf.paragraphs[0]
    p.alignment = align
    run = p.add_run()
    run.text = text
    set_run(run, size, bold, color, font)
    return box


def footer(slide, no):
    add_box(slide, 0, Inches(7.22), W, Inches(0.28), NAVY)
    add_text(slide, Inches(0.4), Inches(7.22), Inches(10), Inches(0.28),
             "Kampüs Kırtasiye  ·  Yazılım Testi  ·  Rıdvan Çağlar Mutlu", 11, False, PAPER)
    add_text(slide, Inches(11.6), Inches(7.22), Inches(1.4), Inches(0.28),
             f"{no:02d}", 11, True, TERR, PP_ALIGN.RIGHT)


def chrome(slide, no, title, kicker=None):
    add_box(slide, 0, 0, W, H, PAPER)
    add_box(slide, 0, 0, W, Inches(0.12), TERR)
    add_box(slide, 0, Inches(0.12), W, Inches(0.92), NAVY)
    add_text(slide, Inches(0.45), Inches(0.28), Inches(11), Inches(0.6), title, 26, True, WHITE)
    footer(slide, no)


def bullets(slide, l, t, w, items, size=18):
    box = slide.shapes.add_textbox(l, t, w, Inches(5.4))
    tf = box.text_frame
    tf.word_wrap = True
    for i, item in enumerate(items):
        p = tf.paragraphs[0] if i == 0 else tf.add_paragraph()
        p.level = 0
        p.space_after = Pt(10)
        run = p.add_run()
        run.text = "▸  " + item
        set_run(run, size, False, INK)


def table_slide(slide, rows, l=Inches(0.45), t=Inches(1.3), w=Inches(12.4), h=Inches(4.8)):
    tbl = slide.shapes.add_table(len(rows), len(rows[0]), l, t, w, h).table
    for r, row in enumerate(rows):
        for c, val in enumerate(row):
            cell = tbl.cell(r, c)
            cell.text = ""
            p = cell.text_frame.paragraphs[0]
            run = p.add_run()
            run.text = val
            header = r == 0
            set_run(run, 13 if not header else 13, header, WHITE if header else INK)
            fill = NAVY if header else (WHITE if r % 2 else RGBColor(0xEB, 0xE4, 0xD6))
            cell.fill.solid()
            cell.fill.fore_color.rgb = fill
    return tbl


def code_box(slide, l, t, w, h, text):
    add_round(slide, l, t, w, h, CODE_BG)
    box = slide.shapes.add_textbox(l + Inches(0.2), t + Inches(0.15), w - Inches(0.35), h - Inches(0.25))
    tf = box.text_frame
    tf.word_wrap = True
    p = tf.paragraphs[0]
    run = p.add_run()
    run.text = text
    set_run(run, 13, False, RGBColor(0xF4, 0xE8, 0xD0), "Consolas")


def picture(slide, path, l, t, w, h):
    if Path(path).exists():
        slide.shapes.add_picture(str(path), l, t, w, h)


def build():
    prs = Presentation()
    prs.slide_width = W
    prs.slide_height = H
    blank = prs.slide_layouts[6]

    # 1 cover
    s = prs.slides.add_slide(blank)
    add_box(s, 0, 0, W, H, NAVY)
    add_box(s, 0, 0, Inches(0.22), H, TERR)
    add_text(s, Inches(0.7), Inches(1.3), Inches(11), Inches(0.4),
             "YEREL WEB SİTESİ TESTİ", 16, True, TERR)
    add_text(s, Inches(0.7), Inches(1.8), Inches(12), Inches(1.4),
             "Kampüs Kırtasiye", 54, True, WHITE)
    add_text(s, Inches(0.7), Inches(3.3), Inches(11), Inches(0.8),
             "XAMPP üzerinde PHP kırtasiye rafı  ·  Playwright ile 15 senaryo", 22, False, PAPER)
    add_text(s, Inches(0.7), Inches(4.3), Inches(11), Inches(1.2),
             "Hata ayıklama, düzeltme ve regresyon\nRıdvan Çağlar Mutlu  ·  2514216055\nBilgisayar Programcılığı 2. Sınıf  ·  Yazılım Testi  ·  18 Ekim 2026",
             18, False, RGBColor(0xC9, 0xD0, 0xDC))
    add_text(s, Inches(12.2), Inches(6.7), Inches(0.8), Inches(0.4), "01", 16, True, TERR, PP_ALIGN.RIGHT)

    # 2 scope
    s = prs.slides.add_slide(blank)
    chrome(s, 2, "Projenin teslim kapsamı")
    bullets(s, Inches(0.55), Inches(1.4), Inches(12), [
        "Kaynak kod: PHP, HTML ve CSS (Kampüs Kırtasiye)",
        "Çalışan site: http://localhost/kampus-kirtasiye/v2/",
        "Hatalı karşılaştırma sürümü: http://localhost/kampus-kirtasiye/v1/",
        "Test uygulaması: Playwright (1 worker, 0 retry)",
        "Teslim: site, test kodu, V1/V2 sonuçları ve bu sunum",
    ], 20)

    # 3 app
    s = prs.slides.add_slide(blank)
    chrome(s, 3, "Kampüs Kırtasiye uygulaması")
    cards = [
        ("6 ürün", "Kırtasiye + küçük elektronik"),
        ("Giriş ve sepet", "ridvan / Defter2026"),
        ("Teslimat formu", "10 haneli telefon"),
        ("Demo sipariş", "Gerçek ödeme yok"),
    ]
    for i, (a, b) in enumerate(cards):
        x = Inches(0.45 + i * 3.2)
        add_round(s, x, Inches(2.0), Inches(3.0), Inches(3.2), WHITE)
        add_box(s, x, Inches(2.0), Inches(3.0), Inches(0.12), TERR)
        add_text(s, x + Inches(0.2), Inches(2.4), Inches(2.6), Inches(1.2), a, 22, True, NAVY)
        add_text(s, x + Inches(0.2), Inches(3.6), Inches(2.6), Inches(1.2), b, 16, False, MUTED)

    # 4 arch
    s = prs.slides.add_slide(blank)
    chrome(s, 4, "XAMPP ve uygulama mimarisi")
    bullets(s, Inches(0.55), Inches(1.4), Inches(12), [
        "Kurulum: C:/xampp/htdocs/kampus-kirtasiye",
        "Sunucu: Apache + PHP 8  (bu ortamda PHP 8.3 ile doğrulandı)",
        "Veri: Ürünler PHP dizisinde, sepet $_SESSION içinde",
        "Para: Fiyatlar kuruş (int). Teslimat her zaman 14,90 TL",
        "Akış: Tarayıcı isteği → Apache/PHP → HTML yanıtı",
    ], 20)

    # 5 rules
    s = prs.slides.add_slide(blank)
    chrome(s, 5, "Test kuralları ve bitirme ölçütü")
    bullets(s, Inches(0.55), Inches(1.4), Inches(12), [
        "Giriş: Doğru bilgi kabul, hatalı / kilitli hesap ret",
        "Sepet: Doğru ürün, adet, ara toplam ve teslimat",
        "Form: Ad, soyad zorunlu; telefon tam 10 rakam",
        "Sipariş: Boş sepet teslimata geçemez; ürünlü sepet geçer",
        "Filtre: Kırtasiye kategorisi yalnızca 4 ürün göstermelidir",
    ], 20)

    # 6 techniques
    s = prs.slides.add_slide(blank)
    chrome(s, 6, "Test teknikleri ve test verileri")
    table_slide(s, [
        ["Teknik", "Girdi", "Beklenti"],
        ["Pozitif test", "ridvan / Defter2026", "Giriş kabul, 6 ürün"],
        ["Negatif test", "Yanlış şifre / boş alan / mezun", "Uyarı ve giriş engeli"],
        ["Sınır komşuları", "055512345 / 0555123456 / 05551234567", "Ret / kabul / ret"],
        ["Regresyon", "Düzeltme sonrası aynı 15 test", "Eski işlevler korunur"],
    ], h=Inches(4.2))

    # 7-9 scenarios
    s = prs.slides.add_slide(blank)
    chrome(s, 7, "Senaryolar: Giriş ve raf")
    table_slide(s, [
        ["ID", "Senaryo", "Beklenen", "V1", "V2"],
        ["T01", "Boş giriş", "Kullanıcı adı uyarısı", "Geçti", "Geçti"],
        ["T02", "Yanlış şifre", "Giriş reddi", "Geçti", "Geçti"],
        ["T03", "Geçerli giriş", "6 ürün", "Geçti", "Geçti"],
        ["T04", "Kırtasiye filtresi", "4 ürün", "Başarısız", "Geçti"],
        ["T05", "Defter ekleme", "1 adet, 54,90 TL", "Geçti", "Geçti"],
    ], h=Inches(4.2))

    s = prs.slides.add_slide(blank)
    chrome(s, 8, "Senaryolar: Sepet ve form")
    table_slide(s, [
        ["ID", "Senaryo", "Beklenen", "V1", "V2"],
        ["T06", "Defteri kaldır", "0 ürün, 0,00 TL", "Geçti", "Geçti"],
        ["T07", "Boş teslimat formu", "Ad uyarısı", "Geçti", "Geçti"],
        ["T08", "Kilitli hesap (mezun)", "Giriş reddi", "Geçti", "Geçti"],
        ["T09", "Boş şifre", "Şifre uyarısı", "Geçti", "Geçti"],
        ["T10", "Defter + USB + kargo", "398,80 TL", "Geçti", "Geçti"],
    ], h=Inches(4.2))

    s = prs.slides.add_slide(blank)
    chrome(s, 9, "Senaryolar: Sipariş ve sınırlar")
    table_slide(s, [
        ["ID", "Senaryo", "Beklenen", "V1", "V2"],
        ["T11", "10 haneli telefon", "Sipariş tamamlandı", "Geçti", "Geçti"],
        ["T12", "Boş sepet", "Teslimat engeli", "Başarısız", "Geçti"],
        ["T13", "9 haneli telefon", "Formda uyarı", "Geçti", "Geçti"],
        ["T14", "11 haneli telefon", "Formda uyarı", "Geçti", "Geçti"],
        ["T15", "Çıkış sonrası raf", "Giriş sayfası", "Geçti", "Geçti"],
    ], h=Inches(4.2))

    # 10 playwright config
    s = prs.slides.add_slide(blank)
    chrome(s, 10, "Playwright yerel siteye nasıl bağlanır?")
    code_box(s, Inches(0.45), Inches(1.3), Inches(12.4), Inches(4.5),
             "use: {\n"
             "  baseURL: 'http://localhost/kampus-kirtasiye/v2/',\n"
             "  channel: 'chrome',\n"
             "  viewport: { width: 1366, height: 900 },\n"
             "  screenshot: 'only-on-failure',\n"
             "  trace: 'retain-on-failure'\n"
             "}\n\n"
             "test.beforeEach(async ({ page }) => {\n"
             "  await page.goto('index.php?sayfa=giris');\n"
             "});")
    add_text(s, Inches(0.5), Inches(6.0), Inches(12), Inches(0.6),
             "Aynı 15 test V1 ve V2 üzerinde, 1 worker ve 0 retry ile çalıştı.", 16, False, MUTED)

    # 11 login test
    s = prs.slides.add_slide(blank)
    chrome(s, 11, "Giriş testi ve doğrulama")
    code_box(s, Inches(0.45), Inches(1.3), Inches(12.4), Inches(3.6),
             "test('T02 Yanlis sifre reddedilir', async ({ page }) => {\n"
             "  await giris(page, 'ridvan', 'yanlis123');\n"
             "  await expect(page.getByTestId('error'))\n"
             "    .toHaveText('Kullanıcı adı veya şifre hatalı.');\n"
             "  await expect(page).toHaveURL(/sayfa=giris/);\n"
             "});")
    add_text(s, Inches(0.5), Inches(5.2), Inches(12), Inches(1.2),
             "Tıklama işlemdir. expect, işlemin doğru sonuç verdiğini kontrol eder.", 18, False, INK)

    # 12 filter
    s = prs.slides.add_slide(blank)
    chrome(s, 12, "Kategori filtresini kodla kontrol etme")
    code_box(s, Inches(0.45), Inches(1.3), Inches(12.4), Inches(3.8),
             "await page.getByLabel('Kategori').selectOption('kirtasiye');\n"
             "await expect(page).toHaveURL(/kategori=kirtasiye/);\n"
             "await expect(page.getByTestId('product')).toHaveCount(4);\n"
             "await expect(page.getByTestId('product-count'))\n"
             "  .toHaveText('4 ürün listeleniyor');\n")
    add_text(s, Inches(0.5), Inches(5.4), Inches(12), Inches(1.2),
             "Filtre menüsünün durması yetmez; listelenen ürün sayısı da 4 olmalıdır.", 18, False, INK)

    # 13 phone
    s = prs.slides.add_slide(blank)
    chrome(s, 13, "Telefon: PHP kuralı ve sınır testi")
    code_box(s, Inches(0.45), Inches(1.3), Inches(12.4), Inches(4.0),
             "// PHP: teslimat formu\n"
             "if (!preg_match('/^[0-9]{10}$/D', $telefon)) {\n"
             "    $error = 'Telefon numarası 10 rakamdan oluşmalıdır.';\n"
             "}\n\n"
             "// Playwright: üst sınırın komşusu\n"
             "await bilgileriGir(page, '05551234567');\n"
             "await expect(page.getByTestId('error'))\n"
             "  .toHaveText('Telefon numarası 10 rakamdan oluşmalıdır.');")
    add_text(s, Inches(0.5), Inches(5.6), Inches(12), Inches(0.8),
             "9 hane: ret  /  10 hane: kabul  /  11 hane: ret", 18, True, TERR)

    # 14 money
    s = prs.slides.add_slide(blank)
    chrome(s, 14, "Para hesabı: Tam sayı kuruş + teslimat")
    code_box(s, Inches(0.45), Inches(1.3), Inches(12.4), Inches(4.2),
             "// PHP ürün kataloğu\n"
             "1 => ['ad' => 'A4 Spiralli Defter', 'fiyat' => 5490],\n"
             "4 => ['ad' => '64GB USB Bellek', 'fiyat' => 32900],\n"
             "// teslimat_ucreti_kurus() = 1490\n\n"
             "// Playwright: T10\n"
             "expect(araToplam).toBe(5490 + 32900);   // 38390\n"
             "expect(teslimat).toBe(1490);\n"
             "expect(toplam).toBe(39880);\n"
             "await expect(page.getByTestId('total')).toHaveText('398,80 TL');")
    add_text(s, Inches(0.5), Inches(5.7), Inches(12), Inches(0.7),
             "5490 + 32900 + 1490 = 39880 kuruş = 398,80 TL", 18, True, TERR)

    # 15 V1 results
    s = prs.slides.add_slide(blank)
    chrome(s, 15, "V1 sonuçları: İki başarısız test")
    add_round(s, Inches(0.45), Inches(1.4), Inches(3.8), Inches(2.2), WHITE)
    add_text(s, Inches(0.7), Inches(1.6), Inches(3.4), Inches(0.8), "13 geçti / 2 başarısız", 22, True, BAD)
    add_text(s, Inches(0.7), Inches(2.4), Inches(3.4), Inches(0.9), "T04 ve T12 düştü.\nDiğer 13 senaryo yeşil.", 15, False, MUTED)
    bullets(s, Inches(4.6), Inches(1.4), Inches(8), [
        "T04: kirtasiye seçeneği tarayıcıda oluşmadı (bozuk option).",
        "T12: 0 ürünle teslimat formu açıldı.",
        "Kanıt: hata metni, ekran görüntüsü ve trace.",
        "Süre ~34 sn; bunun bir bölümü T04’ün seçenek beklemesi.",
    ], 18)

    # 16 H01
    s = prs.slides.add_slide(blank)
    chrome(s, 16, "H01: Yanlış kapanan option etiketi")
    code_box(s, Inches(0.45), Inches(1.3), Inches(12.4), Inches(4.0),
             "<!-- V1: kırtasiye seçeneği açılmıyor -->\n"
             "</option value=\"kirtasiye\">Yalnız kırtasiye\n\n"
             "<!-- V2: önceki seçenek kapanır, yenisi açılır -->\n"
             "<option value=\"kirtasiye\">Yalnız kırtasiye</option>\n\n"
             "// Değişmeyen test adımı\n"
             "await page.getByLabel('Kategori').selectOption('kirtasiye');")
    add_text(s, Inches(0.5), Inches(5.6), Inches(12), Inches(0.8),
             "V1: T04 başarısız  /  HTML düzeltmesinden sonra V2: T04 geçti", 18, True, NAVY)

    # 17 H02 screens
    s = prs.slides.add_slide(blank)
    chrome(s, 17, "H02: Boş sepetle teslimat formuna geçiş")
    picture(s, SHOTS / "v1-bos-teslimat.png", Inches(0.4), Inches(1.25), Inches(6.2), Inches(4.1))
    picture(s, SHOTS / "sepet-bos-v2.png", Inches(6.75), Inches(1.25), Inches(6.2), Inches(4.1))
    add_text(s, Inches(0.4), Inches(5.45), Inches(6.2), Inches(0.5), "V1: Teslimat formu açıldı", 16, True, BAD)
    add_text(s, Inches(6.75), Inches(5.45), Inches(6.2), Inches(0.5), "V2: Sepette kalınır, uyarı çıkar", 16, True, OK)

    # 18 H02 fix
    s = prs.slides.add_slide(blank)
    chrome(s, 18, "H02 düzeltmesi: PHP tarafında kontrol")
    code_box(s, Inches(0.45), Inches(1.3), Inches(12.4), Inches(4.2),
             "if (in_array($sayfa, ['teslimat', 'onay'], true)\n"
             "    && sepet_adet() === 0) {\n"
             "    flash('Sepetiniz boş. Teslimata geçmeden önce bir ürün ekleyin.');\n"
             "    git('sepet');\n"
             "}\n\n"
             "// Aynı kontrol teslimata_gec ve siparis POST işlemine de eklendi.\n"
             "// Kontrol yalnızca tarayıcı düğmesine bırakılmadı.")
    add_text(s, Inches(0.5), Inches(5.7), Inches(12), Inches(0.8),
             "Düzeltme site kodunda yapıldı; testin beklediği sonuç korunarak yeniden çalıştırıldı.", 16, False, INK)

    # 19 T12
    s = prs.slides.add_slide(blank)
    chrome(s, 19, "T12: Düzeltmeyi aynı testle doğrulama")
    code_box(s, Inches(0.45), Inches(1.3), Inches(12.4), Inches(4.3),
             "await giris(page);\n"
             "await page.getByTestId('cart-link').click();\n"
             "await expect(page.getByTestId('cart-item')).toHaveCount(0);\n"
             "await page.getByTestId('checkout').click();\n"
             "await expect(page).toHaveURL(/sayfa=sepet/);\n"
             "await expect(page.getByTestId('error'))\n"
             "  .toHaveText('Sepetiniz boş. Teslimata geçmeden önce bir ürün ekleyin.');")
    add_text(s, Inches(0.5), Inches(5.8), Inches(12), Inches(0.7),
             "Aynı adımlar + aynı beklenti + düzeltilmiş site = başarılı yeniden test", 16, True, NAVY)

    # 20 V2 regression
    s = prs.slides.add_slide(blank)
    chrome(s, 20, "V2 ve regresyon sonuçları")
    table_slide(s, [
        ["Koşu", "Geçti", "Başarısız", "Süre"],
        ["V1 ilk sürüm", "13", "2", "33,9 sn"],
        ["V2 düzeltilmiş sürüm", "15", "0", "7,8 sn"],
        ["Aynı paket, ayrı koşu", "15", "0", "Tekrar yeşil"],
    ], h=Inches(3.2))
    add_text(s, Inches(0.5), Inches(5.0), Inches(12), Inches(1.2),
             "Süre farkı performans testi sonucu değildir; V1, T04’te seçeneği beklerken zaman kaybeder.", 16, False, MUTED)

    # 21 screenshots
    s = prs.slides.add_slide(blank)
    chrome(s, 21, "Uygulama kanıtı: raf ve teslimat")
    picture(s, SHOTS / "raf.png", Inches(0.35), Inches(1.2), Inches(8.3), Inches(5.5))
    picture(s, SHOTS / "teslimat.png", Inches(8.8), Inches(1.2), Inches(4.2), Inches(2.75))
    picture(s, SHOTS / "sepet-urun.png", Inches(8.8), Inches(4.05), Inches(4.2), Inches(2.65))

    # 22 live demo
    s = prs.slides.add_slide(blank)
    chrome(s, 22, "Derste canlı gösterim sırası")
    steps = [
        "1. XAMPP: Apache açık, V1 ve V2 adreslerine giriş",
        "2. PLAYWRIGHT_AC.cmd: testleri V2 üzerinde başlat",
        "3. İlk rapor (V1): T04 ve T12 başarısızlığını göster",
        "4. Son rapor ve kod: HTML option + PHP sepet kontrolünü karşılaştır",
    ]
    for i, st in enumerate(steps):
        add_round(s, Inches(0.5), Inches(1.4 + i * 1.2), Inches(12.3), Inches(1.05), WHITE)
        add_text(s, Inches(0.75), Inches(1.55 + i * 1.2), Inches(11.8), Inches(0.75), st, 20, False, INK)

    # 23 close
    s = prs.slides.add_slide(blank)
    chrome(s, 23, "Kapsam, sınırlamalar ve kaynaklar")
    bullets(s, Inches(0.55), Inches(1.4), Inches(12), [
        "15/15 sonuç yalnızca seçilen test kapsamını gösterir.",
        "Yük, kapsamlı güvenlik ve mobil testleri yapılmadı.",
        "Kampüs Sepeti’nden fark: kırtasiye rafı, kategori filtresi, 10 haneli telefon, 14,90 TL kargo.",
        "Teori: Dersin verdiği yazılım testi kaynakları",
        "Teknik: Playwright, PHP ve XAMPP resmî belgeleri",
    ], 18)

    prs.save(OUT)
    print("saved", OUT, OUT.stat().st_size)


if __name__ == "__main__":
    build()
