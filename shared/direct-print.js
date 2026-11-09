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
    var maatToggle = document.getElementById('ptMaat'); // alleen Naamstickers: Sticker Groot / Sticker Klein

    // Artikelen per Verkooporder: de Artikelsticker onthoudt een eigen printer, Orderregel en Looplocatie delen er een.
    function group() {
        var active = sortToggle ? sortToggle.querySelector('button.is-active') : null;
        if (active && active.getAttribute('data-sort') === 'artikel') { return 'artikel'; }
        // Naamstickers: Sticker Klein onthoudt een eigen printer, Sticker Groot gebruikt de gewone keuze van de tegel.
        var maat = maatToggle ? maatToggle.querySelector('button.is-active') : null;
        return maat && maat.getAttribute('data-maat') === 'klein' ? 'klein' : '';
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
    if (maatToggle) {
        // Het formaat staat bij het laden al goed (ptouch.js laadt eerst): meteen de printer van dat formaat voorselecteren.
        maatToggle.addEventListener('click', function () { setTimeout(function () { selectSaved(); toonInstellingen(); }, 0); });
        if (group() !== '') { selectSaved(); }
    }

    // Snelheid en zwarting (alleen op tegels die ze tonen): per soort printer een eigen set velden; alleen de set die bij de
    // gekozen printer hoort is zichtbaar. Per browser onthouden, leeg = standaard van de printer.
    var sets = document.querySelectorAll('#directPrintAfdruk .direct-print-instelling');
    Array.prototype.forEach.call(sets, function (set) {
        Array.prototype.forEach.call(set.querySelectorAll('select'), function (veld) {
            var sleutel = 'geeve_' + veld.id + '_' + box.getAttribute('data-tile');
            try {
                var bewaard = window.localStorage.getItem(sleutel);
                if (bewaard !== null) { veld.value = bewaard; } // anders blijft de voorgeselecteerde keuze staan
            } catch (e) { /* opslag niet beschikbaar */ }
            // Staat de standaardoptie (vet) gekozen, dan toont ook het gesloten veld de waarde vet.
            function vetMarkering() {
                var gekozen = veld.options[veld.selectedIndex];
                veld.style.fontWeight = gekozen && gekozen.className === 'is-standaard' ? 'bold' : '';
            }
            vetMarkering();
            veld.addEventListener('change', function () {
                vetMarkering();
                try { window.localStorage.setItem(sleutel, veld.value); } catch (e) { /* opslag niet beschikbaar */ }
            });
        });
    });
    function toonInstellingen() {
        var gekozen = select.options[select.selectedIndex];
        var taal = gekozen ? gekozen.getAttribute('data-taal') : '';
        Array.prototype.forEach.call(sets, function (set) {
            if (set.getAttribute('data-taal') === taal && select.value !== '') { set.removeAttribute('hidden'); } else { set.setAttribute('hidden', ''); }
        });
        pasSnelheidAan(gekozen, taal);
    }
    // Snelheid volgens het gekozen printermodel (data-snelheid-* op de printeroptie, zie geevePrinterOptionsHtml()): waarden
    // buiten het bereik verbergen, een bewaarde hogere keuze terugbrengen tot het maximum, en bij "Standaard" de echte
    // standaard tonen (bijv. "3 (standaard)" bij de Altec TTP-343 Plus).
    function pasSnelheidAan(printer, taal) {
        var veld = document.getElementById('directSnelheid-' + taal);
        if (!veld || !printer || !printer.hasAttribute('data-snelheid-max')) { return; }
        var min = parseInt(printer.getAttribute('data-snelheid-min'), 10);
        var max = parseInt(printer.getAttribute('data-snelheid-max'), 10);
        var standaard = printer.getAttribute('data-snelheid-standaard') || '';
        var leeg = veld.options[0];
        if (!leeg.hasAttribute('data-tekst')) {
            leeg.setAttribute('data-tekst', leeg.textContent);
            leeg.setAttribute('data-klasse', leeg.className);
        }
        if (standaard !== '') {
            leeg.textContent = standaard + ' (standaard)';
            leeg.className = 'is-standaard';
        } else {
            leeg.textContent = leeg.getAttribute('data-tekst');
            leeg.className = leeg.getAttribute('data-klasse');
        }
        leeg.style.fontWeight = leeg.className === 'is-standaard' ? 'bold' : '';
        for (var i = 1; i < veld.options.length; i++) {
            var waarde = parseInt(veld.options[i].value, 10);
            var buiten = waarde < min || waarde > max;
            veld.options[i].hidden = buiten;
            veld.options[i].disabled = buiten;
        }
        var bewaard = null;
        try { bewaard = window.localStorage.getItem('geeve_' + veld.id + '_' + box.getAttribute('data-tile')); } catch (e) { /* opslag niet beschikbaar */ }
        if (bewaard !== null) { veld.value = bewaard; }
        if (veld.value !== '') {
            var gekozenWaarde = parseInt(veld.value, 10);
            if (gekozenWaarde > max) { veld.value = String(max); } else if (gekozenWaarde < min) { veld.value = String(min); }
        }
        var optie = veld.options[veld.selectedIndex];
        veld.style.fontWeight = optie && optie.className === 'is-standaard' ? 'bold' : '';
    }
    function instelling(naam) {
        for (var i = 0; i < sets.length; i++) {
            if (!sets[i].hasAttribute('hidden')) { return sets[i].querySelector('select[id^="direct' + naam + '"]').value; }
        }
        return '';
    }
    select.addEventListener('change', toonInstellingen);
    if (sortToggle) { sortToggle.addEventListener('click', function () { setTimeout(toonInstellingen, 0); }); }
    toonInstellingen();

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
            body: JSON.stringify({ tile: box.getAttribute('data-tile'), printer: select.value, groep: group(), html: sheet.innerHTML, csrf: box.getAttribute('data-csrf'),
                snelheid: instelling('Snelheid'), zwarting: instelling('Zwarting') })
        }).then(function (r) { return r.json().catch(function () { return { ok: false, error: 'Onverwacht antwoord van de server.' }; }); })
            .then(function (d) { say(d.ok ? 'Verstuurd naar ' + d.printer + '.' : (d.error || 'Printen mislukt.'), !d.ok); })
            .catch(function () { say('Geen verbinding met de server.', true); });
    };
}());
