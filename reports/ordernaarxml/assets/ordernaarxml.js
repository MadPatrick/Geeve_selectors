// Order naar XML: ordernummer invoeren, orderkop en regels bekijken en de order downloaden als XML-bestand.
(function () {
    var app = document.getElementById('oxApp');
    if (!app) { return; }

    var nummerInput = document.getElementById('oxNummer');
    var ophalenBtn = document.getElementById('oxOphalen');
    var exportBtn = document.getElementById('oxExport');
    var status = document.getElementById('oxStatus');
    var message = document.getElementById('oxMessage');
    var result = document.getElementById('oxResult');
    var kopBox = document.getElementById('oxKop');
    var table = document.getElementById('oxTable');
    var huidig = '';   // ordernummer waarvan de gegevens op het scherm staan

    function esc(s) { return GeeveEsc(s); }
    function fail(text) { message.textContent = text || ''; message.hidden = !text; }
    function dmy(iso) { return /^\d{4}-\d{2}-\d{2}$/.test(iso) ? iso.split('-').reverse().join('-') : iso; }

    function toon(order) {
        var k = order.kop;
        kopBox.innerHTML = '<div><strong>Order ' + esc(k.ordernummer) + '</strong> &middot; ' + esc(dmy(k.orderdatum)) + '</div>' +
            '<div>Klant: ' + esc(k.klantnummer) + ' ' + esc(k.klantnaam) + '</div>' +
            '<div>Referentie: ' + esc(k.referentie) + ' &middot; Uw referentie: ' + esc(k.uw_referentie) + '</div>';
        var html = '<thead><tr><th>Regel</th><th>Artikelcode</th><th>Omschrijving</th><th class="num">Aantal</th><th class="num">Prijs</th><th>Eenheid</th></tr></thead><tbody>';
        order.regels.forEach(function (r, i) {
            html += '<tr><td>' + (i + 1) + '</td><td>' + esc(r.artikelcode) + '</td><td>' + esc(r.omschrijving) + '</td>' +
                '<td class="num">' + esc(r.aantal) + '</td><td class="num">' + esc(r.prijs) + '</td><td>' + esc(r.eenheid) + '</td></tr>';
        });
        table.innerHTML = html + '</tbody>';
        result.hidden = false;
    }

    function ophalen() {
        var nummer = nummerInput.value.replace(/\s+/g, '');
        fail('');
        if (!/^\d{1,18}$/.test(nummer)) { fail('Vul een geldig ordernummer in (alleen cijfers).'); return; }
        var body = new URLSearchParams();
        body.set('csrf', app.dataset.csrf);
        body.set('nummer', nummer);
        ophalenBtn.disabled = true;
        exportBtn.disabled = true;
        status.textContent = 'Bezig met ophalen…';
        fetch(app.dataset.endpoint, { method: 'POST', body: body, credentials: 'same-origin' })
            .then(function (r) { return r.json().catch(function () { return { ok: false, error: 'Onverwacht antwoord van de server.' }; }); })
            .then(function (json) {
                if (!json.ok) { throw new Error(json.error || 'Ophalen mislukt.'); }
                huidig = nummer;
                toon(json);
                exportBtn.disabled = json.regels.length === 0;
                status.textContent = json.regels.length === 0 ? 'Order gevonden, maar zonder artikelregels.' : json.regels.length + ' regels opgehaald.';
            })
            .catch(function (err) {
                huidig = '';
                result.hidden = true;
                status.textContent = '';
                fail(err.message);
            })
            .then(function () { ophalenBtn.disabled = false; });
    }

    // Download: een gewoon formulier naar xml.php (antwoord is een bestand, de pagina blijft staan).
    function exporteer() {
        if (!huidig) { return; }
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = app.dataset.xml;
        [['csrf', app.dataset.csrf], ['nummer', huidig]].forEach(function (p) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = p[0];
            input.value = p[1];
            form.appendChild(input);
        });
        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
        status.textContent = 'XML-bestand gedownload.';
    }

    ophalenBtn.addEventListener('click', ophalen);
    exportBtn.addEventListener('click', exporteer);
    nummerInput.addEventListener('keydown', function (e) { if (e.key === 'Enter') { ophalen(); } });
    nummerInput.addEventListener('input', function () { exportBtn.disabled = true; huidig = ''; });
})();
