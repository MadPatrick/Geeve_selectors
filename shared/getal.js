/* Gedeelde getalweergave voor alle tegels (laadt via shared/head.php, vóór de eigen scripts).
 * Alleen voor WEERGAVE en afdruk: nooit voor waarden die naar Exact, CSV of zoekvelden gaan.
 * Nederlandse notatie: punt als duizendtal, komma als decimaal (2.000 / 1.234,5). Zelfde uitkomst in elke browser
 * (geen toLocaleString, dat in oudere browsers per taal verschilt). Geen pijlfuncties of nieuwere syntax.
 *
 *   GeeveGetal.duizendtal(2000)            -> "2.000"
 *   GeeveGetal.duizendtal(1234.5, 0, 2)    -> "1.234,5"   (min. 0, max. 2 decimalen)
 *   GeeveGetal.duizendtal(1234.5, 2, 2)    -> "1.234,50"
 *   GeeveGetal.lengteMm('2000')            -> "2.000 MM"  (reeds "2.000" blijft "2.000 MM"; tekst blijft ongewijzigd)
 *   GeeveGetal.tekst('10000')              -> "10.000"    (niet-getal blijft zoals het is) */
(function (global) {
    'use strict';

    function groepeer(heel) {
        return heel.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    function duizendtal(n, minDec, maxDec) {
        n = Number(n);
        if (!isFinite(n)) { return ''; }
        minDec = minDec || 0;
        maxDec = maxDec === undefined ? minDec : maxDec;
        var tekst = Math.abs(n).toFixed(maxDec);
        var delen = tekst.split('.');
        var dec = delen[1] || '';
        while (dec.length > minDec && dec.charAt(dec.length - 1) === '0') { dec = dec.slice(0, -1); }
        var uit = groepeer(delen[0]) + (dec !== '' ? ',' + dec : '');
        return (n < 0 && Number(tekst) !== 0 ? '-' : '') + uit;
    }

    // Tekst die een getal is (1000, 1000,5, 1000.000000) krijgt een duizendtalpunt; al opgemaakt of geen getal: ongewijzigd.
    function tekst(v, maxDec) {
        v = String(v == null ? '' : v).trim();
        if (/^-?\d{1,3}(\.\d{3})+(,\d+)?$/.test(v)) { return v; }          // al "2.000" of "1.234,5"
        if (/^-?\d+$/.test(v)) { return duizendtal(v, 0, 0); }               // "2000"
        if (/^-?\d+[.,]\d+$/.test(v)) {                                      // "10.000000" / "5,49"
            return duizendtal(v.replace(',', '.'), 0, maxDec === undefined ? 3 : maxDec);
        }
        return v;
    }

    // Lengte zoals in de oude app: "2.000 MM" (alleen een getal krijgt " MM" erachter, tekst blijft staan).
    function lengteMm(v) {
        v = String(v == null ? '' : v).trim();
        if (/^-?\d{1,3}(\.\d{3})+$/.test(v)) { return v + ' MM'; }          // al met duizendtalpunt
        if (/^\d+$/.test(v)) { return duizendtal(v, 0, 0) + ' MM'; }
        if (/^\d+,\d+$/.test(v)) { return v + ' MM'; }                       // met decimale komma: zoals ingetypt
        if (/^\d+\.\d+$/.test(v)) { return duizendtal(v, 0, 3) + ' MM'; }
        return v;
    }

    global.GeeveGetal = { duizendtal: duizendtal, tekst: tekst, lengteMm: lengteMm };
})(window);
