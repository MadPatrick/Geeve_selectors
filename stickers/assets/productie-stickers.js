(function () {
    var box = document.getElementById('psActions');
    var input = document.getElementById('psNumber');
    var fetchBtn = document.getElementById('psFetch');
    var status = document.getElementById('psStatus');
    var message = document.getElementById('psMessage');
    var result = document.getElementById('psResult');
    var summary = document.getElementById('psSummary');
    var tbody = document.querySelector('#psTable tbody');
    var all = document.getElementById('psAll');
    var side = document.getElementById('psSide');
    var sheet = document.getElementById('psSheet');
    var printSheet = document.getElementById('psPrintSheet');
    var prevBtn = document.getElementById('psPrev');
    var nextBtn = document.getElementById('psNext');
    var previewCount = document.getElementById('psPreviewCount');
    var copies = document.getElementById('psCopies');
    var printBtn = document.getElementById('psPrint');
    var printInfo = document.getElementById('psPrintInfo');
    var rows = [];
    var asked = '';
    var current = 0; // welke sticker het voorbeeld toont

    var DAYS = ['zondag', 'maandag', 'dinsdag', 'woensdag', 'donderdag', 'vrijdag', 'zaterdag'];
    var MONTHS = ['januari', 'februari', 'maart', 'april', 'mei', 'juni', 'juli', 'augustus', 'september', 'oktober', 'november', 'december'];

    function esc(s) {
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    // Aantallen als "5.490000" of 5.49: zonder overbodige decimalen, met komma.
    function qty(v) {
        var n = Number(v);
        return v === '' || isNaN(n) ? String(v) : n.toLocaleString('nl-NL', { maximumFractionDigits: 3, useGrouping: false });
    }
    // Printdatum, zoals op de oude sticker: "woensdag, 14 oktober 2026".
    function today() {
        var d = new Date();
        return DAYS[d.getDay()] + ', ' + d.getDate() + ' ' + MONTHS[d.getMonth()] + ' ' + d.getFullYear();
    }
    function fail(text) { message.textContent = text; message.hidden = false; }

    function field(cls, text) { return '<div class="f ' + cls + '">' + esc(text) + '</div>'; }

    function sticker(r, date) {
        return '<div class="sticker"><div class="sticker-in">' +
            field('r1 b c-l1', 'Bonnr.:') + field('r1 b c-bonnr', r.ordernr) + field('r1 b c-debnm', r.debiteur) +
            field('r2 c-l2', 'Uw referentie:') + field('r2 c-ref', 'voor productieorder') +
            field('r3 c-l3', 'Aantal:') + field('r3 b big c-val3', qty(r.aantal)) +
            field('r3 c-l4', 'Artikelnr:') + field('r3 b big c-val4', r.artikel) +
            field('r4 c-l3', 'Locatie:') + field('r4 c-val3', r.locatie) +
            '<div class="bc"><svg data-art-code="' + esc(r.artikel) + '"></svg></div>' +
            field('r6 c-date', date) + field('r6 c-loop', r.looplocatie) +
            '</div></div>';
    }

    // Tekst die niet past wordt kleiner gezet (in stapjes), zodat niets wordt afgeknipt.
    function fit(container) {
        Array.prototype.forEach.call(container.querySelectorAll('.sticker .f'), function (el) {
            var size = parseFloat(getComputedStyle(el).fontSize);
            var min = size * 0.55;
            while (el.scrollWidth > el.clientWidth && size > min) {
                size *= 0.95;
                el.style.fontSize = size + 'px';
            }
        });
    }

    function build(container, list, times) {
        var date = today();
        var html = '';
        list.forEach(function (r) { for (var n = 0; n < times; n++) { html += sticker(r, date); } });
        container.innerHTML = html;
        ArtSticker.barcodes(container);
    }

    function selected() {
        return rows.filter(function (r, i) {
            var cb = tbody.querySelector('input[data-i="' + i + '"]');
            return cb && cb.checked;
        });
    }

    function times() {
        var n = parseInt(copies.value, 10);
        return isNaN(n) || n < 1 ? 1 : Math.min(n, 99);
    }

    function updateInfo(list) {
        var n = list.length * times();
        printBtn.disabled = n === 0;
        printInfo.textContent = n === 0 ? '' : n + (n === 1 ? ' sticker' : ' stickers') + (times() > 1 ? ' (' + list.length + ' regels)' : '');
    }

    function renderPreview() {
        var list = selected();
        build(sheet, list, 1);
        side.hidden = list.length === 0; // eerst zichtbaar maken, anders kan fit() niets meten
        sheet.classList.add('is-measuring');
        fit(sheet);
        sheet.classList.remove('is-measuring');
        show(current);
        updateInfo(list);
        all.checked = list.length === rows.length;
        all.indeterminate = list.length > 0 && list.length < rows.length;
        Array.prototype.forEach.call(tbody.querySelectorAll('tr'), function (tr) {
            tr.classList.toggle('is-off', !tr.querySelector('input').checked);
        });
    }

    // Toont sticker i in het voorbeeld.
    function show(i) {
        var list = sheet.querySelectorAll('.sticker');
        current = Math.max(0, Math.min(i, list.length - 1));
        Array.prototype.forEach.call(list, function (el, n) { el.classList.toggle('is-current', n === current); });
        previewCount.textContent = list.length === 0 ? '' : 'Sticker ' + (current + 1) + ' van ' + list.length;
        prevBtn.disabled = current <= 0;
        nextBtn.disabled = current >= list.length - 1;
    }

    function render() {
        var first = rows[0];
        summary.innerHTML =
            '<span class="msp-pill">Order <strong>' + esc(asked) + '</strong></span>' +
            (first.debiteur ? '<span class="msp-pill"><strong>' + esc(first.debiteur) + '</strong></span>' : '') +
            '<span class="msp-pill"><strong>' + rows.length + '</strong> regels</span>';
        tbody.innerHTML = rows.map(function (r, i) {
            return '<tr><td><input type="checkbox" data-i="' + i + '" checked aria-label="' + esc(r.artikel) + '"></td>' +
                '<td>' + esc(r.project) + '</td><td>' + esc(r.locatie) + '</td><td>' + esc(r.looplocatie) + '</td>' +
                '<td>' + esc(r.artikel) + '</td><td class="ka-desc">' + esc(r.omschrijving) + '</td>' +
                '<td class="num">' + esc(qty(r.aantal)) + '</td></tr>';
        }).join('');
        result.hidden = false;
        current = 0;
        renderPreview();
    }

    function load() {
        var nummer = input.value.trim();
        message.hidden = true;
        if (nummer === '') { fail('Vul een ordernummer in.'); input.focus(); return; }
        fetchBtn.disabled = true;
        printBtn.disabled = true;
        status.textContent = 'Bezig met ophalen…';
        var body = new FormData();
        body.append('csrf', box.getAttribute('data-csrf'));
        body.append('nummer', nummer);
        fetch(box.getAttribute('data-endpoint'), { method: 'POST', body: body, credentials: 'same-origin' })
            .then(function (res) { return res.json(); })
            .then(function (json) {
                if (!json.ok) { throw new Error(json.error || 'Onbekende fout.'); }
                rows = json.rows || [];
                asked = nummer;
                if (rows.length === 0) {
                    result.hidden = true;
                    side.hidden = true;
                    fail('Geen productieorderregels gevonden voor order ' + nummer + '.');
                    return;
                }
                render();
            })
            .catch(function (e) { fail(e.message || String(e)); })
            .then(function () { fetchBtn.disabled = false; status.textContent = ''; });
    }

    fetchBtn.addEventListener('click', load);
    input.addEventListener('keydown', function (e) { if (e.key === 'Enter') { load(); } });
    tbody.addEventListener('change', renderPreview);
    prevBtn.addEventListener('click', function () { show(current - 1); });
    nextBtn.addEventListener('click', function () { show(current + 1); });
    copies.addEventListener('input', function () { updateInfo(selected()); });
    all.addEventListener('change', function () {
        Array.prototype.forEach.call(tbody.querySelectorAll('input[type="checkbox"]'), function (cb) { cb.checked = all.checked; });
        renderPreview();
    });

    // Print: de printer kies je in het printvenster van de browser. De datum is de printdatum.
    printBtn.addEventListener('click', function () {
        var list = selected();
        if (list.length === 0) { return; }
        build(printSheet, list, times());
        printSheet.style.display = 'block'; // even zichtbaar voor fit(), daarna weer alleen bij het printen
        fit(printSheet);
        printSheet.style.display = '';
        window.print();
    });
}());
