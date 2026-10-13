(function () {
    var app = document.getElementById('eiApp');
    var endpoint = app.getAttribute('data-endpoint');
    var csrf = app.getAttribute('data-csrf');
    var numberEl = document.getElementById('eiNumber');
    var fetchBtn = document.getElementById('eiFetch');
    var statusEl = document.getElementById('eiStatus');
    var messageEl = document.getElementById('eiMessage');
    var resultEl = document.getElementById('eiResult');
    var viewBox = document.getElementById('eiView');
    var exportBtn = document.getElementById('eiExport');
    var summaryEl = document.getElementById('eiSummary');
    var tableEl = document.getElementById('eiTable');

    var EURO = '_-€ * #,##0.00_-;-€ * #,##0.00_-;_-€ * "-"??_-;_-@_-';
    // Per weergave: kolomkop, bronveld (k) of vaste waarde (v), type (t = tekst, n = getal, e = euro), e = wijzigbaar.
    var VIEWS = {
        orders: { name: 'Orders', cols: [
            { h: 'Ordernumber', k: 'Ordernumber', t: 't' }, { h: 'Line number', k: 'Line number', t: 't' }, { h: 'Quantity', k: 'Quantity', t: 'n' },
            { h: 'Artcode', k: 'Artcode', t: 't' }, { h: 'Item description', k: 'Item description', t: 't', desc: true },
            { h: 'Collo nr', k: 'Collo nr', t: 't', e: true }, { h: 'Collo Type', k: 'Collo Type', t: 't', e: true },
            { h: 'Weight', k: 'Weight', t: 'n', e: true }, { h: 'Length (cm)', k: 'Length (cm)', t: 'n', e: true },
            { h: 'Width (cm)', k: 'Width (cm)', t: 'n', e: true }, { h: 'Height (cm)', k: 'Height (cm)', t: 'n', e: true },
            { h: 'Unit Price', k: 'Unit Price', t: 'e' }, { h: 'Total price', k: 'Total price', t: 'e' },
            { h: 'HS code', k: 'HS code', t: 't' }, { h: 'Country of Origin', k: 'Country of Origin', t: 't' },
            { h: 'Customer Reference', k: 'Customer Reference', t: 't' }
        ] },
        deme: { name: 'Deme', cols: [
            { h: 'Artcode', k: 'Artcode', t: 't' }, { h: 'Omschrijving', k: 'Item description', t: 't', desc: true }, { h: 'Quantity', k: 'Quantity', t: 'n' },
            { h: 'Customer Reference', k: 'Customer Reference', t: 't' }, { h: 'Leeg', v: '', t: 't' },
            { h: 'Country of Origin', k: 'Country of Origin', t: 't' }, { h: 'Unit Price', k: 'Unit Price', t: 'e' }, { h: 'HS code', k: 'HS code', t: 't' }
        ] },
        nov: { name: 'NOV', cols: [
            { h: 'NOV PO NO.', k: 'Customer Reference', t: 't' }, { h: 'PO Line No.', k: 'Line number', t: 't' }, { h: 'INVOICE NO.', k: 'Ordernumber', t: 't' },
            { h: 'NOV Part NO.', k: 'Artcode', t: 't' }, { h: 'Description', k: 'Item description', t: 't', desc: true }, { h: 'HS CODE', k: 'HS code', t: 't' },
            { h: 'Qty', k: 'Quantity', t: 'n' }, { h: 'Net Weight (Kgs)', k: 'Weight', t: 'n', e: true }, { h: 'Unit price', k: 'Unit Price', t: 'e' },
            { h: 'Total Value', k: 'Total price', t: 'e' }, { h: 'COO', k: 'Country of Origin', t: 't' }, { h: 'CURRENCY', v: 'EUR', t: 't' }
        ] }
    };
    var rows = [];      // [{Ordernumber: .., 'Line number': .., ...}]
    var current = 'orders';
    var order = '';

    function esc(s) { return String(s === null || s === undefined ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
    function nlNum(n) { return Number(n).toLocaleString('nl-NL', { minimumFractionDigits: 0, maximumFractionDigits: 4 }); }
    function euro(n) { return '€ ' + Number(n).toLocaleString('nl-NL', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
    function showMessage(text) { messageEl.textContent = text; messageEl.hidden = !text; }
    function val(row, col) { return col.v !== undefined ? col.v : row[col.k]; }
    function toNum(v) {
        if (typeof v === 'number') { return v; }
        var t = String(v === null || v === undefined ? '' : v).trim().replace(/\s/g, '');
        if (t === '') { return null; }
        if (t.indexOf(',') !== -1) { t = t.replace(/\./g, '').replace(',', '.'); }
        return /^-?\d*\.?\d+$/.test(t) ? parseFloat(t) : NaN;
    }

    function totalWeight() {
        var sum = 0;
        rows.forEach(function (r) { var q = toNum(r['Quantity']), w = toNum(r['Weight']); if (q > 0 && w > 0) { sum += q * w; } });
        return sum;
    }
    function renderSummary() {
        summaryEl.innerHTML = '<span class="msp-pill"><strong>' + esc(order) + '</strong>' + (rows.customer ? ' ' + esc(rows.customer) : '') + '</span>' +
            '<span class="msp-pill"><strong>' + rows.length + '</strong> regels</span>' +
            '<span class="msp-pill">Total weight <strong>' + nlNum(totalWeight()) + '</strong> kg</span>';
    }

    function render() {
        var cols = VIEWS[current].cols;
        var html = '<thead><tr>' + cols.map(function (c) { return '<th' + (c.t !== 't' ? ' class="ei-num"' : '') + '>' + esc(c.h) + '</th>'; }).join('') + '</tr></thead><tbody>';
        rows.forEach(function (r, i) {
            html += '<tr>' + cols.map(function (c) {
                var v = val(r, c);
                var cls = (c.t !== 't' ? 'ei-num' : '') + (c.desc ? ' ei-desc' : '');
                if (c.e) {
                    var shown = c.t === 'n' && v !== '' && v !== null && !isNaN(toNum(v)) ? nlNum(toNum(v)) : (v === null ? '' : v);
                    return '<td><input type="text" class="ka-input ei-in' + (c.t === 'n' ? ' ei-num' : '') + '" data-i="' + i + '" data-k="' + esc(c.k) + '" data-n="' + (c.t === 'n' ? 1 : 0) + '" value="' + esc(shown) + '" autocomplete="off" aria-label="' + esc(c.h) + ' regel ' + esc(r['Line number']) + '"></td>';
                }
                var text = c.t === 'e' && v !== '' && v !== null ? euro(v) : (c.t === 'n' && v !== '' && v !== null ? nlNum(v) : v);
                return '<td' + (cls ? ' class="' + cls.trim() + '"' : '') + '>' + esc(text) + '</td>';
            }).join('') + '</tr>';
        });
        tableEl.innerHTML = html + '</tbody>';
        renderSummary();
    }

    // Wijzigingen in de invulvelden worden in de gegevens bewaard (dus ook bij wisselen van weergave en in de Excel).
    tableEl.addEventListener('input', function (e) {
        var el = e.target;
        if (!el.classList || !el.classList.contains('ei-in')) { return; }
        var r = rows[parseInt(el.getAttribute('data-i'), 10)];
        var key = el.getAttribute('data-k');
        if (el.getAttribute('data-n') === '1') {
            var n = toNum(el.value);
            el.classList.toggle('is-bad', n !== null && isNaN(n));
            r[key] = n === null ? '' : (isNaN(n) ? el.value : n);
        } else {
            r[key] = el.value;
        }
        renderSummary();
    });
    viewBox.addEventListener('click', function (e) {
        var b = e.target.closest('button[data-view]');
        if (!b) { return; }
        current = b.getAttribute('data-view');
        Array.prototype.forEach.call(viewBox.querySelectorAll('button'), function (x) { x.classList.toggle('is-active', x === b); });
        render();
    });

    function fetchData() {
        var nr = numberEl.value.trim();
        showMessage('');
        if (!/^\d{1,18}$/.test(nr)) { showMessage('Vul een geldig ordernummer in (alleen cijfers).'); return; }
        fetchBtn.disabled = true;
        statusEl.textContent = 'Gegevens worden opgehaald…';
        var body = new URLSearchParams();
        body.set('csrf', csrf);
        body.set('nummer', nr);
        fetch(endpoint, { method: 'POST', body: body, credentials: 'same-origin' })
            .then(function (r) { return r.json().catch(function () { return { ok: false, error: 'Onverwacht antwoord van de server.' }; }); })
            .then(function (j) {
                if (!j.ok) { throw new Error(j.error || 'Ophalen mislukt.'); }
                rows = j.rows.map(function (arr) {
                    var o = {};
                    j.columns.forEach(function (c, i) { o[c] = arr[i] === null ? '' : (typeof arr[i] === 'string' && c !== 'Item description' ? arr[i].trim() : arr[i]); });
                    return o;
                });
                rows.customer = j.customer || '';
                order = nr;
                resultEl.hidden = false;
                statusEl.textContent = rows.length ? '' : 'Geen regels gevonden voor dit ordernummer.';
                if (!rows.length) { resultEl.hidden = true; }
                render();
            })
            .catch(function (err) { resultEl.hidden = true; statusEl.textContent = ''; showMessage(err.message); })
            .then(function () { fetchBtn.disabled = false; });
    }
    fetchBtn.addEventListener('click', fetchData);
    numberEl.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); fetchData(); } });

    // ---- Excel ----
    function exportExcel() {
        if (typeof XLSX === 'undefined') { showMessage('Excel-bibliotheek niet geladen.'); return; }
        var view = VIEWS[current];
        var cols = view.cols;
        var offset = current === 'orders' ? 2 : 0; // orders: rij 1 = Total weight, rij 2 leeg
        var aoa = [];
        if (offset) { aoa.push(['Total weight', null], []); }
        aoa.push(cols.map(function (c) { return c.h; }));
        rows.forEach(function (r) {
            aoa.push(cols.map(function (c) {
                var v = val(r, c);
                if (c.t === 'n' || c.t === 'e') { var n = toNum(v); return n === null || isNaN(n) ? '' : n; }
                return v === null || v === undefined ? '' : String(v);
            }));
        });
        var ws = XLSX.utils.aoa_to_sheet(aoa);
        var head = { font: { bold: true }, fill: { patternType: 'solid', fgColor: { rgb: 'FFDCE6F1' } }, alignment: { horizontal: 'center', vertical: 'center', wrapText: true } };
        var hr = offset + 1;
        cols.forEach(function (c, ci) {
            var L = XLSX.utils.encode_col(ci);
            ws[L + hr].s = head;
            for (var i = 0; i < rows.length; i++) {
                var cell = ws[L + (hr + 1 + i)];
                if (!cell) { cell = ws[L + (hr + 1 + i)] = { t: 's', v: '' }; }
                if (c.t === 't') { cell.t = 's'; cell.z = '@'; }
                else if (c.t === 'e') { cell.z = EURO; }
                else if (c.k === 'Weight') { cell.z = '0.0000'; }
            }
        });
        if (offset) {
            var qL = XLSX.utils.encode_col(cols.findIndex(function (c) { return c.h === 'Quantity'; }));
            var wL = XLSX.utils.encode_col(cols.findIndex(function (c) { return c.h === 'Weight'; }));
            var first = hr + 1, last = hr + rows.length;
            ws['A1'].s = { font: { bold: true } };
            ws['B1'] = { t: 'n', v: totalWeight(), f: 'SUMPRODUCT(' + qL + first + ':' + qL + last + ',' + wL + first + ':' + wL + last + ')', z: '0.0000', s: { font: { bold: true } } };
        }
        ws['!cols'] = cols.map(function (c) { return { wch: c.desc ? 50 : Math.max(12, c.h.length + 3) }; });
        ws['!ref'] = XLSX.utils.encode_range({ s: { r: 0, c: 0 }, e: { r: aoa.length - 1, c: Math.max(cols.length - 1, 1) } });
        var wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, view.name);
        XLSX.writeFile(wb, 'Export_info_' + order + '_' + view.name + '.xlsx');
    }
    exportBtn.addEventListener('click', exportExcel);
})();
