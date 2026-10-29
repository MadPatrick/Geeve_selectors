(function () {
    var aantal = document.getElementById('kmAantal');
    var preview = document.getElementById('kmPreview');
    var printSheet = document.getElementById('kmPrintSheet');
    var printBtn = document.getElementById('kmPrint');
    var stickers = preview.querySelectorAll('.km-sticker');
    var MAX_PX = 60;       // bovengrens van de zoekruimte; de sticker zelf begrenst eerder
    var MIN_PX = 5;
    var LINE_HEIGHT = 1.1;  // zelfde als line-height in kabelmerkers.css
    var SAFETY = 0.97;      // 3% marge tegen een iets breder lettertype op de printserver

    function esc(s) {
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function el(id) { return document.getElementById(id); }
    function pad(n) { return (n < 10 ? '0' : '') + n; }

    // Datumlijst: vandaag (voorgeselecteerd) met 30 dagen ervoor en erna, als dd-mm-jj.
    var datum = el('kmDatum');
    var nu = new Date();
    for (var d = -30; d <= 30; d++) {
        var dag = new Date(nu.getFullYear(), nu.getMonth(), nu.getDate() + d);
        var tekst = pad(dag.getDate()) + '-' + pad(dag.getMonth() + 1) + '-' + pad(dag.getFullYear() % 100);
        var optie = document.createElement('option');
        optie.value = tekst;
        optie.appendChild(document.createTextNode(tekst));
        if (d === 0) { optie.selected = true; }
        datum.appendChild(optie);
    }

    function join(ids) {
        var delen = [];
        for (var i = 0; i < ids.length; i++) {
            var v = el(ids[i]).value.replace(/^\s+|\s+$/g, '');
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
    function fit(sticker, textEl) {
        var cs = getComputedStyle(sticker);
        var boxH = sticker.clientHeight - parseFloat(cs.paddingTop) - parseFloat(cs.paddingBottom);
        var lines = Math.max(1, value().split('\n').length);
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
        var size = Math.max(MIN_PX, lo * SAFETY);
        textEl.style.fontSize = size + 'px';
        return size;
    }

    function refresh() {
        var typed = value();
        var size = 0;
        Array.prototype.forEach.call(stickers, function (sticker) {
            var textEl = sticker.querySelector('.km-tekst');
            textEl.textContent = typed;
            size = typed === '' ? 0 : fit(sticker, textEl);
        });
        printBtn.disabled = typed.trim() === '';
        return size;
    }

    var velden = ['kmA1', 'kmA2', 'kmA3', 'kmDatum', 'kmB2', 'kmB3'];
    for (var v = 0; v < velden.length; v++) {
        el(velden[v]).addEventListener('input', refresh);
        el(velden[v]).addEventListener('change', refresh);
    }

    document.getElementById('kmClear').addEventListener('click', function () {
        for (var i = 0; i < velden.length; i++) { if (velden[i] !== 'kmDatum') { el(velden[i]).value = ''; } }
        refresh();
        el('kmA1').focus();
    });

    printBtn.addEventListener('click', function () {
        var typed = value();
        if (typed.trim() === '') { return; }
        var size = refresh();
        var one = '<div class="km-sticker"><div class="km-tekst" style="font-size:' + size + 'px">' + esc(typed) + '</div></div>';
        var html = '';
        for (var i = 0; i < count(); i++) { html += one; }
        printSheet.innerHTML = html;
        window.print();
    });

    refresh();
}());
