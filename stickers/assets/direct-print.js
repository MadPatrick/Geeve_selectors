/*
 * Direct printen vanuit een tegel met een printknop. De pagina toont (via geevePrinterSelectHtml()) een keuzelijst
 * "Printer". Kies je een printer, dan stuurt window.print() het afdrukblok naar de server (shared/print-api.php) in plaats
 * van het printvenster van de browser te openen; zonder keuze werkt alles zoals voorheen.
 */
(function () {
    var box = document.getElementById('directPrint');
    var select = document.getElementById('directPrinter');
    if (!box || !select) { return; }
    var status = document.getElementById('directPrintStatus');
    var storeKey = 'directPrinter.' + box.getAttribute('data-tile');
    var browserPrint = window.print.bind(window);

    try {
        var saved = window.localStorage.getItem(storeKey);
        if (saved && Array.prototype.some.call(select.options, function (o) { return o.value === saved; })) { select.value = saved; }
    } catch (e) { /* localStorage kan ontbreken */ }
    select.addEventListener('change', function () {
        try { window.localStorage.setItem(storeKey, select.value); } catch (e) { /* idem */ }
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
