(function () {
    var sticker = document.getElementById('ptSticker');
    var text = document.getElementById('ptText');
    var printSheet = document.getElementById('ptPrintSheet');
    var printBtn = document.getElementById('ptPrint');
    var aantal = document.getElementById('ptAantal');

    var align = 'center';   // horizontaal: left, center of right
    var valign = 'center';  // verticaal: flex-start (boven), center of flex-end (onder)
    var alignBox = document.getElementById('ptAlign');
    var valignBox = document.getElementById('ptValign');
    var MAX_PX = 400;  // bovengrens van de zoekruimte; de sticker zelf begrenst eerder
    var MIN_PX = 6;    // kleiner dan dit schalen we niet; wat dan nog niet past wordt afgeknipt
    var LINE_HEIGHT = 1.15; // zelfde als line-height in ptouch.css
    var SAFETY = 0.97; // 3% marge tegen een iets breder lettertype op de printserver

    // Aantal stickers om te printen: 1 t/m 99, een ongeldige invoer telt als 1.
    function count() {
        var n = parseInt(aantal.value, 10);
        return isNaN(n) || n < 1 ? 1 : Math.min(n, 99);
    }

    function esc(s) { return GeeveEsc(s); }

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
    function boxHeight() {
        var cs = getComputedStyle(sticker);
        return sticker.clientHeight - parseFloat(cs.paddingTop) - parseFloat(cs.paddingBottom);
    }
    function fits(textEl, size, slots) {
        textEl.style.fontSize = size + 'px';
        return slots * size * LINE_HEIGHT <= boxHeight() + 1 && (lineCount() === 0 || textEl.scrollWidth <= textEl.clientWidth + 1); // leeg: de aanwijzing telt niet mee voor de breedte
    }
    // Eigen lettergrootte (pt, veld "Lettergrootte"): leeg of ongeldig = automatisch passend. 1 pt = 96/72 px.
    var fontBox = document.getElementById('ptFont');
    function customPx() {
        var pt = parseFloat(String(fontBox.value).replace(',', '.'));
        return isNaN(pt) || pt < 4 || pt > 200 ? 0 : pt * 96 / 72;
    }
    function fit(textEl) {
        var eigen = customPx();
        if (eigen > 0) { textEl.style.fontSize = eigen + 'px'; return eigen; }
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
    fontBox.addEventListener('input', refresh);
    document.getElementById('ptFontAuto').addEventListener('click', function () { fontBox.value = ''; refresh(); text.focus(); });
    // Klik op de rand van de sticker zet de cursor in het tekstvak.
    sticker.addEventListener('click', function (e) { if (e.target === sticker) { text.focus(); } });

    // Uitlijnen: links, midden of rechts; geldt voor de hele tekst en gaat mee naar de print. De keuze onthouden we per gebruiker
    // in een cookie (een jaar), net als de printerkeuze.
    var ALIGN_COOKIE = 'geeve_ptouch_uitlijnen';
    function setAlign(value) {
        if (value !== 'left' && value !== 'center' && value !== 'right') { value = 'center'; }
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
    setAlign(savedAlign ? decodeURIComponent(savedAlign[1]) : 'center');

    // Verticaal uitlijnen: boven, midden of onder; zelfde werking en cookie-onthouden als horizontaal.
    var VALIGN_COOKIE = 'geeve_ptouch_uitlijnen_verticaal';
    function setValign(value) {
        if (value !== 'flex-start' && value !== 'center' && value !== 'flex-end') { value = 'center'; }
        valign = value;
        Array.prototype.forEach.call(valignBox.querySelectorAll('button'), function (b) { b.classList.toggle('is-active', b.getAttribute('data-valign') === value); });
        sticker.style.alignItems = value;
    }
    valignBox.addEventListener('click', function (e) {
        var btn = e.target.closest('button[data-valign]');
        if (!btn) { return; }
        setValign(btn.getAttribute('data-valign'));
        document.cookie = VALIGN_COOKIE + '=' + valign + '; path=/; max-age=31536000; SameSite=Lax';
        text.focus();
    });
    var savedValign = document.cookie.match(new RegExp('(?:^|; )' + VALIGN_COOKIE + '=([^;]*)'));
    setValign(savedValign ? decodeURIComponent(savedValign[1]) : 'center');

    // Formaat: Groot (102 x 51 mm) of Klein (70 x 25 mm). De maat wordt per gebruiker onthouden (cookie, een jaar). Het voorbeeld
    // krijgt de klasse pt-sticker--klein; voor printen via het browservenster zetten we ook een ongenoemde @page met die maat
    // (browsers zonder ondersteuning voor de page-eigenschap); bij direct printen zorgt de benoemde @page in ptouch.css ervoor.
    var MAAT_COOKIE = 'geeve_ptouch_formaat';
    var maat = 'groot';
    var maatBox = document.getElementById('ptMaat');
    var paginaStijl = document.createElement('style');
    document.head.appendChild(paginaStijl);
    function setMaat(value) {
        maat = value === 'klein' ? 'klein' : 'groot';
        Array.prototype.forEach.call(maatBox.querySelectorAll('button'), function (b) { b.classList.toggle('is-active', b.getAttribute('data-maat') === maat); });
        sticker.classList.toggle('pt-sticker--klein', maat === 'klein');
        paginaStijl.textContent = '@page { size: ' + (maat === 'klein' ? '70mm 25mm' : '102mm 51mm') + '; margin: 0; }';
        refresh();
    }
    maatBox.addEventListener('click', function (e) {
        var btn = e.target.closest('button[data-maat]');
        if (!btn) { return; }
        setMaat(btn.getAttribute('data-maat'));
        document.cookie = MAAT_COOKIE + '=' + maat + '; path=/; max-age=31536000; SameSite=Lax';
        text.focus();
    });

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
        var one = '<div class="pt-sticker' + (maat === 'klein' ? ' pt-sticker--klein' : '') + '" style="align-items:' + valign + '"><div class="pt-text" style="font-size:' + size + 'px;text-align:' + align + '">' + esc(typed) + '</div></div>';
        var html = '';
        for (var i = 0; i < count(); i++) { html += one; }
        printSheet.innerHTML = html;
        window.print();
    });

    var savedMaat = document.cookie.match(new RegExp('(?:^|; )' + MAAT_COOKIE + '=([^;]*)'));
    setMaat(savedMaat ? decodeURIComponent(savedMaat[1]) : 'groot');
    text.focus();
}());
