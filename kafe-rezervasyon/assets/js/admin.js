/**
 * Admin paneli — yalnızca onay (confirm) diyalogları.
 *
 * Silme ve iptal geri alınması zor işlemlerdir. HTML5'in native
 * confirm() penceresi ekstra bağımlılık olmadan "emin misiniz?" sorar.
 * Asıl yetki kontrolü sunucudadır; bu dosya yalnızca yanlış tıklamayı keser.
 *
 * Kullanım: <form data-onay="mesaj metni">...</form>
 */
(function () {
    'use strict';

    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        var mesaj = form.getAttribute('data-onay');
        if (!mesaj) {
            return;
        }

        if (!window.confirm(mesaj)) {
            event.preventDefault();
        }
    });
}());
