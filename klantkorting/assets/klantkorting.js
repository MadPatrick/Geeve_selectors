(function () {
    var form = document.getElementById('kkForm');
    if (!form) { return; }
    var tbody = form.querySelector('#kkTable tbody');
    var message = document.getElementById('kkMessage');
    var info = document.getElementById('kkInfo');
    var lookupUrl = form.getAttribute('data-lookup');
    var priceList = form.getAttribute('data-pricelist');
    var STAFFEL_RE = /^\d+([.,]\d+)?\s*=\s*\d+([.,]\d+)?$/;

    function showInfo(text, isError) {
        info.hidden = true;
        message.hidden = true;
        var box = isError ? message : info;
        box.textContent = text;
        box.hidden = false;
    }

    function staffelValid(text) {
        var parts = text.split(';').map(function (p) { return p.trim(); }).filter(Boolean);
        return parts.length >= 1 && parts.length <= 10 && parts.every(function (p) { return STAFFEL_RE.test(p); });
    }

    function rowValues(row) {
        return {
            group: row.querySelector('.kk-group').value.trim(),
            debcode: row.querySelector('.kk-debcode').value.trim(),
            from: row.querySelector('.kk-from').value,
            to: row.querySelector('.kk-to').value,
            staffel: row.querySelector('.kk-staffel').value.trim()
        };
    }

    function isChanged(row) {
        if (!row.hasAttribute('data-orig')) { return true; }
        var v = rowValues(row);
        return (v.staffel + '|' + v.from + '|' + v.to) !== row.getAttribute('data-orig');
    }

    function setState(row, state, description) {
        row.classList.remove('is-found', 'is-missing', 'is-checking');
        if (state) { row.classList.add(state); }
        row.querySelector('.ka-desc').textContent = description || '';
    }

    function lookup(row, done) {
        done = done || function () {};
        var input = row.querySelector('.kk-group');
        var code = input.value.trim();
        if (code === '') { setState(row, '', ''); done(); return; }
        setState(row, 'is-checking', 'Controleren in Exact…');
        fetch(lookupUrl + '?code=' + encodeURIComponent(code))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (input.value.trim() !== code) { return; }
                if (data.found) {
                    input.value = data.code;
                    var cell = row.querySelector('.ka-itemcell');
                    var label = cell.querySelector('.ka-itemtext');
                    if (!label) {
                        label = document.createElement('strong');
                        label.className = 'ka-itemtext';
                        cell.insertBefore(label, cell.firstChild);
                    }
                    label.textContent = data.code;
                    input.type = 'hidden';
                    setState(row, 'is-found', data.description);
                } else {
                    setState(row, 'is-missing', data.error ? 'Controle mislukt: ' + data.error : 'Artikelgroep niet gevonden in Exact');
                }
            })
            .catch(function () { setState(row, 'is-missing', 'Controle mislukt'); })
            .then(function () { done(); });
    }

    var timers = new WeakMap();
    tbody.addEventListener('input', function (e) {
        if (e.target.classList.contains('kk-group') && e.target.type === 'text') {
            var row = e.target.closest('tr');
            clearTimeout(timers.get(row));
            timers.set(row, setTimeout(function () { lookup(row); }, 400));
        }
    });
    tbody.addEventListener('click', function (e) {
        if (e.target.classList.contains('ka-remove')) { e.target.closest('tr').remove(); }
    });

    function addRow(v, quiet) {
        v = v || {};
        var tr = document.createElement('tr');
        tr.innerHTML =
            '<td class="ka-itemcell"><input type="text" name="group[]" class="ka-input kk-group" placeholder="Artikelgroep" autocomplete="off">' +
            '<input type="hidden" name="id[]" value=""><input type="hidden" name="debcode[]" class="kk-debcode" value=""></td>' +
            '<td class="ka-desc"></td><td class="kk-for"></td>' +
            '<td><input type="date" name="from[]" class="ka-input kk-from"></td>' +
            '<td><input type="date" name="to[]" class="ka-input kk-to"></td>' +
            '<td><input type="text" name="staffel[]" class="ka-input kk-staffel" autocomplete="off"></td>' +
            '<td><button type="button" class="ka-remove" title="Regel verwijderen" aria-label="Regel verwijderen">&times;</button></td>';
        tr.querySelector('.kk-group').value = v.group || '';
        tr.querySelector('.kk-debcode').value = v.debcode || '';
        tr.querySelector('.kk-for').textContent = v.debcode ? v.debcode : 'Alle klanten';
        tr.querySelector('.kk-from').value = v.from || '';
        tr.querySelector('.kk-to').value = v.to || '';
        tr.querySelector('.kk-staffel').value = v.staffel || '';
        tbody.insertBefore(tr, tbody.firstChild);
        var empty = document.getElementById('kkEmpty');
        if (empty) { empty.remove(); }
        if (!quiet) { tr.querySelector('.kk-group').focus(); }
        return tr;
    }
    document.getElementById('kkAdd').addEventListener('click', function () { addRow(); });

    form.addEventListener('submit', function (e) {
        var problems = [];
        var changed = 0;
        Array.prototype.forEach.call(tbody.rows, function (row) {
            if (!isChanged(row)) { return; }
            var v = rowValues(row);
            if (v.group === '' && v.staffel === '') { return; }
            changed++;
            if (v.group === '') { problems.push('Regel zonder artikelgroep'); }
            else if (!staffelValid(v.staffel)) { problems.push('Artikelgroep ' + v.group + ': staffel ongeldig (gebruik aantal=korting; ...)'); }
            else if (row.classList.contains('is-missing') || row.classList.contains('is-checking')) { problems.push('Artikelgroep ' + v.group + ' is niet gevonden/gecontroleerd in Exact'); }
            else if (v.from && v.to && v.to < v.from) { problems.push('Artikelgroep ' + v.group + ': geldig tot ligt voor geldig van'); }
        });
        if (problems.length || changed === 0) {
            e.preventDefault();
            showInfo(problems.length
                ? 'Export niet mogelijk: ' + problems.slice(0, 5).join('; ') + (problems.length > 5 ? ' (+' + (problems.length - 5) + ' meer)' : '') + '.'
                : 'Er zijn geen gewijzigde of nieuwe regels om te exporteren.', true);
            return;
        }
        message.hidden = true;
        var disabled = [];
        Array.prototype.forEach.call(tbody.rows, function (row) {
            var v = rowValues(row);
            if (!isChanged(row) || (v.group === '' && v.staffel === '')) {
                Array.prototype.forEach.call(row.querySelectorAll('input'), function (i) { i.disabled = true; disabled.push(i); });
            }
        });
        setTimeout(function () { disabled.forEach(function (i) { i.disabled = false; }); }, 0);
    });

    // ---- Excel (SheetJS, in de browser) ----
    function dmy(iso) { return iso ? iso.split('-').reverse().join('-') : ''; }
    function toIso(text) {
        text = (text === undefined || text === null) ? '' : String(text).trim();
        var m = /^(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{4})$/.exec(text);
        if (m) { return m[3] + '-' + ('0' + m[2]).slice(-2) + '-' + ('0' + m[1]).slice(-2); }
        return /^\d{4}-\d{2}-\d{2}$/.test(text) ? text : '';
    }
    function cellText(v) { return v === undefined || v === null ? '' : String(v).trim(); }

    document.getElementById('kkTemplate').addEventListener('click', function () {
        if (typeof XLSX === 'undefined') { showInfo('Excel-bibliotheek niet geladen.', true); return; }
        var rows = [['Artikelgroep', 'Omschrijving', 'Debiteurcode', 'Geldig van', 'Geldig tot', 'Staffel']];
        Array.prototype.forEach.call(tbody.rows, function (row) {
            var v = rowValues(row);
            if (v.group === '') { return; }
            rows.push([v.group, row.querySelector('.ka-desc').textContent, v.debcode, dmy(v.from), dmy(v.to), v.staffel]);
        });
        var ws = XLSX.utils.aoa_to_sheet(rows);
        for (var r = rows.length + 1; r <= rows.length + 300; r++) {
            ['A', 'B', 'C', 'D', 'E', 'F'].forEach(function (c) { ws[c + r] = { t: 's', v: '', z: '@' }; });
        }
        ws['!ref'] = 'A1:F' + (rows.length + 300);
        ws['!cols'] = [{ wch: 14 }, { wch: 34 }, { wch: 14 }, { wch: 12 }, { wch: 12 }, { wch: 40 }];
        var help = XLSX.utils.aoa_to_sheet([
            ['Kortingstructuur prijslijst ' + priceList + ' - sjabloon'],
            ['Pas op blad "Kortingen" de kolommen Geldig van / Geldig tot (dd-mm-jjjj) en Staffel aan, of voeg onderaan regels toe.'],
            ['Staffel: aantal=korting, gescheiden door ;  bijvoorbeeld  1=25; 10=30'],
            ['Debiteurcode leeg = geldt voor alle klanten op de prijslijst; ingevuld = klantspecifieke afspraak.'],
            ['Regels worden herkend op artikelgroep + debiteurcode. Omschrijving wordt niet ingelezen.']
        ]);
        help['!cols'] = [{ wch: 120 }];
        var wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'Kortingen');
        XLSX.utils.book_append_sheet(wb, help, 'Uitleg');
        XLSX.writeFile(wb, 'Kortingstructuur_' + priceList.replace(/[^A-Za-z0-9_-]/g, '_') + '.xlsx');
    });

    function importRows(data) {
        var start = 0, c = { group: 0, debcode: 2, from: 3, to: 4, staffel: 5 };
        if (data.length) {
            var head = data[0].map(function (v) { return cellText(v).toLowerCase(); });
            var find = function (names) { return head.findIndex(function (h) { return names.some(function (n) { return h.indexOf(n) === 0; }); }); };
            var g = find(['artikelgroep']), s = find(['staffel']);
            if (g !== -1 && s !== -1) {
                start = 1;
                c = { group: g, staffel: s, debcode: find(['debiteurcode', 'klant']), from: find(['geldig van']), to: find(['geldig tot']) };
            }
        }
        var existing = {};
        Array.prototype.forEach.call(tbody.rows, function (row) {
            var v = rowValues(row);
            if (v.group) { existing[(v.group + '|' + v.debcode).toLowerCase()] = row; }
        });
        var added = 0, updated = 0, skipped = 0, queue = [];
        for (var i = start; i < data.length; i++) {
            var rec = data[i];
            var get = function (idx) { return idx === -1 ? '' : cellText(rec[idx]); };
            var group = get(c.group), staffel = get(c.staffel);
            if (group === '' && staffel === '') { continue; }
            if (group === '' || !staffelValid(staffel)) { skipped++; continue; }
            var v = { group: group, debcode: get(c.debcode), from: toIso(get(c.from)), to: toIso(get(c.to)), staffel: staffel };
            var key = (v.group + '|' + v.debcode).toLowerCase();
            if (existing[key]) {
                var row = existing[key];
                row.querySelector('.kk-staffel').value = v.staffel;
                row.querySelector('.kk-from').value = v.from;
                row.querySelector('.kk-to').value = v.to;
                if (isChanged(row)) { updated++; }
            } else {
                var tr = addRow(v, true);
                existing[key] = tr;
                queue.push(tr);
                added++;
            }
        }
        var running = 0;
        (function next() {
            while (running < 4 && queue.length) {
                running++;
                lookup(queue.shift(), function () { running--; next(); });
            }
        })();
        showInfo('Excel ingelezen: ' + added + ' nieuw, ' + updated + ' bijgewerkt' +
            (skipped ? ', ' + skipped + ' overgeslagen (artikelgroep of staffel ongeldig)' : '') + '.', false);
    }

    var drop = document.getElementById('kkDrop');
    var fileInput = document.getElementById('kkFile');
    function readFile(file) {
        if (!file) { return; }
        if (typeof XLSX === 'undefined') { showInfo('Excel-bibliotheek niet geladen.', true); return; }
        var reader = new FileReader();
        reader.onload = function (ev) {
            try {
                var wb = XLSX.read(ev.target.result, { type: 'array' });
                var name = wb.SheetNames.indexOf('Kortingen') !== -1 ? 'Kortingen' : wb.SheetNames[0];
                importRows(XLSX.utils.sheet_to_json(wb.Sheets[name], { header: 1, raw: false, defval: '' }));
            } catch (err) {
                showInfo('Kon het bestand niet lezen: ' + err.message, true);
            }
        };
        reader.readAsArrayBuffer(file);
    }
    drop.addEventListener('click', function () { fileInput.click(); });
    drop.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); fileInput.click(); } });
    fileInput.addEventListener('click', function (e) { e.stopPropagation(); });
    fileInput.addEventListener('change', function () { readFile(fileInput.files[0]); fileInput.value = ''; });
    ['dragenter', 'dragover'].forEach(function (n) {
        drop.addEventListener(n, function (e) { e.preventDefault(); drop.classList.add('is-over'); });
    });
    drop.addEventListener('dragleave', function () { drop.classList.remove('is-over'); });
    drop.addEventListener('drop', function (e) { e.preventDefault(); drop.classList.remove('is-over'); readFile(e.dataTransfer.files[0]); });
})();
