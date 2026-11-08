/* Gedeelde HTML-escape voor alle tegels (laadt via shared/head.php, vóór de eigen scripts).
 * GeeveEsc(s): tekst veilig in HTML of in een attribuut met dubbele aanhalingstekens zetten. Zelfde uitkomst als de
 * losse esc()-functies die eerder in elke tegel stonden. Geen pijlfuncties of nieuwere syntax (zie CLAUDE.md, browsers). */
(function (global) {
    'use strict';
    global.GeeveEsc = function (s) {
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    };
})(window);
