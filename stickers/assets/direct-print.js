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
    var cookieName = 'geeve_printer_' + box.getAttribute('data-tile');
    var browserPrint = window.print.bind(window);

    // De keuze onthouden we per gebruiker in een cookie (een jaar); de pagina zet hem de volgende keer voorgeselecteerd.
    select.addEventListener('change', function () {
        document.cookie = cookieName + '=' + encodeURIComponent(select.value === '' ? 'browser' : select.value) + '; path=/; max-age=31536000; SameSite=Lax';
        status.textContent = '';
    });

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
            body: JSON.stringify({ tile: box.getAttribute('data-tile'), printer: select.value, html: sheet.innerHTML, csrf: box.getAttribute('data-csrf') })
        }).then(function (r) { return r.json().catch(function () { return { ok: false, error: 'Onverwacht antwoord van de server.' }; }); })
            .then(function (d) { say(d.ok ? 'Verstuurd naar ' + d.printer + '.' : (d.error || 'Printen mislukt.'), !d.ok); })
            .catch(function () { say('Geen verbinding met de server.', true); });
    };
}());
