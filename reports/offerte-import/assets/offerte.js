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
    var itemsChecked = false;
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

    // ---- Template (lege Excel in dezelfde opzet als de bestaande offerte-template) ----
    function downloadTemplate() {
        var rows = [];
        for (var r = 0; r < 111; r++) { rows.push(['', '', '', '', '']); }
        rows[3][0] = 'Valuta'; rows[3][1] = 'EUR';
        rows[5][0] = 'Verkoper';
        rows[7] = ['Debiteurnummer', 'Omschrijving', 'Datum', 'Naam', ''];
        rows[10] = ['Regelnr', 'Tekstomschrijving', 'Artikelregelomschrijving', 'Artikelcode', 'Aantal'];
        for (var i = 1; i <= 100; i++) { rows[10 + i][0] = i; }
        var ws = XLSX.utils.aoa_to_sheet(rows);
        var bold = { font: { bold: true } };
        var head = { font: { bold: true, color: { rgb: 'FFFFFFFF' } }, fill: { patternType: 'solid', fgColor: { rgb: 'FF1F4E78' } }, alignment: { horizontal: 'center' } };
        ['A4', 'A6'].forEach(function (a) { ws[a].s = bold; });
        ['A8', 'B8', 'C8', 'D8', 'A11', 'B11', 'C11', 'D11', 'E11'].forEach(function (a) { ws[a].s = head; });
        var now = new Date();
        ws['C9'] = { t: 'n', v: Math.floor((Date.UTC(now.getFullYear(), now.getMonth(), now.getDate()) / 86400000) + 25569), z: 'dd-mm-yyyy' };
        ws['!cols'] = [{ wch: 16 }, { wch: 34 }, { wch: 34 }, { wch: 24 }, { wch: 10 }];
        ws['!ref'] = 'A1:E111';
        var help = XLSX.utils.aoa_to_sheet([
            [1, 'Geef het verkopernummer (cel B6) en het debiteurnummer (cel A9) in'],
            [2, 'Geef een omschrijving (cel B9) en eventueel een andere datum (cel C9) in'],
            [3, 'Geef vanaf regel 12 de artikelcode en het aantal in (maximaal 100 regels, meer mag door regelnummers door te nummeren)'],
            [4, 'Importeer het bestand in Offerte Import; de velden en artikelen worden gecontroleerd met Exact']
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
                sellerEl.value = cellText(val('B6'));
                debtorEl.value = cellText(val('A9'));
                descEl.value = cellText(val('B9'));
                dateEl.value = parseDate(val('C9')) || todayIso();
                lines = [];
                var range = XLSX.utils.decode_range(ws['!ref'] || 'A1:A1');
                var last = Math.min(range.e.r + 1, 2000);
                for (var row = 12; row <= last; row++) {
                    var line = { nr: cellText(val('A' + row)), text: cellText(val('B' + row)), desc: cellText(val('C' + row)), code: cellText(val('D' + row)), qty: val('E' + row) };
                    if (line.code === '' && line.text === '' && line.desc === '' && (line.qty === null || line.qty === '')) { continue; }
                    lines.push(line);
                }
                itemInfo = {};
                itemsChecked = false;
                importedName = file.name;
                render();
                showInfo(lines.length ? 'Geïmporteerd: ' + file.name + ' (' + lines.length + ' regels). De gegevens worden gecontroleerd met Exact…' : '');
                if (!lines.length) { showMessage('Geen regels gevonden in ' + file.name + '. Vul vanaf regel 12 de artikelcode en het aantal in (kolom D en E).'); }
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
            lines.forEach(function (l) { if (l.code !== '' && !seen[l.code.toUpperCase()]) { seen[l.code.toUpperCase()] = true; body.append('items[]', l.code); } });
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
                    itemsChecked = true;
                    if (lines.length) { showInfo('Geïmporteerd: ' + importedName + ' (' + lines.length + ' regels), gecontroleerd met Exact.'); }
                }
                render();
            })
            .catch(function (err) {
                if (ticket !== validating) { return; }
                showMessage('Controle met Exact mislukt: ' + err.message);
            });
    }
    function scheduleValidate() {
        clearTimeout(timer);
        timer = setTimeout(function () { validate(false); }, 400);
    }

    // ---- Tonen en beoordelen ----
    function lineState(l) {
        if (l.code === '') { return { cls: 'is-text', label: 'Tekstregel (niet geëxporteerd)', ok: true, export: false }; }
        if (qtyOf(l.qty) === null) { return { cls: 'is-incomplete', label: 'Aantal ontbreekt of is ongeldig', ok: false, export: false }; }
        if (!itemsChecked) { return { cls: '', label: 'Wordt gecontroleerd…', ok: false, export: false, pending: true }; }
        var info = itemInfo[l.code.toUpperCase()];
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
            var info = st.info || itemInfo[l.code.toUpperCase()] || null;
            html += '<tr class="' + st.cls + '">' +
                '<td>' + esc(l.nr) + '</td><td>' + esc(l.text) + '</td><td>' + esc(l.desc) + '</td><td>' + esc(l.code) + '</td>' +
                '<td class="num">' + esc(l.qty === null ? '' : l.qty) + '</td>' +
                '<td>' + esc(st.label) + '</td><td>' + esc(info && info.found ? info.description : '') + '</td><td>' + esc(info && info.found ? info.condition : '') + '</td></tr>';
        });
        tbody.innerHTML = html;
        resultEl.hidden = lines.length === 0;

        var articles = states.filter(function (s) { return s.cls !== 'is-text'; }).length;
        var missing = states.filter(function (s) { return s.cls === 'is-missing'; }).length;
        var incomplete = states.filter(function (s) { return s.cls === 'is-incomplete'; }).length;
        var pending = states.some(function (s) { return s.pending; });
        summaryEl.innerHTML =
            '<span class="msp-pill"><strong>' + lines.length + '</strong> regels</span>' +
            '<span class="msp-pill"><strong>' + articles + '</strong> artikelregels</span>' +
            (missing ? '<span class="msp-pill oi-pill-bad"><strong>' + missing + '</strong> niet in Exact</span>' : '') +
            (incomplete ? '<span class="msp-pill oi-pill-warn"><strong>' + incomplete + '</strong> zonder geldig aantal</span>' : '');

        // Export alleen als alles klopt
        var problems = [];
        if (!sellerInfo || !sellerInfo.found) { problems.push('verkoper'); }
        if (!debtorInfo || !debtorInfo.found) { problems.push('debiteurnummer'); }
        if (descEl.value.trim() === '') { problems.push('omschrijving'); }
        if (!dateOk) { problems.push('datum'); }
        if (!states.some(function (s) { return s.export; })) { problems.push('artikelregels'); }
        if (missing) { problems.push(missing + ' artikel(en) die niet in Exact bestaan'); }
        if (incomplete) { problems.push(incomplete + ' regel(s) zonder geldig aantal'); }
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
            out.push('     <Item code="' + xmlEsc(l.code) + '"></Item>');
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
