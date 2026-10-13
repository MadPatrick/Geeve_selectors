(function () {
    var box = document.getElementById('rpActions');
    var input = document.getElementById('rpNumber');
    // Rapporten met meerdere invoervelden (bijv. groep + periode) gebruiken [data-field]; anders het ene nummerveld.
    var fields = Array.prototype.slice.call(box.querySelectorAll('[data-field]'));
    var fetchBtn = document.getElementById('rpFetch');
    var downloadBtn = document.getElementById('rpDownload');
    var status = document.getElementById('rpStatus');
    var message = document.getElementById('rpMessage');
    var result = document.getElementById('rpResult');
    var summary = document.getElementById('rpSummary');
    var table = document.getElementById('rpTable');
    var data = null;
    var asked = '';
    var ISO_DATE = /^\d{4}-\d{2}-\d{2}$/;

    function esc(s) {
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function nl(n) { return Number(n).toLocaleString('nl-NL', { minimumFractionDigits: 0, maximumFractionDigits: 4 }); }
    function isEuro(i) { return (data.currency || []).indexOf(data.columns[i]) !== -1; }
    function isDate(i) { return !!(data.dates && data.dates[i]); }
    function isPct(i) { return (data.percent || []).indexOf(data.columns[i]) !== -1; }
    function pct(n) { return Number(n).toLocaleString('nl-NL', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' %'; }
    function euro(n) { return '€ ' + Number(n).toLocaleString('nl-NL', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
    function dmy(iso) { return iso.split('-').reverse().join('-'); }
    function fail(text) { message.textContent = text; message.hidden = false; }

    function render() {
        summary.innerHTML =
            (box.getAttribute('data-label') ? '<span class="msp-pill">' + esc(box.getAttribute('data-label')) + ' <strong>' + esc(asked) + '</strong></span>' : '') +
            (data.kop || []).map(function (k) {
                return '<span class="msp-pill">' + (k.label ? esc(k.label) + ' ' : '') + '<strong>' + esc(k.type === 'euro' ? euro(k.value) : k.value) + '</strong></span>';
            }).join('') +
            '<span class="msp-pill"><strong>' + nl(data.rows.length) + '</strong> regels</span>';
        var html = '<thead><tr>' + data.columns.map(function (c) { return '<th>' + esc(c.trim()) + '</th>'; }).join('') + '</tr></thead><tbody>';
        data.rows.forEach(function (r) {
            html += '<tr>' + r.map(function (v, i) {
                if (v === null) { return '<td></td>'; }
                if ((data.nowrap || []).indexOf(data.columns[i]) !== -1) { return '<td class="nowrap">' + esc(v) + '</td>'; }
                if (isPct(i) && typeof v === 'number') { return '<td class="num">' + pct(v) + '</td>'; }
                if (isEuro(i) && typeof v === 'number') { return '<td class="num">' + euro(v) + '</td>'; }
                if (data.numeric[i] && typeof v === 'number') { return '<td class="num">' + nl(v) + '</td>'; }
                if (isDate(i) && typeof v === 'string' && ISO_DATE.test(v)) { return '<td>' + dmy(v) + '</td>'; }
                return '<td>' + esc(v) + '</td>';
            }).join('') + '</tr>';
        });
        table.innerHTML = html + '</tbody>';
        result.hidden = false;
    }

    function fetchData() {
        message.hidden = true;
        var number = '';
        var params = [];
        if (fields.length) {
            for (var f = 0; f < fields.length; f++) {
                var val = fields[f].value.trim();
                if (val === '') { fail('Vul ' + (fields[f].getAttribute('data-name') || 'alle velden') + ' in.'); return; }
                params.push([fields[f].getAttribute('data-field'), val]);
            }
        } else {
            number = input.value.trim();
            if (!/^\d{1,18}$/.test(number)) { fail('Vul een geldig nummer in (alleen cijfers).'); return; }
            params.push(['nummer', number]);
        }
        fetchBtn.disabled = true;
        downloadBtn.disabled = true;
        status.textContent = 'Bezig met ophalen…';
        var body = new URLSearchParams();
        body.set('csrf', box.getAttribute('data-csrf'));
        params.forEach(function (p) { body.set(p[0], p[1]); });
        fetch(box.getAttribute('data-endpoint'), { method: 'POST', body: body })
            .then(function (r) { return r.json().catch(function () { return { ok: false, error: 'Onverwacht antwoord van de server.' }; }); })
            .then(function (json) {
                if (!json.ok) { throw new Error(json.error || 'Ophalen mislukt.'); }
                data = json;
                asked = json.fileTag || number;
                render();
                downloadBtn.disabled = data.rows.length === 0;
                status.textContent = data.rows.length === 0
                    ? 'Geen regels gevonden.'
                    : 'Opgehaald om ' + new Date().toLocaleTimeString('nl-NL', { hour: '2-digit', minute: '2-digit' }) + '.';
            })
            .catch(function (err) {
                status.textContent = '';
                result.hidden = true;
                fail(err.message);
            })
            .then(function () { fetchBtn.disabled = false; });
    }

    fetchBtn.addEventListener('click', fetchData);
    (fields.length ? fields : [input]).forEach(function (el) {
        el.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); fetchData(); } });
    });

    downloadBtn.addEventListener('click', function () {
        if (!data || typeof XLSX === 'undefined') { fail('Excel-bibliotheek niet geladen.'); return; }
        var titles = data.titles || [];
        // Bovenaan de titelregels (nummer en naam), dan een lege regel en daarna de tabelkop op rij HR+1.
        var HR = titles.length ? titles.length + 1 : 0;
        var aoa = [];
        titles.forEach(function (line) { aoa.push([line]); });
        if (titles.length) { aoa.push([]); }
        aoa.push(data.columns);
        data.rows.forEach(function (r) { aoa.push(r.map(function (v) { return v === null ? '' : v; })); });
        var ws = XLSX.utils.aoa_to_sheet(aoa);
        var ACCOUNTING = '_-€ * #,##0.00_-;-€ * #,##0.00_-;_-€ * "-"??_-;_-@_-'; // Excel-opmaak "Financieel"
        var LINE = { style: 'thin', color: { rgb: 'FFBFBFBF' } };
        var BORDER = { top: LINE, bottom: LINE, left: LINE, right: LINE };
        var colIndex = {};
        data.columns.forEach(function (name, i) { colIndex[name] = i; });
        var formulas = data.formulas || {};
        var lastCol = data.columns.length - 1;

        titles.forEach(function (line, i) {
            ws[XLSX.utils.encode_cell({ r: i, c: 0 })].s = { font: { bold: true, sz: 18 } };
        });

        for (var c = 0; c <= lastCol; c++) {
            // Tabelkop: donkerblauw met witte vette tekst.
            var head = ws[XLSX.utils.encode_cell({ r: HR, c: c })];
            if (head) {
                head.s = {
                    font: { bold: true, color: { rgb: 'FFFFFFFF' } },
                    fill: { patternType: 'solid', fgColor: { rgb: 'FF1F4E78' } },
                    alignment: { horizontal: 'center', vertical: 'center', wrapText: true },
                    border: BORDER
                };
            }
            var template = formulas[data.columns[c]];
            if (template) {
                var missing = false;
                template.replace(/\{([^}]+)\}/g, function (m, name) { if (!(name in colIndex)) { missing = true; } });
                if (missing) { template = null; }
            }
            // Percentage zonder decimalen als alle waarden hele getallen zijn.
            var wholePct = true;
            if (isPct(c)) { data.rows.forEach(function (row) { if (row[c] !== null && Number(row[c]) % 1 !== 0) { wholePct = false; } }); }
            for (var r = HR + 1; r < aoa.length; r++) {
                var addr = XLSX.utils.encode_cell({ r: r, c: c });
                var cell = ws[addr];
                if (!cell) { cell = ws[addr] = { t: 's', v: '' }; }
                if ((data.text || []).indexOf(data.columns[c]) !== -1) {
                    // Artikelnummers altijd als tekst (geen getal, voorloopnullen blijven staan).
                    cell.t = 's'; cell.v = String(cell.v); cell.z = '@';
                }
                if (cell.t === 'n' && isPct(c)) { cell.v = cell.v / 100; cell.z = wholePct ? '0%' : '0.00%'; }
                if (cell.t === 'n' && isEuro(c)) { cell.z = ACCOUNTING; }
                if (cell.t === 's' && isDate(c) && ISO_DATE.test(cell.v)) {
                    var p = cell.v.split('-');
                    cell = ws[addr] = { t: 'n', v: Math.round(Date.UTC(+p[0], +p[1] - 1, +p[2]) / 86400000) + 25569, z: 'dd-mm-yyyy' };
                }
                if (template && cell.t === 'n') {
                    // Formule op dezelfde rij; de opgehaalde waarde blijft als berekende waarde staan.
                    cell.f = template.replace(/\{([^}]+)\}/g, function (m, name) { return XLSX.utils.encode_col(colIndex[name]) + (r + 1); });
                }
                cell.s = { border: BORDER, alignment: { vertical: 'center' } };
            }
        }

        ws['!ref'] = XLSX.utils.encode_range({ s: { r: 0, c: 0 }, e: { r: aoa.length - 1, c: lastCol } });
        ws['!autofilter'] = { ref: XLSX.utils.encode_range({ s: { r: HR, c: 0 }, e: { r: aoa.length - 1, c: lastCol } }) };
        ws['!rows'] = [];
        titles.forEach(function (line, i) { ws['!rows'][i] = { hpt: 24 }; });
        ws['!rows'][HR] = { hpt: 36 };
        ws['!cols'] = data.columns.map(function (name, c) {
            var w = Math.min(name.length + 4, 18);
            data.rows.slice(0, 200).forEach(function (row) {
                if (row[c] !== null) { w = Math.max(w, Math.min(String(row[c]).length + 2, 50)); }
            });
            return { wch: w };
        });
        var wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, box.getAttribute('data-sheet'));
        XLSX.writeFile(wb, box.getAttribute('data-file') + '_' + asked + '.xlsx');
    });
})();
