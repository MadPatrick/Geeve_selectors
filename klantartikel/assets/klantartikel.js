(function () {
    var form = document.getElementById('kaForm');
    if (!form) { return; }
    var tbody = form.querySelector('#kaTable tbody');
    var message = document.getElementById('kaMessage');
    var count = document.getElementById('kaCount');
    var lookupUrl = form.getAttribute('data-lookup');
    var timers = new WeakMap();

    var filterItem = document.getElementById('kaFilterItem');
    var filterCode = document.getElementById('kaFilterCode');

    var PAGE_SIZE = 50;
    var page = 1;
    var pager = document.getElementById('kaPager');
    var pageInfo = document.getElementById('kaPageInfo');

    // Filter + paginering: alle regels blijven in het formulier (en dus in de export), alleen de zichtbaarheid wisselt.
    function applyFilter(resetPage) {
        if (resetPage === true) { page = 1; }
        var fi = filterItem.value.trim().toLowerCase();
        var fc = filterCode.value.trim().toLowerCase();
        var matched = [];
        Array.prototype.forEach.call(tbody.rows, function (row) {
            var item = row.querySelector('.ka-item').value.trim().toLowerCase();
            var code = row.querySelector('.ka-code').value.trim().toLowerCase();
            // Lege (net toegevoegde) regels blijven zichtbaar zodat ze invulbaar blijven.
            var blank = item === '' && code === '';
            var ok = blank || ((fi === '' || item.indexOf(fi) !== -1) && (fc === '' || code.indexOf(fc) !== -1));
            row.hidden = true;
            if (ok) { matched.push(row); }
        });
        var pages = Math.max(1, Math.ceil(matched.length / PAGE_SIZE));
        page = Math.min(Math.max(page, 1), pages);
        matched.slice((page - 1) * PAGE_SIZE, page * PAGE_SIZE).forEach(function (row) { row.hidden = false; });
        pager.hidden = pages <= 1;
        pageInfo.textContent = 'Pagina ' + page + ' van ' + pages + ' (' + matched.length + ' regels)';
        document.getElementById('kaPrev').disabled = page <= 1;
        document.getElementById('kaNext').disabled = page >= pages;
    }
    document.getElementById('kaPrev').addEventListener('click', function () { page--; applyFilter(); });
    document.getElementById('kaNext').addEventListener('click', function () { page++; applyFilter(); });

    filterItem.addEventListener('input', function () { applyFilter(true); });
    filterCode.addEventListener('input', function () { applyFilter(true); });

    function updateCount() { count.textContent = '(' + tbody.rows.length + ')'; }

    function setState(row, state, description) {
        row.classList.remove('is-found', 'is-missing', 'is-checking');
        if (state) { row.classList.add(state); }
        row.querySelector('.ka-desc').textContent = description || (state === 'is-missing' ? 'Niet gevonden in Exact' : '');
    }

    function lookup(row, done) {
        done = done || function () {};
        var input = row.querySelector('.ka-item');
        var code = input.value.trim();
        if (code === '') { setState(row, '', ''); done(); return; }
        setState(row, 'is-checking', 'Controleren in Exact…');
        fetch(lookupUrl + '?code=' + encodeURIComponent(code))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (input.value.trim() !== code) { done(); return; } // intussen gewijzigd
                if (data.found) {
                    // Gevonden artikelnummer wordt vastgezet (niet meer wijzigbaar).
                    input.value = data.code;
                    var label = document.createElement('span');
                    label.className = 'ka-itemtext';
                    label.textContent = data.code;
                    input.type = 'hidden';
                    input.parentNode.insertBefore(label, input);
                    setState(row, 'is-found', data.description);
                    if (document.activeElement === input || document.activeElement === document.body) { row.querySelector('.ka-code').focus(); }
                } else {
                    setState(row, 'is-missing', data.error ? 'Controle mislukt: ' + data.error : 'Niet gevonden in Exact');
                }
            })
            .catch(function () { setState(row, 'is-missing', 'Controle mislukt'); })
            .then(function () { done(); });
    }

    function schedule(row) {
        clearTimeout(timers.get(row));
        timers.set(row, setTimeout(function () { lookup(row); }, 400));
    }

    function addRow(item, code, quiet) {
        var tr = document.createElement('tr');
        tr.innerHTML =
            '<td class="ka-itemcell"><input type="text" name="artikel[]" class="ka-input ka-item" placeholder="Artikelnummer" autocomplete="off"></td>' +
            '<td class="ka-desc"></td>' +
            '<td><input type="text" name="klantartikel[]" class="ka-input ka-code" autocomplete="off"></td>' +
            '<td><button type="button" class="ka-remove" title="Regel verwijderen" aria-label="Regel verwijderen">&times;</button></td>';
        tr.querySelector('.ka-item').value = item || '';
        tr.querySelector('.ka-code').value = code || '';
        tbody.insertBefore(tr, tbody.firstChild);
        if (!quiet) {
            updateCount();
            applyFilter(true);
            tr.querySelector('.ka-item').focus();
        }
        return tr;
    }

    document.getElementById('kaAdd').addEventListener('click', function () { addRow(); });

    tbody.addEventListener('input', function (e) {
        if (e.target.classList.contains('ka-item')) { schedule(e.target.closest('tr')); }
    });
    tbody.addEventListener('click', function (e) {
        if (e.target.classList.contains('ka-remove')) {
            e.target.closest('tr').remove();
            updateCount();
            applyFilter();
        }
    });

    // Alleen nieuwe regels (geen data-orig) en regels met een gewijzigd klantartikelnummer gaan mee in de XML.
    function isChanged(row) {
        var code = row.querySelector('.ka-code').value.trim();
        return !row.hasAttribute('data-orig') || code !== row.getAttribute('data-orig');
    }

    form.addEventListener('submit', function (e) {
        var problems = [];
        var changed = 0;
        Array.prototype.forEach.call(tbody.rows, function (row) {
            var item = row.querySelector('.ka-item').value.trim();
            var code = row.querySelector('.ka-code').value.trim();
            if (item === '' && code === '') { return; }
            if (!isChanged(row)) { return; }
            changed++;
            if (item === '' || code === '') { problems.push('Regel met onvolledige invoer (' + (item || code) + ')'); }
            else if (row.classList.contains('is-missing') || row.classList.contains('is-checking')) { problems.push('Artikel ' + item + ' is niet gevonden/gecontroleerd in Exact'); }
        });
        if (problems.length || changed === 0) {
            e.preventDefault();
            message.textContent = problems.length
                ? 'Export niet mogelijk: ' + problems.slice(0, 5).join('; ') + (problems.length > 5 ? ' (+' + (problems.length - 5) + ' meer)' : '') + '.'
                : 'Er zijn geen gewijzigde of nieuwe regels om te exporteren.';
            message.hidden = false;
            return;
        }
        message.hidden = true;
        // Ongewijzigde regels uitzetten zodat ze niet worden meegestuurd (beide velden, zodat de volgorde klopt).
        var disabled = [];
        Array.prototype.forEach.call(tbody.rows, function (row) {
            if (!isChanged(row)) {
                Array.prototype.forEach.call(row.querySelectorAll('input'), function (i) { i.disabled = true; disabled.push(i); });
            }
        });
        setTimeout(function () { disabled.forEach(function (i) { i.disabled = false; }); }, 0);
    });

    // ---- Excel-sjabloon en -import (SheetJS, volledig in de browser) ----
    var info = document.getElementById('kaInfo');
    var drop = document.getElementById('kaDrop');
    var fileInput = document.getElementById('kaFile');

    function showInfo(text, isError) {
        info.hidden = true;
        message.hidden = true;
        var box = isError ? message : info;
        box.textContent = text;
        box.hidden = false;
    }

    document.getElementById('kaTemplate').addEventListener('click', function () {
        if (typeof XLSX === 'undefined') { showInfo('Excel-bibliotheek niet geladen.', true); return; }
        var ws = XLSX.utils.aoa_to_sheet([['Artikelnummer', 'Klantartikelnummer']]);
        // Tekstformaat (@) op de invulcellen zodat artikelnummers met voorloopnullen intact blijven.
        for (var r = 2; r <= 1001; r++) {
            ws['A' + r] = { t: 's', v: '', z: '@' };
            ws['B' + r] = { t: 's', v: '', z: '@' };
        }
        ws['!ref'] = 'A1:B1001';
        ws['!cols'] = [{ wch: 28 }, { wch: 28 }];
        var help = XLSX.utils.aoa_to_sheet([
            ['Klant artikelnummers - sjabloon'],
            ['Vul op blad "Artikelen" per regel het Geeve-artikelnummer (kolom A) en het klantartikelnummer (kolom B) in.'],
            ['De regels gelden voor alle klanten op de gekozen prijslijst. Sla het bestand op en importeer het in de applicatie.'],
            ['Artikelnummers die al in de lijst staan krijgen het nieuwe klantartikelnummer; nieuwe artikelen worden toegevoegd en in Exact gecontroleerd.']
        ]);
        help['!cols'] = [{ wch: 120 }];
        var wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'Artikelen');
        XLSX.utils.book_append_sheet(wb, help, 'Uitleg');
        XLSX.writeFile(wb, 'Klantartikelen_sjabloon.xlsx');
    });

    function cellText(v) { return v === undefined || v === null ? '' : String(v).trim(); }

    function importRows(data) {
        var start = 0, colItem = 0, colCode = 1;
        // Kopregel herkennen op naam; anders kolom A en B gebruiken.
        if (data.length) {
            var head = data[0].map(function (v) { return cellText(v).toLowerCase(); });
            var hi = head.findIndex(function (h) { return h.indexOf('artikelnummer') === 0 || h === 'artcodegeeve' || h === 'artikel'; });
            var hc = head.findIndex(function (h) { return h.indexOf('klantartikel') === 0 || h === 'artcodeklant'; });
            if (hi !== -1 && hc !== -1) { colItem = hi; colCode = hc; start = 1; }
            else if (hi !== -1 || hc !== -1) { start = 1; }
        }
        var existing = {};
        Array.prototype.forEach.call(tbody.rows, function (row) {
            var k = row.querySelector('.ka-item').value.trim().toLowerCase();
            if (k) { existing[k] = row; }
        });
        var added = 0, updated = 0, skipped = 0, queue = [];
        for (var i = start; i < data.length; i++) {
            var item = cellText(data[i][colItem]);
            var code = cellText(data[i][colCode]);
            if (item === '' && code === '') { continue; }
            if (item === '' || code === '') { skipped++; continue; }
            var key = item.toLowerCase();
            if (existing[key]) {
                var codeInput = existing[key].querySelector('.ka-code');
                if (codeInput.value.trim() !== code) { codeInput.value = code; updated++; }
            } else {
                var tr = addRow(item, code, true);
                existing[key] = tr;
                queue.push(tr);
                added++;
            }
        }
        updateCount();
        applyFilter(true);
        // Nieuwe artikelen in Exact controleren, enkele tegelijk.
        var running = 0;
        (function next() {
            while (running < 4 && queue.length) {
                running++;
                lookup(queue.shift(), function () { running--; next(); });
            }
        })();
        showInfo('Excel ingelezen: ' + added + ' nieuw, ' + updated + ' bijgewerkt' +
            (skipped ? ', ' + skipped + ' overgeslagen (onvolledige regel)' : '') +
            '. Nieuwe artikelen worden in Exact gecontroleerd.', false);
    }

    function readFile(file) {
        if (!file) { return; }
        if (typeof XLSX === 'undefined') { showInfo('Excel-bibliotheek niet geladen.', true); return; }
        var reader = new FileReader();
        reader.onload = function (ev) {
            try {
                var wb = XLSX.read(ev.target.result, { type: 'array' });
                var sheetName = wb.SheetNames.indexOf('Artikelen') !== -1 ? 'Artikelen' : wb.SheetNames[0];
                importRows(XLSX.utils.sheet_to_json(wb.Sheets[sheetName], { header: 1, raw: false, defval: '' }));
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
    drop.addEventListener('drop', function (e) {
        e.preventDefault();
        drop.classList.remove('is-over');
        readFile(e.dataTransfer.files[0]);
    });
})();
