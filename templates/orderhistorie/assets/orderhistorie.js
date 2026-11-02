// Orderhistorie: klant kiezen, zoeken op klant/periode/artikel/omschrijving, daarna per kolom verder filteren en downloaden als Excel.
(function () {
    var app = document.getElementById('ohApp');
    if (!app) { return; }

    var klantInput = document.getElementById('ohKlant');
    var klantHits = document.getElementById('ohKlantHits');
    var vanInput = document.getElementById('ohVan');
    var totInput = document.getElementById('ohTot');
    var artnrInput = document.getElementById('ohArtnr');
    var omscInput = document.getElementById('ohOmsc');
    var zoekBtn = document.getElementById('ohZoek');
    var downloadBtn = document.getElementById('ohDownload');
    var status = document.getElementById('ohStatus');
    var message = document.getElementById('ohMessage');
    var result = document.getElementById('ohResult');
    var summary = document.getElementById('ohSummary');
    var table = document.getElementById('ohTable');
    var MAX_SHOWN = 1000;      // regels op het scherm; filters en download gaan over alle opgehaalde regels
    var ISO_DATE = /^\d{4}-\d{2}-\d{2}$/;

    var klant = null;          // {debnr, naam}: gekozen uit de lijst
    var data = null;           // antwoord van data.php
    var shown = [];            // indexen in data.rows na het filteren, in schermvolgorde
    var colFilters = [];       // filtertekst per kolom
    var sortCol = -1;
    var sortDesc = false;
    var cache = [];            // weergavetekst per regel en kolom, voor snel filteren

    function esc(s) {
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function nl(n) { return Number(n).toLocaleString('nl-NL', { maximumFractionDigits: 4 }); }
    function dmy(iso) { return iso.split('-').reverse().join('-'); }
    function fail(text) { message.textContent = text || ''; message.hidden = !text; }
    function getJson(url) { return fetch(url, { credentials: 'same-origin' }).then(function (r) { return r.json(); }); }

    // Weergavetekst van een cel (zo ziet de gebruiker hem, dus daarop wordt ook gefilterd).
    function cellText(v, i) {
        if (v === null || v === undefined) { return ''; }
        if (data.dates[i] && typeof v === 'string' && ISO_DATE.test(v)) { return dmy(v); }
        if (data.numeric[i] && typeof v === 'number') { return nl(v); }
        return String(v);
    }

    // ---- Klant: zoekveld met keuzelijst ----
    (function () {
        var hits = [];
        var active = -1;
        var timer = null;
        var seq = 0;
        var lastQuery = '';

        function close() { klantHits.hidden = true; active = -1; klantInput.setAttribute('aria-expanded', 'false'); }
        function render() {
            klantHits.innerHTML = hits.map(function (hit, i) {
                return '<li role="option" data-i="' + i + '"' + (i === active ? ' class="is-active"' : '') + '><strong>' + esc(hit.debnr) + '</strong> ' + esc(hit.naam) + '</li>';
            }).join('');
            klantHits.hidden = hits.length === 0;
            klantInput.setAttribute('aria-expanded', hits.length ? 'true' : 'false');
        }
        function pick(i) {
            if (!hits[i]) { return; }
            klant = hits[i];
            klantInput.value = klant.debnr + ' ' + klant.naam;
            close();
        }
        function search() {
            var q = klantInput.value.trim();
            if (q.length < 2) { hits = []; lastQuery = ''; close(); return Promise.resolve(); }
            var mine = ++seq;
            lastQuery = q;
            return getJson(app.dataset.klanten + '?q=' + encodeURIComponent(q)).then(function (json) {
                if (mine !== seq) { return; }
                if (!json.ok) { fail(json.error || 'Zoeken mislukt.'); hits = []; close(); return; }
                fail('');
                hits = json.rows;
                active = -1;
                render();
            }).catch(function () { if (mine === seq) { fail('Zoeken mislukt.'); } });
        }
        // Enter in het klantveld: de bovenste (of gemarkeerde) klant uit de lijst kiezen en meteen zoeken.
        function enter() {
            var q = klantInput.value.trim();
            clearTimeout(timer);
            if (klant) { zoek(); return; }
            function kies() {
                if (hits.length) { pick(active >= 0 ? active : 0); }
                zoek();
            }
            if (hits.length && lastQuery === q) { kies(); } else { search().then(kies); }
        }

        klantInput.addEventListener('input', function () {
            if (klant && klantInput.value !== klant.debnr + ' ' + klant.naam) { klant = null; }
            clearTimeout(timer);
            timer = setTimeout(search, 250);
        });
        klantInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); enter(); return; }
            if (klantHits.hidden) { return; }
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                active = (active + (e.key === 'ArrowDown' ? 1 : -1) + hits.length) % hits.length;
                render();
            } else if (e.key === 'Escape') {
                close();
            }
        });
        klantHits.addEventListener('mousedown', function (e) {
            var li = e.target.closest('li');
            if (li) { e.preventDefault(); pick(parseInt(li.getAttribute('data-i'), 10)); }
        });
        klantInput.addEventListener('blur', function () { setTimeout(close, 120); });
    })();

    // ---- Zoeken ----
    function zoek() {
        fail('');
        if (!klant && klantInput.value.trim() === '' && artnrInput.value.trim() === '' && omscInput.value.trim() === '') {
            fail('Vul een klant, artikelnummer of omschrijving in om te zoeken.');
            return;
        }
        var body = new URLSearchParams();
        body.set('csrf', app.dataset.csrf);
        if (klant) {
            body.set('debnr', klant.debnr);
            body.set('klant', klant.naam);
        } else if (klantInput.value.trim() !== '') {
            body.set('klantzoek', klantInput.value.trim());
        }
        if (/[?&]diagnose/.test(location.search)) { body.set('diagnose', '1'); }
        body.set('van', vanInput.value);
        body.set('tot', totInput.value);
        body.set('artnr', artnrInput.value.trim());
        body.set('omsc', omscInput.value.trim());
        zoekBtn.disabled = true;
        downloadBtn.disabled = true;
        var started = Date.now();
        status.textContent = 'Bezig met zoeken… 0 s';
        var ticker = setInterval(function () { status.textContent = 'Bezig met zoeken… ' + Math.round((Date.now() - started) / 1000) + ' s'; }, 1000);
        fetch(app.dataset.endpoint, { method: 'POST', body: body })
            .then(function (r) { return r.json().catch(function () { return { ok: false, error: 'Onverwacht antwoord van de server.' }; }); })
            .then(function (json) {
                if (!json.ok) { throw new Error(json.error || 'Zoeken mislukt.'); }
                data = json;
                colFilters = data.columns.map(function () { return ''; });
                sortCol = -1;
                sortDesc = false;
                cache = data.rows.map(function (row) { return row.map(cellText); });
                buildHead();
                refresh();
                result.hidden = false;
                plakFilterrij();
                downloadBtn.disabled = data.rows.length === 0;
                status.textContent = (data.rows.length === 0 ? 'Geen regels gevonden' : 'Opgehaald om ' + new Date().toLocaleTimeString('nl-NL', { hour: '2-digit', minute: '2-digit' })) +
                    ' (' + Math.round((Date.now() - started) / 100) / 10 + ' s' +
                    (data.tijden ? ', waarvan bron zoeken ' + data.tijden.bron + ' ms en ophalen uit SQL ' + data.tijden.ophalen + ' ms' : '') + ').';
            })
            .catch(function (err) {
                status.textContent = 'Mislukt na ' + Math.round((Date.now() - started) / 100) / 10 + ' s.';
                result.hidden = true;
                fail(err.message);
            })
            .then(function () { clearInterval(ticker); zoekBtn.disabled = false; });
    }

    // ---- Tabel: kop met sorteren, filterrij per kolom, regels ----
    function buildHead() {
        var html = '<thead><tr>' + data.columns.map(function (c, i) { return '<th data-col="' + i + '" title="Klik om te sorteren">' + esc(c) + '</th>'; }).join('') + '</tr>' +
            '<tr class="oh-filterrow">' + data.columns.map(function (c, i) {
                return '<th><input type="text" class="ka-input" data-filter="' + i + '" placeholder="Zoek…" aria-label="Filter ' + esc(c) + '" autocomplete="off"></th>';
            }).join('') + '</tr></thead><tbody></tbody>';
        table.innerHTML = html;
    }

    // De filterrij plakt direct onder de kolomnamen als de pagina scrolt; meten kan pas als de tabel zichtbaar is.
    function plakFilterrij() {
        var kop = table.querySelector('thead tr:first-child');
        if (!kop) { return; }
        Array.prototype.forEach.call(table.querySelectorAll('tr.oh-filterrow th'), function (th) { th.style.top = kop.offsetHeight + 'px'; });
    }

    function matches(ri) {
        for (var c = 0; c < colFilters.length; c++) {
            if (colFilters[c] !== '' && cache[ri][c].toLowerCase().indexOf(colFilters[c]) === -1) { return false; }
        }
        return true;
    }
    function compare(a, b) {
        var va = data.rows[a][sortCol];
        var vb = data.rows[b][sortCol];
        if (va === null || va === undefined) { va = ''; }
        if (vb === null || vb === undefined) { vb = ''; }
        var r = (typeof va === 'number' && typeof vb === 'number') ? va - vb : String(va).localeCompare(String(vb), 'nl', { numeric: true, sensitivity: 'base' });
        return sortDesc ? -r : r;
    }

    function refresh() {
        shown = [];
        for (var i = 0; i < data.rows.length; i++) { if (matches(i)) { shown.push(i); } }
        if (sortCol >= 0) { shown.sort(compare); }
        var html = '';
        shown.slice(0, MAX_SHOWN).forEach(function (ri) {
            html += '<tr>' + data.rows[ri].map(function (v, i) {
                var text = cache[ri][i];
                if (data.numeric[i]) { return '<td class="num">' + esc(text) + '</td>'; }
                return '<td' + (data.columns[i] === 'Omschrijving' ? ' class="oh-omsc"' : '') + '>' + esc(text) + '</td>';
            }).join('') + '</tr>';
        });
        table.tBodies[0].innerHTML = html;
        Array.prototype.forEach.call(table.querySelectorAll('thead tr:first-child th'), function (th, i) {
            th.classList.toggle('is-sorted', i === sortCol);
            th.classList.toggle('is-desc', i === sortCol && sortDesc);
        });
        var filtered = shown.length !== data.rows.length;
        summary.innerHTML = (data.kop || []).map(function (k) {
            return '<span class="msp-pill">' + esc(k.label) + ' <strong>' + esc(k.value) + '</strong></span>';
        }).join('') +
            '<span class="msp-pill"><strong>' + nl(shown.length) + '</strong> regels' + (filtered ? ' van ' + nl(data.rows.length) : '') + '</span>' +
            (shown.length > MAX_SHOWN ? '<span class="msp-pill">Op het scherm: eerste <strong>' + nl(MAX_SHOWN) + '</strong> (download bevat alles)</span>' : '');
        downloadBtn.disabled = shown.length === 0;
    }

    var filterTimer = null;
    table.addEventListener('input', function (e) {
        var idx = e.target.getAttribute && e.target.getAttribute('data-filter');
        if (idx === null || idx === undefined) { return; }
        colFilters[+idx] = e.target.value.trim().toLowerCase();
        clearTimeout(filterTimer);
        filterTimer = setTimeout(refresh, 200);
    });
    table.addEventListener('click', function (e) {
        var th = e.target.closest ? e.target.closest('th[data-col]') : null;
        if (!th || !data) { return; }
        var c = +th.getAttribute('data-col');
        sortDesc = sortCol === c ? !sortDesc : false;
        sortCol = c;
        refresh();
    });

    // Standaard: "Afgeleverd vanaf" = 1 januari van het vorige jaar, "Tot en met" = vandaag (lokale datum).
    (function () {
        function iso(d) { return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2); }
        var now = new Date();
        vanInput.value = (now.getFullYear() - 1) + '-01-01';
        totInput.value = iso(now);
    })();

    zoekBtn.addEventListener('click', zoek);
    [vanInput, totInput, artnrInput, omscInput].forEach(function (el) {
        el.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); zoek(); } });
    });

    // ---- Excel: de regels zoals ze na het filteren en sorteren op het scherm staan (alle, niet alleen de eerste 1000) ----
    GeeveProgress.onClick(downloadBtn, 'Excel maken…', function () {
        if (!data || typeof XLSX === 'undefined') { fail('Excel-bibliotheek niet geladen.'); return false; }
        var HR = 3; // rij 0 titel, rij 1 datum, rij 2 leeg, rij 3 kolomkop
        var now = new Date();
        var aoa = [['Orderhistorie ' + (data.klantNaam || '')], [now.toLocaleDateString('nl-NL')], [], data.columns];
        shown.forEach(function (ri) { aoa.push(data.rows[ri].map(function (v) { return v === null ? '' : v; })); });
        var ws = XLSX.utils.aoa_to_sheet(aoa);
        var LINE = { style: 'thin', color: { rgb: 'FFBFBFBF' } };
        var BORDER = { top: LINE, bottom: LINE, left: LINE, right: LINE };
        var lastCol = data.columns.length - 1;
        ws[XLSX.utils.encode_cell({ r: 0, c: 0 })].s = { font: { bold: true, sz: 18 } };
        for (var c = 0; c <= lastCol; c++) {
            ws[XLSX.utils.encode_cell({ r: HR, c: c })].s = {
                font: { bold: true, color: { rgb: 'FFFFFFFF' } },
                fill: { patternType: 'solid', fgColor: { rgb: 'FF1F4E78' } },
                alignment: { horizontal: 'center', vertical: 'center', wrapText: true },
                border: BORDER
            };
            for (var r = HR + 1; r < aoa.length; r++) {
                var addr = XLSX.utils.encode_cell({ r: r, c: c });
                var cell = ws[addr];
                if (!cell) { cell = ws[addr] = { t: 's', v: '' }; }
                if (data.dates[c] && cell.t === 's' && ISO_DATE.test(cell.v)) {
                    var p = cell.v.split('-');
                    cell = ws[addr] = { t: 'n', v: Math.round(Date.UTC(+p[0], +p[1] - 1, +p[2]) / 86400000) + 25569, z: 'dd-mm-yyyy' };
                } else if (!data.numeric[c]) {
                    // Artikel-, order- en referentienummers altijd als tekst (voorloopnullen blijven staan).
                    cell.t = 's'; cell.v = String(cell.v); cell.z = '@';
                }
                cell.s = { border: BORDER, alignment: { vertical: 'center' } };
            }
        }
        ws['!ref'] = XLSX.utils.encode_range({ s: { r: 0, c: 0 }, e: { r: aoa.length - 1, c: lastCol } });
        ws['!autofilter'] = { ref: XLSX.utils.encode_range({ s: { r: HR, c: 0 }, e: { r: aoa.length - 1, c: lastCol } }) };
        ws['!rows'] = [{ hpt: 24 }];
        ws['!rows'][HR] = { hpt: 24 };
        ws['!cols'] = data.columns.map(function (name, c) {
            var w = Math.min(name.length + 4, 18);
            shown.slice(0, 200).forEach(function (ri) {
                var v = data.rows[ri][c];
                if (v !== null) { w = Math.max(w, Math.min(String(v).length + 2, 50)); }
            });
            return { wch: w };
        });
        var wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'Orderhistorie');
        var tag = String(data.klantNaam || 'alle klanten').replace(/[^A-Za-z0-9._-]+/g, '_');
        XLSX.writeFile(wb, 'Orderhistorie_' + tag + '.xlsx');
    });
})();
