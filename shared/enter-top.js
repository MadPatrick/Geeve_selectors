/*
 * Gedeelde Enter-regel voor zoekvelden met een keuzelijst (GeeveEnterTop): Enter neemt de gemarkeerde regel, of anders
 * de bovenste uit de lijst, en het veld handelt de keuze af (vult in, zoekt, gaat naar het volgende veld).
 * Geladen door shared/head.php. Bewust ES5 (geen pijlfuncties) zodat het overal werkt waar de rest van de portal werkt.
 *
 * Gebruik in de keydown-afhandeling van het zoekveld (voor de eigen Enter-regel):
 *   if (GeeveEnterTop.handle(e, {
 *       isOpen: function () { return !list.hidden; },   // is de lijst zichtbaar?
 *       count: function () { return hits.length; },     // aantal resultaten
 *       active: function () { return active; },         // gemarkeerde regel (-1 = geen)
 *       pick: function (i) { ... },                     // kies resultaat i
 *       search: function () { return promise; },        // optioneel: lijst nog niet getoond -> nu zoeken (vult hits)
 *       skip: function () { return false; }             // optioneel: true = Enter laten voor wat het veld al deed
 *   })) { return; }
 */
(function (global) {
    'use strict';

    function handle(e, o) {
        if (!e || e.key !== 'Enter' || !o) { return false; }
        if (o.skip && o.skip()) { return false; }
        if (o.isOpen() && o.count() > 0) {
            e.preventDefault();
            o.pick(o.active() >= 0 ? o.active() : 0);
            return true;
        }
        if (!o.isOpen() && o.search && e.target && String(e.target.value || '').replace(/^\s+|\s+$/g, '').length >= 2) {
            // Net getypt, lijst nog niet geladen: nu zoeken en dan de bovenste nemen.
            e.preventDefault();
            var p = o.search();
            if (p && p.then) { p.then(function () { if (o.count() > 0) { o.pick(0); } }); }
            return true;
        }
        return false;
    }

    global.GeeveEnterTop = { handle: handle };
}(window));
