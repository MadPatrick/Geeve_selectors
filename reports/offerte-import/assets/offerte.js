(function () {
    var app = document.getElementById('oiApp');
    var endpoint = app.getAttribute('data-validate');
    var csrf = app.getAttribute('data-csrf');
    var sellerEl = document.getElementById('oiSeller');
    var debtorEl = document.getElementById('oiDebtor');
    var descEl = document.getElementById('oiDescription');
    var dateEl = document.getElementById('oiDate');
    var checks = {
        seller: document.getElementById('oiSellerCheck'),
        debtor: document.getElementById('oiDebtorCheck'),
        desc: document.getElementById('oiDescriptionCheck'),
        date: document.getElementById('oiDateCheck')
    };
    var dropEl = document.getElementById('oiDrop');
    var fileEl = document.getElementById('oiFile');
    var messageEl = document.getElementById('oiMessage');
    var infoEl = document.getElementById('oiInfo');
    var exportBtn = document.getElementById('oiExport');
    var resultEl = document.getElementById('oiResult');
    var summaryEl = document.getElementById('oiSummary');
    var tbody = document.querySelector('#oiTable tbody');

    var lines = [];          // {nr, text, desc, code, qty}
    var itemInfo = {};       // CODE (hoofdletters) -> {found, description, condition}
    var sellerInfo = null;   // {found, name}
    var debtorInfo = null;
    var checked = {};        // CODE -> true zodra de controle met Exact klaar is
    var codeTimers = {};
    var importedName = '';
    var timer = null;
    var validating = 0;

    function esc(s) {
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function pad(n) { return ('0' + n).slice(-2); }
    function toIso(d) { return d.getUTCFullYear() + '-' + pad(d.getUTCMonth() + 1) + '-' + pad(d.getUTCDate()); }
    function todayIso() { var n = new Date(); return n.getFullYear() + '-' + pad(n.getMonth() + 1) + '-' + pad(n.getDate()); }
    function serialToIso(n) { return toIso(new Date(Math.round((n - 25569) * 86400000))); }
    function parseDate(v) {
        if (v === null || v === undefined || v === '') { return ''; }
        if (typeof v === 'number') { return serialToIso(v); }
        if (v instanceof Date) { return toIso(new Date(Date.UTC(v.getFullYear(), v.getMonth(), v.getDate()))); }
        var s = String(v).trim(), m;
        if ((m = /^(\d{4})-(\d{2})-(\d{2})/.exec(s))) { return m[1] + '-' + m[2] + '-' + m[3]; }
        if ((m = /^(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{4})$/.exec(s))) { return m[3] + '-' + pad(+m[2]) + '-' + pad(+m[1]); }
        return '';
    }
    function cellText(v) {
        if (v === null || v === undefined) { return ''; }
        if (typeof v === 'number') { return String(v); }
        return String(v).trim();
    }
    // Artikelcode zoals bedoeld (zonder spaties aan de randen) en als sleutel (hoofdletters); het veld zelf blijft ongemoeid tijdens typen.
    function cv(l) { return l.code.trim(); }
    function ck(l) { return l.code.trim().toUpperCase(); }
    function qtyOf(q) {
        var n = Number(String(q === null || q === undefined ? '' : q).replace(',', '.'));
        return isFinite(n) && n > 0 ? n : null;
    }
    function showMessage(text) { messageEl.textContent = text; messageEl.hidden = !text; }
    function showInfo(text) { infoEl.textContent = text; infoEl.hidden = !text; }
    function setCheck(el, text, ok) {
        el.textContent = text;
        el.className = 'oi-check' + (text ? (ok ? ' is-ok' : ' is-bad') : '');
    }

    // ---- Template: alleen de regels (Regelnr, Aantal, Artikelcode); de kopvelden vul je in op het dashboard ----
    function downloadTemplate() {
        var rows = [['Regelnr', 'Aantal', 'Artikelcode']];
        for (var i = 1; i <= 100; i++) { rows.push([i, '', '']); }
        var ws = XLSX.utils.aoa_to_sheet(rows);
        var head = { font: { bold: true, color: { rgb: 'FFFFFFFF' } }, fill: { patternType: 'solid', fgColor: { rgb: 'FF1F4E78' } }, alignment: { horizontal: 'center' } };
        ['A1', 'B1', 'C1'].forEach(function (a) { ws[a].s = head; });
        for (var r = 2; r <= 101; r++) { ws['C' + r] = { t: 's', v: '', z: '@' }; } // artikelcodes altijd als tekst
        ws['!cols'] = [{ wch: 10 }, { wch: 10 }, { wch: 28 }];
        ws['!ref'] = 'A1:C101';
        var help = XLSX.utils.aoa_to_sheet([
            [1, 'Geef per regel het aantal (kolom B) en de artikelcode (kolom C) in'],
            [2, 'Meer dan 100 regels? Nummer de regelnummers gewoon door'],
            [3, 'Verkoper, debiteurnummer, omschrijving en datum vul je in op het dashboard (Offerte Import)'],
            [4, 'Importeer het bestand in Offerte Import; de artikelen worden gecontroleerd met Exact en de artikelomschrijving wordt opgehaald']
        ]);
        help['!cols'] = [{ wch: 4 }, { wch: 110 }];
        var wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'Order');
        XLSX.utils.book_append_sheet(wb, help, 'Uitleg');
        XLSX.writeFile(wb, 'Offerte_import_template.xlsx');
    }

    // ---- Excel inlezen ----
    function importFile(file) {
        showMessage('');
        showInfo('');
        var reader = new FileReader();
        reader.onload = function (e) {
            try {
                var wb = XLSX.read(new Uint8Array(e.target.result), { type: 'array' });
                var name = wb.SheetNames.indexOf('Order') !== -1 ? 'Order' : wb.SheetNames[0];
                var ws = wb.Sheets[name];
                var val = function (a) { return ws[a] ? ws[a].v : null; };
                var range = XLSX.utils.decode_range(ws['!ref'] || 'A1:A1');
                var last = Math.min(range.e.r + 1, 2000);

                // De kop met Artikelcode zoeken (nieuwe template: rij 1; oude template: rij 11) en de kolommen op naam bepalen.
                var headRow = 0, cols = {};
                for (var hr = 1; hr <= Math.min(last, 40) && !headRow; hr++) {
                    for (var c = 0; c <= Math.min(range.e.c, 15); c++) {
                        var addr = XLSX.utils.encode_col(c) + hr;
                        if (String(val(addr) || '').trim().toLowerCase() === 'artikelcode') { headRow = hr; }
                    }
                }
                if (!headRow) { throw new Error('Geen kolom "Artikelcode" gevonden. Gebruik de template (Regelnr, Aantal, Artikelcode).'); }
                for (var cc = 0; cc <= Math.min(range.e.c, 15); cc++) {
                    var colName = String(val(XLSX.utils.encode_col(cc) + headRow) || '').trim().toLowerCase();
                    if (colName) { cols[colName] = XLSX.utils.encode_col(cc); }
                }
                if (!cols['aantal']) { throw new Error('Geen kolom "Aantal" gevonden. Gebruik de template (Regelnr, Aantal, Artikelcode).'); }

                // Oude template (met kopvelden bovenin): die velden worden ook overgenomen als ze erin staan.
                if (String(val('A8') || '').trim().toLowerCase() === 'debiteurnummer') {
                    sellerEl.value = cellText(val('B6'));
                    debtorEl.value = cellText(val('A9'));
                    descEl.value = cellText(val('B9'));
                    dateEl.value = parseDate(val('C9')) || dateEl.value || todayIso();
                }

                lines = [];
                for (var row = headRow + 1; row <= last; row++) {
                    var line = {
                        nr: cols['regelnr'] ? cellText(val(cols['regelnr'] + row)) : '',
                        qty: val(cols['aantal'] + row),
                        code: cellText(val(cols['artikelcode'] + row)).toUpperCase()
                    };
                    if (line.code === '' && (line.qty === null || line.qty === '')) { continue; }
                    lines.push(line);
                }
                itemInfo = {};
                checked = {};
                importedName = file.name;
                render();
                showInfo(lines.length ? 'Geïmporteerd: ' + file.name + ' (' + lines.length + ' regels). De gegevens worden gecontroleerd met Exact…' : '');
                if (!lines.length) { showMessage('Geen regels gevonden in ' + file.name + '. Vul in de template het aantal (kolom B) en de artikelcode (kolom C) in.'); }
                validate(true);
            } catch (err) {
                showMessage('Het bestand kon niet worden gelezen: ' + err.message);
            }
        };
        reader.onerror = function () { showMessage('Het bestand kon niet worden gelezen.'); };
        reader.readAsArrayBuffer(file);
    }

    // ---- Controle met Exact ----
    function validate(withItems) {
        var body = new URLSearchParams();
        body.set('csrf', csrf);
        body.set('verkoper', sellerEl.value.trim());
        body.set('debiteur', debtorEl.value.trim());
        if (withItems) {
            var seen = {};
            lines.forEach(function (l) { if (cv(l) !== '' && !seen[ck(l)]) { seen[ck(l)] = true; body.append('items[]', cv(l)); } });
        }
        var ticket = ++validating;
        fetch(endpoint, { method: 'POST', body: body })
            .then(function (r) { return r.json().catch(function () { return { ok: false, error: 'Onverwacht antwoord van de server.' }; }); })
            .then(function (json) {
                if (ticket !== validating) { return; }
                if (!json.ok) { throw new Error(json.error || 'Controle mislukt.'); }
                sellerInfo = json.verkoper;
                debtorInfo = json.debiteur;
                if (withItems) {
                    itemInfo = json.items || {};
                    lines.forEach(function (l) { if (cv(l) !== '') { checked[ck(l)] = true; } });
                    if (lines.length) { showInfo('Geïmporteerd: ' + importedName + ' (' + lines.length + ' regels), gecontroleerd met Exact.'); }
                }
                render();
            })
            .catch(function (err) {
                if (ticket !== validating) { return; }
                showMessage('Controle met Exact mislukt: ' + err.message);
            });
    }
    // Eén artikelcode (na een wijziging in de tabel) opnieuw controleren; verkoper en debiteur blijven ongemoeid.
    function validateItem(code) {
        var key = code.toUpperCase();
        var body = new URLSearchParams();
        body.set('csrf', csrf);
        body.append('items[]', code);
        fetch(endpoint, { method: 'POST', body: body })
            .then(function (r) { return r.json().catch(function () { return { ok: false, error: 'Onverwacht antwoord van de server.' }; }); })
            .then(function (json) {
                if (!json.ok) { throw new Error(json.error || 'Controle mislukt.'); }
                // Alleen toepassen als de code nog zo in de tabel staat.
                if (!lines.some(function (l) { return ck(l) === key; })) { return; }
                if (json.items && json.items[key]) { itemInfo[key] = json.items[key]; } else { delete itemInfo[key]; }
                checked[key] = true;
                render();
            })
            .catch(function (err) { showMessage('Controle met Exact mislukt: ' + err.message); });
    }
    function scheduleValidate() {
        clearTimeout(timer);
        timer = setTimeout(function () { validate(false); }, 400);
    }

    // ---- Tonen en beoordelen ----
    function lineState(l) {
        if (cv(l) === '') { return { cls: 'is-incomplete', label: 'Artikelcode ontbreekt', ok: false, export: false }; }
        if (qtyOf(l.qty) === null) { return { cls: 'is-incomplete', label: 'Aantal ontbreekt of is ongeldig', ok: false, export: false }; }
        if (!checked[ck(l)]) { return { cls: '', label: 'Wordt gecontroleerd…', ok: false, export: false, pending: true }; }
        var info = itemInfo[ck(l)];
        if (!info || !info.found) { return { cls: 'is-missing', label: 'Niet in Exact', ok: false, export: false }; }
        return { cls: '', label: 'Ja', ok: true, export: true, info: info };
    }

    function render() {
        // Veldcontroles
        var seller = sellerEl.value.trim();
        if (seller === '') { setCheck(checks.seller, '', true); }
        else if (!sellerInfo) { setCheck(checks.seller, 'Wordt gecontroleerd…', true); checks.seller.className = 'oi-check'; }
        else { setCheck(checks.seller, sellerInfo.found ? '✓ ' + sellerInfo.name : '✗ Verkoper niet gevonden', sellerInfo.found); }
        var debtor = debtorEl.value.trim();
        if (debtor === '') { setCheck(checks.debtor, '', true); }
        else if (!debtorInfo) { setCheck(checks.debtor, 'Wordt gecontroleerd…', true); checks.debtor.className = 'oi-check'; }
        else { setCheck(checks.debtor, debtorInfo.found ? '✓ ' + debtorInfo.name : '✗ Debiteur niet gevonden', debtorInfo.found); }
        setCheck(checks.desc, descEl.value.trim() === '' ? (lines.length ? '✗ Omschrijving ontbreekt' : '') : '', descEl.value.trim() !== '');
        var dateOk = /^\d{4}-\d{2}-\d{2}$/.test(dateEl.value);
        setCheck(checks.date, dateOk || !lines.length ? '' : '✗ Datum ontbreekt', dateOk);

        // Tabel
        var states = lines.map(lineState);
        var html = '';
        lines.forEach(function (l, i) {
            var st = states[i];
            var info = st.info || itemInfo[ck(l)] || null;
            html += '<tr class="' + st.cls + '">' +
                '<td>' + esc(l.nr) + '</td><td class="oi-qty">' + esc(l.qty === null ? '' : l.qty) + '</td>' +
                '<td><input type="text" class="ka-input oi-code" data-i="' + i + '" value="' + esc(l.code) + '" maxlength="30" autocomplete="off" spellcheck="false" aria-label="Artikelcode regel ' + esc(l.nr || (i + 1)) + '"></td>' +
                '<td>' + esc(info && info.found ? info.description : '') + '</td>' +
                '<td>' + esc(st.label) + '</td><td>' + esc(info && info.found ? info.condition : '') + '</td></tr>';
        });
        // Staat de cursor in een artikelcode, dan blijft die daar na het opnieuw tekenen van de tabel.
        var active = document.activeElement;
        var focusIdx = active && active.classList && active.classList.contains('oi-code') ? active.getAttribute('data-i') : null;
        var caret = focusIdx !== null ? active.selectionStart : null;
        tbody.innerHTML = html;
        if (focusIdx !== null) {
            var again = tbody.querySelector('input.oi-code[data-i="' + focusIdx + '"]');
            if (again) { again.focus(); if (caret !== null) { again.setSelectionRange(caret, caret); } }
        }
        resultEl.hidden = lines.length === 0;

        var articles = states.length;
        var missing = states.filter(function (s) { return s.cls === 'is-missing'; }).length;
        var incomplete = states.filter(function (s) { return s.cls === 'is-incomplete'; }).length;
        var pending = states.some(function (s) { return s.pending; });
        summaryEl.innerHTML =
            '<span class="msp-pill"><strong>' + lines.length + '</strong> regels</span>' +
            (missing ? '<span class="msp-pill oi-pill-bad"><strong>' + missing + '</strong> niet in Exact</span>' : '') +
            (incomplete ? '<span class="msp-pill oi-pill-warn"><strong>' + incomplete + '</strong> onvolledig (aantal of artikelcode)</span>' : '');

        // Export alleen als alles klopt
        var problems = [];
        if (!sellerInfo || !sellerInfo.found) { problems.push('verkoper'); }
        if (!debtorInfo || !debtorInfo.found) { problems.push('debiteurnummer'); }
        if (descEl.value.trim() === '') { problems.push('omschrijving'); }
        if (!dateOk) { problems.push('datum'); }
        if (!states.some(function (s) { return s.export; })) { problems.push('artikelregels'); }
        if (missing) { problems.push(missing + ' artikel(en) die niet in Exact bestaan'); }
        if (incomplete) { problems.push(incomplete + ' onvolledige regel(s) (aantal of artikelcode)'); }
        var ready = lines.length > 0 && problems.length === 0 && !pending;
        exportBtn.disabled = !ready;
        exportBtn.title = ready || !lines.length ? '' : 'Eerst oplossen: ' + problems.join(', ');
    }

    // ---- XML (zelfde opbouw als de macro in de Excel-template) ----
    function xmlEsc(s) { return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
    function buildXml() {
        var debtor = xmlEsc(debtorEl.value.trim());
        var date = dateEl.value;
        var out = [];
        out.push('<?xml version="1.0" encoding="UTF-8"?>');
        out.push('<eExact xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="eExact-Schema.xsd">');
        out.push('<Orders>');
        out.push(' <Order type="Q">');
        out.push('   <Description>' + xmlEsc(descEl.value.trim()) + '</Description>');
        out.push('   <OrderedBy>');
        out.push('      <Debtor code="' + debtor + '"  Number="' + debtor + '"></Debtor>');
        out.push('      <Date>' + date + '</Date>');
        out.push('   </OrderedBy>');
        out.push('   <DeliverTo>');
        out.push('      <Debtor code="' + debtor + '"  Number="' + debtor + '"></Debtor>');
        out.push('      <Date>' + date + '</Date>');
        out.push('   </DeliverTo>');
        out.push('   <InvoiceTo>');
        out.push('      <Debtor code="' + debtor + '"  Number="' + debtor + '"></Debtor>');
        out.push('   </InvoiceTo>');
        var n = 0;
        lines.forEach(function (l) {
            var st = lineState(l);
            if (!st.export) { return; }
            n++;
            out.push('   <OrderLine lineNo="' + xmlEsc(l.nr !== '' ? l.nr : n) + '">');
            out.push('     <Quantity>' + qtyOf(l.qty) + '</Quantity>');
            out.push('     <Item code="' + xmlEsc(cv(l)) + '"></Item>');
            out.push('  </OrderLine>');
        });
        out.push(' </Order>');
        out.push('</Orders>');
        out.push('</eExact>');
        return out.join('\r\n') + '\r\n';
    }
    function exportXml() {
        if (exportBtn.disabled) { return; }
        var blob = new Blob([buildXml()], { type: 'application/xml;charset=utf-8' });
        var a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'Offerte-' + debtorEl.value.trim().replace(/[^A-Za-z0-9._-]+/g, '_') + '.xml';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        setTimeout(function () { URL.revokeObjectURL(a.href); }, 1000);
        showInfo('XML-bestand gemaakt. Importeer het in Exact via Systeem\\Importeren XML\\Logistieke import.');
    }

    // Artikelcode wijzigen in de tabel: altijd hoofdletters, en na een korte pauze opnieuw controleren met Exact.
    tbody.addEventListener('input', function (e) {
        var el = e.target;
        if (!el.classList || !el.classList.contains('oi-code')) { return; }
        var i = +el.getAttribute('data-i');
        var pos = el.selectionStart;
        el.value = el.value.toUpperCase();
        if (pos !== null) { el.setSelectionRange(pos, pos); }
        lines[i].code = el.value; // ruwe invoer (alleen hoofdletters), zodat een spatie midden in de code niet verdwijnt
        var code = el.value.trim();
        // Rij direct bijwerken (nog niet gecontroleerd), daarna debounce de controle.
        render();
        clearTimeout(codeTimers[i]);
        if (code !== '' && !checked[code.toUpperCase()]) {
            codeTimers[i] = setTimeout(function () { validateItem(code); }, 400);
        }
    });

    // ---- Koppelingen ----
    document.getElementById('oiTemplate').addEventListener('click', function () {
        if (typeof XLSX === 'undefined') { showMessage('Excel-bibliotheek niet geladen.'); return; }
        downloadTemplate();
    });
    exportBtn.addEventListener('click', exportXml);
    dropEl.addEventListener('click', function () { fileEl.click(); });
    dropEl.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); fileEl.click(); } });
    fileEl.addEventListener('change', function () { if (fileEl.files[0]) { importFile(fileEl.files[0]); fileEl.value = ''; } });
    ['dragenter', 'dragover'].forEach(function (ev) { dropEl.addEventListener(ev, function (e) { e.preventDefault(); dropEl.classList.add('is-over'); }); });
    ['dragleave', 'drop'].forEach(function (ev) { dropEl.addEventListener(ev, function (e) { e.preventDefault(); dropEl.classList.remove('is-over'); }); });
    dropEl.addEventListener('drop', function (e) { var f = e.dataTransfer && e.dataTransfer.files[0]; if (f) { importFile(f); } });
    [sellerEl, debtorEl].forEach(function (el) {
        el.addEventListener('input', function () { if (el === sellerEl) { sellerInfo = null; } else { debtorInfo = null; } render(); scheduleValidate(); });
    });
    [descEl, dateEl].forEach(function (el) { el.addEventListener('input', render); });
    dateEl.value = todayIso();
    render();
})();
