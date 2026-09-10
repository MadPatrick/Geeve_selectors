# Werkafspraken voor Claude in deze repo

## Versienummer (APP_VERSION in index.php / data.php)
- Hoog de versie maximaal **1x per dag** op, niet bij elke losse wijziging aan
  CSS/JS - tenzij de gebruiker expliciet aangeeft dat het die keer wel moet.
  (De query-string `?v=<?= APP_VERSION ?>` op `assets/style.css` en
  `assets/selector.js` is puur voor cache-busting; dat werkt ook als de
  versie een keer per dag i.p.v. per commit omhoog gaat.)
- Het derde cijfer (patch) mag doorlopen tot en met 99 (dus ook dubbele
  cijfers, bijv. `0.0.23`) voordat het middelste cijfer (minor) omhoog gaat.
- `index.php` en `data.php` declareren `APP_VERSION` allebei apart (geen
  gedeelde include hiervoor) - bij het ophogen dus in beide bestanden
  aanpassen.
