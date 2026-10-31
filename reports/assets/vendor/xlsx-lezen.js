/*
 * Excel inlezen met de bijgewerkte SheetJS (0.20.3, klantartikel/assets/vendor/xlsx.core.min.js) en schrijven met opmaak
 * via xlsx-js-style (reports/assets/vendor/xlsx-style.min.js, gebaseerd op SheetJS 0.18.5).
 *
 * Volgorde op de pagina: eerst xlsx.core.min.js, dan dit bestand, dan xlsx-style.min.js. xlsx-style zet een eigen globale
 * XLSX neer; deze regel bewaart de nieuwe versie daarvoor als XLSX_LEZEN. Inlezen (XLSX_LEZEN.read) gebruikt zo de
 * versie waarin de bekende lees-kwetsbaarheden van 0.18.5 zijn opgelost; schrijven blijft ongewijzigd.
 */
window.XLSX_LEZEN = window.XLSX;
