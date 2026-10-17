// Veld Prijslijst (Klant Artikelnummers en Kortingstructuur): zoeken op nummer of omschrijving van de prijslijst (stfoms;
// welke prijslijsten bepaalt het endpoint in data-lists), met suggesties terwijl je
// typt. Fuzzy: hoofdletters, spaties, punten, koppeltekens en accenten tellen niet mee en de woorden mogen in elke volgorde.
// Een keuze vult het nummer in en zoekt meteen. Een getypt nummer met Enter werkt zoals voorheen.
(function () {
    var input = document.getElementById('plSearch');
    var list = document.getElementById('plSearchHits');
    if (!input || !list) { return; }
    var form = input.form;
    var lists = null;   // [{nr, oms, key}] na het laden
    var hits = [];
    var active = -1;
    var MAX = 12;

    function norm(s) {
        return String(s).toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]/g, '');
    }
    function esc(s) {
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function load() {
        if (lists !== null) { return; }
        lists = [];
        fetch(input.getAttribute('data-lists'), { credentials: 'same-origin' })
            .then(function (res) { return res.json(); })
            .then(function (json) {
                if (!json.ok) { return; }
                lists = json.lists.map(function (l) { return { nr: l.nr, oms: l.oms, key: norm(l.nr + ' ' + l.oms) }; });
                if (document.activeElement === input) { update(); }
            })
            .catch(function () { /* geen suggesties; gewoon een nummer intypen blijft werken */ });
    }

    function close() { list.hidden = true; active = -1; input.setAttribute('aria-expanded', 'false'); }

    function render() {
        list.innerHTML = hits.map(function (l, i) {
            return '<li role="option" data-i="' + i + '"' + (i === active ? ' class="is-active" aria-selected="true"' : '') + '>' +
                '<strong>' + esc(l.nr) + '</strong> ' + esc(l.oms) + '</li>';
        }).join('');
        list.hidden = hits.length === 0;
        input.setAttribute('aria-expanded', hits.length ? 'true' : 'false');
    }

    function update() {
        var words = input.value.split(/\s+/).map(norm).filter(Boolean);
        if (!lists || words.length === 0) { hits = []; close(); return; }
        // Nummer dat precies klopt of ermee begint eerst, daarna de rest op volgorde.
        var q = norm(input.value);
        hits = lists.filter(function (l) { return words.every(function (w) { return l.key.indexOf(w) !== -1; }); })
            .sort(function (a, b) { return (b.nr === q) - (a.nr === q) || (b.nr.indexOf(q) === 0) - (a.nr.indexOf(q) === 0); })
            .slice(0, MAX);
        active = -1;
        render();
    }

    function pick(i) {
        if (!hits[i]) { return; }
        input.value = hits[i].nr;
        close();
        form.submit();
    }

    input.addEventListener('focus', function () { load(); });
    input.addEventListener('input', update);
    input.addEventListener('keydown', function (e) {
        if (list.hidden) { return; }
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            active = (active + (e.key === 'ArrowDown' ? 1 : -1) + hits.length) % hits.length;
            render();
        } else if (e.key === 'Enter' && (active >= 0 || !/^\d+$/.test(input.value.trim()))) {
            // Gekozen suggestie, of een naam getypt (geen nummer): neem de (eerste) suggestie.
            e.preventDefault();
            pick(active >= 0 ? active : 0);
        } else if (e.key === 'Escape') {
            close();
        }
    });
    // mousedown i.p.v. click: gebeurt vóór de blur van het veld.
    list.addEventListener('mousedown', function (e) {
        var li = e.target.closest('li[data-i]');
        if (li) { e.preventDefault(); pick(Number(li.getAttribute('data-i'))); }
    });
    input.addEventListener('blur', close);
    if (document.activeElement === input) { load(); }
}());
