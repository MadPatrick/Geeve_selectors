(function () {
    var tile = document.querySelector('a.tile[data-lock]');
    var modal = document.getElementById('lockModal');
    if (!tile || !modal) { return; }
    var form = document.getElementById('lockForm');
    var input = document.getElementById('lockCode');
    var error = document.getElementById('lockError');
    var cancel = document.getElementById('lockCancel');
    var busy = false;

    function open() {
        error.hidden = true;
        input.value = '';
        modal.hidden = false;
        setTimeout(function () { input.focus(); }, 30);
    }
    function close() { modal.hidden = true; tile.focus(); }

    // Een klik op de beveiligde tegel opent de popup in plaats van de pagina.
    tile.addEventListener('click', function (e) {
        e.preventDefault();
        open();
    });
    cancel.addEventListener('click', close);
    modal.addEventListener('click', function (e) { if (e.target === modal) { close(); } });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !modal.hidden) { close(); } });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (busy) { return; }
        busy = true;
        error.hidden = true;
        var body = new URLSearchParams();
        body.set('csrf', form.getAttribute('data-csrf'));
        body.set('scope', tile.getAttribute('data-lock'));
        body.set('code', input.value);
        fetch('unlock.php', { method: 'POST', body: body })
            .then(function (r) { return r.json().catch(function () { return { ok: false, error: 'Onverwacht antwoord van de server.' }; }); })
            .then(function (json) {
                if (json.ok) { window.location.href = tile.getAttribute('href'); return; }
                error.textContent = json.error || 'Onjuiste code.';
                error.hidden = false;
                input.value = '';
                input.focus();
            })
            .catch(function () { error.textContent = 'Geen verbinding met de server.'; error.hidden = false; })
            .then(function () { busy = false; });
    });
})();
