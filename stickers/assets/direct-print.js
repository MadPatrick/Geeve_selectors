/*
 * Direct printen vanuit een tegel met een printknop. De pagina toont (via geevePrinterSelectHtml()) een keuzelijst
 * "Printer". Kies je een printer (standaard de eerste of je vorige keuze), dan stuurt window.print() het afdrukblok naar de server (shared/print-api.php) in plaats
 * van het printvenster van de browser te openen; zonder keuze werkt alles zoals voorheen.
 */
(function () {
    var box = document.getElementById('directPrint');
    var select = document.getElementById('directPrinter');
    if (!box || !select) { return; }
    var status = document.getElementById('directPrintStatus');
    var browserPrint = window.print.bind(window);
    var sortToggle = document.getElementById('soSort'); // alleen "Artikelen per Verkooporder"

    // Artikelen per Verkooporder: de Artikelsticker onthoudt een eigen printer, Orderregel en Looplocatie delen er een.
    function group() {
        var active = sortToggle ? sortToggle.querySelector('button.is-active') : null;
        return active && active.getAttribute('data-sort') === 'artikel' ? 'artikel' : '';
    }
    function cookieName() { return 'geeve_printer_' + box.getAttribute('data-tile') + (group() === '' ? '' : '_' + group()); }
    function savedPrinter() {
        var match = document.cookie.match(new RegExp('(?:^|; )' + cookieName() + '=([^;]*)'));
        return match ? decodeURIComponent(match[1]) : null;
    }
    function selectSaved() {
        var saved = savedPrinter();
        if (saved === null) { return; }
        var value = saved === 'browser' ? '' : saved;
        for (var i = 0; i < select.options.length; i++) {
            if (select.options[i].value === value) { select.value = value; return; }
        }
    }

    // De keuze onthouden we per gebruiker in een cookie (een jaar); de pagina zet hem de volgende keer voorgeselecteerd.
    select.addEventListener('change', function () {
        document.cookie = cookieName() + '=' + encodeURIComponent(select.value === '' ? 'browser' : select.value) + '; path=/; max-age=31536000; SameSite=Lax';
        status.textContent = '';
    });
    // Wisselen tussen de stickersoorten: de printer die daar het laatst bij gekozen was weer voorselecteren.
    if (sortToggle) {
        sortToggle.addEventListener('click', function () { setTimeout(selectSaved, 0); });
    }

    function say(text, error) {
        status.textContent = text;
        status.className = 'direct-print-status' + (error ? ' is-error' : '');
    }

    window.print = function () {
        if (select.value === '') { browserPrint(); return; }
        var sheet = document.getElementById(box.getAttribute('data-sheet'));
        if (!sheet || sheet.innerHTML.trim() === '') { say('Er is niets om te printen.', true); return; }
        say('Bezig met printen...', false);
        fetch(box.getAttribute('data-endpoint'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ tile: box.getAttribute('data-tile'), printer: select.value, groep: group(), html: sheet.innerHTML, csrf: box.getAttribute('data-csrf') })
        }).then(function (r) { return r.json().catch(function () { return { ok: false, error: 'Onverwacht antwoord van de server.' }; }); })
            .then(function (d) { say(d.ok ? 'Verstuurd naar ' + d.printer + '.' : (d.error || 'Printen mislukt.'), !d.ok); })
            .catch(function () { say('Geen verbinding met de server.', true); });
    };
}());
