// "Klanten op prijslijst" (Klant Artikelnummers en Kortingstructuur): zoekveld dat de klant-chips filtert op naam en
// debiteurnummer. Fuzzy: hoofdletters, spaties, punten, koppeltekens e.d. tellen niet mee, en elk zoekwoord moet ergens
// voorkomen (in willekeurige volgorde), dus "breko rep" vindt "BREKO REPARATIE B.V." en "bv breko" ook.
(function () {
    function norm(s) {
        return String(s).toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]/g, '');
    }

    Array.prototype.forEach.call(document.querySelectorAll('input[data-chip-filter]'), function (input) {
        var box = document.getElementById(input.getAttribute('data-chip-filter'));
        var count = document.getElementById(input.getAttribute('data-chip-count'));
        if (!box) { return; }
        var chips = Array.prototype.map.call(box.querySelectorAll('.ka-chip'), function (chip) {
            return { el: chip, text: norm(chip.textContent) };
        });

        input.addEventListener('input', function () {
            var words = input.value.split(/\s+/).map(norm).filter(Boolean);
            var shown = 0;
            chips.forEach(function (c) {
                var hit = words.every(function (w) { return c.text.indexOf(w) !== -1; });
                c.el.hidden = !hit;
                if (hit) { shown++; }
            });
            if (count) { count.textContent = '(' + (words.length ? shown + ' van ' + chips.length : chips.length) + ')'; }
        });
    });
}());
