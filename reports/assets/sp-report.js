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
    var measure = null;      // gekozen telling bij rapporten met meerdere reeksen (data.sets)
    var measureBox = document.getElementById('rpMeasure');
    var modeInput = document.getElementById('rpModus');
    var viewInput = document.getElementById('rpWeergave');
    var viewBox = document.getElementById('rpView');
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

    // Som-kolommen (Totaal, Deze week, Week x, Deze maand) krijgen een eigen opmaak.
    function withSum(td, i) {
        if ((data.sumColumns || []).indexOf(data.columns[i]) === -1) { return td; }
        return td.indexOf('<td class="') === 0 ? td.replace('<td class="', '<td class="rp-sum ') : td.replace('<td>', '<td class="rp-sum">');
    }

    function render() {
        summary.innerHTML =
            (box.getAttribute('data-label') ? '<span class="msp-pill">' + esc(box.getAttribute('data-label')) + ' <strong>' + esc(asked) + '</strong></span>' : '') +
            (data.kop || []).map(function (k) {
                return '<span class="msp-pill">' + (k.label ? esc(k.label) + ' ' : '') + '<strong>' + esc(k.type === 'euro' ? euro(k.value) : k.value) + '</strong></span>';
            }).join('') +
            (data.hideRowCount ? '' : '<span class="msp-pill"><strong>' + nl(data.rows.length) + '</strong> regels</span>');
        function sumClass(i) { return (data.sumColumns || []).indexOf(data.columns[i]) !== -1 ? ' class="rp-sum"' : ''; }
        var html = '<thead><tr>' + data.columns.map(function (c, i) { return '<th' + sumClass(i) + '>' + esc(c.trim()) + '</th>'; }).join('') + '</tr></thead><tbody>';
        data.rows.forEach(function (r, ri) {
            var emp = data.rowIds ? data.rowIds[ri] : null;
            html += '<tr' + (data.sets && r[0] === 'Totaal' ? ' class="rp-total"' : '') + (emp !== null && emp !== undefined ? ' data-emp="' + emp + '"' : '') + '>' + r.map(function (v, i) { return withSum((function () {
                if (v === null) { return data.yearPending && data.columns[i] === 'Dit jaar' ? '<td class="num">…</td>' : '<td></td>'; }
                if ((data.nowrap || []).indexOf(data.columns[i]) !== -1) { return '<td class="nowrap">' + esc(v) + '</td>'; }
                if (isPct(i) && typeof v === 'number') { return '<td class="num">' + pct(v) + '</td>'; }
                if (isEuro(i) && typeof v === 'number') { return '<td class="num">' + euro(v) + '</td>'; }
                if (data.numeric[i] && typeof v === 'number') {
                    // Bij rapporten met details (colRanges) is een getal in de regel van een medewerker klikbaar.
                    var clickable = data.colRanges && data.colRanges[i] && emp !== null && emp !== undefined;
                    return '<td class="num' + (clickable ? ' rp-click' : '') + '"' + (clickable ? ' data-col="' + i + '" title="Toon de orders"' : '') + '>' + nl(v) + '</td>';
                }
                if (isDate(i) && typeof v === 'string' && ISO_DATE.test(v)) { return '<td>' + dmy(v) + '</td>'; }
                return '<td>' + esc(v) + '</td>';
            })(), i); }).join('') + '</tr>';
        });
        table.innerHTML = html + '</tbody>';
        result.hidden = false;
    }

    // Schakelaar Orders / Orderregels: wisselt de getoonde reeks zonder opnieuw op te halen.
    function selectMeasure(name) {
        measure = name;
        data.rows = data.sets[name];
        data.rowIds = data.setIds ? data.setIds[name] : null;
        if (measureBox) {
            measureBox.hidden = false;
            Array.prototype.forEach.call(measureBox.querySelectorAll('[data-measure]'), function (b) {
                b.classList.toggle('is-active', b.getAttribute('data-measure') === name);
            });
        }
        render();
        downloadBtn.disabled = data.rows.length === 0 || !!data.yearPending;
    }
    if (measureBox) {
        Array.prototype.forEach.call(measureBox.querySelectorAll('[data-measure]'), function (b) {
            b.addEventListener('click', function () { if (data && data.sets) { selectMeasure(b.getAttribute('data-measure')); } });
        });
    }
    // Schakelaar Overzicht / Eigen periode: bij een eigen periode verschijnen de datumvelden.
    if (viewBox) {
        Array.prototype.forEach.call(viewBox.querySelectorAll('[data-view]'), function (b) {
            b.addEventListener('click', function () {
                viewInput.value = b.getAttribute('data-view');
                Array.prototype.forEach.call(viewBox.querySelectorAll('[data-view]'), function (x) { x.classList.toggle('is-active', x === b); });
                Array.prototype.forEach.call(box.querySelectorAll('.rp-dates'), function (el) { el.hidden = viewInput.value !== 'periode'; });
                if (data && viewInput.value === 'overzicht') { fetchData(); }
            });
        });
    }
    // "Dit jaar" is zwaar en wordt daarom apart opgehaald, nadat het overzicht al getoond wordt; daarna vult de kolom zich aan.
    function loadYear(forData, params) {
        var body = new URLSearchParams();
        body.set('csrf', box.getAttribute('data-csrf'));
        params.forEach(function (p) { body.set(p[0], p[1]); });
        body.set('jaar', '1');
        status.textContent = 'Dit jaar wordt aangevuld…';
        fetch(box.getAttribute('data-endpoint'), { method: 'POST', body: body })
            .then(function (r) { return r.json().catch(function () { return { ok: false, error: 'Onverwacht antwoord van de server.' }; }); })
            .then(function (json) {
                if (!json.ok || !json.year) { throw new Error(json.error || 'Ophalen mislukt.'); }
                if (data !== forData) { return; }
                var last = data.columns.length - 1;
                Object.keys(data.sets).forEach(function (name) {
                    var rows = data.sets[name], ids = data.setIds[name];
                    rows.forEach(function (row, i) {
                        if (ids[i] !== null && ids[i] !== undefined) {
                            var n = (json.year[name] || {})[ids[i]];
                            row[last] = n > 0 ? n : null;
                        } else if (row[0] === 'Totaal') {
                            var tot = (json.yearTotals || {})[name];
                            row[last] = tot > 0 ? tot : null;
                        }
                    });
                });
                data.yearPending = false;
                data.rows = data.sets[measure];
                render();
                downloadBtn.disabled = data.rows.length === 0;
                status.textContent = 'Opgehaald om ' + new Date().toLocaleTimeString('nl-NL', { hour: '2-digit', minute: '2-digit' }) + '.';
            })
            .catch(function (err) {
                if (data !== forData) { return; }
                data.yearPending = false;
                render();
                downloadBtn.disabled = data.rows.length === 0;
                status.textContent = '';
                fail('Dit jaar kon niet worden opgehaald: ' + err.message);
            });
    }

    // Schakelaar Productie / Verkoop: andere gegevens, dus (als er al iets getoond wordt) opnieuw ophalen.
    Array.prototype.forEach.call(box.querySelectorAll('[data-mode]'), function (b) {
        b.addEventListener('click', function () {
            modeInput.value = b.getAttribute('data-mode');
            Array.prototype.forEach.call(box.querySelectorAll('[data-mode]'), function (x) { x.classList.toggle('is-active', x === b); });
            if (data) { fetchData(); }
        });
    });

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
                if (data.sets) {
                    var keys = Object.keys(data.sets);
                    if (!measure || !(measure in data.sets)) { measure = keys[0]; }
                    selectMeasure(measure);
                }
                render();
                downloadBtn.disabled = data.rows.length === 0 || !!data.yearPending;
                status.textContent = data.rows.length === 0
                    ? 'Geen regels gevonden.'
                    : 'Opgehaald om ' + new Date().toLocaleTimeString('nl-NL', { hour: '2-digit', minute: '2-digit' }) + '.';
                if (data.yearPending) { loadYear(data, params); }
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
        var titles = (data.titles || []).map(function (line) { return line.replace('{meting}', measure || ''); });
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
        XLSX.utils.book_append_sheet(wb, ws, box.getAttribute('data-sheet') + (measure ? ' ' + measure : ''));
        XLSX.writeFile(wb, box.getAttribute('data-file') + '_' + asked + (measure ? '_' + measure : '') + '.xlsx');
    });

    // Details: klik op een getal in de regel van een medewerker -> de orders (of pakbonnen) erachter, direct onder die regel.
    // Nog een klik op hetzelfde getal klapt ze weer dicht; een klik op een ander getal verplaatst ze.
    function closeDetail() {
        var open = table.querySelector('tr.rp-detail-row');
        if (open) { open.parentNode.removeChild(open); }
        Array.prototype.forEach.call(table.querySelectorAll('td.rp-active'), function (c) { c.classList.remove('rp-active'); });
    }
    function detailHtml(d) {
        var h = '<div class="rp-detail-title">' + esc(d.title) + ' (' + nl(d.rows.length) + (d.truncated ? '+' : '') + ')</div>';
        if (d.truncated) { h += '<div class="rp-detail-note">Alleen de eerste 500 worden getoond.</div>'; }
        h += '<table class="ka-table rp-detail-table"><thead><tr>' + d.columns.map(function (c) { return '<th>' + esc(c) + '</th>'; }).join('') + '</tr></thead><tbody>';
        d.rows.forEach(function (row) {
            h += '<tr>' + row.map(function (v, i) {
                if (v === null || v === '') { return '<td></td>'; }
                if (d.columns[i] === 'Datum' && ISO_DATE.test(v)) { return '<td>' + dmy(v) + '</td>'; }
                if (typeof v === 'number') { return '<td class="num">' + nl(v) + '</td>'; }
                return '<td>' + esc(v) + '</td>';
            }).join('') + '</tr>';
        });
        return h + '</tbody></table>';
    }
    table.addEventListener('click', function (e) {
        var td = e.target.closest ? e.target.closest('td.rp-click') : null;
        if (!td || !data || !data.colRanges) { return; }
        var tr = td.parentNode;
        var wasActive = td.classList.contains('rp-active');
        closeDetail();
        if (wasActive) { return; }
        var range = data.colRanges[+td.getAttribute('data-col')];
        if (!range) { return; }
        td.classList.add('rp-active');
        var row = document.createElement('tr');
        row.className = 'rp-detail-row';
        var cell = document.createElement('td');
        cell.colSpan = tr.children.length;
        cell.innerHTML = '<div class="rp-detail-title">Bezig met ophalen…</div>';
        row.appendChild(cell);
        tr.parentNode.insertBefore(row, tr.nextSibling);
        var body = new URLSearchParams();
        body.set('csrf', box.getAttribute('data-csrf'));
        body.set('modus', modeInput ? modeInput.value : '');
        body.set('detail', tr.getAttribute('data-emp'));
        body.set('dvan', range[0]);
        body.set('dtot', range[1]);
        fetch(box.getAttribute('data-endpoint'), { method: 'POST', body: body })
            .then(function (r) { return r.json().catch(function () { return { ok: false, error: 'Onverwacht antwoord van de server.' }; }); })
            .then(function (json) {
                if (!json.ok || !json.detail) { throw new Error(json.error || 'Ophalen mislukt.'); }
                if (row.parentNode) { cell.innerHTML = detailHtml(json.detail); }
            })
            .catch(function (err) {
                if (row.parentNode) { cell.innerHTML = '<div class="ka-message ka-message--error">' + esc(err.message) + '</div>'; }
            });
    });
})();
