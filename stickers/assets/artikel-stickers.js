(function () {
    var box = document.getElementById('saActions');
    var input = document.getElementById('saSearch');
    var status = document.getElementById('saStatus');
    var message = document.getElementById('saMessage');
    var result = document.getElementById('saResult');
    var tbody = document.querySelector('#saTable tbody');
    var all = document.getElementById('saAll');
    var side = document.getElementById('saSide');
    var sheet = document.getElementById('saSheet');
    var printSheet = document.getElementById('saPrintSheet');
    var copies = document.getElementById('saCopies');
    var printInfo = document.getElementById('saPrintInfo');
    var buttons = document.querySelectorAll('.sa-btn');
    var rows = [];
    var current = -1;   // aangeklikte regel (voorbeeld)
    var timer = null;
    var seq = 0;        // alleen het antwoord op de laatste zoekopdracht tonen

    var MODULE_MM = 0.38; // breedte van één streepje in de barcode
    var MAX_BC_MM = 94;   // past binnen de 102 mm met 4 mm marge

    function esc(s) {
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function fail(text) { message.textContent = text; message.hidden = false; }

    function sticker(r) {
        return '<div class="sticker">' +
            '<div class="f desc">' + esc(r.omschrijving) + '</div>' +
            '<div class="bc"><svg data-code="' + esc(r.artikel) + '"></svg></div>' +
            '<div class="f art">' + esc(r.artikel) + '</div>' +
            '</div>';
    }

    // Tekst die niet past wordt kleiner gezet (in stapjes), zodat niets wordt afgeknipt.
    function fit(el) {
        var size = parseFloat(getComputedStyle(el).fontSize);
        var min = size * 0.4;
        while (el.scrollWidth > el.clientWidth && size > min) {
            size *= 0.95;
            el.style.fontSize = size + 'px';
        }
    }

    // Code 128 van het artikelnummer; breedte volgt het aantal streepjes (max. de breedte van de sticker).
    function barcodes(container) {
        Array.prototype.forEach.call(container.querySelectorAll('svg[data-code]'), function (svg) {
            var code = svg.getAttribute('data-code');
            if (code === '') { return; }
            try {
                JsBarcode(svg, code, { format: 'CODE128', displayValue: false, margin: 0, width: 1, height: 100 });
                var modules = parseFloat(svg.getAttribute('width')) || 0;
                svg.setAttribute('viewBox', '0 0 ' + modules + ' 100');
                svg.setAttribute('preserveAspectRatio', 'none');
                svg.removeAttribute('style');
                svg.removeAttribute('height');
                svg.setAttribute('width', Math.min(modules * MODULE_MM, MAX_BC_MM) + 'mm');
            } catch (e) {
                svg.outerHTML = '<span>Geen barcode mogelijk</span>';
            }
        });
    }

    function build(container, list, times) {
        var html = '';
        list.forEach(function (r) { for (var n = 0; n < times; n++) { html += sticker(r); } });
        container.innerHTML = html;
        barcodes(container);
    }

    function checked() {
        return rows.filter(function (r, i) {
            var cb = tbody.querySelector('input[data-i="' + i + '"]');
            return cb && cb.checked;
        });
    }

    // Wat er geprint wordt: de aangevinkte regels, of (zonder vinkjes) de aangeklikte regel.
    function toPrint() {
        var list = checked();
        return list.length > 0 ? list : (current >= 0 ? [rows[current]] : []);
    }

    function times() {
        var n = parseInt(copies.value, 10);
        return isNaN(n) || n < 1 ? 1 : Math.min(n, 99);
    }

    function update() {
        var list = toPrint();
        var n = list.length * times();
        printInfo.textContent = n === 0 ? '' : n + (n === 1 ? ' sticker' : ' stickers') + (list.length > 1 ? ' (' + list.length + ' artikelen)' : '');
        Array.prototype.forEach.call(buttons, function (b) { b.disabled = n === 0; });
        var c = checked().length;
        all.checked = rows.length > 0 && c === rows.length;
        all.indeterminate = c > 0 && c < rows.length;
    }

    function select(i) {
        current = i;
        Array.prototype.forEach.call(tbody.querySelectorAll('tr'), function (tr, n) { tr.classList.toggle('is-current', n === i); });
        if (i < 0) { side.hidden = true; sheet.innerHTML = ''; update(); return; }
        side.hidden = false; // eerst zichtbaar maken, anders kan fit() niets meten
        build(sheet, [rows[i]], 1);
        Array.prototype.forEach.call(sheet.querySelectorAll('.f'), fit);
        update();
    }

    function render(more) {
        tbody.innerHTML = rows.map(function (r, i) {
            return '<tr data-row="' + i + '"><td><input type="checkbox" data-i="' + i + '" aria-label="' + esc(r.artikel) + '"></td>' +
                '<td>' + esc(r.artikel) + '</td><td class="ka-desc">' + esc(r.omschrijving) + '</td></tr>';
        }).join('');
        result.hidden = rows.length === 0;
        status.textContent = rows.length === 0 ? '' : rows.length + (rows.length === 1 ? ' artikel' : ' artikelen') + (more ? ' (eerste ' + rows.length + ', zoek specifieker)' : '');
        // Exacte treffer (bijv. "g16s" → G16S) meteen in het voorbeeld, anders de eerste regel.
        var q = input.value.trim().toLowerCase();
        var exact = -1;
        rows.forEach(function (r, i) { if (exact < 0 && r.artikel.toLowerCase() === q) { exact = i; } });
        select(rows.length === 0 ? -1 : (exact >= 0 ? exact : 0));
    }

    function load() {
        var zoek = input.value.trim();
        message.hidden = true;
        if (zoek === '') { rows = []; render(false); return; }
        var mine = ++seq;
        status.textContent = 'Bezig met zoeken…';
        var body = new FormData();
        body.append('csrf', box.getAttribute('data-csrf'));
        body.append('zoek', zoek);
        fetch(box.getAttribute('data-endpoint'), { method: 'POST', body: body, credentials: 'same-origin' })
            .then(function (res) { return res.json(); })
            .then(function (json) {
                if (mine !== seq) { return; }
                if (!json.ok) { throw new Error(json.error || 'Onbekende fout.'); }
                rows = json.rows || [];
                render(!!json.more);
                if (rows.length === 0) { fail('Geen artikelen gevonden met "' + zoek + '" in het artikelnummer.'); }
            })
            .catch(function (e) { if (mine === seq) { status.textContent = ''; fail(e.message || String(e)); } });
    }

    // Zoeken tijdens het typen (kort wachten), of direct met Enter.
    input.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(load, 350); });
    input.addEventListener('keydown', function (e) { if (e.key === 'Enter') { clearTimeout(timer); load(); } });

    tbody.addEventListener('click', function (e) {
        var tr = e.target.closest('tr[data-row]');
        if (!tr || e.target.matches('input[type="checkbox"]')) { return; }
        select(parseInt(tr.getAttribute('data-row'), 10));
    });
    tbody.addEventListener('change', update);
    all.addEventListener('change', function () {
        Array.prototype.forEach.call(tbody.querySelectorAll('input[type="checkbox"]'), function (cb) { cb.checked = all.checked; });
        update();
    });
    copies.addEventListener('input', update);

    // Geel, Zilver en Geeve printen dezelfde sticker; de rol/printer kies je in het printvenster van de browser.
    Array.prototype.forEach.call(buttons, function (btn) {
        btn.addEventListener('click', function () {
            var list = toPrint();
            if (list.length === 0) { return; }
            build(printSheet, list, times());
            printSheet.style.display = 'block'; // even zichtbaar voor fit(), daarna weer alleen bij het printen
            Array.prototype.forEach.call(printSheet.querySelectorAll('.f'), fit);
            printSheet.style.display = '';
            document.title = 'Stickers ' + btn.getAttribute('data-roll');
            window.print();
            document.title = 'Stickers op Artikelnummer | Geeve Hydraulics';
        });
    });

    update();
}());
