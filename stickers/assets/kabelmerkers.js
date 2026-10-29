(function () {
    var aantal = document.getElementById('kmAantal');
    var preview = document.getElementById('kmPreview');
    var printSheet = document.getElementById('kmPrintSheet');
    var printBtn = document.getElementById('kmPrint');
    var stickers = preview.querySelectorAll('.km-sticker');
    var MAX_PX = 60;       // bovengrens van de zoekruimte; de sticker zelf begrenst eerder
    var MIN_PX = 5;
    var LINE_HEIGHT = 1.1;  // zelfde als line-height in kabelmerkers.css
    var STANDAARD_PX = 12 * 96 / 72;   // standaard lettergrootte (12 pt); alleen kleiner als de tekst niet in 55 mm past
    var SAFETY = 0.97;      // 3% marge tegen een iets breder lettertype op de printserver

    function esc(s) {
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function el(id) { return document.getElementById(id); }
    function pad(n) { return (n < 10 ? '0' : '') + n; }

    // Datum: kalender om op te klikken, standaard vandaag. In de sticker als dd-mm-jj. Een oude browser zonder datumveld toont een
    // tekstveld; daar typ je jjjj-mm-dd.
    var datum = el('kmDatum');
    var nu = new Date();
    datum.value = nu.getFullYear() + '-' + pad(nu.getMonth() + 1) + '-' + pad(nu.getDate());
    function datumTekst() {
        var m = datum.value.match(/^(\d{4})-(\d{2})-(\d{2})$/);
        return m ? m[3] + '-' + m[2] + '-' + m[1].slice(2) : '';
    }

    // Max. tekens per hele regel (de schuine strepen niet meegeteld; de datum telt als dd-mm-jj = 8 tekens). Print: 55 mm tussen de transportgaten.
    var MAX_REGEL = 20;
    var REGELS = [['kmA1', 'kmA2', 'kmA3'], ['kmDatum', 'kmB2', 'kmB3']];
    function lengte(id) {
        return (id === 'kmDatum' ? datumTekst() : el(id).value).replace(/^\s+|\s+$/g, '').length;
    }
    function limiteer(actief) {
        for (var r = 0; r < REGELS.length; r++) {
            var ids = REGELS[r], rest = MAX_REGEL;
            if (ids.indexOf(actief) === -1) { continue; }
            for (var i = 0; i < ids.length; i++) { if (ids[i] !== actief) { rest -= lengte(ids[i]); } }
            rest = Math.max(0, rest);
            if (actief !== 'kmDatum' && el(actief).value.length > rest) { el(actief).value = el(actief).value.slice(0, rest); }
        }
    }

    function join(ids) {
        var delen = [];
        for (var i = 0; i < ids.length; i++) {
            var v = (ids[i] === 'kmDatum' ? datumTekst() : el(ids[i]).value).replace(/^\s+|\s+$/g, '');
            if (v !== '') { delen.push(v); }
        }
        return delen.join('/');
    }

    // Regel 1 = A1/A2/A3, regel 2 = Datum/B2/B3; lege velden worden overgeslagen.
    function value() {
        var r1 = join(['kmA1', 'kmA2', 'kmA3']);
        var r2 = join(['kmDatum', 'kmB2', 'kmB3']);
        // De datum staat altijd ingevuld; zonder een van de andere velden is er niets te printen.
        if (r1 === '' && join(['kmB2', 'kmB3']) === '') { return ''; }
        return r1 === '' ? r2 : r1 + '\n' + r2;
    }

    function count() {
        var n = parseInt(aantal.value, 10);
        return isNaN(n) || n < 1 ? 1 : Math.min(n, 99);
    }

    // Grootste letter waarbij de tekst binnen de sticker past (hoogte en breedte).
    function fit(sticker, textEl, tekst) {
        var cs = getComputedStyle(sticker);
        var boxH = sticker.clientHeight - parseFloat(cs.paddingTop) - parseFloat(cs.paddingBottom);
        var lines = Math.max(1, tekst.split('\n').length);
        function fits(size) {
            textEl.style.fontSize = size + 'px';
            return lines * size * LINE_HEIGHT <= boxH + 1 && textEl.scrollWidth <= textEl.clientWidth + 1;
        }
        var lo = MIN_PX, hi = MAX_PX;
        if (fits(hi)) { lo = hi; } else {
            while (hi - lo > 0.25) {
                var mid = (lo + hi) / 2;
                if (fits(mid)) { lo = mid; } else { hi = mid; }
            }
        }
        var size = Math.min(STANDAARD_PX, Math.max(MIN_PX, lo * SAFETY));
        textEl.style.fontSize = size + 'px';
        return size;
    }

    function refresh() {
        var typed = value();
        var size = 0;
        // Alleen de bovenste sticker toont de tekst; de andere twee blijven leeg (voorbeeld, later Excel-import).
        var textEl = stickers[0].querySelector('.km-tekst');
        textEl.textContent = typed;
        size = typed === '' ? 0 : fit(stickers[0], textEl, typed);
        printBtn.disabled = typed.trim() === '' && geselecteerd().length === 0;
        meldMax();
        return size;
    }

    // Melding op het scherm zodra het maximum aantal tekens van een regel is gebruikt.
    function meldMax() {
        var vol = [];
        for (var r = 0; r < REGELS.length; r++) {
            var totaal = 0;
            for (var i = 0; i < REGELS[r].length; i++) { totaal += lengte(REGELS[r][i]); }
            if (totaal >= MAX_REGEL) { vol.push(r + 1); }
        }
        var m = el('kmMelding');
        m.hidden = vol.length === 0;
        m.textContent = vol.length === 0 ? '' : 'Maximaal aantal tekens bereikt op regel ' + vol.join(' en ') + ' (' + MAX_REGEL + ' tekens per regel, de schuine strepen en de datum van 8 tekens meegerekend waar die in de regel staat).';
    }

    var velden = ['kmA1', 'kmA2', 'kmA3', 'kmDatum', 'kmB2', 'kmB3'];
    for (var v = 0; v < velden.length; v++) {
        (function (id) {
            var onder = function () { limiteer(id); refresh(); };
            el(id).addEventListener('input', onder);
            el(id).addEventListener('change', onder);
        }(velden[v]));
    }

    document.getElementById('kmClear').addEventListener('click', function () {
        for (var i = 0; i < velden.length; i++) { if (velden[i] !== 'kmDatum') { el(velden[i]).value = ''; } }
        refresh();
        el('kmA1').focus();
    });

    // Grootste lettergrootte voor een tekst, gemeten op de bovenste voorbeeldsticker (die daarna weer de ingetypte tekst krijgt).
    function sizeFor(tekst) {
        var textEl = stickers[0].querySelector('.km-tekst');
        textEl.textContent = tekst;
        return fit(stickers[0], textEl, tekst);
    }

    printBtn.addEventListener('click', function () {
        var rijen = geselecteerd();
        var html = '', i, k;
        if (rijen.length > 0) {
            for (i = 0; i < rijen.length; i++) {
                var sz = sizeFor(rijen[i]);
                var eenR = '<div class="km-sticker"><div class="km-tekst" style="font-size:' + sz + 'px">' + esc(rijen[i]) + '</div></div>';
                for (k = 0; k < count(); k++) { html += eenR; }
            }
            refresh();
        } else {
            var typed = value();
            if (typed.trim() === '') { return; }
            var size = refresh();
            var one = '<div class="km-sticker"><div class="km-tekst" style="font-size:' + size + 'px">' + esc(typed) + '</div></div>';
            for (i = 0; i < count(); i++) { html += one; }
        }
        printSheet.innerHTML = html;
        window.print();
    });

    // ---- Excel inlezen: kolommen Tekst A1, A2, A3, B1, B2, B3; elke rij is een sticker (regel 1 = A1/A2/A3, regel 2 = B1/B2/B3) ----
    var KOLOMMEN = ['A1', 'A2', 'A3', 'B1', 'B2', 'B3'];
    var MAX_RIJEN = 500;
    var importRijen = [];
    var dropEl = el('kmDrop'), fileEl = el('kmFile'), tabelEl = el('kmImportTabel'), importMelding = el('kmImportMelding');

    function celTekst(cel) {
        if (!cel) { return ''; }
        return String(cel.w !== undefined ? cel.w : (cel.v === undefined || cel.v === null ? '' : cel.v)).replace(/^\s+|\s+$/g, '');
    }
    function samen(delen) {
        var uit = [];
        for (var i = 0; i < delen.length; i++) { if (delen[i] !== '') { uit.push(delen[i]); } }
        return uit.join('/');
    }
    function rijTekst(r) {
        var r1 = samen([r.A1, r.A2, r.A3]), r2 = samen([r.B1, r.B2, r.B3]);
        return r1 === '' ? r2 : (r2 === '' ? r1 : r1 + '\n' + r2);
    }
    function regelLengte(delen) {
        var n = 0;
        for (var i = 0; i < delen.length; i++) { n += delen[i].length; }
        return n;
    }

    function geselecteerd() {
        var uit = [];
        var vinkjes = tabelEl.querySelectorAll('tbody input[type=checkbox]');
        for (var i = 0; i < vinkjes.length; i++) {
            if (vinkjes[i].checked) {
                var t = rijTekst(importRijen[parseInt(vinkjes[i].getAttribute('data-rij'), 10)]);
                if (t !== '') { uit.push(t); }
            }
        }
        return uit;
    }

    function toonImport() {
        var html = '<div class="ka-table-wrap"><table class="ka-table"><thead><tr><th class="km-vink"><input type="checkbox" id="kmAlle" aria-label="Alles selecteren" checked></th>';
        var k, i;
        for (k = 0; k < KOLOMMEN.length; k++) { html += '<th>Tekst ' + KOLOMMEN[k] + '</th>'; }
        html += '</tr></thead><tbody>';
        var teLang = 0;
        for (i = 0; i < importRijen.length; i++) {
            var r = importRijen[i];
            var lang = regelLengte([r.A1, r.A2, r.A3]) > MAX_REGEL || regelLengte([r.B1, r.B2, r.B3]) > MAX_REGEL;
            if (lang) { teLang++; }
            html += '<tr' + (lang ? ' class="km-te-lang"' : '') + '><td class="km-vink"><input type="checkbox" data-rij="' + i + '" checked aria-label="Rij ' + (i + 1) + '"></td>';
            for (k = 0; k < KOLOMMEN.length; k++) { html += '<td>' + esc(r[KOLOMMEN[k]]) + '</td>'; }
            html += '</tr>';
        }
        tabelEl.innerHTML = html + '</tbody></table></div>';
        tabelEl.hidden = importRijen.length === 0;
        importMelding.hidden = false;
        importMelding.className = 'ka-message ' + (teLang > 0 ? 'ka-message--error' : 'ka-message--ok');
        importMelding.textContent = importRijen.length + ' rij(en) ingelezen.' + (teLang > 0 ? ' ' + teLang + ' rij(en) hebben meer dan ' + MAX_REGEL + ' tekens op een regel (rood); het lettertype wordt daar kleiner.' : ' Vink de rijen aan die je wilt printen; het aantal per rij staat bij de Print-knop.');
        refresh();
    }

    function leesBestand(file) {
        importMelding.hidden = true;
        var reader = new FileReader();
        reader.onload = function (e) {
            try {
                var wb = XLSX.read(new Uint8Array(e.target.result), { type: 'array' });
                var ws = wb.Sheets[wb.SheetNames[0]];
                var range = XLSX.utils.decode_range(ws['!ref'] || 'A1:A1');
                var cols = {}, headRow = -1, c, r;
                // De kop zoeken: een van de eerste 10 rijen met "Tekst A1" e.d. (spaties en hoofdletters maken niet uit).
                for (r = range.s.r; r <= Math.min(range.e.r, range.s.r + 9) && headRow < 0; r++) {
                    for (c = range.s.c; c <= range.e.c; c++) {
                        var naam = celTekst(ws[XLSX.utils.encode_cell({ r: r, c: c })]).toLowerCase().replace(/\s+/g, '');
                        for (var k = 0; k < KOLOMMEN.length; k++) {
                            if (naam === 'tekst' + KOLOMMEN[k].toLowerCase()) { cols[KOLOMMEN[k]] = c; headRow = r; }
                        }
                    }
                }
                if (headRow < 0) { throw new Error('geen kolommen "Tekst A1" t/m "Tekst B3" gevonden'); }
                importRijen = [];
                for (r = headRow + 1; r <= range.e.r && importRijen.length < MAX_RIJEN; r++) {
                    var rij = {}, leeg = true;
                    for (var j = 0; j < KOLOMMEN.length; j++) {
                        var col = cols[KOLOMMEN[j]];
                        rij[KOLOMMEN[j]] = col === undefined ? '' : celTekst(ws[XLSX.utils.encode_cell({ r: r, c: col })]);
                        if (rij[KOLOMMEN[j]] !== '') { leeg = false; }
                    }
                    if (!leeg) { importRijen.push(rij); }
                }
                toonImport();
            } catch (err) {
                importRijen = [];
                tabelEl.hidden = true;
                importMelding.hidden = false;
                importMelding.className = 'ka-message ka-message--error';
                importMelding.textContent = 'Het bestand kon niet worden gelezen: ' + (err && err.message ? err.message : err);
                refresh();
            }
        };
        reader.readAsArrayBuffer(file);
    }

    dropEl.addEventListener('click', function () { fileEl.click(); });
    dropEl.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); fileEl.click(); } });
    fileEl.addEventListener('change', function () { if (fileEl.files[0]) { leesBestand(fileEl.files[0]); fileEl.value = ''; } });
    ['dragenter', 'dragover'].forEach(function (ev) { dropEl.addEventListener(ev, function (e) { e.preventDefault(); dropEl.classList.add('is-over'); }); });
    ['dragleave', 'drop'].forEach(function (ev) { dropEl.addEventListener(ev, function (e) { e.preventDefault(); dropEl.classList.remove('is-over'); }); });
    dropEl.addEventListener('drop', function (e) { var f = e.dataTransfer && e.dataTransfer.files[0]; if (f) { leesBestand(f); } });

    tabelEl.addEventListener('change', function (e) {
        var t = e.target;
        if (t.id === 'kmAlle') {
            var alle = tabelEl.querySelectorAll('tbody input[type=checkbox]');
            for (var i = 0; i < alle.length; i++) { alle[i].checked = t.checked; }
        }
        refresh();
    });

    el('kmTemplate').addEventListener('click', function () {
        var ws = XLSX.utils.aoa_to_sheet([['Tekst A1', 'Tekst A2', 'Tekst A3', 'Tekst B1', 'Tekst B2', 'Tekst B3']]);
        var wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'Kabelmerkers');
        XLSX.writeFile(wb, 'Kabelmerkers_template.xlsx');
    });

    refresh();
}());
