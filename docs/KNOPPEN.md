# Knoppen en hun kleurvariabelen

Elke soort knop heeft **vaste kleurvariabelen** (`shared/tokens.css`, sectie Knoppen). Een app geeft een knop alleen de klasse
van zijn soort; kleuren komen nooit uit een losse waarde of uit een algemene variabele (`--accent`, `--ok`, ...). Wijzig je
de variabele van een soort, dan verandert elke knop van die soort in alle apps. Controle: `php tools/check-knoppen.php`.

## Knopsoorten

| Soort | Klasse / element | Vlak | Rand | Tekst/icoon | Hover |
|---|---|---|---|---|---|
| **Print** | `.print-btn` (shared) | `--btn-print` | `--btn-print-line` | `--btn-print-ink` | vlak `--btn-print-hover` |
| **PDF** | `.pdf-button` | `--btn-pdf` (wit) | `--btn-pdf-line` (rood) | `--btn-pdf-ink` | vlak `--btn-pdf-hover` (rood), tekst `--btn-pdf-hover-ink` |
| **Hoofdactie** (Opzoeken, Upload, Ontgrendelen) | `.ka-button`, `.upload-button`, `.submit-button`, `.lock-button`, `.exact-write-button` | `--btn-primary` | `--btn-primary-line` | `--btn-primary-ink` | - |
| **Tweede keuze** | `.ka-button--secondary` | `--btn-secondary` | `--btn-secondary-line` | `--btn-secondary-ink` | - |
| **Groen** (Gegevens ophalen, Wissen, Exporteer, Kopieer, Kaart printen) | `.clear-filter-button`, `.link-button`, `.copy-button`, `#rpFetch`, `#aiExport`, `#oiExport`, ... | `--btn-green` | `--btn-green-line` | `--btn-green-ink` | vlak `--btn-green-hover` |
| **Verwijderen** | `.card-remove-button` | `--btn-white` | `--btn-white-line` | `--btn-ink-danger` | vlak `--btn-danger-hover`, rand `--btn-danger-hover-line` |
| **Toevoegen (rond)** | `.ka-add` | `--btn-add` | `--btn-add-line` | `--btn-ink-light` | - |
| **Rustige toevoegknop** | `.add-slot-button`, `.location-add-button` | `--btn-white` | `--btn-white-line` | `--btn-ink-quiet` | vlak `--btn-accent-hover` / `--btn-white-hover`, tekst `--btn-ink-accent` |
| **Keuzeschakelaar** | `.segment`, `.rp-toggle button` | `--btn-choice` | `--btn-choice-line` | `--btn-choice-ink` | gekozen: vlak `--btn-blue`, tekst `--btn-ink-light` |
| **Wit tweede keuze** (slot-dialoog) | `.lock-button--secondary` | `--btn-white` | `--btn-white-line` | `--btn-ink` | - |
| **Header-knop** (home, icoon, PDF in de topbalk) | `.header-home-button`, `.header-icon-button`, `.topbar-pdf-button` | `--btn-glass` | `--btn-glass-line` | `--btn-ink-light` | vlak `--btn-glass-hover`, rand `--btn-glass-line-hover` |
| **Header: opslaan / annuleren** | `.header-icon-button-save` / `-cancel` | - | `--btn-save-line` | `--btn-save-ink` | `--btn-save-hover*` / `--btn-cancel-hover*` |
| **Config (tandwiel)** | `#configButton` | - | `--btn-config-line` | `--btn-config-ink` | tekst `--btn-ink-light` |
| **Gevonden/fout** (Stauff kopieerknop) | `.copy-button.is-found` | `--btn-danger` | `--btn-danger-line` | - | - |

De soorten Print, Groen en Hoofdactie verwijzen naar dezelfde basiskleuren (`--btn-green`, `--btn-red`). Print en Groen zijn
dus nu even licht groen (afspraak in `CLAUDE.md`); omdat Print een eigen set heeft, kan Print later apart wijzigen.

## Regels

1. Een nieuwe knop kiest een soort uit de tabel en gebruikt **alleen** de variabelen van die soort. Past er geen soort, voeg dan
   eerst een nieuwe soort (set `--btn-<soort>*`) toe aan `tokens.css` en aan deze tabel.
2. Randen zijn altijd 1px en donkerder dan de vulling (`*-line`). Knoppen staan `var(--btn-gap)` uit elkaar.
3. Geen losse hex-/rgba-waarden en geen `--accent`, `--ok`, `--ok-fill`, `--accent-dark` in een knopregel.
4. Een Print-knop is altijd `.print-btn` (markup in `shared/README.md`).
5. Draai na een wijziging `php tools/check-knoppen.php`; die geeft een melding bij een knop die een andere variabele gebruikt.

## Overzicht per app (stand oktober 2026)

Gegenereerd uit de CSS; de eerste kolommen zijn de klasse en de app of het bestand.

| Selector | App | Vlak | Rand | Tekst |
|---|---|---|---|---|
| `.pdf-button` | adapters, configurator, hoses | `--btn-pdf` | `--btn-pdf-line` | `--btn-pdf-ink` |
| `.upload-button` | adapters, hoses | `--btn-primary` | `--btn-primary-line` | `--btn-primary-ink` |
| `#configButton` | hoofdscherm | - | `--btn-config-line` | `--btn-config-ink` |
| `.lock-button` | hoofdscherm | `--btn-primary` | `--btn-primary-line` | `--btn-primary-ink` |
| `.lock-button--secondary` | hoofdscherm | `--btn-white` | `--btn-white-line` | `--btn-ink` |
| `.topbar-pdf-button` | hoses | `--btn-glass` | `--btn-glass-line` | `--btn-ink-light` |
| `.clear-filter-button` | hoses | `--btn-green` | `--btn-green-line` | `--btn-green-ink` |
| `.header-icon-button-save` | hoses | - | `--btn-save-line` | `--btn-save-ink` |
| `.card-remove-button` | hoses | `--btn-white` | `--btn-white-line` | `--btn-ink-danger` |
| `.add-slot-button` | hoses | - | `--btn-white-line` | `--btn-ink-quiet` |
| `.ka-button` | klantartikel (en alle tegels met `.ka-button`) | `--btn-primary` | `--btn-primary-line` | `--btn-primary-ink` |
| `#rpFetch, #eiFetch, #mspFetch` | klantartikel | `--btn-green` | `--btn-green-line` | `--btn-green-ink` |
| `.ka-button--secondary, #eiExport` | klantartikel | `--btn-secondary` | `--btn-secondary-line` | `--btn-secondary-ink` |
| `.ka-add` | klantartikel | `--btn-add` | `--btn-add-line` | `--btn-ink-light` |
| `#aiExport` | artikel-import | `--btn-green` | `--btn-green-line` | `--btn-green-ink` |
| `.rp-toggle button` | msp | `--btn-blue` (gekozen) | - | `--btn-ink` |
| `.oi-del` | offerte-import | hover `--btn-del-hover` | - | `--btn-ink-quiet`, hover `--btn-ink-danger` |
| `#oiExport` | offerte-import | `--btn-green` | `--btn-green-line` | `--btn-green-ink` |
| `.header-home-button`, `.header-icon-button` | shared | `--btn-glass` | `--btn-glass-line` | `--btn-ink-light` |
| `.print-btn` | shared (alle Print-knoppen) | `--btn-print` | `--btn-print-line` | `--btn-print-ink` |
| `.submit-button` | slkprinten, stauff | `--btn-primary` | `--btn-primary-line` | `--btn-primary-ink` |
| `.button-fill` | slkprinten | `--btn-fill` | - | - |
| `.link-button` | slkprinten, stauff | `--btn-green` | `--btn-green-line` | `--btn-green-ink` |
| `.exact-live-list button` | stauff | `--btn-white` | - | `--btn-ink-accent` |
| `.segment` | stauff | `--btn-choice` | `--btn-choice-line` | `--btn-choice-ink` |
| `.location-add-button` | stauff | `--btn-white` | `--btn-white-line` | `--btn-ink-quiet` |
| `.copy-button` | stauff | `--btn-green` | `--btn-green-line` | `--btn-green-ink` |
| `.exact-write-button` | stauff | `--btn-primary` | `--btn-primary-line` | `--btn-primary-ink` |
