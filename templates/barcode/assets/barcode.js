// Barcode inkoopontvangst: inkooporder + artikel opzoeken, open aantal tonen. Boeken is nog niet aangesloten (zie docs/BARCODE-ONTVANGST.md).
(function () {
    var app = document.getElementById('bcApp');
    if (!app) { return; }

    var form = document.getElementById('bcForm');
    var orderVeld = document.getElementById('bcOrder');
    var artikelVeld = document.getElementById('bcArtikel');
    var message = document.getElementById('bcMessage');
    var leverancier = document.getElementById('bcLeverancier');
    var resultaat = document.getElementById('bcResultaat');
    var loading = false;

    function nl(n) { return Number(n).toLocaleString('nl-NL', { maximumFractionDigits: 4 }); }
    function fail(text) { message.textContent = text || ''; message.hidden = !text; }
    function zet(id, text) { document.getElementById(id).textContent = text; }

    function zoek() {
        if (loading) { return; }
        loading = true;
        fail('');
        resultaat.hidden = true;
        var body = new URLSearchParams();
        body.set('csrf', app.dataset.csrf);
        body.set('order', orderVeld.value);
        body.set('artikel', artikelVeld.value);
        fetch(app.dataset.endpoint, { method: 'POST', body: body })
            .then(function (r) { return r.json().catch(function () { return { ok: false, error: 'Onverwacht antwoord van de server.' }; }); })
            .then(function (json) {
                loading = false;
                if (json.leverancier !== undefined) { leverancier.textContent = 'Leverancier: ' + (json.leverancier || '-'); }
                if (!json.ok) { fail(json.error || 'Opzoeken mislukt.'); return; }
                zet('bcOmschrijving', json.artikel + (json.omschrijving ? ' - ' + json.omschrijving : ''));
                zet('bcBesteld', nl(json.besteld));
                zet('bcOntvangen', nl(json.ontvangen));
                zet('bcOpen', nl(json.open));
                resultaat.hidden = false;
                document.getElementById('bcAantal').focus();
            }, function () {
                loading = false;
                fail('Geen verbinding met de server.');
            });
    }

    form.addEventListener('submit', function (e) { e.preventDefault(); zoek(); });
    // Een scanner sluit af met Enter: na het ordernummer door naar het artikelveld.
    orderVeld.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && orderVeld.value !== '' && artikelVeld.value === '') {
            e.preventDefault();
            artikelVeld.focus();
        }
    });
})();
