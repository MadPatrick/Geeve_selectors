(function () {
    var box = document.getElementById('soActions');
    var input = document.getElementById('soNumber');
    var fetchBtn = document.getElementById('soFetch');
    var printBtn = document.getElementById('soPrint');
    var status = document.getElementById('soStatus');
    var message = document.getElementById('soMessage');
    var result = document.getElementById('soResult');
    var summary = document.getElementById('soSummary');
    var tbody = document.querySelector('#soTable tbody');
    var all = document.getElementById('soAll');
    var previewPanel = document.getElementById('soPreviewPanel');
    var previewCount = document.getElementById('soPreviewCount');
    var sheet = document.getElementById('soSheet');
    var prevBtn = document.getElementById('soPrev');
    var nextBtn = document.getElementById('soNext');
    var current = 0;      // welke sticker het voorbeeld toont
    var rows = [];
    var asked = '';

    var DAYS = ['zondag', 'maandag', 'dinsdag', 'woensdag', 'donderdag', 'vrijdag', 'zaterdag'];
    var MONTHS = ['januari', 'februari', 'maart', 'april', 'mei', 'juni', 'juli', 'augustus', 'september', 'oktober', 'november', 'december'];

    function esc(s) {
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    // Aantallen uit Exact komen als "10.000000": zonder overbodige decimalen, met komma.
    function qty(v) {
        var n = Number(v);
        return v === '' || isNaN(n) ? String(v) : n.toLocaleString('nl-NL', { maximumFractionDigits: 3, useGrouping: false });
    }
    // Zoals in NiceLabel: "woensdag, 14 oktober 2026".
    function today() {
        var d = new Date();
        return DAYS[d.getDay()] + ', ' + d.getDate() + ' ' + MONTHS[d.getMonth()] + ' ' + d.getFullYear();
    }
    function fail(text) { message.textContent = text; message.hidden = false; }

    function field(cls, text) { return '<div class="f ' + cls + '">' + esc(text) + '</div>'; }

    function sticker(r, date) {
        return '<div class="sticker">' +
            field('r1 b c-l1', 'Bonnr.:') + field('r1 b c-bonnr', r.bonnr) + field('r1 b c-debnm', r.debnm) +
            field('r2 c-l2', 'Uw referentie:') + field('r2 c-ref', r.uw_ref) +
            field('r3 c-l3', 'Aantal:') + field('r3 b big c-val3', qty(r.besteld)) +
            field('r3 c-l4', 'Artikelnr:') + field('r3 b big c-val4', r.artikelnummer) +
            field('r4 c-l3', 'Locatie:') + field('r4 c-val3', r.locatie) +
            field('r4 c-l4', 'Uw Artikelnr:') + field('r4 large c-val4', r.uw_artikelnummer) +
            field('r5 c-l5', 'Country Of Origin:') + field('r5 c-coo', r.ia_CountryOfOrigin) +
            '<div class="bc"><svg data-code="' + esc(r.artikelnummer) + '"></svg></div>' +
            field('r6 c-l6', 'rgl:') + field('r6 c-rgl', r.ord_rgl) +
            field('r6 c-date', date) + field('r6 c-loop', r.looplocatie) +
            '</div>';
    }

    // Tekst die niet past wordt kleiner gezet (in stapjes), zodat niets wordt afgeknipt.
    function fit(el) {
        var size = parseFloat(getComputedStyle(el).fontSize);
        var min = size * 0.55;
        while (el.scrollWidth > el.clientWidth && size > min) {
            size *= 0.95;
            el.style.fontSize = size + 'px';
        }
    }

    function selected() {
        return rows.filter(function (r, i) {
            var cb = tbody.querySelector('input[data-i="' + i + '"]');
            return cb && cb.checked;
        });
    }

    function renderPreview() {
        var list = selected();
        var date = today();
        sheet.innerHTML = list.map(function (r) { return sticker(r, date); }).join('');
        Array.prototype.forEach.call(sheet.querySelectorAll('svg[data-code]'), function (svg) {
            var code = svg.getAttribute('data-code');
            if (code === '') { return; }
            try {
                JsBarcode(svg, code, { format: 'CODE128', displayValue: false, margin: 0, width: 2, height: 100 });
                svg.setAttribute('preserveAspectRatio', 'none');
                svg.removeAttribute('style');
            } catch (e) {
                svg.outerHTML = '<span class="so-nobarcode">Geen barcode mogelijk</span>';
            }
        });
        previewPanel.hidden = list.length === 0; // eerst zichtbaar maken, anders kan fit() niets meten
        sheet.classList.add('is-measuring'); // alle stickers even zichtbaar voor fit(), daarna weer één
        Array.prototype.forEach.call(sheet.querySelectorAll('.f'), fit);
        sheet.classList.remove('is-measuring');
        show(current);
        printBtn.disabled = list.length === 0;
        printBtn.textContent = list.length === 0 ? 'Print stickers' : 'Print ' + list.length + (list.length === 1 ? ' sticker' : ' stickers');
        all.checked = list.length === rows.length;
        all.indeterminate = list.length > 0 && list.length < rows.length;
        Array.prototype.forEach.call(tbody.querySelectorAll('tr'), function (tr) {
            tr.classList.toggle('is-off', !tr.querySelector('input').checked);
        });
    }

    // Toont sticker i (de rest blijft in de pagina staan voor het printen).
    function show(i) {
        var all = sheet.querySelectorAll('.sticker');
        current = Math.max(0, Math.min(i, all.length - 1));
        Array.prototype.forEach.call(all, function (el, n) { el.classList.toggle('is-current', n === current); });
        previewCount.textContent = all.length === 0 ? '' : 'Sticker ' + (current + 1) + ' van ' + all.length;
        prevBtn.disabled = current <= 0;
        nextBtn.disabled = current >= all.length - 1;
    }

    function render(first) {
        summary.innerHTML =
            '<span class="msp-pill">Order <strong>' + esc(asked) + '</strong></span>' +
            (first.debnm ? '<span class="msp-pill">' + esc(first.debnr) + ' <strong>' + esc(first.debnm) + '</strong></span>' : '') +
            '<span class="msp-pill"><strong>' + rows.length + '</strong> regels</span>';
        tbody.innerHTML = rows.map(function (r, i) {
            return '<tr><td><input type="checkbox" data-i="' + i + '" checked aria-label="Regel ' + esc(r.ord_rgl) + '"></td>' +
                '<td>' + esc(r.ord_rgl) + '</td><td>' + esc(r.artikelnummer) + '</td><td class="ka-desc">' + esc(r.artikelnaam) + '</td>' +
                '<td class="num">' + esc(qty(r.besteld)) + '</td><td class="num">' + esc(qty(r.geleverd)) + '</td>' +
                '<td>' + esc(r.locatie) + '</td><td>' + esc(r.looplocatie) + '</td><td>' + esc(r.uw_artikelnummer) + '</td>' +
                '<td>' + esc(r.ia_CountryOfOrigin) + '</td></tr>';
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
                    previewPanel.hidden = true;
                    fail('Geen regels gevonden voor order ' + nummer + '.');
                    return;
                }
                render(rows[0]);
            })
            .catch(function (e) { fail(e.message || String(e)); })
            .then(function () { fetchBtn.disabled = false; status.textContent = ''; });
    }

    fetchBtn.addEventListener('click', load);
    input.addEventListener('keydown', function (e) { if (e.key === 'Enter') { load(); } });
    tbody.addEventListener('change', renderPreview);
    prevBtn.addEventListener('click', function () { show(current - 1); });
    nextBtn.addEventListener('click', function () { show(current + 1); });
    all.addEventListener('change', function () {
        Array.prototype.forEach.call(tbody.querySelectorAll('input[type="checkbox"]'), function (cb) { cb.checked = all.checked; });
        renderPreview();
    });
    printBtn.addEventListener('click', function () {
        // De datum op de sticker is de printdatum: vlak voor het printen opnieuw opbouwen.
        renderPreview();
        window.print();
    });
}());
