# Werkafspraken voor Claude in deze repo

Zie ook de portal-brede `../CLAUDE.md` (gedeelde layout, gedeeld versienummer) - dit bestand
bevat alleen de afspraken die specifiek voor `/hoses` gelden.

## Versienummer (APP_VERSION in index.php / data.php)
- `APP_VERSION` komt uit het gedeelde `version.php` op portal-rootniveau (zie `../CLAUDE.md`), niet
  uit een eigen losse constante - `index.php` en `data.php` laden dat allebei apart via
  `define('APP_VERSION', is_file(__DIR__ . '/../version.php') ? (string) require __DIR__ . '/../version.php' : '...')`,
  dus bij het ophogen past dat ene `version.php` het voor beide bestanden tegelijk aan.
- Hoog de versie maximaal **1x per dag** op, niet bij elke losse wijziging aan
  CSS/JS - tenzij de gebruiker expliciet aangeeft dat het die keer wel moet.
  (De query-string `?v=<?= APP_VERSION ?>` op `assets/style.css` en
  `assets/selector.js` is puur voor cache-busting; dat werkt ook als de
  versie een keer per dag i.p.v. per commit omhoog gaat.)
- Het derde cijfer (patch) mag doorlopen tot en met 99 (dus ook dubbele
  cijfers, bijv. `0.0.23`) voordat het middelste cijfer (minor) omhoog gaat.
