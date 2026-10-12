(function () {
    var box = document.getElementById('mspActions');
    var fetchBtn = document.getElementById('mspFetch');
    var downloadBtn = document.getElementById('mspDownload');
    var status = document.getElementById('mspStatus');
    var message = document.getElementById('mspMessage');
    var result = document.getElementById('mspResult');
    var summary = document.getElementById('mspSummary');
    var table = document.getElementById('mspTable');
    var data = null;

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

    function fail(text) {
        message.textContent = text;
        message.hidden = false;
    }

    function render() {
        var cols = data.columns, rows = data.rows;
        var idx = function (name) { return cols.indexOf(name); };
        var qty = idx('qty'), price = idx('Invoice Price'), inv = idx('Invoice Number');
        var totalQty = 0, totalPrice = 0, invoices = {};
        rows.forEach(function (r) {
            if (qty !== -1) { totalQty += Number(r[qty]) || 0; }
            if (price !== -1) { totalPrice += Number(r[price]) || 0; }
            if (inv !== -1 && r[inv] !== null) { invoices[r[inv]] = true; }
        });
        summary.innerHTML =
            '<span class="msp-pill"><strong>' + nl(rows.length, 0) + '</strong> regels</span>' +
            (inv !== -1 ? '<span class="msp-pill"><strong>' + nl(Object.keys(invoices).length, 0) + '</strong> facturen</span>' : '') +
            (qty !== -1 ? '<span class="msp-pill">Totaal aantal <strong>' + nl(totalQty, null) + '</strong></span>' : '') +
            (price !== -1 ? '<span class="msp-pill">Totaal factuurbedrag <strong>' + nl(totalPrice, 2) + '</strong></span>' : '');

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
        status.textContent = 'Bezig met ophalen… dit kan even duren.';
        var body = new URLSearchParams();
        body.set('csrf', box.getAttribute('data-csrf'));
        fetch(box.getAttribute('data-endpoint'), { method: 'POST', body: body })
            .then(function (r) { return r.json().catch(function () { return { ok: false, error: 'Onverwacht antwoord van de server.' }; }); })
            .then(function (json) {
                if (!json.ok) { throw new Error(json.error || 'Ophalen mislukt.'); }
                data = json;
                render();
                downloadBtn.disabled = data.rows.length === 0;
                status.textContent = 'Opgehaald om ' + new Date().toLocaleTimeString('nl-NL', { hour: '2-digit', minute: '2-digit' }) + '.';
            })
            .catch(function (err) {
                status.textContent = '';
                result.hidden = true;
                fail(err.message);
            })
            .then(function () { fetchBtn.disabled = false; });
    });

    downloadBtn.addEventListener('click', function () {
        if (!data || typeof XLSX === 'undefined') { fail('Excel-bibliotheek niet geladen.'); return; }
        var cols = data.columns;
        var aoa = [cols];
        data.rows.forEach(function (r) {
            aoa.push(r.map(function (v, i) {
                if (v === null) { return ''; }
                if (cols[i] === 'fakdat' && typeof v === 'string' && ISO_DATE.test(v)) {
                    var p = v.split('-');
                    return new Date(Date.UTC(+p[0], +p[1] - 1, +p[2]));
                }
                return v;
            }));
        });
        var ws = XLSX.utils.aoa_to_sheet(aoa, { cellDates: true });
        // Opmaak zoals in het bronbestand: datum als datum, prijs met 2 decimalen, tekst als tekst.
        var range = XLSX.utils.decode_range(ws['!ref']);
        for (var c = range.s.c; c <= range.e.c; c++) {
            for (var r = 1; r <= range.e.r; r++) {
                var cell = ws[XLSX.utils.encode_cell({ r: r, c: c })];
                if (!cell) { continue; }
                if (cols[c] === 'fakdat' && cell.t === 'd') { cell.z = 'dd-mm-yyyy'; }
                if (cols[c] === 'Invoice Price' && cell.t === 'n') { cell.z = '0.00'; }
            }
        }
        ws['!cols'] = cols.map(function (c) {
            var w = { 'End Customer Number': 22, 'Part Number': 24, 'qty': 8, 'Invoice Number': 16, 'Invoice Date (DD/MM/YYYY)': 26, 'dagen': 8, 'fakdat': 13, 'FINartgrp': 11, 'Invoice Price': 14 }[c];
            return { wch: w || 4 };
        });
        var wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'Temp_Export_MSP_factuurregels');
        var d = new Date();
        var stamp = d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2);
        XLSX.writeFile(wb, 'MSP_report_' + stamp + '.xlsx');
    });
})();
