(function () {
    var fields = document.querySelectorAll('#acFields [data-field]');
    var sheet = document.getElementById('acSheet');
    var printSheet = document.getElementById('acPrintSheet');
    var copies = document.getElementById('acCopies');
    var dateInput = document.querySelector('[data-field="datum"]');

    // Waarschuwingsdriehoek en accu-symbool (blaasaccumulator), als lijnen zodat ze scherp printen.
    var WARN = '<svg class="warn" viewBox="0 0 38 34" aria-hidden="true"><path d="M19 2.5 36 32H2Z" fill="none" stroke="#000" stroke-width="2.6" stroke-linejoin="round"/>' +
        '<path d="M19 11v12" stroke="#000" stroke-width="3.4" stroke-linecap="round"/><circle cx="19" cy="27.6" r="1.9"/></svg>';
    var ACC = '<svg class="acc" viewBox="0 0 13 34" aria-hidden="true"><rect x="1" y="1" width="11" height="28" rx="5.5" fill="none" stroke="#000" stroke-width="1"/>' +
        '<path d="M1 10.5h11" stroke="#000" stroke-width=".8"/><path d="M3.8 5h5.4L6.5 9.2Z" fill="none" stroke="#000" stroke-width=".8"/>' +
        '<path d="M6.5 29v4.5" stroke="#000" stroke-width="1"/></svg>';

    function esc(s) {
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function pad(n) { return (n < 10 ? '0' : '') + n; }
    function isoToday() { var d = new Date(); return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }
    // Datumveld (jjjj-mm-dd) zoals op de sticker: dd-mm-jjjj.
    function nlDate(v) { var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(v); return m ? m[3] + '-' + m[2] + '-' + m[1] : v; }

    // Voorvuldruk standaard in bar: alleen een getal krijgt " BAR" erachter.
    function druk(v) { return /^\d+([.,]\d+)?$/.test(v) ? v + ' BAR' : v; }

    function values() {
        var v = {};
        Array.prototype.forEach.call(fields, function (el) { v[el.getAttribute('data-field')] = el.value.trim(); });
        return v;
    }

    function sticker(v) {
        return '<div class="accu">' + WARN + ACC +
            '<div class="f r r1">' + esc(v.bedrijf) + '</div>' +
            '<div class="f r r2">DATE :&nbsp;' + esc(nlDate(v.datum)) + '</div>' +
            '<div class="f r r3">PRE-FILL PRESSURE :&nbsp;' + esc(druk(v.druk)) + '</div>' +
            '<div class="f r r4">' + esc(v.vrij) + '</div>' +
            '</div>';
    }

    // Tekst die niet past wordt kleiner gezet (in stapjes), zodat niets wordt afgeknipt.
    function fit(container) {
        Array.prototype.forEach.call(container.querySelectorAll('.f'), function (el) {
            var size = parseFloat(getComputedStyle(el).fontSize);
            var min = size * 0.4;
            while (el.scrollWidth > el.clientWidth && size > min) {
                size *= 0.95;
                el.style.fontSize = size + 'px';
            }
        });
    }

    function preview() {
        sheet.innerHTML = sticker(values());
        fit(sheet);
    }

    function times() {
        var n = parseInt(copies.value, 10);
        return isNaN(n) || n < 1 ? 1 : Math.min(n, 99);
    }

    Array.prototype.forEach.call(fields, function (el) { el.addEventListener('input', preview); });

    // Leegmaken (het gummetje in de oude app): bedrijfsnaam blijft, datum terug naar vandaag.
    document.getElementById('acClear').addEventListener('click', function () {
        Array.prototype.forEach.call(fields, function (el) {
            var f = el.getAttribute('data-field');
            if (f === 'druk' || f === 'vrij') { el.value = ''; }
        });
        dateInput.value = isoToday();
        copies.value = 1;
        preview();
        document.querySelector('[data-field="druk"]').focus();
    });

    document.getElementById('acPrint').addEventListener('click', function () {
        var html = sticker(values());
        var all = '';
        for (var n = 0; n < times(); n++) { all += html; }
        printSheet.innerHTML = all;
        printSheet.style.display = 'block'; // even zichtbaar voor fit(), daarna weer alleen bij het printen
        fit(printSheet);
        printSheet.style.display = '';
        window.print();
    });

    dateInput.value = isoToday();
    preview();
}());
