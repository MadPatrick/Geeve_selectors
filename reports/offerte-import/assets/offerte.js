(function () {
    var app = document.getElementById('oiApp');
    var endpoint = app.getAttribute('data-validate');
    // Dezelfde pagina voor offertes (debiteur) en inkooporders (leverancier): alleen teksten en de XML verschillen.
    var purchase = app.getAttribute('data-kind') === 'inkoop';
    var T = purchase
        ? { page: 'Inkooporder Import', seller: 'inkoper', Seller: 'Inkoper', party: 'leverancier', Party: 'Leverancier', partyNr: 'leveranciernummer', soort: 'crediteur', orderType: 'B', partyTag: 'Creditor', filePrefix: 'Inkooporder', template: 'Inkooporder_import_template.xlsx' }
        : { page: 'Offerte Import', seller: 'verkoper', Seller: 'Verkoper', party: 'debiteur', Party: 'Debiteur', partyNr: 'debiteurnummer', soort: 'debiteur', orderType: 'Q', partyTag: 'Debtor', filePrefix: 'Offerte', template: 'Offerte_import_template.xlsx' };
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
    var problemsEl = document.getElementById('oiProblems');
    var resultEl = document.getElementById('oiResult');
    var summaryEl = document.getElementById('oiSummary');
    var tbody = document.querySelector('#oiTable tbody');

    var lines = [];          // {nr, text, desc, code, qty, supplier}
    // Offerte: optionele kolom Leveranciersnummer (artikelcode van de leverancier). Is de artikelcode leeg, dan zoekt de controle
    // ons artikelnummer op en vult het in; zonder (eenduidige) match blijft de artikelcode leeg.
    var useSupplier = !purchase;
    var supplierInfo = {};   // LEVERANCIERSNUMMER (hoofdletters) -> {item, matches}
    var itemInfo = {};       // CODE (hoofdletters) -> {found, description}
    var sellerInfo = null;   // {found, name}
    var debtorInfo = null;
    var checked = {};        // CODE -> true zodra de controle met Exact klaar is
    var codeTimers = {};
    var supTimer = null;
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
    // Aantal met enkelvoud of meervoud: pl(1, 'regel', 'regels') = '1 regel'.
    function pl(n, one, many) { return n + ' ' + (n === 1 ? one : many); }
    function showMessage(text) { messageEl.textContent = text; messageEl.hidden = !text; }
    function showInfo(text) { infoEl.textContent = text; infoEl.hidden = !text; }
    function setCheck(el, text, ok) {
        el.textContent = text;
        el.className = 'oi-check' + (text ? (ok ? ' is-ok' : ' is-bad') : '');
    }

    // ---- Template: alleen de regels (Regelnr, Aantal, Artikelcode, bij offertes ook Leveranciersnummer); de kopvelden vul je in op het dashboard ----
    function downloadTemplate() {
        var rows = [useSupplier ? ['Regelnr', 'Aantal', 'Artikelcode', 'Leveranciersnummer'] : ['Regelnr', 'Aantal', 'Artikelcode']];
        for (var i = 1; i <= 100; i++) { rows.push(useSupplier ? [i, '', '', ''] : [i, '', '']); }
        var ws = XLSX.utils.aoa_to_sheet(rows);
        var head = { font: { bold: true, color: { rgb: 'FFFFFFFF' } }, fill: { patternType: 'solid', fgColor: { rgb: 'FF1F4E78' } }, alignment: { horizontal: 'center' } };
        (useSupplier ? ['A1', 'B1', 'C1', 'D1'] : ['A1', 'B1', 'C1']).forEach(function (a) { ws[a].s = head; });
        for (var r = 2; r <= 101; r++) {
            ws['C' + r] = { t: 's', v: '', z: '@' }; // artikelcodes altijd als tekst
            if (useSupplier) { ws['D' + r] = { t: 's', v: '', z: '@' }; } // leveranciersnummers ook (voorloopnullen blijven staan)
        }
        ws['!cols'] = useSupplier ? [{ wch: 10 }, { wch: 10 }, { wch: 28 }, { wch: 28 }] : [{ wch: 10 }, { wch: 10 }, { wch: 28 }];
        ws['!ref'] = useSupplier ? 'A1:D101' : 'A1:C101';
        var help = XLSX.utils.aoa_to_sheet(useSupplier ? [
            [1, 'Geef per regel het aantal (kolom B) en de artikelcode (kolom C) in'],
            [2, 'Weet je alleen het artikelnummer van de leverancier? Laat de artikelcode leeg en vul het leveranciersnummer (kolom D) in; ons artikelnummer wordt dan in Exact opgezocht'],
            [3, 'Staan artikelcode en leveranciersnummer allebei ingevuld, dan geldt de artikelcode'],
            [4, 'Meer dan 100 regels? Nummer de regelnummers gewoon door'],
            [5, 'Verkoper, debiteurnummer, omschrijving en datum vul je in op het dashboard (Offerte Import)'],
            [6, 'Importeer het bestand in Offerte Import; de artikelen worden gecontroleerd met Exact en de artikelomschrijving wordt opgehaald']
        ] : [
            [1, 'Geef per regel het aantal (kolom B) en de artikelcode (kolom C) in'],
            [2, 'Meer dan 100 regels? Nummer de regelnummers gewoon door'],
            [3, 'Verkoper of inkoper, debiteur- of leveranciernummer, omschrijving en datum vul je in op het dashboard (Offerte Import of Inkooporder Import)'],
            [4, 'Importeer het bestand in Offerte Import of Inkooporder Import; de artikelen worden gecontroleerd met Exact en de artikelomschrijving wordt opgehaald']
        ]);
        help['!cols'] = [{ wch: 4 }, { wch: 110 }];
        var wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'Order');
        XLSX.utils.book_append_sheet(wb, help, 'Uitleg');
        XLSX.writeFile(wb, T.template);
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
                if (!purchase && String(val('A8') || '').trim().toLowerCase() === 'debiteurnummer') {
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
                        code: cellText(val(cols['artikelcode'] + row)).toUpperCase(),
                        supplier: ''
                    };
                    var supCol = useSupplier ? (cols['leveranciersnummer'] || cols['leveranciernummer']) : null;
                    if (supCol && line.code === '') { line.supplier = cellText(val(supCol + row)); }
                    if (line.code === '' && line.supplier === '' && (line.qty === null || line.qty === '')) { continue; }
                    lines.push(line);
                }
                itemInfo = {};
                checked = {};
                supplierInfo = {};
                importedName = file.name;
                render();
                showInfo(lines.length ? 'Geïmporteerd: ' + file.name + ' (' + pl(lines.length, 'regel', 'regels') + '). De gegevens worden gecontroleerd met Exact…' : '');
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
    // Per verzoek hooguit BATCH_SIZE artikelcodes/leveranciersnummers: PHP kapt een POST boven max_input_vars (standaard 1000) stilzwijgend af,
    // en een kleiner verzoek houdt de server minder lang bezig. De batches gaan na elkaar; de tabel wordt na elke batch bijgewerkt.
    var BATCH_SIZE = 400;
    // all = ook al gecontroleerde artikelen opnieuw (nodig als de leverancier wijzigt: de minimale bestelhoeveelheid hangt daarvan af).
    function validate(withItems, quiet, all) {
        var jobs = [];   // {type: 'item'|'sup', value}
        if (withItems) {
            var seen = {};
            lines.forEach(function (l) {
                if (cv(l) !== '' && !seen[ck(l)] && (all || !checked[ck(l)])) { seen[ck(l)] = true; jobs.push({ type: 'item', value: cv(l) }); }
            });
            var seenSup = {};
            lines.forEach(function (l) {
                var s = supplierPending(l) ? l.supplier.trim() : '';
                if (s !== '' && !seenSup[s.toUpperCase()]) { seenSup[s.toUpperCase()] = true; jobs.push({ type: 'sup', value: s }); }
            });
        }
        var batches = [];
        for (var j = 0; j < jobs.length; j += BATCH_SIZE) { batches.push(jobs.slice(j, j + BATCH_SIZE)); }
        if (!batches.length) { batches.push([]); } // alleen verkoper en debiteur
        var ticket = ++validating;

        function runBatch(n) {
            var body = new URLSearchParams();
            body.set('csrf', csrf);
            body.set('verkoper', sellerEl.value.trim());
            body.set('debiteur', debtorEl.value.trim());
            body.set('soort', T.soort);
            batches[n].forEach(function (job) { body.append(job.type === 'item' ? 'items[]' : 'suppliers[]', job.value); });
            return fetch(endpoint, { method: 'POST', body: body })
                .then(function (r) { return r.json().catch(function () { return { ok: false, error: 'Onverwacht antwoord van de server.' }; }); })
                .then(function (json) {
                    if (ticket !== validating) { return; }
                    if (!json.ok) { throw new Error(json.error || 'Controle mislukt.'); }
                    sellerInfo = json.verkoper;
                    debtorInfo = json.partij || json.debiteur;
                    if (withItems) {
                        var found = json.items || {};
                        Object.keys(found).forEach(function (k) { itemInfo[k] = found[k]; });
                        // Leveranciersnummers van deze batch: gevonden artikelnummer invullen als artikelcode (alleen waar die nog leeg is).
                        var sup = json.suppliers || {};
                        lines.forEach(function (l) {
                            if (!supplierPending(l)) { return; }
                            var key = l.supplier.trim().toUpperCase();
                            if (!sup[key]) { return; } // hoort bij een andere batch
                            supplierInfo[key] = sup[key];
                            if (sup[key].item) { l.code = sup[key].item.toUpperCase(); l.autoCode = true; }
                        });
                        batches[n].forEach(function (job) {
                            if (job.type === 'item') { checked[job.value.toUpperCase()] = true; if (!found[job.value.toUpperCase()]) { delete itemInfo[job.value.toUpperCase()]; } }
                        });
                        Object.keys(sup).forEach(function (k) { if (sup[k].item) { checked[sup[k].item.toUpperCase()] = true; } });
                        if (n + 1 < batches.length && !quiet) {
                            showInfo('Geïmporteerd: ' + importedName + ' (' + pl(lines.length, 'regel', 'regels') + '). Controle met Exact: ' + Math.min((n + 1) * BATCH_SIZE, jobs.length) + ' van ' + jobs.length + '…');
                        }
                    }
                    render();
                    if (n + 1 < batches.length) { return runBatch(n + 1); }
                    if (withItems) {
                        lines.forEach(function (l) { if (cv(l) !== '') { checked[ck(l)] = true; } });
                        if (lines.length && !quiet) { showInfo('Geïmporteerd: ' + importedName + ' (' + pl(lines.length, 'regel', 'regels') + '), gecontroleerd met Exact.'); }
                        render();
                    }
                });
        }
        runBatch(0).catch(function (err) {
            if (ticket !== validating) { return; }
            showMessage('Controle met Exact mislukt: ' + err.message);
        });
    }
    // Eén artikelcode (na een wijziging in de tabel) opnieuw controleren; verkoper/inkoper en debiteur/leverancier blijven ongemoeid.
    function validateItem(code) {
        var key = code.toUpperCase();
        var body = new URLSearchParams();
        body.set('csrf', csrf);
        body.append('items[]', code);
        body.set('debiteur', debtorEl.value.trim());
        body.set('soort', T.soort);
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
        timer = setTimeout(function () { validate(purchase && lines.length > 0, true, true); }, 400);
    }

    // Regel met alleen een leveranciersnummer waarvan het artikelnummer nog niet is opgezocht.
    function supplierPending(l) {
        return useSupplier && cv(l) === '' && !!l.supplier && l.supplier.trim() !== '' && !supplierInfo[l.supplier.trim().toUpperCase()];
    }

    // ---- Tonen en beoordelen ----
    function lineState(l) {
        var sup = useSupplier && l.supplier ? l.supplier.trim() : '';
        if (cv(l) === '' && sup === '' && (l.qty === null || String(l.qty).trim() === '')) { return { cls: 'is-empty', label: 'Nog leeg: vul aan of verwijder', ok: false, export: false }; }
        if (cv(l) === '' && sup !== '') {
            var si = supplierInfo[sup.toUpperCase()];
            if (!si) { return { cls: '', label: 'Leveranciersnummer wordt opgezocht…', ok: false, export: false, pending: true }; }
            if (si.matches && si.matches.length > 1) { return { cls: 'is-incomplete', label: 'Leveranciersnummer hoort bij meerdere artikelen (' + si.matches.join(', ') + '): vul de artikelcode in', ok: false, export: false }; }
            if (si.item) { return { cls: 'is-incomplete', label: 'Artikelcode ontbreekt', ok: false, export: false }; }
            return { cls: 'is-incomplete', label: 'Geen artikel bij leveranciersnummer: vul de artikelcode in', ok: false, export: false };
        }
        if (cv(l) === '') { return { cls: 'is-incomplete', label: 'Artikelcode ontbreekt', ok: false, export: false }; }
        if (qtyOf(l.qty) === null) { return { cls: 'is-incomplete', label: 'Aantal ontbreekt of is ongeldig', ok: false, export: false }; }
        if (!checked[ck(l)]) { return { cls: '', label: 'Wordt gecontroleerd…', ok: false, export: false, pending: true }; }
        var info = itemInfo[ck(l)];
        if (!info || !info.found) { return { cls: 'is-missing', label: 'Niet in Exact', ok: false, export: false }; }
        var q = qtyOf(l.qty);
        if (purchase && info.minOrder > 0 && q < info.minOrder) {
            return { cls: 'is-minorder', label: 'Ja', ok: true, export: true, info: info, minorder: true };
        }
        return { cls: '', label: 'Ja', ok: true, export: true, info: info };
    }

    function render() {
        // Veldcontroles
        var seller = sellerEl.value.trim();
        if (seller === '') { setCheck(checks.seller, '', true); }
        else if (!sellerInfo) { setCheck(checks.seller, 'Wordt gecontroleerd…', true); checks.seller.className = 'oi-check'; }
        else { setCheck(checks.seller, sellerInfo.found ? '✓ ' + sellerInfo.name : '✗ ' + T.Seller + ' niet gevonden', sellerInfo.found); }
        var debtor = debtorEl.value.trim();
        if (debtor === '') { setCheck(checks.debtor, '', true); }
        else if (!debtorInfo) { setCheck(checks.debtor, 'Wordt gecontroleerd…', true); checks.debtor.className = 'oi-check'; }
        else { setCheck(checks.debtor, debtorInfo.found ? '✓ ' + debtorInfo.name : '✗ ' + T.Party + ' niet gevonden', debtorInfo.found); }
        setCheck(checks.desc, descEl.value.trim() === '' ? (lines.length ? '✗ Omschrijving ontbreekt' : '') : '', descEl.value.trim() !== '');
        var dateOk = /^\d{4}-\d{2}-\d{2}$/.test(dateEl.value);
        setCheck(checks.date, dateOk || !lines.length ? '' : '✗ Datum ontbreekt', dateOk);

        // Tabel
        var states = lines.map(lineState);
        var html = '';
        lines.forEach(function (l, i) { html += rowHtml(l, i, states[i]); });
        // Staat de cursor in een artikelcode, dan blijft die daar na het opnieuw tekenen van de tabel.
        var active = document.activeElement;
        var focusCls = active && active.classList ? (active.classList.contains('oi-code') ? 'oi-code' : (active.classList.contains('oi-qty') ? 'oi-qty' : (active.classList.contains('oi-sup') ? 'oi-sup' : null))) : null;
        var focusIdx = focusCls ? active.getAttribute('data-i') : null;
        var caret = focusCls ? active.selectionStart : null;
        tbody.innerHTML = html;
        if (focusIdx !== null) {
            var again = tbody.querySelector('input.' + focusCls + '[data-i="' + focusIdx + '"]');
            if (again) { again.focus(); if (caret !== null) { again.setSelectionRange(caret, caret); } }
        }
        renderSummary(states, seller, debtor);
    }

    function rowHtml(l, i, st) {
        var info = st.info || itemInfo[ck(l)] || null;
        return '<tr class="' + st.cls + '">' +
            '<td>' + esc(l.nr) + '</td>' +
            '<td><input type="text" class="ka-input oi-qty" data-i="' + i + '" value="' + esc(l.qty === null ? '' : l.qty) + '" maxlength="12" inputmode="decimal" autocomplete="off" aria-label="Aantal regel ' + esc(l.nr || (i + 1)) + '"></td>' +
            (purchase ? '<td class="oi-min">' + (info && info.minOrder > 0 ? esc(info.minOrder) : '') + '</td>' : '') +
            (useSupplier ? '<td><input type="text" class="ka-input oi-sup" data-i="' + i + '" value="' + esc(l.supplier || '') + '" maxlength="40" autocomplete="off" spellcheck="false" aria-label="Leveranciersnummer regel ' + esc(l.nr || (i + 1)) + '"></td>' : '') +
            '<td><input type="text" class="ka-input oi-code" data-i="' + i + '" value="' + esc(l.code) + '" maxlength="30" autocomplete="off" spellcheck="false" aria-label="Artikelcode regel ' + esc(l.nr || (i + 1)) + '"></td>' +
            '<td class="oi-desc">' + esc(info && info.found ? info.description : '') + '</td>' +
            '<td class="oi-status">' + esc(st.label) + '</td>' +
            '<td><button type="button" class="oi-del" data-i="' + i + '" title="Regel verwijderen" aria-label="Regel verwijderen">&times;</button></td></tr>';
    }

    // Na typen in één regel: alleen die regel (zonder de invoervelden aan te raken) en de samenvatting bijwerken, niet de hele tabel.
    function renderRow(i) {
        var tr = tbody.rows[i];
        if (!tr) { render(); return; }
        var states = lines.map(lineState);
        var st = states[i];
        var info = st.info || itemInfo[ck(lines[i])] || null;
        tr.className = st.cls;
        var min = tr.querySelector('.oi-min');
        if (min) { min.textContent = info && info.minOrder > 0 ? String(info.minOrder) : ''; }
        tr.querySelector('.oi-desc').textContent = info && info.found ? info.description : '';
        tr.querySelector('.oi-status').textContent = st.label;
        renderSummary(states, sellerEl.value.trim(), debtorEl.value.trim());
    }

    function renderSummary(states, seller, debtor) {
        var dateOk = /^\d{4}-\d{2}-\d{2}$/.test(dateEl.value);
        var articles = states.length;
        var missing = states.filter(function (s) { return s.cls === 'is-missing'; }).length;
        var incomplete = states.filter(function (s) { return s.cls === 'is-incomplete'; }).length;
        var empty = states.filter(function (s) { return s.cls === 'is-empty'; }).length;
        var minOrder = states.filter(function (s) { return s.minorder; }).length;
        var pending = states.some(function (s) { return s.pending; });
        summaryEl.innerHTML =
            '<span class="msp-pill"><strong>' + lines.length + '</strong> ' + (lines.length === 1 ? 'regel' : 'regels') + '</span>' +
            (missing ? '<span class="msp-pill oi-pill-bad"><strong>' + missing + '</strong> niet in Exact</span>' : '') +
            (minOrder ? '<span class="msp-pill oi-pill-warn"><strong>' + minOrder + '</strong> onder minimale bestelhoeveelheid</span>' : '') +
            (incomplete ? '<span class="msp-pill oi-pill-warn"><strong>' + incomplete + '</strong> onvolledig (aantal of artikelcode)</span>' : '');

        // Export alleen als alles klopt
        var problems = [];
        if (seller === '') { problems.push(T.seller + ' invullen'); } else if (!sellerInfo) { problems.push(T.seller + ' wordt gecontroleerd'); } else if (!sellerInfo.found) { problems.push(T.seller + ' bestaat niet in Exact'); }
        if (debtor === '') { problems.push(T.partyNr + ' invullen'); } else if (!debtorInfo) { problems.push(T.partyNr + ' wordt gecontroleerd'); } else if (!debtorInfo.found) { problems.push(T.partyNr + ' bestaat niet in Exact'); }
        if (descEl.value.trim() === '') { problems.push('omschrijving invullen'); }
        if (!dateOk) { problems.push('datum invullen'); }
        if (!states.some(function (s) { return s.export; })) { problems.push('minstens één artikelregel met artikelcode en aantal'); }
        if (missing) { problems.push(pl(missing, 'artikel', 'artikelen') + ' die niet in Exact bestaan (rood) aanpassen of verwijderen'); }
        if (incomplete) { problems.push(pl(incomplete, 'regel', 'regels') + ' zonder geldig aantal of artikelcode (oranje) aanvullen of verwijderen'); }
        if (empty) { problems.push(pl(empty, 'lege regel', 'lege regels') + ' invullen of verwijderen'); }
        if (pending) { problems.push('artikelen worden nog gecontroleerd'); }
        var ready = lines.length > 0 && problems.length === 0;
        exportBtn.disabled = !ready;
        exportBtn.title = ready || !lines.length ? '' : 'Eerst oplossen: ' + problems.join(', ');
        // Zichtbaar wat er nog ontbreekt zolang er regels zijn en de export nog niet kan.
        if (lines.length && !ready) {
            problemsEl.innerHTML = '<strong>Exporteren kan pas als alles klopt en is ingevuld.</strong> Nog nodig: ' + problems.map(esc).join('; ') + '.';
            problemsEl.hidden = false;
        } else {
            problemsEl.hidden = true;
        }
    }

    // ---- XML (zelfde opbouw als de macro in de Excel-template) ----
    function xmlEsc(s) { return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
    function buildXml() {
        var debtor = xmlEsc(debtorEl.value.trim());
        var date = dateEl.value;
        var party = '<' + T.partyTag + ' code="' + debtor + '" ' + (purchase ? 'number' : ' Number') + '="' + debtor + '"></' + T.partyTag + '>';
        var out = [];
        out.push('<?xml version="1.0" encoding="UTF-8"?>');
        out.push('<eExact xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="eExact-Schema.xsd">');
        out.push('<Orders>');
        out.push(' <Order type="' + T.orderType + '">');
        out.push('   <Description>' + xmlEsc(descEl.value.trim()) + '</Description>');
        var resource = '<Resource number="' + xmlEsc(sellerEl.value.trim()) + '"></Resource>';
        if (purchase) {
            // Inkooporder (opbouw van een Exact-export): inkoper als Resource, leverancier in OrderedAt.
            out.push('   ' + resource);
            out.push('   <OrderedAt>');
            out.push('      ' + party);
            out.push('      <Date>' + date + '</Date>');
            out.push('   </OrderedAt>');
        } else {
            out.push('   <OrderedBy>');
            out.push('      ' + party);
            out.push('      <Date>' + date + '</Date>');
            out.push('   </OrderedBy>');
        }
        if (!purchase) {
            // Offerte (zoals de macro in de Excel-template): ook afleveradres en factuuradres.
            out.push('   <DeliverTo>');
            out.push('      ' + party);
            out.push('      <Date>' + date + '</Date>');
            out.push('   </DeliverTo>');
            out.push('   <InvoiceTo>');
            out.push('      ' + party);
            out.push('   </InvoiceTo>');
        }
        var n = 0;
        lines.forEach(function (l) {
            var st = lineState(l);
            if (!st.export) { return; }
            n++;
            out.push('   <OrderLine lineNo="' + xmlEsc(l.nr !== '' ? l.nr : n) + '">');
            out.push('     <Quantity>' + qtyOf(l.qty) + '</Quantity>');
            out.push('     <Item code="' + xmlEsc(cv(l)) + '"></Item>');
            if (purchase) { out.push('     ' + resource); }
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
        a.download = T.filePrefix + '-' + debtorEl.value.trim().replace(/[^A-Za-z0-9._-]+/g, '_') + '.xml';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        setTimeout(function () { URL.revokeObjectURL(a.href); }, 1000);
        showInfo('XML-bestand gemaakt. Importeer het in Exact via Systeem\\Importeren XML\\Logistieke import.');
    }

    // Artikelcode wijzigen in de tabel: altijd hoofdletters, en na een korte pauze opnieuw controleren met Exact.
    tbody.addEventListener('input', function (e) {
        var el = e.target;
        if (el.classList && el.classList.contains('oi-qty')) {
            lines[+el.getAttribute('data-i')].qty = el.value;
            renderRow(+el.getAttribute('data-i'));
            return;
        }
        if (el.classList && el.classList.contains('oi-sup')) {
            // Leveranciersnummer gewijzigd: is de artikelcode leeg, dan na een korte pauze ons artikelnummer opnieuw opzoeken.
            var line = lines[+el.getAttribute('data-i')];
            line.supplier = el.value;
            // Een artikelcode die uit het oude leveranciersnummer kwam, hoort daar niet meer bij: leegmaken en opnieuw opzoeken.
            if (line.autoCode) { line.code = ''; line.autoCode = false; render(); } else { renderRow(+el.getAttribute('data-i')); }
            clearTimeout(supTimer);
            supTimer = setTimeout(function () { validate(true, true); }, 500);
            return;
        }
        if (!el.classList || !el.classList.contains('oi-code')) { return; }
        var i = +el.getAttribute('data-i');
        var pos = el.selectionStart;
        el.value = el.value.toUpperCase();
        if (pos !== null) { el.setSelectionRange(pos, pos); }
        lines[i].autoCode = false;
        lines[i].code = el.value; // ruwe invoer (alleen hoofdletters), zodat een spatie midden in de code niet verdwijnt
        var code = el.value.trim();
        // Rij direct bijwerken (nog niet gecontroleerd), daarna debounce de controle.
        renderRow(i);
        clearTimeout(codeTimers[i]);
        if (code !== '' && !checked[code.toUpperCase()]) {
            codeTimers[i] = setTimeout(function () { validateItem(code); }, 400);
        }
    });

    // Regel verwijderen.
    tbody.addEventListener('click', function (e) {
        var btn = e.target.closest ? e.target.closest('button.oi-del') : null;
        if (!btn) { return; }
        lines.splice(+btn.getAttribute('data-i'), 1);
        render();
    });
    // Regel toevoegen: nieuw regelnummer (hoogste + 1) en de cursor in de artikelcode.
    document.getElementById('oiAdd').addEventListener('click', function () {
        var max = 0;
        lines.forEach(function (l) { var n = parseInt(l.nr, 10); if (isFinite(n) && n > max) { max = n; } });
        lines.push({ nr: String(max + 1), qty: '', code: '', supplier: '' });
        render();
        var inputs = tbody.querySelectorAll('input.oi-code');
        if (inputs.length) { inputs[inputs.length - 1].focus(); }
        resultEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
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
    // ---- Zoeken op naam (fuzzy) in een nummerveld ----
    function attachSuggest(inputEl, kind, noneText, afterPick) {
        var box = document.createElement('ul');
        box.className = 'oi-suggest';
        box.hidden = true;
        inputEl.parentNode.style.position = 'relative';
        inputEl.parentNode.appendChild(box);
        var results = [], active = -1, timer = null, seq = 0;
        function close() { box.hidden = true; active = -1; }
        function mark() {
            Array.prototype.forEach.call(box.children, function (li, i) { li.classList.toggle('is-active', i === active); });
        }
        function pick(i) {
            if (!results[i]) { return; }
            inputEl.value = results[i].nummer;
            close();
            afterPick();
        }
        function show() {
            box.innerHTML = '';
            results.forEach(function (r, i) {
                var li = document.createElement('li');
                var n = document.createElement('strong'); n.textContent = r.nummer;
                li.appendChild(n); li.appendChild(document.createTextNode(' \u2013 ' + r.naam));
                li.addEventListener('mousedown', function (e) { e.preventDefault(); pick(i); });
                box.appendChild(li);
            });
            active = -1;
            box.hidden = results.length === 0;
        }
        function note(text) {
            results = []; active = -1;
            box.innerHTML = '';
            var li = document.createElement('li'); li.className = 'is-note'; li.textContent = text;
            box.appendChild(li); box.hidden = false;
        }
        function search() {
            var q = inputEl.value.trim();
            if (q.length < 2) { results = []; show(); return; }
            if (/^\d+$/.test(q) && q.length > 6) { results = []; show(); return; }
            var my = ++seq;
            var body = new URLSearchParams();
            body.set('csrf', csrf); body.set('q', q); body.set('soort', kind);
            fetch(endpoint.replace('validate.php', 'search.php'), { method: 'POST', body: body, credentials: 'same-origin' })
                .then(function (r) { return r.json().catch(function () { return { ok: false, error: 'Onverwacht antwoord van de server.' }; }); })
                .then(function (j) {
                    if (my !== seq) { return; }
                    if (!j.ok) { note('Zoeken mislukt: ' + (j.error || 'onbekende fout')); return; }
                    results = j.results || [];
                    if (results.length === 0) { note(noneText); } else { show(); }
                })
                .catch(function (e) { if (my === seq) { note('Zoeken mislukt: ' + e.message); } });
        }
        inputEl.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(search, 250); });
        inputEl.addEventListener('keydown', function (e) {
            if (box.hidden) { return; }
            if (e.key === 'ArrowDown') { e.preventDefault(); active = Math.min(active + 1, results.length - 1); mark(); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); active = Math.max(active - 1, 0); mark(); }
            else if (e.key === 'Enter' && active >= 0) { e.preventDefault(); pick(active); }
            else if (e.key === 'Escape') { close(); }
        });
        inputEl.addEventListener('blur', function () { setTimeout(close, 100); });
    }
    attachSuggest(debtorEl, T.soort, 'Geen ' + T.party + ' gevonden', function () { debtorInfo = null; render(); scheduleValidate(); });
    attachSuggest(sellerEl, 'medewerker', 'Geen ' + T.seller + ' gevonden', function () { sellerInfo = null; render(); scheduleValidate(); });
    [descEl, dateEl].forEach(function (el) { el.addEventListener('input', render); });
    dateEl.value = todayIso();
    descEl.value = 'Import';
    render();
})();
