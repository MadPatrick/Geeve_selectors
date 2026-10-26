// Gebogen buis: klant kiezen (korting uit de prijslijst van de klant), artikelregels met aantal, bruto prijs en vrije voorraad,
// montage en notities, en daaronder bruto / korting / netto. Alle bedragen en aantallen accepteren komma of punt.
(function () {
    var app = document.getElementById('gbApp');
    if (!app) { return; }

    var klantInput = document.getElementById('gbKlant');
    var klantHits = document.getElementById('gbKlantHits');
    var klantInfo = document.getElementById('gbKlantInfo');
    var message = document.getElementById('gbMessage');
    var body = document.getElementById('gbBody');
    var montage = document.getElementById('gbMontage');
    var urls = { klanten: app.dataset.klanten, artikelen: app.dataset.artikelen, korting: app.dataset.korting };

    var klant = null;            // {debnr, naam, prijslijst}
    var rows = [];               // {tr, item, qty, pct, note, ...}
    var kortingTimer = null;
    var kortingSeq = 0;

    function esc(s) {
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function num(text) {
        var n = parseFloat(String(text).replace(/\s/g, '').replace(',', '.'));
        return isFinite(n) ? n : 0;
    }
    function euro(n) {
        return '€ ' + n.toLocaleString('nl-NL', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    function showError(text) {
        message.textContent = text || '';
        message.hidden = !text;
    }
    function getJson(url) {
        return fetch(url, { credentials: 'same-origin' }).then(function (res) { return res.json(); });
    }

    // Zoekveld met keuzelijst; herbruikbaar voor klant en artikel. onPick krijgt het gekozen resultaat.
    function attachSearch(input, list, endpoint, label, onPick) {
        var hits = [];
        var active = -1;
        var timer = null;
        var seq = 0;

        function close() { list.hidden = true; active = -1; input.setAttribute('aria-expanded', 'false'); }
        function render() {
            list.innerHTML = hits.map(function (hit, i) {
                return '<li role="option" data-i="' + i + '"' + (i === active ? ' class="is-active"' : '') + '>' + label(hit) + '</li>';
            }).join('');
            list.hidden = hits.length === 0;
            input.setAttribute('aria-expanded', hits.length ? 'true' : 'false');
        }
        function pick(i) {
            if (!hits[i]) { return; }
            var hit = hits[i];
            close();
            onPick(hit);
        }
        function search() {
            var q = input.value.trim();
            if (q.length < 2) { hits = []; close(); return Promise.resolve(); }
            var mine = ++seq;
            return getJson(endpoint + '?q=' + encodeURIComponent(q)).then(function (json) {
                if (mine !== seq) { return; }
                if (!json.ok) { showError(json.error || 'Zoeken mislukt.'); hits = []; close(); return; }
                showError('');
                hits = json.rows;
                active = -1;
                render();
            }).catch(function () { if (mine === seq) { showError('Zoeken mislukt.'); } });
        }

        input.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(search, 250); });
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && list.hidden && input.value.trim().length >= 2) {
                // Lijst nog niet getoond (net getypt): meteen zoeken en de bovenste waarde nemen.
                e.preventDefault();
                clearTimeout(timer);
                search().then(function () { if (hits.length) { pick(0); } });
                return;
            }
            if (list.hidden) { return; }
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                active = (active + (e.key === 'ArrowDown' ? 1 : -1) + hits.length) % hits.length;
                render();
            } else if (e.key === 'Enter') {
                e.preventDefault();
                pick(active >= 0 ? active : 0);
            } else if (e.key === 'Escape') {
                close();
            }
        });
        list.addEventListener('mousedown', function (e) {
            var li = e.target.closest('li');
            if (li) { e.preventDefault(); pick(parseInt(li.getAttribute('data-i'), 10)); }
        });
        input.addEventListener('blur', function () { setTimeout(close, 120); });
    }

    // ---- Klant ----
    attachSearch(klantInput, klantHits, urls.klanten,
        function (k) { return '<strong>' + esc(k.debnr) + '</strong> ' + esc(k.naam) + (k.prijslijst ? ' <small>prijslijst ' + esc(k.prijslijst) + '</small>' : ''); },
        function (k) {
            klant = k;
            klantInput.value = k.debnr + ' ' + k.naam;
            klantInfo.hidden = false;
            klantInfo.innerHTML = k.prijslijst
                ? 'Korting uit prijslijst <strong>' + esc(k.prijslijst) + '</strong> van deze klant.'
                : 'Deze klant heeft geen prijslijst: geen korting.';
            fetchKorting();
            // Enter ging door naar het volgende veld: het artikelveld van de eerste regel.
            if (rows.length) { rows[0].tr.querySelector('.gb-item').focus(); }
        });
    klantInput.addEventListener('input', function () {
        if (klant && klantInput.value !== klant.debnr + ' ' + klant.naam) {
            klant = null;
            klantInfo.hidden = true;
            fetchKorting();
        }
    });

    // ---- Artikelregels ----
    function stockText(r) {
        if (!r.item) { return ''; }
        if (r.item.voorraad === '') { return '-'; }
        return r.item.voorraad;
    }

    function addRow() {
        var tr = document.createElement('tr');
        tr.innerHTML =
            '<td><input type="text" class="ka-input gb-item" placeholder="Artikelnummer of omschrijving" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false">' +
            '<ul class="gb-hits" role="listbox" hidden></ul><span class="gb-desc"></span></td>' +
            '<td><input type="text" class="ka-input gb-qty" inputmode="decimal" value="1" autocomplete="off"></td>' +
            '<td class="gb-num gb-price"></td><td class="gb-num gb-total"></td><td class="gb-num gb-stock"></td>' +
            '<td class="gb-num gb-disc"></td>' +
            '<td><button type="button" class="gb-del" title="Regel verwijderen" aria-label="Regel verwijderen">&times;</button></td>';
        body.appendChild(tr);
        var r = { tr: tr, item: null, pct: 0, note: '' };
        rows.push(r);

        var input = tr.querySelector('.gb-item');
        attachSearch(input, tr.querySelector('.gb-hits'), urls.artikelen,
            function (a) {
                return '<strong>' + esc(a.code) + '</strong> ' + esc(a.omschrijving) +
                    (a.prijs !== null ? ' <small>' + esc(euro(a.prijs)) + '</small>' : '');
            },
            function (a) {
                r.item = a;
                input.value = a.code;
                refresh(r);
                fetchKorting();
                // Door naar het aantal.
                var qty = r.tr.querySelector('.gb-qty');
                qty.focus();
                if (qty.select) { qty.select(); }
            });
        input.addEventListener('input', function () {
            if (r.item && input.value !== r.item.code) { r.item = null; refresh(r); fetchKorting(); }
        });
        tr.querySelector('.gb-qty').addEventListener('input', function () { refresh(r); scheduleKorting(); });
        tr.querySelector('.gb-del').addEventListener('click', function () {
            rows.splice(rows.indexOf(r), 1);
            tr.remove();
            if (rows.length === 0) { addRow(); }
            totals();
        });
        input.focus();
        return r;
    }

    function qtyOf(r) { return num(r.tr.querySelector('.gb-qty').value); }

    function refresh(r) {
        var tr = r.tr;
        tr.querySelector('.gb-desc').textContent = r.item ? r.item.omschrijving : '';
        tr.querySelector('.gb-price').textContent = r.item && r.item.prijs !== null ? euro(r.item.prijs) : (r.item ? 'onbekend' : '');
        tr.querySelector('.gb-total').textContent = r.item && r.item.prijs !== null ? euro(r.item.prijs * qtyOf(r)) : '';
        var stock = tr.querySelector('.gb-stock');
        stock.textContent = stockText(r);
        stock.classList.toggle('gb-stock-low', !!r.item && r.item.voorraad !== '' && num(r.item.voorraad) < qtyOf(r));
        var disc = tr.querySelector('.gb-disc');
        disc.innerHTML = r.item && klant ? esc(r.pct.toLocaleString('nl-NL', { maximumFractionDigits: 2 })) + ' %' +
            (r.note ? '<span class="gb-note">' + esc(r.note) + '</span>' : '') : '';
        totals();
    }

    // ---- Korting van de klant ----
    function scheduleKorting() { clearTimeout(kortingTimer); kortingTimer = setTimeout(fetchKorting, 400); }

    function fetchKorting() {
        clearTimeout(kortingTimer);
        var mine = ++kortingSeq;
        var active = rows.filter(function (r) { return r.item; });
        if (!klant || active.length === 0) {
            rows.forEach(function (r) { r.pct = 0; r.note = ''; refresh(r); });
            return;
        }
        var items = active.map(function (r) { return { groep: r.item.groep, aantal: qtyOf(r) }; });
        getJson(urls.korting + '?debnr=' + encodeURIComponent(klant.debnr) + '&items=' + encodeURIComponent(JSON.stringify(items)))
            .then(function (json) {
                if (mine !== kortingSeq) { return; }
                if (!json.ok) { showError(json.error || 'Korting ophalen mislukt.'); return; }
                showError('');
                active.forEach(function (r, i) { r.pct = json.items[i].pct; r.note = json.items[i].note; });
                rows.forEach(function (r) { if (!r.item) { r.pct = 0; r.note = ''; } refresh(r); });
            })
            .catch(function () { if (mine === kortingSeq) { showError('Korting ophalen mislukt.'); } });
    }

    // ---- Totalen: bruto = artikelen + montage, korting alleen over de artikelen, netto = bruto - korting ----
    function totals() {
        var bruto = 0;
        var korting = 0;
        var artikelen = 0;
        rows.forEach(function (r) {
            if (!r.item || r.item.prijs === null) { return; }
            var line = r.item.prijs * qtyOf(r);
            bruto += line;
            artikelen += line;
            korting += line * r.pct / 100;
        });
        bruto += num(montage.value);
        document.getElementById('gbKortingPct').textContent = artikelen > 0 && korting > 0
            ? '(' + (korting / artikelen * 100).toLocaleString('nl-NL', { maximumFractionDigits: 2 }) + ' %)' : '';
        document.getElementById('gbBruto').textContent = euro(bruto);
        document.getElementById('gbKorting').textContent = (korting > 0 ? '- ' : '') + euro(korting);
        document.getElementById('gbNetto').textContent = euro(bruto - korting);
    }

    montage.addEventListener('input', totals);
    document.getElementById('gbAdd').addEventListener('click', addRow);
    addRow();
    totals();
})();
