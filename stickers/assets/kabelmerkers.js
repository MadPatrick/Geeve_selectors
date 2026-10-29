(function () {
    var input = document.getElementById('kmText');
    var aantal = document.getElementById('kmAantal');
    var preview = document.getElementById('kmPreview');
    var printSheet = document.getElementById('kmPrintSheet');
    var printBtn = document.getElementById('kmPrint');
    var stickers = preview.querySelectorAll('.km-sticker');
    var MAX_PX = 120;       // bovengrens van de zoekruimte; de sticker zelf begrenst eerder
    var MIN_PX = 5;
    var LINE_HEIGHT = 1.1;  // zelfde als line-height in kabelmerkers.css
    var SAFETY = 0.97;      // 3% marge tegen een iets breder lettertype op de printserver

    function esc(s) {
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // Maximaal twee regels; lege regels aan het eind weg.
    function value() {
        var lines = input.value.replace(/\r/g, '').replace(/\n+$/, '').split('\n');
        return lines.slice(0, 2).join('\n');
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

    input.addEventListener('input', refresh);
    aantal.addEventListener('input', function () { /* alleen bij het printen van belang */ });

    document.getElementById('kmClear').addEventListener('click', function () {
        input.value = '';
        refresh();
        input.focus();
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
    input.focus();
}());
