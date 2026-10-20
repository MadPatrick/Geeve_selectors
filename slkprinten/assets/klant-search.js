// Veld Klant (stap 1): suggesties terwijl je typt, uit de klanten (naam + klantnummer) met een slangregel in de afgelopen
// 2 jaar (index.php?klanten=1). Je kunt op naam en op klantnummer zoeken. Fuzzy, in 3 rondes - wat het best past bepaalt
// welke suggesties getoond worden (maximaal 12), de lijst zelf staat gesorteerd op klantnummer, laag naar hoog:
//  1. alle getypte woorden komen in het klantnummer of de naam voor (hoofdletters, spaties, punten, accenten tellen niet mee, volgorde vrij);
//  2. de getypte letters staan in die volgorde in de naam ("plfngr" -> Palfinger);
//  3. tikfouten: elk woord lijkt op het begin van een woord in de naam (1 fout, of 2 bij langere woorden).
// Een keuze vult de naam in en zoekt meteen; Enter zonder keuze zoekt op wat er getypt is, zoals voorheen (een getypt
// klantnummer wordt daarbij omgezet naar de naam van die klant).
(function () {
    var input = document.getElementById('klant');
    var list = document.getElementById('klantHits');
    if (!input || !list) { return; }
    var form = input.form;
    var klanten = null;   // [{naam, nr, key, tokens}] na het laden
    var hits = [];
    var active = -1;
    var MAX = 12;

    function norm(s) {
        return String(s).toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]/g, '');
    }
    function esc(s) {
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // Levenshtein-afstand, afgebroken zodra die groter dan max wordt.
    function distance(a, b, max) {
        if (Math.abs(a.length - b.length) > max) { return max + 1; }
        var prev = [], cur, i, j;
        for (j = 0; j <= b.length; j++) { prev[j] = j; }
        for (i = 1; i <= a.length; i++) {
            cur = [i];
            var rowMin = i;
            for (j = 1; j <= b.length; j++) {
                cur[j] = Math.min(prev[j] + 1, cur[j - 1] + 1, prev[j - 1] + (a[i - 1] === b[j - 1] ? 0 : 1));
                if (cur[j] < rowMin) { rowMin = cur[j]; }
            }
            if (rowMin > max) { return max + 1; }
            prev = cur;
        }
        return prev[b.length];
    }

    function isSubsequence(q, key) {
        var k = 0;
        for (var i = 0; i < q.length; i++) {
            k = key.indexOf(q[i], k);
            if (k === -1) { return false; }
            k++;
        }
        return true;
    }

    // Tikfout-tolerant: lijkt het woord op het begin (even lang) van een van de woorden in de naam?
    function closeToToken(w, tokens) {
        if (w.length < 3) { return false; }
        var max = w.length >= 7 ? 2 : 1;
        return tokens.some(function (t) {
            return distance(w, t.slice(0, w.length), max) <= max || distance(w, t, max) <= max;
        });
    }

    // Klantnummer van laag naar hoog (cijfers als getal); klanten zonder nummer achteraan, daarna op naam.
    function byNumber(a, b) {
        if (a.nr === '' || b.nr === '') { return (a.nr === '') - (b.nr === '') || a.naam.localeCompare(b.naam, 'nl'); }
        return a.nr.localeCompare(b.nr, 'nl', { numeric: true }) || a.naam.localeCompare(b.naam, 'nl');
    }

    function load() {
        if (klanten !== null) { return; }
        klanten = [];
        fetch(input.getAttribute('data-klanten'), { credentials: 'same-origin', cache: 'no-store' })
            .then(function (res) { return res.json(); })
            .then(function (json) {
                if (!json.ok) { return; }
                klanten = json.klanten.map(function (k) {
                    var naam = k.naam, nr = k.nr || '';
                    return { naam: naam, nr: nr, nrKey: norm(nr), key: norm(nr + ' ' + naam), tokens: (nr + ' ' + naam).split(/[\s.,\-\/&()]+/).map(norm).filter(Boolean) };
                });
                if (document.activeElement === input) { update(); }
            })
            .catch(function () { /* geen suggesties; gewoon een naam intypen blijft werken */ });
    }

    function close() { list.hidden = true; active = -1; input.setAttribute('aria-expanded', 'false'); }

    function render() {
        list.innerHTML = hits.map(function (k, i) {
            return '<li role="option" data-i="' + i + '"' + (i === active ? ' class="is-active" aria-selected="true"' : '') + '>' +
                (k.nr ? '<span class="klant-nr">' + esc(k.nr) + '</span> ' : '') + esc(k.naam) + '</li>';
        }).join('');
        list.hidden = hits.length === 0;
        input.setAttribute('aria-expanded', hits.length ? 'true' : 'false');
    }

    function update() {
        var words = input.value.split(/\s+/).map(norm).filter(Boolean);
        var q = norm(input.value);
        if (!klanten || q.length < 2) { hits = []; close(); return; }

        var scored = [];
        klanten.forEach(function (k) {
            var score = -1;
            if (words.every(function (w) { return k.key.indexOf(w) !== -1; })) {
                score = (k.key.indexOf(q) === 0 || k.nrKey === q) ? 0 : (k.tokens.some(function (t) { return t.indexOf(words[0]) === 0; }) ? 1 : 2);
            } else if (isSubsequence(q, k.key)) {
                score = 3;
            } else if (words.every(function (w) { return k.key.indexOf(w) !== -1 || closeToToken(w, k.tokens); })) {
                score = 4;
            }
            if (score >= 0) { scored.push({ k: k, score: score }); }
        });
        scored.sort(function (a, b) { return a.score - b.score || byNumber(a.k, b.k); });
        hits = scored.slice(0, MAX).map(function (s) { return s.k; }).sort(byNumber);
        active = -1;
        render();
    }

    function pick(i) {
        if (!hits[i]) { return; }
        input.value = hits[i].naam;
        close();
        // Zelfde als zelf op Zoeken klikken (laat de knop ook "Bezig met zoeken…" tonen).
        if (form.requestSubmit) { form.requestSubmit(); } else { form.submit(); }
    }

    // Een getypt klantnummer (bijv. 10234) zoekt op de naam van die klant, want de orders worden op naam gezocht.
    form.addEventListener('submit', function () {
        var typed = input.value.trim();
        if (klanten && /^\d+$/.test(typed)) {
            var match = klanten.filter(function (k) { return k.nr === typed; })[0];
            if (match) { input.value = match.naam; }
        }
    });
    input.addEventListener('focus', load);
    input.addEventListener('input', update);
    input.addEventListener('keydown', function (e) {
        if (list.hidden) { return; }
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            active = (active + (e.key === 'ArrowDown' ? 1 : -1) + hits.length) % hits.length;
            render();
        } else if (e.key === 'Enter' && active >= 0) {
            e.preventDefault();
            pick(active);
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
