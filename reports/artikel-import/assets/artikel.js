(function () {
    var app = document.getElementById('aiApp');
    var endpoint = app.getAttribute('data-validate');
    var csrf = app.getAttribute('data-csrf');
    var dropEl = document.getElementById('aiDrop');
    var fileEl = document.getElementById('aiFile');
    var messageEl = document.getElementById('aiMessage');
    var infoEl = document.getElementById('aiInfo');
    var warningsEl = document.getElementById('aiWarnings');
    var problemsEl = document.getElementById('aiProblems');
    var exportBtn = document.getElementById('aiExport');
    var resultEl = document.getElementById('aiResult');
    var summaryEl = document.getElementById('aiSummary');
    var tbody = document.querySelector('#aiTable tbody');

    // Velden in de volgorde van de tabel; "col" is de kolomkop in de Excel-template (Artikelgegevens_Import_Ramon).
    var FIELDS = [
        { key: 'desc', col: 'Omschrijving', type: 'text', max: 60, cls: 'ai-desc' },
        { key: 'status', col: 'Status', type: 'status' },
        { key: 'weight', col: 'Gewicht', type: 'num' },
        { key: 'price', col: 'Verkoopprijs', type: 'num' },
        { key: 'group', col: 'Artikelgroep', type: 'text', max: 20, cls: 'ai-short' },
        { key: 'supplier', col: 'Code leverancier', type: 'text', max: 20, cls: 'ai-short' },
        { key: 'supItem', col: 'Artikelcode leverancier', type: 'text', max: 30, cls: 'ai-mid' },
        { key: 'supPrice', col: 'Inkoopprijs leverancier', type: 'num' },
        { key: 'supSize', col: 'Bestelgrootte leverancier', type: 'num' }
    ];
    var STATUS = { A: 'A - Actief', D: 'D - Vervallen', E: 'E - Niet actief' };
    var SUPPLIER_DETAIL = ['supItem', 'supPrice', 'supSize'];

    var lines = [];          // {code, desc, status, weight, price, group, supplier, supItem, supPrice, supSize}
    var itemInfo = {};       // CODE -> antwoord van validate.php (found, description, status, ...)
    var supplierInfo = {};   // CODE -> {found, name, nr}
    var groupInfo = {};      // CODE -> {found, description}
    var inFlight = { items: {}, suppliers: {}, groups: {} };
    var warnings = [];
    var importedName = '';
    var timer = null;

    function esc(s) {
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function showMessage(text) { messageEl.textContent = text; messageEl.hidden = !text; }
    function showInfo(text) { infoEl.textContent = text; infoEl.hidden = !text; }
    function key(v) { return String(v || '').trim().toUpperCase(); }
    function emptyLine() {
        var l = { code: '' };
        FIELDS.forEach(function (f) { l[f.key] = ''; });
        return l;
    }
    // Getal zoals ingevuld (komma of punt als decimaalteken; met beide is de punt het duizendtalteken) -> getal of null.
    function parseNum(v) {
        var s = String(v === null || v === undefined ? '' : v).trim().replace(/\s/g, '');
        if (s === '') { return null; }
        if (s.indexOf(',') !== -1 && s.indexOf('.') !== -1) { s = s.replace(/\./g, ''); }
        s = s.replace(',', '.');
        if (!/^-?\d+(\.\d+)?$/.test(s)) { return NaN; }
        return Number(s);
    }
    function nl(n) { return String(n).replace('.', ','); }
    function cellText(v) {
        if (v === null || v === undefined) { return ''; }
        if (typeof v === 'number') { return nl(v); }
        return String(v).trim();
    }
    function statusOf(v) {
        var m = /^\s*([A-Za-z])(\s*[-–].*)?$/.exec(String(v || ''));
        return m ? m[1].toUpperCase() : String(v || '').trim();
    }

    // ---- Template: dezelfde kolommen als de Excel-template, kop in rij 1 ----
    function downloadTemplate() {
        // Kolomvolgorde zoals de oude template (niet die van de tabel op het dashboard).
        var head = ['Artikelcode', 'Status', 'Gewicht', 'Verkoopprijs', 'Code leverancier', 'Artikelcode leverancier', 'Inkoopprijs leverancier', 'Bestelgrootte leverancier', 'Artikelgroep', 'Omschrijving'];
        var ws = XLSX.utils.aoa_to_sheet([head]);
        var style = { font: { bold: true, color: { rgb: 'FFFFFFFF' } }, fill: { patternType: 'solid', fgColor: { rgb: 'FF1F4E78' } }, alignment: { horizontal: 'center' } };
        var textCols = ['A', 'B', 'E', 'F', 'I', 'J']; // codes en teksten altijd als tekst (voorloopnullen blijven staan)
        head.forEach(function (h, c) { ws[XLSX.utils.encode_col(c) + '1'].s = style; });
        for (var r = 2; r <= 501; r++) { textCols.forEach(function (c) { ws[c + r] = { t: 's', v: '', z: '@' }; }); }
        ws['!cols'] = [{ wch: 22 }, { wch: 8 }, { wch: 10 }, { wch: 12 }, { wch: 16 }, { wch: 24 }, { wch: 22 }, { wch: 24 }, { wch: 12 }, { wch: 50 }];
        ws['!ref'] = 'A1:J501';
        var help = XLSX.utils.aoa_to_sheet([
            ['Kolom', 'Veld', 'Betekenis'],
            ['A', 'Artikelcode', 'Verplicht (max. 30 tekens). Bestaat het artikel niet in Exact, dan wordt het aangemaakt (vul dan ook de omschrijving in).'],
            ['B', 'Status', 'A = Actief, D = Vervallen, E = Niet actief. Leeg = oude waarde blijft staan.'],
            ['C', 'Gewicht', 'Nettogewicht, decimalen met een komma. Leeg = oude waarde blijft staan.'],
            ['D', 'Verkoopprijs', 'Decimalen met een komma. Leeg = oude waarde blijft staan.'],
            ['E', 'Code leverancier', 'Crediteurnummer. Leeg = er worden geen leveranciergegevens geïmporteerd. Bestaat de leverancier nog niet bij het artikel, dan wordt hij toegevoegd.'],
            ['F', 'Artikelcode leverancier', 'Leeg = oude waarde blijft staan (alleen met een code leverancier).'],
            ['G', 'Inkoopprijs leverancier', 'Decimalen met een komma. Leeg = oude waarde blijft staan (alleen met een code leverancier).'],
            ['H', 'Bestelgrootte leverancier', 'Leeg = oude waarde blijft staan (alleen met een code leverancier).'],
            ['I', 'Artikelgroep', 'Leeg = oude waarde blijft staan.'],
            ['J', 'Omschrijving', 'Leeg = oude waarde blijft staan.'],
            [],
            ['', 'Importeren', 'Importeer het bestand in Reports > Artikelgegevens Import: elk artikel wordt gecontroleerd met Exact, de huidige waarden worden getoond en de velden zijn daar nog aan te passen.']
        ]);
        help['!cols'] = [{ wch: 6 }, { wch: 24 }, { wch: 120 }];
        var wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'Items');
        XLSX.utils.book_append_sheet(wb, help, 'Uitleg');
        XLSX.writeFile(wb, 'Artikelgegevens_import_template.xlsx');
    }

    // ---- Excel inlezen (nieuwe template: kop in rij 1; oude template: kop in rij 11) ----
    var COLUMN_KEYS = { 'artikelcode': 'code' };
    FIELDS.forEach(function (f) { COLUMN_KEYS[f.col.toLowerCase()] = f.key; });

    function importFile(file) {
        showMessage('');
        showInfo('');
        var reader = new FileReader();
        reader.onload = function (e) {
            try {
                var wb = XLSX.read(new Uint8Array(e.target.result), { type: 'array' });
                var name = wb.SheetNames.indexOf('Items') !== -1 ? 'Items' : wb.SheetNames[0];
                var ws = wb.Sheets[name];
                var val = function (a) { return ws[a] ? ws[a].v : null; };
                var range = XLSX.utils.decode_range(ws['!ref'] || 'A1:A1');
                var last = Math.min(range.e.r + 1, 5000);
                var lastCol = Math.min(range.e.c, 30);

                var headRow = 0, cols = {};
                for (var hr = 1; hr <= Math.min(last, 40) && !headRow; hr++) {
                    for (var c = 0; c <= lastCol; c++) {
                        if (String(val(XLSX.utils.encode_col(c) + hr) || '').trim().toLowerCase() === 'artikelcode') { headRow = hr; break; }
                    }
                }
                if (!headRow) { throw new Error('Geen kolom "Artikelcode" gevonden. Gebruik de template.'); }
                for (var cc = 0; cc <= lastCol; cc++) {
                    var colName = String(val(XLSX.utils.encode_col(cc) + headRow) || '').trim().replace(/\s+/g, ' ').toLowerCase();
                    if (COLUMN_KEYS[colName] && !cols[COLUMN_KEYS[colName]]) { cols[COLUMN_KEYS[colName]] = XLSX.utils.encode_col(cc); }
                }

                lines = [];
                for (var row = headRow + 1; row <= last; row++) {
                    var line = emptyLine(), any = false;
                    Object.keys(cols).forEach(function (k) {
                        line[k] = cellText(val(cols[k] + row));
                        if (line[k] !== '') { any = true; }
                    });
                    if (!any) { continue; }
                    // Uitlegregels van de oude template ("(30 Alfanumeric)", "*") overslaan.
                    if (line.code === '*' || /^\(.*\)$/.test(line.code)) { continue; }
                    line.status = statusOf(line.status);
                    lines.push(line);
                }
                importedName = file.name;
                buildTable();
                if (lines.length) {
                    showInfo('Geïmporteerd: ' + file.name + ' (' + lines.length + ' regels). De gegevens worden gecontroleerd met Exact…');
                } else {
                    showMessage('Geen regels gevonden in ' + file.name + '.');
                }
                checkNow();
            } catch (err) {
                showMessage('Het bestand kon niet worden gelezen: ' + err.message);
            }
        };
        reader.onerror = function () { showMessage('Het bestand kon niet worden gelezen.'); };
        reader.readAsArrayBuffer(file);
    }

    // ---- Controle met Exact: alleen wat nog niet gecontroleerd is ----
    function checkNow() {
        clearTimeout(timer);
        var body = new URLSearchParams();
        body.set('csrf', csrf);
        var sent = { items: [], suppliers: [], groups: [] };
        function add(list, store, field, value) {
            var k = key(value);
            if (k === '' || store[k] || inFlight[list][k] || sent[list].indexOf(k) !== -1) { return; }
            sent[list].push(k);
            body.append(field, String(value).trim());
        }
        lines.forEach(function (l) {
            if (l.code.trim().length <= 30) { add('items', itemInfo, 'items[]', l.code); }
            if (l.supplier.trim().length <= 20) { add('suppliers', supplierInfo, 'suppliers[]', l.supplier); }
            if (l.group.trim().length <= 20) { add('groups', groupInfo, 'groups[]', l.group); }
        });
        if (!sent.items.length && !sent.suppliers.length && !sent.groups.length) { refresh(); return; }
        Object.keys(sent).forEach(function (list) { sent[list].forEach(function (k) { inFlight[list][k] = true; }); });
        refresh();
        fetch(endpoint, { method: 'POST', body: body, credentials: 'same-origin' })
            .then(function (r) { return r.json().catch(function () { return { ok: false, error: 'Onverwacht antwoord van de server.' }; }); })
            .then(function (json) {
                if (!json.ok) { throw new Error(json.error || 'Controle mislukt.'); }
                sent.items.forEach(function (k) { itemInfo[k] = (json.items && json.items[k]) || { found: false }; });
                sent.suppliers.forEach(function (k) { supplierInfo[k] = (json.suppliers && json.suppliers[k]) || { found: false }; });
                sent.groups.forEach(function (k) { groupInfo[k] = (json.groups && json.groups[k]) || { found: false }; });
                (json.warnings || []).forEach(function (w) { if (warnings.indexOf(w) === -1) { warnings.push(w); } });
                if (importedName && sent.items.length) { showInfo('Geïmporteerd: ' + importedName + ' (' + lines.length + ' regels), gecontroleerd met Exact.'); }
            })
            .catch(function (err) { showMessage('Controle met Exact mislukt: ' + err.message); })
            .then(function () {
                Object.keys(sent).forEach(function (list) { sent[list].forEach(function (k) { delete inFlight[list][k]; }); });
                refresh();
            });
    }
    function scheduleCheck() {
        clearTimeout(timer);
        timer = setTimeout(checkNow, 400);
    }

    // ---- Huidige waarden uit Exact ----
    // Leveranciergegevens: van de ingevulde leverancier, of (zonder code leverancier) van de hoofdleverancier.
    function accountOf(l, info) {
        var accounts = (info && info.accounts) || [];
        var s = key(l.supplier);
        if (s === '') {
            for (var i = 0; i < accounts.length; i++) { if (accounts[i].main) { return accounts[i]; } }
            return accounts.length === 1 ? accounts[0] : null;
        }
        var sup = supplierInfo[s];
        for (var j = 0; j < accounts.length; j++) {
            var a = key(accounts[j].supplier);
            if (a === s || (sup && sup.found && (a === key(sup.nr) || a === key(sup.code)))) { return accounts[j]; }
        }
        return null;
    }
    function exactValue(l, f, info, account) {
        if (!info || !info.found) { return ''; }
        switch (f.key) {
            case 'desc': return info.description || '';
            case 'status': return info.status || '';
            case 'weight': return info.weight !== '' ? nl(info.weight) : '';
            case 'price': return info.price !== '' ? nl(info.price) : '';
            case 'group': return info.group || '';
            case 'supplier': return account ? account.supplier : '';
            case 'supItem': return account ? account.itemCode : '';
            case 'supPrice': return account && account.price !== '' ? nl(account.price) : '';
            case 'supSize': return account && account.size !== '' ? nl(account.size) : '';
        }
        return '';
    }
    function differs(f, value, exact) {
        if (value.trim() === '') { return false; }
        if (f.type === 'num') {
            var a = parseNum(value), b = parseNum(exact);
            return b === null || isNaN(b) || a !== b;
        }
        if (f.type === 'status') { return value.toUpperCase() !== String(exact).trim().toUpperCase(); }
        return value.trim() !== String(exact).trim();
    }

    // ---- Beoordelen ----
    function lineState(l, counts) {
        var k = key(l.code);
        var st = { cells: {}, label: '', cls: '' };
        if (k === '') {
            st.label = 'Nee'; st.cls = 'is-incomplete'; st.problem = 'nocode';
            return st;
        }
        if (l.code.trim().length > 30) { st.label = 'Artikelcode te lang (max. 30)'; st.cls = 'is-missing'; st.problem = 'long'; return st; }
        var info = itemInfo[k];
        if (!info) { st.label = 'Wordt gecontroleerd…'; st.pending = true; }
        else if (info.found) { st.label = 'Ja'; st.exists = true; }
        else { st.label = 'Nieuw artikel'; st.cls = 'is-new'; st.isNew = true; }
        if (counts[k] > 1) { st.problem = 'double'; st.cls = 'is-incomplete'; st.label += ' (dubbel)'; }

        var account = accountOf(l, info);
        FIELDS.forEach(function (f) {
            var v = String(l[f.key] || '');
            var exact = exactValue(l, f, info, account);
            var cell = { exact: exact, changed: !!(info && info.found) && differs(f, v, exact), bad: '' };
            if (f.type === 'num' && v.trim() !== '' && (isNaN(parseNum(v)) || parseNum(v) < 0)) { cell.bad = 'Geen geldig getal'; }
            if (f.type === 'status' && v !== '' && !STATUS[v.toUpperCase()]) { cell.bad = 'Onbekende status (A, D of E)'; }
            if (f.key === 'group' && v.trim() !== '') {
                var g = groupInfo[key(v)];
                if (!g) { st.pending = true; } else if (!g.found) { cell.bad = 'Artikelgroep bestaat niet in Exact'; } else { cell.note = g.description; }
            }
            if (f.key === 'supplier' && v.trim() !== '') {
                var s = supplierInfo[key(v)];
                if (!s) { st.pending = true; } else if (!s.found) { cell.bad = 'Leverancier bestaat niet in Exact'; } else { cell.note = s.name; }
            }
            if (SUPPLIER_DETAIL.indexOf(f.key) !== -1 && v.trim() !== '' && l.supplier.trim() === '' && !cell.bad) {
                cell.bad = 'Alleen met een code leverancier';
            }
            if (f.key === 'desc' && st.isNew && v.trim() === '') { cell.bad = 'Nieuw artikel: omschrijving invullen'; }
            st.cells[f.key] = cell;
        });
        st.bad = FIELDS.filter(function (f) { return st.cells[f.key].bad; }).length;
        st.changed = FIELDS.filter(function (f) { return st.cells[f.key].changed; }).length;
        if (st.bad && !st.problem) { st.cls = 'is-incomplete'; }
        return st;
    }

    // ---- Tabel: rijen met invoervelden alleen opnieuw opbouwen bij importeren/toevoegen/verwijderen ----
    function input(i, f, value) {
        var attrs = ' data-i="' + i + '" data-k="' + f.key + '" autocomplete="off" spellcheck="false"';
        if (f.type === 'status') {
            var opts = '<option value=""></option>';
            Object.keys(STATUS).forEach(function (s) { opts += '<option value="' + s + '"' + (value.toUpperCase() === s ? ' selected' : '') + '>' + esc(STATUS[s]) + '</option>'; });
            if (value !== '' && !STATUS[value.toUpperCase()]) { opts += '<option value="' + esc(value) + '" selected>' + esc(value) + '</option>'; }
            return '<select class="ka-input ai-status"' + attrs + ' aria-label="Status">' + opts + '</select>';
        }
        var cls = f.type === 'num' ? 'ai-num' : (f.cls || '');
        return '<input type="text" class="ka-input ' + cls + '"' + attrs + ' value="' + esc(value) + '" maxlength="' + (f.max || 15) + '"' +
            (f.type === 'num' ? ' inputmode="decimal"' : '') + ' aria-label="' + esc(f.col) + '">';
    }
    function buildTable() {
        var html = '';
        lines.forEach(function (l, i) {
            html += '<tr data-i="' + i + '">' +
                '<td><input type="text" class="ka-input ai-code" data-i="' + i + '" data-k="code" value="' + esc(l.code) + '" maxlength="40" autocomplete="off" spellcheck="false" aria-label="Artikelcode"></td>' +
                '<td class="ai-state"></td>';
            FIELDS.forEach(function (f) { html += '<td>' + input(i, f, String(l[f.key] || '')) + '</td>'; });
            html += '<td><button type="button" class="ai-del" data-i="' + i + '" title="Regel verwijderen" aria-label="Regel verwijderen">&times;</button></td></tr>';
        });
        tbody.innerHTML = html;
        resultEl.hidden = lines.length === 0;
        refresh();
    }

    function refresh() {
        var counts = {};
        lines.forEach(function (l) { var k = key(l.code); if (k) { counts[k] = (counts[k] || 0) + 1; } });
        var states = lines.map(function (l) { return lineState(l, counts); });

        Array.prototype.forEach.call(tbody.rows, function (tr) {
            var i = +tr.getAttribute('data-i');
            var st = states[i], l = lines[i];
            if (!st) { return; }
            tr.className = st.cls;
            tr.querySelector('.ai-state').textContent = st.label;
            FIELDS.forEach(function (f) {
                var el = tr.querySelector('[data-k="' + f.key + '"]');
                var cell = st.cells[f.key];
                if (!el) { return; }
                if (!cell) { el.classList.remove('is-changed', 'is-bad'); el.title = ''; if (el.tagName === 'INPUT') { el.placeholder = ''; } return; }
                el.classList.toggle('is-changed', cell.changed && !cell.bad);
                el.classList.toggle('is-bad', !!cell.bad);
                var tips = [];
                if (cell.bad) { tips.push(cell.bad); }
                if (cell.note) { tips.push(cell.note); }
                if (cell.exact !== '' && (cell.changed || String(l[f.key]).trim() === '')) { tips.push('In Exact: ' + (f.type === 'status' && STATUS[cell.exact] ? STATUS[cell.exact] : cell.exact)); }
                el.title = tips.join(' - ');
                if (el.tagName === 'INPUT') { el.placeholder = cell.exact; }
                else { el.options[0].textContent = cell.exact !== '' ? '(' + cell.exact + ')' : ''; }
            });
        });

        var total = states.length;
        var existing = states.filter(function (s) { return s.exists; }).length;
        var created = states.filter(function (s) { return s.isNew; }).length;
        var changed = states.reduce(function (n, s) { return n + (s.changed || 0); }, 0);
        var badRows = states.filter(function (s) { return s.bad || s.problem; }).length;
        var pending = states.some(function (s) { return s.pending; });
        summaryEl.innerHTML =
            '<span class="msp-pill"><strong>' + total + '</strong> regels</span>' +
            (existing ? '<span class="msp-pill"><strong>' + existing + '</strong> bestaand</span>' : '') +
            (created ? '<span class="msp-pill ai-pill-new"><strong>' + created + '</strong> nieuw (wordt aangemaakt)</span>' : '') +
            (changed ? '<span class="msp-pill ai-pill-changed"><strong>' + changed + '</strong> gewijzigde velden</span>' : '') +
            (badRows ? '<span class="msp-pill ai-pill-bad"><strong>' + badRows + '</strong> met fouten</span>' : '');

        var problems = [];
        var n = function (test) { return states.filter(test).length; };
        var c;
        if ((c = n(function (s) { return s.problem === 'nocode'; }))) { problems.push(c + ' regel(s) zonder artikelcode'); }
        if ((c = n(function (s) { return s.problem === 'long'; }))) { problems.push(c + ' artikelcode(s) langer dan 30 tekens'); }
        if ((c = n(function (s) { return s.problem === 'double'; }))) { problems.push(c + ' regels met een dubbele artikelcode'); }
        var byReason = {};
        states.forEach(function (s) { FIELDS.forEach(function (f) { var b = s.cells[f.key] && s.cells[f.key].bad; if (b) { var r = f.col + ': ' + b.charAt(0).toLowerCase() + b.slice(1); byReason[r] = (byReason[r] || 0) + 1; } }); });
        Object.keys(byReason).forEach(function (r) { problems.push(r + ' (' + byReason[r] + 'x)'); });
        if (pending) { problems.push('gegevens worden nog gecontroleerd met Exact'); }
        var ready = lines.length > 0 && problems.length === 0;
        exportBtn.disabled = !ready;
        exportBtn.title = ready || !lines.length ? '' : 'Eerst oplossen: ' + problems.join(', ');
        if (lines.length && !ready) {
            problemsEl.innerHTML = '<strong>Exporteren kan pas als alles klopt.</strong> Nog nodig: ' + problems.map(esc).join('; ') + '.';
            problemsEl.hidden = false;
        } else {
            problemsEl.hidden = true;
        }
        warningsEl.hidden = warnings.length === 0;
        warningsEl.textContent = warnings.join(' ');
    }

    // ---- XML (zelfde opbouw als de macro ConvertToXML in de Excel-template) ----
    // Tekens buiten gewone ASCII als &#n; (zoals de macro), en &, <, >, " altijd ge-escaped.
    function xmlEsc(s) {
        return String(s).replace(/[&<>"]|[^\x20-\x7e]/g, function (ch) { return '&#' + ch.charCodeAt(0) + ';'; });
    }
    function xmlNum(v) { return String(parseNum(v)); }
    function buildXml() {
        var out = [];
        out.push('<?xml version="1.0" encoding="UTF-8"?>');
        out.push('<eExact xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="eExact-Schema.xsd">');
        out.push('<Items>');
        lines.forEach(function (l) {
            var t = function (k) { return String(l[k] || '').trim(); };
            out.push(' <Item code="' + xmlEsc(t('code')) + '">');
            out.push('  <Condition>' + xmlEsc(t('status').toUpperCase()) + '</Condition>');
            if (t('desc') !== '') { out.push('  <Description>' + xmlEsc(t('desc')) + '</Description>'); }
            if (t('group') !== '') { out.push('  <Assortment number="' + xmlEsc(t('group')) + '">'); out.push(' </Assortment>'); }
            if (t('price') !== '') {
                out.push('  <Sales>');
                out.push('   <Price type="S">');
                out.push('    <Value>' + xmlNum(t('price')) + '</Value>');
                out.push('   </Price>');
                out.push('  </Sales>');
            }
            if (t('weight') !== '') {
                out.push('  <Dimension>');
                out.push('    <WeightNet>' + xmlNum(t('weight')) + '</WeightNet>');
                out.push('  </Dimension>');
            }
            if (t('supplier') !== '') {
                out.push('  <ItemAccounts>');
                out.push('   <ItemAccount default="">');
                out.push('    <Account code="" type="S"><Creditor code="' + xmlEsc(t('supplier')) + '"/></Account>');
                if (t('supItem') !== '') { out.push('    <ItemCode>' + xmlEsc(t('supItem')) + '</ItemCode>'); }
                out.push('    <Purchase>');
                if (t('supPrice') !== '') {
                    out.push('     <Price type="P">');
                    out.push('      <Value>' + xmlNum(t('supPrice')) + '</Value>');
                    out.push('     </Price>');
                }
                if (t('supSize') !== '') { out.push('     <OrderSize>' + xmlNum(t('supSize')) + '</OrderSize>'); }
                out.push('    </Purchase>');
                out.push('   </ItemAccount>');
                out.push('  </ItemAccounts>');
            }
            out.push(' </Item>');
        });
        out.push('</Items>');
        out.push('</eExact>');
        return out.join('\r\n') + '\r\n';
    }
    function exportXml() {
        if (exportBtn.disabled) { return; }
        var blob = new Blob([buildXml()], { type: 'application/xml;charset=utf-8' });
        var a = document.createElement('a');
        var d = new Date(), p = function (x) { return ('0' + x).slice(-2); };
        a.href = URL.createObjectURL(blob);
        a.download = 'Items-' + d.getFullYear() + p(d.getMonth() + 1) + p(d.getDate()) + '-' + p(d.getHours()) + p(d.getMinutes()) + '.xml';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        setTimeout(function () { URL.revokeObjectURL(a.href); }, 1000);
        showInfo('XML-bestand gemaakt (' + lines.length + ' artikelen). Importeer het in Exact via Systeem\\Importeren XML.');
    }

    // ---- Wijzigen in de tabel ----
    function onEdit(e) {
        var el = e.target;
        var k = el.getAttribute && el.getAttribute('data-k');
        if (!k) { return; }
        lines[+el.getAttribute('data-i')][k] = el.value;
        refresh();
        if (k === 'code' || k === 'supplier' || k === 'group') { scheduleCheck(); }
    }
    tbody.addEventListener('input', onEdit);
    tbody.addEventListener('change', function (e) { if (e.target.tagName === 'SELECT') { onEdit(e); } });
    tbody.addEventListener('click', function (e) {
        var btn = e.target.closest ? e.target.closest('button.ai-del') : null;
        if (!btn) { return; }
        lines.splice(+btn.getAttribute('data-i'), 1);
        buildTable();
    });
    document.getElementById('aiAdd').addEventListener('click', function () {
        lines.push(emptyLine());
        buildTable();
        var inputs = tbody.querySelectorAll('input.ai-code');
        if (inputs.length) { inputs[inputs.length - 1].focus(); }
        resultEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    });

    // ---- Koppelingen ----
    document.getElementById('aiTemplate').addEventListener('click', function () {
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
    refresh();
})();
