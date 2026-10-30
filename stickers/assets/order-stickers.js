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
    var PAGE_SIZE = 10;   // regels per pagina in de tabel
    var page = 0;
    var pager = document.getElementById('soPager');
    var pageInfo = document.getElementById('soPageInfo');
    var pagePrev = document.getElementById('soPagePrev');
    var pageNext = document.getElementById('soPageNext');
    var rows = [];
    var alleRows = [];   // alle regels van de order; rows is daarvan de selectie (bij "Backorders" alleen wat nog niet geleverd is)
    var backorders = document.getElementById('soBackorders');
    var asked = '';
    var sortBy = 'loop'; // 'loop' (looplocatie, standaard), 'regel' (orderregelnummer) of 'artikel' (artikelsticker, op orderregel)
    var BC_MODULE_MM = 0.38; // breedte van één streepje in de barcode, zelfde als de artikelsticker
    var BC_MAX_MM = 96.7;    // breedte van het barcodevak
    var sortToggle = document.getElementById('soSort');

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
        if (sortBy === 'artikel') { return ArtSticker.html(r.artikelnaam, r.artikelnummer, true); }
        return '<div class="sticker"><div class="sticker-in">' +
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
            '</div></div>';
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
        // De artikelsticker (70 × 25 mm) krijgt zijn eigen paginaformaat mee, zodat hij bij het printen gecentreerd op een
        // label van die maat staat en niet links boven op de 102 × 54 mm-pagina van de orderstickers.
        var pagina = sortBy === 'artikel' ? '<style>@page { size: 70mm 25mm; margin: 0; }</style>' : '';
        sheet.innerHTML = pagina + list.map(function (r) { return sticker(r, date); }).join('');
        Array.prototype.forEach.call(sheet.querySelectorAll('svg[data-code]'), function (svg) {
            var code = svg.getAttribute('data-code');
            if (code === '') { return; }
            try {
                // Vaste streepjesbreedte, links uitgelijnd: niet uitrekken over de hele breedte van de sticker.
                JsBarcode(svg, code, { format: 'CODE128', displayValue: false, margin: 0, width: 1, height: 100 });
                var modules = parseFloat(svg.getAttribute('width')) || 0;
                svg.setAttribute('viewBox', '0 0 ' + modules + ' 100');
                svg.setAttribute('preserveAspectRatio', 'none');
                svg.removeAttribute('style');
                svg.removeAttribute('height');
                svg.style.width = Math.min(modules * BC_MODULE_MM, BC_MAX_MM) + 'mm';
            } catch (e) {
                svg.outerHTML = '<span class="so-nobarcode">Geen barcode mogelijk</span>';
            }
        });
        ArtSticker.barcodes(sheet);
        previewPanel.hidden = list.length === 0; // eerst zichtbaar maken, anders kan fit() niets meten
        sheet.classList.add('is-measuring'); // alle stickers even zichtbaar voor fit(), daarna weer één
        Array.prototype.forEach.call(sheet.querySelectorAll('.sticker:not(.sticker--art) .f'), fit);
        ArtSticker.fit(sheet);
        sheet.classList.remove('is-measuring');
        show(current);
        printBtn.disabled = list.length === 0;
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

    // Tabel per 10 regels; de andere regels blijven (verborgen) staan, dus hun vinkje telt gewoon mee.
    function showPage(n) {
        var pages = Math.max(1, Math.ceil(rows.length / PAGE_SIZE));
        page = Math.max(0, Math.min(n, pages - 1));
        Array.prototype.forEach.call(tbody.querySelectorAll('tr'), function (tr, i) {
            tr.hidden = Math.floor(i / PAGE_SIZE) !== page;
        });
        pager.hidden = pages <= 1;
        pageInfo.textContent = 'Pagina ' + (page + 1) + ' van ' + pages;
        pagePrev.disabled = page <= 0;
        pageNext.disabled = page >= pages - 1;
    }

    // Natuurlijke volgorde ("2" voor "10"); lege looplocaties achteraan, daarbinnen op orderregel.
    function natural(a, b) { return String(a).localeCompare(String(b), 'nl', { numeric: true, sensitivity: 'base' }); }
    function sortRows() {
        rows.sort(function (a, b) {
            if (sortBy === 'loop') {
                if ((a.looplocatie === '') !== (b.looplocatie === '')) { return a.looplocatie === '' ? 1 : -1; }
                var c = natural(a.looplocatie, b.looplocatie);
                if (c !== 0) { return c; }
            }
            return natural(a.ord_rgl, b.ord_rgl);
        });
    }

    function render(first) {
        summary.innerHTML =
            '<span class="msp-pill">Order <strong>' + esc(asked) + '</strong></span>' +
            (first.debnm ? '<span class="msp-pill">' + esc(first.debnr) + ' <strong>' + esc(first.debnm) + '</strong></span>' : '') +
            '<span class="msp-pill"><strong>' + rows.length + '</strong> regels</span>';
        sortRows();
        renderTable();
        result.hidden = false;
        showPage(0);
        current = 0;
        renderPreview();
    }

    function renderTable() {
        tbody.innerHTML = rows.map(function (r, i) {
            return '<tr><td><input type="checkbox" data-i="' + i + '"' + (r.off ? '' : ' checked') + ' aria-label="Regel ' + esc(r.ord_rgl) + '"></td>' +
                '<td>' + esc(r.ord_rgl) + '</td><td>' + esc(r.artikelnummer) + '</td><td class="ka-desc">' + esc(r.artikelnaam) + '</td>' +
                '<td class="num">' + esc(qty(r.besteld)) + '</td><td class="num">' + esc(qty(r.geleverd)) + '</td>' +
                '<td>' + esc(r.locatie) + '</td><td>' + esc(r.looplocatie) + '</td><td>' + esc(r.uw_artikelnummer) + '</td>' +
                '<td>' + esc(r.ia_CountryOfOrigin) + '</td></tr>';
        }).join('');
    }

    // Andere volgorde kiezen: uitgevinkte regels blijven uitgevinkt.
    sortToggle.addEventListener('click', function (e) {
        var btn = e.target.closest('button[data-sort]');
        if (!btn || btn.getAttribute('data-sort') === sortBy) { return; }
        sortBy = btn.getAttribute('data-sort');
        Array.prototype.forEach.call(sortToggle.querySelectorAll('button'), function (b) { b.classList.toggle('is-active', b === btn); });
        if (rows.length === 0) { return; }
        rows.forEach(function (r, i) {
            var cb = tbody.querySelector('input[data-i="' + i + '"]');
            r.off = cb ? !cb.checked : false;
        });
        sortRows();
        renderTable();
        showPage(0);
        current = 0;
        renderPreview();
    });

    // Backorders: alleen de regels waarvan nog niet alles is geleverd (geleverd < besteld).
    function nogTeLeveren(r) {
        var besteld = Number(r.besteld);
        var geleverd = Number(r.geleverd);
        return isNaN(besteld) || isNaN(geleverd) || geleverd < besteld;
    }
    function applyFilter() {
        rows = backorders.checked ? alleRows.filter(nogTeLeveren) : alleRows.slice();
        if (alleRows.length === 0) { return; }
        if (rows.length === 0) {
            result.hidden = true;
            previewPanel.hidden = true;
            printBtn.disabled = true;
            fail('Alle regels van order ' + asked + ' zijn al geleverd (geen backorders).');
            return;
        }
        message.hidden = true;
        render(rows[0]);
    }
    backorders.addEventListener('change', applyFilter);

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
                alleRows = json.rows || [];
                asked = nummer;
                rows = alleRows;
                if (rows.length === 0) {
                    result.hidden = true;
                    previewPanel.hidden = true;
                    fail('Geen regels gevonden voor order ' + nummer + '.');
                    return;
                }
                applyFilter();
            })
            .catch(function (e) { fail(e.message || String(e)); })
            .then(function () { fetchBtn.disabled = false; status.textContent = ''; });
    }

    fetchBtn.addEventListener('click', load);
    input.addEventListener('keydown', function (e) { if (e.key === 'Enter') { load(); } });
    tbody.addEventListener('change', renderPreview);
    pagePrev.addEventListener('click', function () { showPage(page - 1); });
    pageNext.addEventListener('click', function () { showPage(page + 1); });
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
