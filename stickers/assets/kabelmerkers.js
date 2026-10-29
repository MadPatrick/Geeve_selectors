(function () {
    var aantal = document.getElementById('kmAantal');
    var preview = document.getElementById('kmPreview');
    var printSheet = document.getElementById('kmPrintSheet');
    var printBtn = document.getElementById('kmPrint');
    var stickers = preview.querySelectorAll('.km-sticker');
    var MAX_PX = 60;       // bovengrens van de zoekruimte; de sticker zelf begrenst eerder
    var MIN_PX = 5;
    var LINE_HEIGHT = 1.1;  // zelfde als line-height in kabelmerkers.css
    var KLEINER_PX = 2 * 96 / 72;   // lettertype 2 pt kleiner dan het grootste dat past (1 pt = 96/72 px)
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
    var MAX_REGEL = 16;
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
        var size = Math.max(MIN_PX, lo * SAFETY - KLEINER_PX);
        textEl.style.fontSize = size + 'px';
        return size;
    }

    function refresh() {
        var typed = value();
        var size = 0;
        // Alleen de bovenste sticker toont de tekst; de andere twee blijven leeg (voorbeeld, later Excel-import).
        var textEl = stickers[0].querySelector('.km-tekst');
        textEl.textContent = typed;
        size = typed === '' ? 0 : fit(stickers[0], textEl);
        printBtn.disabled = typed.trim() === '';
        return size;
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
