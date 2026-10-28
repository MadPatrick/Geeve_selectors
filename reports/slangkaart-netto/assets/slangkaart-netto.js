// Slangkaart nettoprijzen: slangassemblages met nettoprijs, met sorteren, filter per kolom en Download Excel.
(function () {
    var app = document.getElementById('snApp');
    if (!app) { return; }

    var vernieuwBtn = document.getElementById('snVernieuw');
    var downloadBtn = document.getElementById('snDownload');
    var message = document.getElementById('snMessage');
    var result = document.getElementById('snResult');
    var summary = document.getElementById('snSummary');
    var table = document.getElementById('snTable');
    var TITEL = 'SLANGASSEMBLAGES MET NETTOPRIJS';
    var NAAMKOLOM = 0;                 // Slangassemblage: standaardsortering

    var data = null;
    var shown = [];
    var colFilters = [];
    var sortCol = NAAMKOLOM;
    var sortDesc = false;
    var MAXREGELS = 1000;              // meer regels op het scherm laat de browser vastlopen; Excel krijgt alles
    var cache = [];
    var ISO_DATE = /^\d{4}-\d{2}-\d{2}$/;
    var loading = false;

    function esc(s) {
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function nl(n) { return Number(n).toLocaleString('nl-NL', { maximumFractionDigits: 4 }); }
    function fail(text) { message.textContent = text || ''; message.hidden = !text; }
    function cellText(v, i) {
        if (v === null || v === undefined) { return ''; }
        if (data.dates[i] && typeof v === 'string' && ISO_DATE.test(v)) { return v.split('-').reverse().join('-'); }
        if (data.numeric[i] && typeof v === 'number') { return Number(v).toLocaleString('nl-NL', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
        return String(v);
    }

    // Zoeken tijdens het typen: kort na de laatste toets (en op Enter) starten. Loopt er nog een zoekopdracht (of toont de knop
    // nog zijn voortgangsbalk), dan wachten we tot de knop weer vrij is; laad() leest de velden pas bij het starten.
    var zoekTimer = null;
    var wacht = false;
    function startZoeken() {
        if (loading || vernieuwBtn.geeveBusy) {
            if (!wacht) { wacht = true; setTimeout(function () { wacht = false; startZoeken(); }, 120); }
            return;
        }
        vernieuwBtn.click();
    }
    function zoekMetTekst() {
        if (document.getElementById('snNummer').value.trim().length < 2) { return; } // te weinig tekst: niet de hele lijst ophalen
        startZoeken();
    }
    (function () {
        var el = document.getElementById('snNummer');
        el.addEventListener('input', function () {
            clearTimeout(zoekTimer);
            zoekTimer = setTimeout(zoekMetTekst, 400);
        });
        el.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { clearTimeout(zoekTimer); zoekMetTekst(); }
        });
    })();

    // ---- Klant: zoekveld met keuzelijst (typen toont klanten, Enter kiest de bovenste en zoekt exact op klantnummer) ----
    var klantInput = document.getElementById('snKlant');
    var klantHits = document.getElementById('snKlantHits');
    var klant = null;          // {debnr, naam}: gekozen uit de lijst
    (function () {
        var hits = [];
        var active = -1;
        var timer = null;
        var seq = 0;
        var lastQuery = '';

        function getJson(url) { return fetch(url, { credentials: 'same-origin' }).then(function (r) { return r.json(); }); }
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
            startZoeken();
        }
        function search() {
            var q = klantInput.value.trim();
            if (q.length < 1) { hits = []; lastQuery = ''; close(); return Promise.resolve(); }
            var mine = ++seq;
            lastQuery = q;
            return getJson(app.dataset.klanten + '?q=' + encodeURIComponent(q)).then(function (json) {
                if (mine !== seq) { return; }
                if (!json.ok) { fail(json.error || 'Klanten zoeken mislukt.'); hits = []; close(); return; }
                fail('');
                hits = json.rows;
                active = -1;
                render();
            }).catch(function () { if (mine === seq) { fail('Klanten zoeken mislukt.'); } });
        }
        // Enter: de bovenste (of gemarkeerde) klant uit de lijst kiezen en meteen zoeken.
        function enter() {
            var q = klantInput.value.trim();
            clearTimeout(timer);
            if (klant) { startZoeken(); return; }
            if (q === '') { return; }
            function kies() {
                if (hits.length) { pick(active >= 0 ? active : 0); } else { startZoeken(); } // niets gevonden: zoek op de getypte tekst
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
            var li = e.target.closest ? e.target.closest('li') : null;
            if (li) { e.preventDefault(); pick(parseInt(li.getAttribute('data-i'), 10)); }
        });
        klantInput.addEventListener('blur', function () { setTimeout(close, 120); });
    })();

    // Alleen op de knop Vernieuwen: de voortgangsbalk zit in de knop zelf.
    function laad() {
        if (loading) { return false; }
        loading = true;
        fail('');
        var body = new URLSearchParams();
        body.set('csrf', app.dataset.csrf);
        if (klant) { body.set('debnr', klant.debnr); } else { body.set('klant', klantInput.value); }
        body.set('nummer', document.getElementById('snNummer').value);
        return fetch(app.dataset.endpoint, { method: 'POST', body: body })
            .then(function (r) { return r.json().catch(function () { return { ok: false, error: 'Onverwacht antwoord van de server.' }; }); })
            .then(function (json) {
                if (!json.ok) { throw new Error(json.error || 'Ophalen mislukt.'); }
                fail(json.waarschuwing || '');
                var oud = colFilters;
                data = json;
                colFilters = data.columns.map(function (c, i) { return oud[i] || ''; });
                cache = data.rows.map(function (row) { return row.map(cellText); });
                buildHead();
                refresh();
                result.hidden = false;
                plakFilterrij();
            })
            .then(function () { loading = false; }, function (err) {
                loading = false;
                fail(err.message);
                throw err;
            });
    }

    function buildHead() {
        var vals = [];
        Array.prototype.forEach.call(table.querySelectorAll('[data-filter]'), function (el) { vals[+el.getAttribute('data-filter')] = el.value; });
        table.innerHTML = '<thead><tr>' + data.columns.map(function (c, i) { return '<th data-col="' + i + '" title="Klik om te sorteren">' + esc(c) + '</th>'; }).join('') + '</tr>' +
            '<tr class="oh-filterrow">' + data.columns.map(function (c, i) {
                return '<th><input type="text" class="ka-input" data-filter="' + i + '" placeholder="Zoek…" aria-label="Filter ' + esc(c) + '" autocomplete="off" value="' + esc(vals[i] || '') + '"></th>';
            }).join('') + '</tr></thead><tbody></tbody>';
    }
    // De filterrij plakt direct onder de kolomnamen als de lijst scrolt; meten kan pas als de tabel zichtbaar is.
    function plakFilterrij() {
        var kop = table.querySelector('thead tr:first-child');
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
        shown.slice(0, MAXREGELS).forEach(function (ri) {
            html += '<tr>' + data.rows[ri].map(function (v, i) {
                if (data.numeric[i]) { return '<td class="num">' + esc(cache[ri][i]) + '</td>'; }
                return '<td>' + esc(cache[ri][i]) + '</td>';
            }).join('') + '</tr>';
        });
        table.tBodies[0].innerHTML = html;
        Array.prototype.forEach.call(table.querySelectorAll('thead tr:first-child th'), function (th, i) {
            th.classList.toggle('is-sorted', i === sortCol);
            th.classList.toggle('is-desc', i === sortCol && sortDesc);
        });
        var filtered = shown.length !== data.rows.length;
        summary.innerHTML = '<span class="msp-pill"><strong>' + nl(shown.length) + '</strong> slangassemblages' + (filtered ? ' van ' + nl(data.rows.length) : '') + '</span>' +
            (shown.length > MAXREGELS ? ' <span class="msp-pill">Scherm toont de eerste ' + nl(MAXREGELS) + '; verfijn het filter of download de Excel voor alles.</span>' : '');
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

    GeeveProgress.onClick(vernieuwBtn, 'Ophalen…', laad);

    // ---- Excel: opmaak als de sheet van Carl (blauwe titelbalk over alle kolommen, kolomkop met filter, kolombreedtes), regels zoals op het scherm ----
    GeeveProgress.onClick(downloadBtn, 'Excel maken…', function () {
        if (!data || typeof XLSX === 'undefined') { fail('Excel-bibliotheek niet geladen.'); return false; }
        var lastCol = data.columns.length - 1;
        var aoa = [[TITEL], data.columns];
        shown.forEach(function (ri) { aoa.push(data.rows[ri].map(function (v) { return v === null ? '' : v; })); });
        var ws = XLSX.utils.aoa_to_sheet(aoa);
        var LINE = { style: 'thin', color: { rgb: 'FFBFBFBF' } };
        var BORDER = { top: LINE, bottom: LINE, left: LINE, right: LINE };
        var BLAUW = { patternType: 'solid', fgColor: { rgb: 'FF156082' } };
        for (var c = 0; c <= lastCol; c++) {
            var t = XLSX.utils.encode_cell({ r: 0, c: c });
            if (!ws[t]) { ws[t] = { t: 's', v: '' }; }
            ws[t].s = { font: { bold: true, sz: 28, color: { rgb: 'FFFFFFFF' } }, fill: BLAUW, alignment: { horizontal: 'center', vertical: 'center' } };
            ws[XLSX.utils.encode_cell({ r: 1, c: c })].s = {
                font: { bold: true, color: { rgb: 'FFFFFFFF' } }, fill: BLAUW,
                alignment: { horizontal: 'center', vertical: 'center', wrapText: true }, border: BORDER
            };
            for (var r = 2; r < aoa.length; r++) {
                var addr = XLSX.utils.encode_cell({ r: r, c: c });
                var cell = ws[addr];
                if (!cell) { cell = ws[addr] = { t: 's', v: '' }; }
                if (data.dates[c] && cell.t === 's' && ISO_DATE.test(cell.v)) {
                    var dp = cell.v.split('-');
                    cell = ws[addr] = { t: 'n', v: Math.round(Date.UTC(+dp[0], +dp[1] - 1, +dp[2]) / 86400000) + 25569, z: 'dd-mm-yyyy' };
                } else if (!data.numeric[c]) { cell.t = 's'; cell.v = String(cell.v); cell.z = '@'; } // slangnummers als tekst
                cell.s = { border: BORDER, alignment: { vertical: 'center' } };
            }
        }
        ws['!ref'] = XLSX.utils.encode_range({ s: { r: 0, c: 0 }, e: { r: aoa.length - 1, c: lastCol } });
        ws['!merges'] = [{ s: { r: 0, c: 0 }, e: { r: 0, c: lastCol } }];
        ws['!autofilter'] = { ref: XLSX.utils.encode_range({ s: { r: 1, c: 0 }, e: { r: aoa.length - 1, c: lastCol } }) };
        ws['!rows'] = [{ hpt: 36 }, { hpt: 20 }];
        ws['!cols'] = data.columns.map(function (name, c) {
            var w = name.length + 4;
            shown.slice(0, 300).forEach(function (ri) {
                var v = data.rows[ri][c];
                if (v !== null) { w = Math.max(w, Math.min(String(v).length + 2, 70)); }
            });
            return { wch: w };
        });
        var wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'Nettoprijzen');
        XLSX.writeFile(wb, 'Slangassemblages_met_nettoprijs.xlsx');
    });
})();
