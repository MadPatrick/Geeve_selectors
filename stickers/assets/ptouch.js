(function () {
    var sticker = document.getElementById('ptSticker');
    var text = document.getElementById('ptText');
    var printSheet = document.getElementById('ptPrintSheet');
    var printBtn = document.getElementById('ptPrint');

    var align = 'left';
    var alignBox = document.getElementById('ptAlign');
    var MAX_PX = 400;  // bovengrens van de zoekruimte; de sticker zelf begrenst eerder
    var MIN_PX = 6;    // kleiner dan dit schalen we niet; wat dan nog niet past wordt afgeknipt
    var LINE_HEIGHT = 1.15; // zelfde als line-height in ptouch.css
    var SAFETY = 0.97; // 3% marge tegen een iets breder lettertype op de printserver

    function esc(s) {
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // Tekst zoals getypt, zonder de afsluitende nieuwe regel die de browser soms toevoegt.
    function value() {
        return text.innerText.replace(/\r/g, '').replace(/\n+$/, '');
    }

    // Aantal regels zoals ze op de sticker staan, inclusief een lege regel waar de cursor staat. Na Enter aan het eind zet de
    // browser twee nieuwe-regeltekens ("Jan\n\n" is twee regels), daarom telt het laatste teken dan niet mee.
    function lineCount() {
        var raw = text.innerText.replace(/\r/g, '');
        if (raw === '') { return 0; }
        if (raw.charAt(raw.length - 1) === '\n') { raw = raw.slice(0, -1); }
        return raw.split('\n').length;
    }

    // Zoekt de grootste letter waarbij de tekst op de sticker past, gerekend met de regels + één lege regel eronder (minimaal
    // 2 regels hoog): een lege sticker of één regel begint dus met twee regels groot, Enter maakt er drie. De tekst staat
    // bovenaan. Regels breken niet af (elke getypte regel blijft één regel); scrollWidth > clientWidth betekent: te breed.
    // Geeft de gekozen grootte (px) terug, met een kleine marge omdat het lettertype op de printserver iets kan afwijken.
    function fits(textEl, size, slots) {
        textEl.style.fontSize = size + 'px';
        return slots * size * LINE_HEIGHT <= textEl.clientHeight + 1 && (lineCount() === 0 || textEl.scrollWidth <= textEl.clientWidth + 1); // leeg: de aanwijzing telt niet mee voor de breedte
    }
    function fit(textEl) {
        var slots = Math.max(2, lineCount() + 1);
        var lo = MIN_PX, hi = MAX_PX;
        if (fits(textEl, hi, slots)) { lo = hi; } else {
            while (hi - lo > 0.25) {
                var mid = (lo + hi) / 2;
                if (fits(textEl, mid, slots)) { lo = mid; } else { hi = mid; }
            }
        }
        var size = Math.max(MIN_PX, lo * SAFETY);
        textEl.style.fontSize = size + 'px';
        return size;
    }

    function refresh() {
        // Een leeg veld laat de browser soms een <br> achter; weghalen zodat de aanwijzing weer verschijnt.
        if (value().trim() === '' && text.innerHTML !== '') { text.innerHTML = ''; }
        fit(text);
        printBtn.disabled = value().trim() === '';
    }

    // Plak altijd als platte tekst (geen opmaak van Word of een webpagina op de sticker).
    text.addEventListener('paste', function (e) {
        e.preventDefault();
        var plain = (e.clipboardData || window.clipboardData).getData('text');
        document.execCommand('insertText', false, plain.replace(/\r/g, ''));
    });
    text.addEventListener('input', refresh);
    // Klik op de rand van de sticker zet de cursor in het tekstvak.
    sticker.addEventListener('click', function (e) { if (e.target === sticker) { text.focus(); } });

    // Uitlijnen: links, midden of rechts; geldt voor de hele tekst en gaat mee naar de print. De keuze onthouden we per gebruiker
    // in een cookie (een jaar), net als de printerkeuze.
    var ALIGN_COOKIE = 'geeve_ptouch_uitlijnen';
    function setAlign(value) {
        if (value !== 'left' && value !== 'center' && value !== 'right') { value = 'left'; }
        align = value;
        Array.prototype.forEach.call(alignBox.querySelectorAll('button'), function (b) { b.classList.toggle('is-active', b.getAttribute('data-align') === value); });
        text.style.textAlign = value;
    }
    alignBox.addEventListener('click', function (e) {
        var btn = e.target.closest('button[data-align]');
        if (!btn) { return; }
        setAlign(btn.getAttribute('data-align'));
        document.cookie = ALIGN_COOKIE + '=' + align + '; path=/; max-age=31536000; SameSite=Lax';
        text.focus();
    });
    var savedAlign = document.cookie.match(new RegExp('(?:^|; )' + ALIGN_COOKIE + '=([^;]*)'));
    setAlign(savedAlign ? decodeURIComponent(savedAlign[1]) : 'left');

    document.getElementById('ptClear').addEventListener('click', function () {
        text.innerHTML = '';
        refresh();
        text.focus();
    });

    printBtn.addEventListener('click', function () {
        var typed = value();
        if (typed.trim() === '') { return; }
        // De lettergrootte van het voorbeeld gaat mee in de print, zodat de printversie dezelfde regelafbreking heeft.
        var size = fit(text);
        var one = '<div class="pt-sticker"><div class="pt-text" style="font-size:' + size + 'px;text-align:' + align + '">' + esc(typed) + '</div></div>';
        printSheet.innerHTML = one;
        window.print();
    });

    refresh();
    text.focus();
}());
