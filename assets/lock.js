(function () {
    var tiles = Array.prototype.slice.call(document.querySelectorAll('a.tile[data-lock]'));
    var modal = document.getElementById('lockModal');
    if (!modal) { return; }
    var form = document.getElementById('lockForm');
    var input = document.getElementById('lockCode');
    var error = document.getElementById('lockError');
    var cancel = document.getElementById('lockCancel');
    var current = null;   // de tegel waarvoor de code wordt gevraagd
    var busy = false;

    function open(tile) {
        current = tile;
        error.hidden = true;
        input.value = '';
        modal.hidden = false;
        setTimeout(function () { input.focus(); }, 30);
    }
    function close() { modal.hidden = true; if (current) { current.focus(); } }

    // Een klik op een beveiligde tegel opent de popup in plaats van de pagina.
    tiles.forEach(function (tile) {
        tile.addEventListener('click', function (e) {
            e.preventDefault();
            open(tile);
        });
    });
    cancel.addEventListener('click', close);
    modal.addEventListener('click', function (e) { if (e.target === modal) { close(); } });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !modal.hidden) { close(); } });

    // Doorverwijzing vanaf een beveiligde pagina (?unlock=<tegel>): meteen om de code vragen.
    var wanted = new URLSearchParams(window.location.search).get('unlock');
    if (wanted) {
        var match = tiles.filter(function (t) { return t.getAttribute('data-lock') === wanted; })[0];
        if (match) { open(match); }
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (busy || !current) { return; }
        busy = true;
        error.hidden = true;
        var body = new URLSearchParams();
        body.set('csrf', form.getAttribute('data-csrf'));
        body.set('scope', current.getAttribute('data-lock'));
        body.set('code', input.value);
        fetch(form.getAttribute('data-unlock-url'), { method: 'POST', body: body })
            .then(function (r) { return r.json().catch(function () { return { ok: false, error: 'Onverwacht antwoord van de server.' }; }); })
            .then(function (json) {
                if (json.ok) { window.location.href = current.getAttribute('href'); return; }
                error.textContent = json.error || 'Onjuiste code.';
                error.hidden = false;
                input.value = '';
                input.focus();
            })
            .catch(function () { error.textContent = 'Geen verbinding met de server.'; error.hidden = false; })
            .then(function () { busy = false; });
    });
})();
