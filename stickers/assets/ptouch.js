(function () {
    var sticker = document.getElementById('ptSticker');
    var text = document.getElementById('ptText');
    var printSheet = document.getElementById('ptPrintSheet');
    var copies = document.getElementById('ptCopies');
    var printBtn = document.getElementById('ptPrint');

    var START_PX = 40; // standaard lettergrootte
    var MIN_PX = 6;    // kleiner dan dit schalen we niet; wat dan nog niet past wordt afgeknipt
    var STEP_PX = 0.5;

    function esc(s) {
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // Tekst zoals getypt, zonder de afsluitende nieuwe regel die de browser soms toevoegt.
    function value() {
        return text.innerText.replace(/\r/g, '').replace(/\n+$/, '');
    }

    // Begint op 40 px en zet de letter in stapjes kleiner tot alles op de sticker past (.pt-text heeft max-height 100%,
    // dus scrollHeight > clientHeight betekent: de tekst is hoger dan het vak). Geeft de gekozen grootte (px).
    function fit(textEl) {
        var size = START_PX;
        textEl.style.fontSize = size + 'px';
        while (size > MIN_PX && (textEl.scrollHeight > textEl.clientHeight + 1 || textEl.scrollWidth > textEl.clientWidth + 1)) {
            size -= STEP_PX;
            textEl.style.fontSize = size + 'px';
        }
        return size;
    }

    function refresh() {
        // Een leeg veld laat de browser soms een <br> achter; weghalen zodat de aanwijzing weer verschijnt.
        if (value().trim() === '' && text.innerHTML !== '') { text.innerHTML = ''; }
        fit(text);
        printBtn.disabled = value().trim() === '';
    }

    function times() {
        var n = parseInt(copies.value, 10);
        return isNaN(n) || n < 1 ? 1 : Math.min(n, 99);
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

    document.getElementById('ptClear').addEventListener('click', function () {
        text.innerHTML = '';
        copies.value = 1;
        refresh();
        text.focus();
    });

    printBtn.addEventListener('click', function () {
        var typed = value();
        if (typed.trim() === '') { return; }
        // De lettergrootte van het voorbeeld gaat mee in de print, zodat de printversie dezelfde regelafbreking heeft.
        var size = fit(text);
        var one = '<div class="pt-sticker"><div class="pt-text" style="font-size:' + size + 'px">' + esc(typed) + '</div></div>';
        var all = '';
        for (var n = 0; n < times(); n++) { all += one; }
        printSheet.innerHTML = all;
        window.print();
    });

    refresh();
    text.focus();
}());
