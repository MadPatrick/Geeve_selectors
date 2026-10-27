// Productie Orders: overzicht gereed te melden productieorders (zoals de Excel van Carl), met sorteren, filter per kolom en Download Excel.
(function () {
    var app = document.getElementById('poApp');
    if (!app) { return; }

    var vernieuwBtn = document.getElementById('poVernieuw');
    var downloadBtn = document.getElementById('poDownload');
    var status = document.getElementById('poStatus');
    var message = document.getElementById('poMessage');
    var result = document.getElementById('poResult');
    var summary = document.getElementById('poSummary');
    var table = document.getElementById('poTable');
    var REFRESH_MS = 60 * 60 * 1000;   // de Excel ververste elk uur (interval 60 minuten)
    var TITEL = 'OVERZICHT GEREED TE MELDEN PRODUCTIE ORDERS';
    var NAAMKOLOM = 2;                 // projectnm (kolom 0 is Moederproject, kolom 1 ProjectNr): standaardsortering, zoals in de Excel

    var data = null;
    var shown = [];
    var colFilters = [];
    var sortCol = NAAMKOLOM;
    var sortDesc = false;
    var cache = [];
    var loading = false;

    function esc(s) {
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function nl(n) { return Number(n).toLocaleString('nl-NL', { maximumFractionDigits: 4 }); }
    function fail(text) { message.textContent = text || ''; message.hidden = !text; }
    function cellText(v, i) {
        if (v === null || v === undefined) { return ''; }
        if (data.numeric[i] && typeof v === 'number') { return nl(v); }
        return String(v);
    }

    function laad() {
        if (loading) { return; }
        loading = true;
        fail('');
        vernieuwBtn.disabled = true;
        var started = Date.now();
        status.textContent = 'Bezig met ophalen…';
        var body = new URLSearchParams();
        body.set('csrf', app.dataset.csrf);
        fetch(app.dataset.endpoint, { method: 'POST', body: body })
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
                status.textContent = 'Opgehaald om ' + new Date().toLocaleTimeString('nl-NL', { hour: '2-digit', minute: '2-digit' }) +
                    ' (' + Math.round((Date.now() - started) / 100) / 10 + ' s).';
            })
            .catch(function (err) {
                status.textContent = 'Mislukt na ' + Math.round((Date.now() - started) / 100) / 10 + ' s.';
                fail(err.message);
            })
            .then(function () { loading = false; vernieuwBtn.disabled = false; });
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
        shown.forEach(function (ri) {
            html += '<tr>' + data.rows[ri].map(function (v, i) {
                if (data.numeric[i]) { return '<td class="num">' + esc(cache[ri][i]) + '</td>'; }
                return '<td' + (i === NAAMKOLOM ? ' class="po-naam"' : '') + '>' + esc(cache[ri][i]) + '</td>';
            }).join('') + '</tr>';
        });
        table.tBodies[0].innerHTML = html;
        Array.prototype.forEach.call(table.querySelectorAll('thead tr:first-child th'), function (th, i) {
            th.classList.toggle('is-sorted', i === sortCol);
            th.classList.toggle('is-desc', i === sortCol && sortDesc);
        });
        var filtered = shown.length !== data.rows.length;
        summary.innerHTML = '<span class="msp-pill"><strong>' + nl(shown.length) + '</strong> productieorders' + (filtered ? ' van ' + nl(data.rows.length) : '') + '</span>';
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

    vernieuwBtn.addEventListener('click', laad);
    laad();
    setInterval(laad, REFRESH_MS);

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
                if (!data.numeric[c]) { cell.t = 's'; cell.v = String(cell.v); cell.z = '@'; } // projectnummers als tekst
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
        XLSX.utils.book_append_sheet(wb, ws, 'Productie Orders');
        XLSX.writeFile(wb, 'Overzicht_gereed_te_melden_productieorders.xlsx');
    });
})();
