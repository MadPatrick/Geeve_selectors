(function () {
    var box = document.getElementById('mspActions');
    var fetchBtn = document.getElementById('mspFetch');
    var downloadBtn = document.getElementById('mspDownload');
    var uploadBtn = document.getElementById('mspUpload');
    var mergeBox = document.getElementById('mspMerge');
    var status = document.getElementById('mspStatus');
    var message = document.getElementById('mspMessage');
    var result = document.getElementById('mspResult');
    var summary = document.getElementById('mspSummary');
    var table = document.getElementById('mspTable');
    var data = null;
    var monthSel = document.getElementById('mspMonth');
    var yearSel = document.getElementById('mspYear');
    var period = null; // gekozen factuurmaand van de opgehaalde gegevens
    var MONTHS = ['januari', 'februari', 'maart', 'april', 'mei', 'juni', 'juli', 'augustus', 'september', 'oktober', 'november', 'december'];

    var NUMERIC = { 'qty': null, 'FINartgrp': 0, 'Invoice Price': 2 };
    var ISO_DATE = /^\d{4}-\d{2}-\d{2}$/;

    function esc(s) {
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    // decimals = vast aantal decimalen; null = zoveel als nodig (max. 2), voor hoeveelheden die soms een fractie zijn.
    function nl(n, decimals) {
        var fixed = decimals !== null;
        return Number(n).toLocaleString('nl-NL', { minimumFractionDigits: fixed ? decimals : 0, maximumFractionDigits: fixed ? decimals : 2 });
    }
    function dmy(iso) { return iso.split('-').reverse().join('-'); }

    // Rijen zoals getoond/geëxporteerd: losse regels, of samengevoegd per klant + artikel + factuur (+ datum en artikelgroep).
    // Aantal en bedrag worden opgeteld; de aantallen zijn per regel al op halve stuks afgerond.
    function viewRows() {
        if (!mergeBox.checked) { return data.rows; }
        var cols = data.columns;
        var ci = cols.indexOf('End Customer Number'), pi = cols.indexOf('Part Number'), ni = cols.indexOf('Invoice Number');
        var di = cols.indexOf('Invoice Date (DD/MM/YYYY)'), gi = cols.indexOf('FINartgrp'), qi = cols.indexOf('qty'), bi = cols.indexOf('Invoice Price');
        var map = {}, out = [];
        data.rows.forEach(function (r) {
            var key = [r[ci], r[pi], r[ni], r[di], r[gi]].join('|');
            if (!map.hasOwnProperty(key)) { map[key] = r.slice(); out.push(map[key]); return; }
            var t = map[key];
            t[qi] = Math.round((Number(t[qi]) + Number(r[qi])) * 100) / 100;
            t[bi] = Math.round((Number(t[bi]) + Number(r[bi])) * 100) / 100;
        });
        return out;
    }

    function fail(text) {
        message.textContent = text;
        message.hidden = false;
    }

    function render() {
        var cols = data.columns, rows = viewRows();
        var idx = function (name) { return cols.indexOf(name); };
        var inv = idx('Invoice Number');
        var invoices = {};
        rows.forEach(function (r) {
            if (inv !== -1 && r[inv] !== null) { invoices[r[inv]] = true; }
        });
        summary.innerHTML =
            '<span class="msp-pill">Factuurmaand <strong>' + MONTHS[period.month - 1] + ' ' + period.year + '</strong></span>' +
            '<span class="msp-pill"><strong>' + nl(rows.length, 0) + '</strong> regels</span>' +
            (inv !== -1 ? '<span class="msp-pill"><strong>' + nl(Object.keys(invoices).length, 0) + '</strong> facturen</span>' : '');

        var html = '<thead><tr>' + cols.map(function (c) { return '<th>' + esc(c.trim()) + '</th>'; }).join('') + '</tr></thead><tbody>';
        rows.forEach(function (r) {
            html += '<tr>' + r.map(function (v, i) {
                var name = cols[i];
                if (v === null) { return '<td></td>'; }
                if (name in NUMERIC) { return '<td class="num">' + nl(v, NUMERIC[name]) + '</td>'; }
                if (typeof v === 'string' && ISO_DATE.test(v)) { return '<td>' + dmy(v) + '</td>'; }
                return '<td>' + esc(v) + '</td>';
            }).join('') + '</tr>';
        });
        table.innerHTML = html + '</tbody>';
        result.hidden = false;
    }

    fetchBtn.addEventListener('click', function () {
        message.hidden = true;
        fetchBtn.disabled = true;
        downloadBtn.disabled = true;
        uploadBtn.disabled = true;
        status.textContent = 'Bezig met ophalen… dit kan even duren.';
        var body = new URLSearchParams();
        body.set('csrf', box.getAttribute('data-csrf'));
        body.set('maand', monthSel.value);
        body.set('jaar', yearSel.value);
        var asked = { month: +monthSel.value, year: +yearSel.value };
        fetch(box.getAttribute('data-endpoint'), { method: 'POST', body: body })
            .then(function (r) { return r.json().catch(function () { return { ok: false, error: 'Onverwacht antwoord van de server.' }; }); })
            .then(function (json) {
                if (!json.ok) { throw new Error(json.error || 'Ophalen mislukt.'); }
                data = json;
                period = asked;
                render();
                downloadBtn.disabled = uploadBtn.disabled = data.rows.length === 0;
                status.textContent = 'Opgehaald voor ' + MONTHS[period.month - 1] + ' ' + period.year + ' om ' + new Date().toLocaleTimeString('nl-NL', { hour: '2-digit', minute: '2-digit' }) + '.';
            })
            .catch(function (err) {
                status.textContent = '';
                result.hidden = true;
                fail(err.message);
            })
            .then(function () { fetchBtn.disabled = false; });
    });

    GeeveProgress.onClick(downloadBtn, 'Excel maken…', function () {
        if (!data || typeof XLSX === 'undefined') { fail('Excel-bibliotheek niet geladen.'); return false; }
        var cols = data.columns;
        var aoa = [cols];
        viewRows().forEach(function (r) {
            aoa.push(r.map(function (v, i) {
                if (v === null) { return ''; }
                if (cols[i] === 'fakdat' && typeof v === 'string' && ISO_DATE.test(v)) {
                    // Excel-serienummer (hele dag, geen tijdsdeel): dagen sinds 30-12-1899.
                    var p = v.split('-');
                    return { __serial: Math.round(Date.UTC(+p[0], +p[1] - 1, +p[2]) / 86400000) + 25569 };
                }
                return v;
            }));
        });
        var serials = [];
        aoa.forEach(function (row, r) {
            row.forEach(function (v, c) {
                if (v && typeof v === 'object' && '__serial' in v) { serials.push([r, c, v.__serial]); row[c] = ''; }
            });
        });
        var ws = XLSX.utils.aoa_to_sheet(aoa);
        serials.forEach(function (x) {
            ws[XLSX.utils.encode_cell({ r: x[0], c: x[1] })] = { t: 'n', v: x[2], z: 'dd-mm-yyyy' };
        });
        // Opmaak zoals in het bronbestand: datum als datum, prijs met 2 decimalen, tekst als tekst.
        var range = XLSX.utils.decode_range(ws['!ref']);
        for (var c = range.s.c; c <= range.e.c; c++) {
            for (var r = 1; r <= range.e.r; r++) {
                var cell = ws[XLSX.utils.encode_cell({ r: r, c: c })];
                if (!cell) { continue; }
                if (cols[c] === 'Invoice Price' && cell.t === 'n') { cell.z = '0.00'; }
            }
        }
        ws['!cols'] = cols.map(function (c) {
            var w = { 'End Customer Number': 22, 'Part Number': 24, 'qty': 8, 'Invoice Number': 16, 'Invoice Date (DD/MM/YYYY)': 26, 'dagen': 8, 'fakdat': 13, 'FINartgrp': 11, 'Invoice Price': 14 }[c];
            return { wch: w || 4 };
        });
        var wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'Temp_Export_MSP_factuurregels');
        // Bestandsnaam met de factuurmaand, bijv. MSP_report_2026-09.xlsx
        var stamp = period.year + '-' + ('0' + period.month).slice(-2);
        XLSX.writeFile(wb, 'MSP_report_' + stamp + '.xlsx');
    });

    mergeBox.addEventListener('change', function () { if (data) { render(); } });

    // Opslaan met "Opslaan als"-venster (kiezen waar het bestand komt) in Chrome/Edge; Firefox kent dat niet
    // en valt terug op een gewone download naar de downloadmap.
    function saveAs(wb, name, bookType) {
        if (typeof window.showSaveFilePicker !== 'function') {
            XLSX.writeFile(wb, name, { bookType: bookType });
            return;
        }
        var bytes = XLSX.write(wb, { bookType: bookType, type: 'array' });
        window.showSaveFilePicker({
            suggestedName: name,
            types: [{ description: 'Excel-bestand', accept: { 'application/vnd.ms-excel': ['.xls'] } }]
        }).then(function (handle) {
            return handle.createWritable().then(function (w) {
                return w.write(new Blob([bytes])).then(function () { return w.close(); });
            });
        }).catch(function (err) {
            if (err && err.name === 'AbortError') { return; } // venster gesloten: niets doen
            XLSX.writeFile(wb, name, { bookType: bookType });
        });
    }

    // Export in het formaat van het MSP-uploadsjabloon (Parker): zes kolommen, geen opvulspaties, datum als tekst dd/mm/jjjj.
    GeeveProgress.onClick(uploadBtn, 'Export maken…', function () {
        if (!data || typeof XLSX === 'undefined') { fail('Excel-bibliotheek niet geladen.'); return false; }
        var cols = data.columns;
        var ci = cols.indexOf('End Customer Number'), pi = cols.indexOf('Part Number'), qi = cols.indexOf('qty');
        var ni = cols.indexOf('Invoice Number'), di = cols.indexOf('Invoice Date (DD/MM/YYYY)');
        var aoa = [['End Customer Number', 'Part Number', 'Quantity', 'Invoice Number', 'Invoice Date (DD-MM-YYYY)', 'Expected Amount (Optional)']];
        viewRows().forEach(function (r) {
            aoa.push([String(r[ci]).trim(), String(r[pi]).trim(), Number(r[qi]), String(r[ni]).trim(), String(r[di]).trim(), '']);
        });
        var ws = XLSX.utils.aoa_to_sheet(aoa);
        ws['!cols'] = [{ wch: 22 }, { wch: 26 }, { wch: 10 }, { wch: 16 }, { wch: 26 }, { wch: 26 }];
        var wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'Sheet1');
        var stamp = period.year + '-' + ('0' + period.month).slice(-2);
        saveAs(wb, 'MSP_upload_' + stamp + (mergeBox.checked ? '_samengevoegd' : '') + '.xls', 'biff8');
    });
})();
