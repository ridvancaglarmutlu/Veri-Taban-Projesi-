#!/usr/bin/env python3
from pathlib import Path

from pptx import Presentation
from pptx.dml.color import RGBColor
from pptx.enum.shapes import MSO_SHAPE
from pptx.enum.text import PP_ALIGN
from pptx.util import Inches, Pt

NAVY = RGBColor(0x0F, 0x2A, 0x3D)
TERR = RGBColor(0x0B, 0x6E, 0x4F)
PAPER = RGBColor(0xEE, 0xF3, 0xF1)
WHITE = RGBColor(0xFF, 0xFF, 0xFF)
INK = RGBColor(0x12, 0x20, 0x2B)
MUTED = RGBColor(0x4D, 0x5C, 0x58)
OK = RGBColor(0x1F, 0x7A, 0x4D)
BAD = RGBColor(0xA6, 0x1F, 0x2B)
CODE = RGBColor(0x10, 0x20, 0x29)
CREAM = RGBColor(0xE7, 0xF3, 0xEA)

W = Inches(13.333)
H = Inches(7.5)
HERE = Path(__file__).resolve().parent
SHOTS = HERE / "gorseller"
OUT = HERE / "Kampus_Kirtasiye_Yazilim_Testi_Sunumu.pptx"


def run(r, size=18, bold=False, color=INK, font="Calibri"):
    r.font.size = Pt(size)
    r.font.bold = bold
    r.font.color.rgb = color
    r.font.name = font


def box(slide, l, t, w, h, fill):
    sh = slide.shapes.add_shape(MSO_SHAPE.RECTANGLE, l, t, w, h)
    sh.line.fill.background()
    sh.fill.solid()
    sh.fill.fore_color.rgb = fill
    return sh


def roundb(slide, l, t, w, h, fill):
    sh = slide.shapes.add_shape(MSO_SHAPE.ROUNDED_RECTANGLE, l, t, w, h)
    sh.adjustments[0] = 0.08
    sh.line.fill.background()
    sh.fill.solid()
    sh.fill.fore_color.rgb = fill
    return sh


def text(slide, l, t, w, h, s, size=18, bold=False, color=INK, align=PP_ALIGN.LEFT):
    tb = slide.shapes.add_textbox(l, t, w, h)
    tf = tb.text_frame
    tf.word_wrap = True
    p = tf.paragraphs[0]
    p.alignment = align
    r = p.add_run()
    r.text = s
    run(r, size, bold, color)
    return tb


def footer(slide, no):
    box(slide, 0, Inches(7.22), W, Inches(0.28), NAVY)
    text(slide, Inches(0.4), Inches(7.22), Inches(10.5), Inches(0.28),
         "Kampüs Kırtasiye  ·  TechTarget software testing  ·  Rıdvan Çağlar Mutlu", 11, False, CREAM)
    text(slide, Inches(11.6), Inches(7.22), Inches(1.4), Inches(0.28), f"{no:02d}", 11, True, TERR, PP_ALIGN.RIGHT)


def chrome(slide, no, title):
    box(slide, 0, 0, W, H, PAPER)
    box(slide, 0, 0, W, Inches(0.12), TERR)
    box(slide, 0, Inches(0.12), W, Inches(0.92), NAVY)
    text(slide, Inches(0.45), Inches(0.28), Inches(12), Inches(0.6), title, 24, True, WHITE)
    footer(slide, no)


def bullets(slide, l, t, w, items, size=18):
    tb = slide.shapes.add_textbox(l, t, w, Inches(5.5))
    tf = tb.text_frame
    tf.word_wrap = True
    for i, item in enumerate(items):
        p = tf.paragraphs[0] if i == 0 else tf.add_paragraph()
        p.space_after = Pt(9)
        r = p.add_run()
        r.text = "▸  " + item
        run(r, size, False, INK)


def table_slide(slide, rows, l=Inches(0.4), t=Inches(1.25), w=Inches(12.5), h=Inches(4.6)):
    tbl = slide.shapes.add_table(len(rows), len(rows[0]), l, t, w, h).table
    for r, row in enumerate(rows):
        for c, val in enumerate(row):
            cell = tbl.cell(r, c)
            cell.text = ""
            p = cell.text_frame.paragraphs[0]
            run_ = p.add_run()
            run_.text = val
            header = r == 0
            run(run_, 12, header, WHITE if header else INK)
            cell.fill.solid()
            cell.fill.fore_color.rgb = NAVY if header else (WHITE if r % 2 else RGBColor(0xDC, 0xE8, 0xE3))
    return tbl


def code_box(slide, l, t, w, h, s):
    roundb(slide, l, t, w, h, CODE)
    tb = slide.shapes.add_textbox(l + Inches(0.2), t + Inches(0.14), w - Inches(0.35), h - Inches(0.24))
    tf = tb.text_frame
    tf.word_wrap = True
    p = tf.paragraphs[0]
    r = p.add_run()
    r.text = s
    run(r, 13, False, CREAM, "Consolas")


def pic(slide, name, l, t, w, h):
    p = SHOTS / name
    if p.exists():
        slide.shapes.add_picture(str(p), l, t, w, h)


def build():
    prs = Presentation()
    prs.slide_width = W
    prs.slide_height = H
    blank = prs.slide_layouts[6]

    # 01 cover — aynı ders kalıbı, farklı marka
    s = prs.slides.add_slide(blank)
    box(s, 0, 0, W, H, NAVY)
    box(s, 0, 0, Inches(0.22), H, TERR)
    text(s, Inches(0.7), Inches(1.2), Inches(12), Inches(0.35), "YEREL WEB SİTESİ TESTİ", 16, True, TERR)
    text(s, Inches(0.7), Inches(1.65), Inches(12), Inches(1.1), "Kampüs Kırtasiye", 48, True, WHITE)
    text(s, Inches(0.7), Inches(3.0), Inches(12), Inches(1.4),
         "XAMPP üzerinde PHP sitesi\nPlaywright ile test, hata düzeltme ve regresyon", 22, False, CREAM)
    text(s, Inches(0.7), Inches(4.7), Inches(12), Inches(1.5),
         "TechTarget: software testing = işlevi değerlendir, hata ve boşluk ara.\n"
         "Rıdvan Çağlar Mutlu  ·  2514216055\n"
         "Bilgisayar Programcılığı 2. Sınıf  ·  Yazılım Testi  ·  18 Ekim 2026", 17, False, RGBColor(0xC5, 0xD6, 0xCF))
    text(s, Inches(12.2), Inches(6.7), Inches(0.8), Inches(0.35), "01", 16, True, TERR, PP_ALIGN.RIGHT)

    # 02 teslim
    s = prs.slides.add_slide(blank)
    chrome(s, 2, "Projenin teslim kapsamı")
    bullets(s, Inches(0.5), Inches(1.35), Inches(12.2), [
        "Bize ait kaynak kod: PHP, HTML ve CSS (Kampüs Kırtasiye, Kampüs Sepeti değil)",
        "Çalışan site: http://localhost/kampus-kirtasiye/v2/",
        "Test uygulaması: http://localhost/kampus-kirtasiye/test-uygulamasi/",
        "Otomasyon: Playwright grafik arayüzü (PLAYWRIGHT_AC.cmd)",
        "Teslim: site, test kodu, V1/V2 sonucu ve bu sunum",
    ], 20)

    # 03 app
    s = prs.slides.add_slide(blank)
    chrome(s, 3, "Kampüs Kırtasiye uygulaması")
    items = [
        ("6 ürün", "Kırtasiye + elektronik"),
        ("Giriş ve sepet", "ridvan / Defter2026"),
        ("Teslimat formu", "10 haneli telefon"),
        ("Demo sipariş", "Gerçek ödeme yok"),
    ]
    for i, (a, b) in enumerate(items):
        x = Inches(0.45 + i * 3.2)
        roundb(s, x, Inches(2.0), Inches(3.0), Inches(3.1), WHITE)
        text(s, x + Inches(0.2), Inches(2.3), Inches(2.6), Inches(1.1), a, 22, True, NAVY)
        text(s, x + Inches(0.2), Inches(3.5), Inches(2.6), Inches(1.1), b, 16, False, MUTED)

    # 04 arch
    s = prs.slides.add_slide(blank)
    chrome(s, 4, "XAMPP ve uygulama mimarisi")
    bullets(s, Inches(0.5), Inches(1.35), Inches(12.2), [
        "Kurulum: C:/xampp/htdocs/kampus-kirtasiye",
        "Sunucu: Apache + PHP  ·  Kapı: /  Site: /v2/  Hatalı kanıt: /v1/",
        "Veri: Ürünler PHP dizisinde, sepet $_SESSION içinde",
        "Para: kuruş (int). Kampüs teslimatı her sepet için 14,90 TL",
        "Akış: Tarayıcı isteği → Apache/PHP → HTML yanıtı",
    ], 20)

    # 05 TechTarget + rules
    s = prs.slides.add_slide(blank)
    chrome(s, 5, "TechTarget tanımı ve bitirme ölçütü")
    bullets(s, Inches(0.5), Inches(1.3), Inches(12.2), [
        "Software testing: yazılımın işlevini değerlendirmek; hata, boşluk ve beklenen-sonuç farkını aramak.",
        "Amaç: canlıya çıkmadan (derste teslimden önce) hatayı kapatmak.",
        "Giriş: doğru bilgi kabul, hatalı / kilitli hesap ret.",
        "Sepet: doğru ürün, adet, 14,90 TL kargo ve toplam.",
        "Form: zorunlu alanlar ve tam 10 rakamlı telefon.",
        "Sipariş: boş sepet ilerlemez; ürünlü sepet ilerler.",
    ], 18)

    # 06 techniques
    s = prs.slides.add_slide(blank)
    chrome(s, 6, "Test teknikleri ve test verileri")
    table_slide(s, [
        ["Teknik (TechTarget)", "Girdi", "Beklenti"],
        ["Fonksiyonel / kara kutu", "ridvan / Defter2026", "Giriş kabul, 6 ürün"],
        ["Negatif test", "Yanlış şifre / boş alan / mezun", "Uyarı ve giriş engeli"],
        ["Sınır komşuları", "9 / 10 / 11 haneli telefon", "Ret / kabul / ret"],
        ["Regresyon (atlanmamalı)", "Düzeltme sonrası aynı 15 test", "Eski işlevler korunur"],
    ], h=Inches(4.3))

    # 07-09 scenarios
    s = prs.slides.add_slide(blank)
    chrome(s, 7, "Senaryolar: Giriş ve raf")
    table_slide(s, [
        ["ID", "Senaryo", "Beklenen", "V1", "V2"],
        ["T01", "Boş giriş", "Kullanıcı adı uyarısı", "Geçti", "Geçti"],
        ["T02", "Yanlış şifre", "Giriş reddi", "Geçti", "Geçti"],
        ["T03", "Geçerli giriş", "6 ürün", "Geçti", "Geçti"],
        ["T04", "Kırtasiye filtresi", "4 ürün", "Başarısız", "Geçti"],
        ["T05", "Defter ekleme", "1 adet, 54,90 TL", "Geçti", "Geçti"],
    ], h=Inches(4.3))

    s = prs.slides.add_slide(blank)
    chrome(s, 8, "Senaryolar: Sepet ve form")
    table_slide(s, [
        ["ID", "Senaryo", "Beklenen", "V1", "V2"],
        ["T06", "Defteri kaldır", "0 ürün, 0,00 TL", "Geçti", "Geçti"],
        ["T07", "Boş teslimat formu", "Ad uyarısı", "Geçti", "Geçti"],
        ["T08", "Kilitli hesap (mezun)", "Giriş reddi", "Geçti", "Geçti"],
        ["T09", "Boş şifre", "Şifre uyarısı", "Geçti", "Geçti"],
        ["T10", "Defter + USB + kargo", "398,80 TL", "Geçti", "Geçti"],
    ], h=Inches(4.3))

    s = prs.slides.add_slide(blank)
    chrome(s, 9, "Senaryolar: Sipariş ve sınırlar")
    table_slide(s, [
        ["ID", "Senaryo", "Beklenen", "V1", "V2"],
        ["T11", "10 haneli telefon", "Sipariş tamamlandı", "Geçti", "Geçti"],
        ["T12", "Boş sepet", "Teslimat engeli", "Başarısız", "Geçti"],
        ["T13", "9 haneli telefon", "Formda uyarı", "Geçti", "Geçti"],
        ["T14", "11 haneli telefon", "Formda uyarı", "Geçti", "Geçti"],
        ["T15", "Çıkış sonrası raf", "Giriş sayfası", "Geçti", "Geçti"],
    ], h=Inches(4.3))

    # 10 playwright
    s = prs.slides.add_slide(blank)
    chrome(s, 10, "Playwright yerel siteye nasıl bağlanır?")
    code_box(s, Inches(0.45), Inches(1.25), Inches(12.4), Inches(4.6),
             "use: {\n"
             "  baseURL: 'http://localhost/kampus-kirtasiye/v2/',\n"
             "  channel: 'chrome',\n"
             "  viewport: { width: 1366, height: 900 },\n"
             "  screenshot: 'only-on-failure',\n"
             "  trace: 'retain-on-failure'\n"
             "}\n"
             "test.beforeEach(async ({ page }) => {\n"
             "  await page.goto('index.php?sayfa=giris');\n"
             "});")
    text(s, Inches(0.5), Inches(6.05), Inches(12), Inches(0.7),
         "Aynı kod V1 ve V2 üzerinde, 1 worker ve 0 retry ile çalıştı.", 16, False, MUTED)

    # 11 login
    s = prs.slides.add_slide(blank)
    chrome(s, 11, "Giriş testi ve doğrulama")
    code_box(s, Inches(0.45), Inches(1.25), Inches(12.4), Inches(3.8),
             "test('T02 Yanlis sifre reddedilir', async ({ page }) => {\n"
             "  await giris(page, 'ridvan', 'yanlis123');\n"
             "  await expect(page.getByTestId('error'))\n"
             "    .toHaveText('Kullanıcı adı veya şifre hatalı.');\n"
             "  await expect(page).toHaveURL(/sayfa=giris/);\n"
             "});")
    text(s, Inches(0.5), Inches(5.3), Inches(12), Inches(1.2),
         "Tıklama işlemdir. expect, işlemin doğru sonuç verdiğini kontrol eder.", 18, False, INK)

    # 12 filter
    s = prs.slides.add_slide(blank)
    chrome(s, 12, "Kategori filtresini kodla kontrol etme")
    code_box(s, Inches(0.45), Inches(1.25), Inches(12.4), Inches(3.8),
             "await page.getByLabel('Kategori').selectOption('kirtasiye');\n"
             "await expect(page).toHaveURL(/kategori=kirtasiye/);\n"
             "await expect(page.getByTestId('product')).toHaveCount(4);\n"
             "await expect(page.getByTestId('product-count'))\n"
             "  .toHaveText('4 ürün listeleniyor');")
    text(s, Inches(0.5), Inches(5.3), Inches(12), Inches(1.2),
         "Filtrenin görünmesi yetmez; ürün sayısı da 4 olmalıdır.", 18, False, INK)

    # 13 phone
    s = prs.slides.add_slide(blank)
    chrome(s, 13, "Telefon: PHP kuralı ve sınır testi")
    code_box(s, Inches(0.45), Inches(1.25), Inches(12.4), Inches(4.1),
             "if (!preg_match('/^[0-9]{10}$/D', $telefon)) {\n"
             "    $error = 'Telefon numarası 10 rakamdan oluşmalıdır.';\n"
             "}\n\n"
             "await bilgileriGir(page, '05551234567');\n"
             "await expect(page.getByTestId('error'))\n"
             "  .toHaveText('Telefon numarası 10 rakamdan oluşmalıdır.');")
    text(s, Inches(0.5), Inches(5.6), Inches(12), Inches(0.8),
         "9 hane: ret  /  10 hane: kabul  /  11 hane: ret", 18, True, TERR)

    # 14 money
    s = prs.slides.add_slide(blank)
    chrome(s, 14, "Para hesabı: Tam sayı kuruş + teslimat")
    code_box(s, Inches(0.45), Inches(1.25), Inches(12.4), Inches(4.2),
             "1 => ['ad' => 'A4 Spiralli Defter', 'fiyat' => 5490],\n"
             "4 => ['ad' => '64GB USB Bellek', 'fiyat' => 32900],\n"
             "teslimat = 1490\n\n"
             "expect(araToplam).toBe(5490 + 32900);\n"
             "expect(teslimat).toBe(1490);\n"
             "expect(toplam).toBe(39880);\n"
             "await expect(page.getByTestId('total')).toHaveText('398,80 TL');")
    text(s, Inches(0.5), Inches(5.7), Inches(12), Inches(0.7),
         "5490 + 32900 + 1490 = 39880 kuruş = 398,80 TL", 18, True, TERR)

    # 15 V1
    s = prs.slides.add_slide(blank)
    chrome(s, 15, "V1 sonuçları: İki başarısız test")
    roundb(s, Inches(0.45), Inches(1.4), Inches(3.9), Inches(2.3), WHITE)
    text(s, Inches(0.7), Inches(1.6), Inches(3.5), Inches(0.8), "13 geçti / 2 başarısız", 20, True, BAD)
    text(s, Inches(0.7), Inches(2.5), Inches(3.5), Inches(0.9), "TechTarget: hatayı teslimden önce bul.", 15, False, MUTED)
    bullets(s, Inches(4.7), Inches(1.4), Inches(8), [
        "T04: kirtasiye seçeneği tarayıcıda oluşmadı.",
        "T12: 0 ürünle teslimat formu açıldı.",
        "Kanıt: hata mesajı, ekran görüntüsü ve trace.",
        "Süre 33,9 sn; bir bölümü T04 beklemesi.",
    ], 17)

    # 16 H01
    s = prs.slides.add_slide(blank)
    chrome(s, 16, "H01: Yanlış kapanan option etiketi")
    code_box(s, Inches(0.45), Inches(1.25), Inches(12.4), Inches(4.2),
             "<!-- V1: Yeni seçenek açılmıyor -->\n"
             "</option value=\"kirtasiye\">Yalnız kırtasiye\n\n"
             "<!-- V2: Önceki seçenek kapanıyor, yenisi açılıyor -->\n"
             "<option value=\"kirtasiye\">Yalnız kırtasiye</option>\n\n"
             "await page.getByLabel('Kategori').selectOption('kirtasiye');")
    text(s, Inches(0.5), Inches(5.7), Inches(12), Inches(0.8),
         "V1: T04 başarısız  /  HTML düzeltmesinden sonra V2: T04 geçti", 18, True, NAVY)

    # 17 H02
    s = prs.slides.add_slide(blank)
    chrome(s, 17, "H02: Boş sepetle teslimat formuna geçiş")
    pic(s, "v1-bos-teslimat.png", Inches(0.35), Inches(1.2), Inches(6.3), Inches(4.15))
    pic(s, "sepet-bos-v2.png", Inches(6.75), Inches(1.2), Inches(6.2), Inches(4.15))
    text(s, Inches(0.4), Inches(5.5), Inches(6.2), Inches(0.5), "V1: Teslimat formu açıldı", 16, True, BAD)
    text(s, Inches(6.75), Inches(5.5), Inches(6.2), Inches(0.5), "V2: Sepette uyarı çıktı", 16, True, OK)

    # 18 fix
    s = prs.slides.add_slide(blank)
    chrome(s, 18, "H02 düzeltmesi: PHP tarafında kontrol")
    code_box(s, Inches(0.45), Inches(1.25), Inches(12.4), Inches(4.2),
             "if (in_array($sayfa, ['teslimat', 'onay'], true)\n"
             "    && sepet_adet() === 0) {\n"
             "    flash('Sepetiniz boş. Teslimata geçmeden önce bir ürün ekleyin.');\n"
             "    git('sepet');\n"
             "}\n"
             "// Aynı kontrol sipariş POST işlemine de eklendi.\n"
             "// Kontrol yalnızca tarayıcı düğmesine bırakılmadı.")
    text(s, Inches(0.5), Inches(5.7), Inches(12), Inches(0.8),
         "Düzeltme site kodunda yapıldı; testin beklediği sonuç korunarak yeniden çalıştırıldı.", 16, False, INK)

    # 19 T12
    s = prs.slides.add_slide(blank)
    chrome(s, 19, "T12: Düzeltmeyi aynı testle doğrulama")
    code_box(s, Inches(0.45), Inches(1.25), Inches(12.4), Inches(4.3),
             "await giris(page);\n"
             "await page.getByTestId('cart-link').click();\n"
             "await expect(page.getByTestId('cart-item')).toHaveCount(0);\n"
             "await page.getByTestId('checkout').click();\n"
             "await expect(page).toHaveURL(/sayfa=sepet/);\n"
             "await expect(page.getByTestId('error'))\n"
             "  .toHaveText('Sepetiniz boş. Teslimata geçmeden önce bir ürün ekleyin.');")
    text(s, Inches(0.5), Inches(5.8), Inches(12), Inches(0.7),
         "Aynı adımlar + aynı beklenti + düzeltilmiş site = başarılı yeniden test", 16, True, NAVY)

    # 20 V2
    s = prs.slides.add_slide(blank)
    chrome(s, 20, "V2 ve regresyon sonuçları")
    table_slide(s, [
        ["Koşu", "Geçti", "Başarısız", "Süre"],
        ["V1 ilk sürüm", "13", "2", "33,9 sn"],
        ["V2 düzeltilmiş sürüm", "15", "0", "7,8 sn"],
        ["Playwright UI teyidi", "15", "0", "Ayrı koşu"],
    ], h=Inches(3.1))
    text(s, Inches(0.5), Inches(4.7), Inches(12.3), Inches(1.6),
         "TechTarget: regresyon atlanmamalı. Süre farkı performans testi değildir;\n"
         "V1 hata bekleme süresi içerir.", 16, False, MUTED)

    # 21 proof
    s = prs.slides.add_slide(blank)
    chrome(s, 21, "Test uygulamasındaki sonuç kanıtı")
    pic(s, "test-sonuc.png", Inches(0.3), Inches(1.15), Inches(7.4), Inches(5.7))
    pic(s, "raf.png", Inches(7.85), Inches(1.15), Inches(5.15), Inches(5.7))

    # 22 live
    s = prs.slides.add_slide(blank)
    chrome(s, 22, "Derste canlı gösterim sırası")
    steps = [
        "1. XAMPP: Apache açık, localhost/kampus-kirtasiye sitesine giriş",
        "2. PLAYWRIGHT_AC.cmd: Run all ile testleri başlat",
        "3. İlk rapor (V1): T04 ve T12 başarısızlığını göster",
        "4. Son rapor ve kod: Düzeltmeyi açıklayıp karşılaştır",
    ]
    for i, st in enumerate(steps):
        roundb(s, Inches(0.5), Inches(1.35 + i * 1.3), Inches(12.3), Inches(1.1), WHITE)
        text(s, Inches(0.75), Inches(1.55 + i * 1.3), Inches(11.8), Inches(0.7), st, 20, False, INK)

    # 23 sources
    s = prs.slides.add_slide(blank)
    chrome(s, 23, "Kapsam, sınırlamalar ve kaynaklar")
    bullets(s, Inches(0.5), Inches(1.3), Inches(12.2), [
        "15/15 sonuç yalnızca seçilen test kapsamını gösterir.",
        "Yük, kapsamlı güvenlik ve mobil testleri yapılmadı.",
        "Teori: TechTarget — What is software testing? Definition, types and importance.",
        "https://www.techtarget.com/whatis/definition/software-testing",
        "Teknik: Playwright, PHP ve XAMPP resmî belgeleri.",
    ], 18)

    prs.save(OUT)
    root = Path("/workspace") / OUT.name
    root.write_bytes(OUT.read_bytes())
    print("saved", OUT, OUT.stat().st_size)


if __name__ == "__main__":
    build()
