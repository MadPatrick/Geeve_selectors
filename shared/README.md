# Gedeelde layout - gids voor een nieuwe app

Dit document is de complete referentie om een **nieuwe subapp** aan de Geeve Selectors-portal toe
te voegen met dezelfde huisstijl als alle andere apps, zonder iets te kopiëren. Het hoofdscherm en
elke subapp (`hoses`, `adapters`, `configurator`, `stauff`, `slangkaarten`, `stickers`) gebruiken
dit al. Zie ook de korte samenvatting in de root-`README.md` ("Gedeelde layout") - dit document
gaat dieper en is bedoeld als checklist/kopieersjabloon.

## Wat je krijgt

- **`shared/style.css`** - kleurtokens, CSS-resets, de header/brand-panel-chrome en de meest
  gebruikte formulierprimitieven (`.panel`, `.field`, `input`, `select`). Zie "CSS-referentie"
  hieronder voor de volledige lijst.
- **`shared/header.php`** - de brand-panel/header-markup (logo's, "terug naar hoofdmenu"-knop,
  `<h1>`, optionele topline-rij) als parametriseerbare PHP-include.
- **`version.php`** (root) - één gedeeld versienummer voor alle apps, zie "Versienummer"
  hieronder.

Wat je **niet** krijgt (en zelf per app moet maken): de eigen pagina-inhoud, app-specifieke CSS
(bijv. `.section-heading`, `.eyebrow`, `.status-pill` - zie "Wat hoort in je eigen `assets/style.css`"
hieronder) en app-specifieke JS.

## Verwachte mapstructuur van een nieuwe app

```
<appnaam>/
├── index.php          (verplicht - zie "Stap voor stap" hieronder)
├── assets/
│   ├── style.css       (app-specifieke stijlen + eventuele overrides)
│   └── <appnaam>.js     (eigen logica, optioneel)
├── api/                (eigen PHP-endpoints, optioneel)
└── images/             (eigen afbeeldingen, optioneel - los van de portal-brede images/ met
                         geeve.jpg/rubix.jpg die shared/header.php gebruikt)
```

## Stap voor stap

1. **Maak de map** `<appnaam>/` met `assets/style.css` (mag eerst leeg zijn).

2. **`<appnaam>/index.php`** - begin met het standaard kopblok (cache-busting + escaping, identiek
   in elke app, zie bijv. `stauff/index.php`):

   ```php
   <?php

   declare(strict_types=1);

   // Eén gedeeld versienummer voor hoofdscherm + alle subapps (version.php op
   // rootniveau) - valt terug op deze waarde als dat bestand ontbreekt.
   define('APP_VERSION', is_file(__DIR__ . '/../version.php') ? (string) require __DIR__ . '/../version.php' : '0.2.1');

   function h(string $value): string
   {
       return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
   }

   // Cache-busting op basis van de laatste wijzigingsdatum van het bestand
   // zelf, zodat elke aanpassing aan style.css/<appnaam>.js automatisch een
   // nieuwe URL krijgt - geen handmatige versie-ophoging nodig.
   function assetVersion(string $relativePath): string
   {
       $full = __DIR__ . '/' . $relativePath;
       $mtime = @filemtime($full);
       return $mtime !== false ? (string) $mtime : APP_VERSION;
   }
   ?>
   ```

   `shared/header.php` gebruikt zelf `h()` voor het escapen van de variabelen die je meegeeft, maar
   definieert die functie niet - elke app moet 'm dus zelf (zoals hierboven) declareren vóórdat de
   include gebruikt wordt.

3. **`<head>`** - laad `shared/style.css` **vóór** de eigen `assets/style.css`, zodat de eigen
   stylesheet specifieke waarden kan overschrijven zonder de hele regel te hoeven herhalen:

   ```html
   <!doctype html>
   <html lang="nl">
   <head>
       <meta charset="utf-8">
       <meta name="viewport" content="width=device-width, initial-scale=1">
       <meta name="robots" content="noindex,nofollow">
       <title>&lt;Apptitel&gt; | Geeve Hydraulics</title>
       <link rel="icon" href="../favicon.ico?v=<?= h(assetVersion('../favicon.ico')) ?>" type="image/x-icon">
       <link rel="stylesheet" href="../shared/style.css?v=<?= h(assetVersion('../shared/style.css')) ?>">
       <link rel="stylesheet" href="assets/style.css?v=<?= h(assetVersion('assets/style.css')) ?>">
   </head>
   ```

4. **`<body>`** - open `<main class="page-shell">` en include `shared/header.php` direct erna, met
   minimaal `$headerTitle` gezet:

   ```php
   <body>
   <main class="page-shell">
       <?php
       $headerTitle = '<Apptitel>';
       require __DIR__ . '/../shared/header.php';
       ?>

       <!-- eigen pagina-inhoud, bijv.: -->
       <section class="panel">
           ...
       </section>
   </main>
   </body>
   </html>
   ```

   Dat is het minimale geval. Zie "`shared/header.php`-parameters" hieronder voor een topline-rij
   met eigen icoon-knoppen/versietekst (bijv. een config- of data-knop).

5. **Eigen stijlen** - vul `assets/style.css` alleen met wat deze ene app nodig heeft: eigen
   secties (`.section-heading`, stappen, statuspillen, ...) en eventuele **overrides** van een
   gedeelde regel (bijv. een bredere `.page-shell`). Zie "Wat hoort in je eigen
   `assets/style.css`" hieronder.

6. **(Optioneel) registreer de app op het hoofdscherm** - de tegel-grid op de portal-homepage
   (`index.php`, `.tile-grid`/`.tile`) is zelf **geen** onderdeel van de gedeelde layout (elke
   tegel heeft een eigen titel/icoon/omschrijving) - voeg daar zelf een `<a class="tile"
   href="<appnaam>/index.php">`-blok toe naar het voorbeeld van de bestaande tegels, als de nieuwe
   app vanaf het hoofdscherm bereikbaar moet zijn.

## `shared/header.php`-parameters

Vóór de `require` zetten (zie ook de docblock bovenin `shared/header.php` zelf):

| Variabele | Type | Verplicht | Default | Omschrijving |
|---|---|---|---|---|
| `$headerTitle` | string | **ja** | - | Tekst voor `<h1>`. |
| `$headerImagesPath` | string | nee | `'../images/'` | Pad naar de map met `geeve.jpg`/`rubix.jpg`. Het hoofdscherm zet hier `'images/'` (geen `../`, want dat staat al op rootniveau). |
| `$headerShowHome` | bool | nee | `true` | Toont de "terug naar hoofdmenu"-knop. Het hoofdscherm zet hier `false` (dat IS het hoofdmenu). |
| `$headerHomeHref` | string | nee | `'../index.php'` | Link van die knop. |
| `$headerTopline` | string (kale HTML) | nee | `''` | Eigen inhoud boven de titel (icoon-knoppen/versietekst/statuspil). Wordt **ongefilterd** uitgevoerd (geen `h()`) - dus zelf escapen wat niet al vaste markup is. Leeg = geen topline-rij. |

Bouw `$headerTopline` met `ob_start()`/`ob_get_clean()` vóór het includen zodra het meer dan 1
regel is. Voorbeeld met meerdere icoon-knoppen (naar `hoses/index.php`):

```php
<?php
$headerTitle = 'Hose and fitting Selector';
ob_start();
?>
<div class="topbar-status-pill <?= h($dataState) ?>"><?= h($dataLabel) ?></div>
<button type="button" id="editToggleButton" class="header-icon-button" title="Gegevens wijzigen" aria-label="Gegevens wijzigen">
    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M12 20h9"></path>
        <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"></path>
    </svg>
</button>
<?php
$headerTopline = ob_get_clean();
require __DIR__ . '/../shared/header.php';
?>
```

Een eenvoudige topline met alleen versietekst (naar `slangkaarten`/`stickers`):

```php
<?php
$headerTitle = '<Apptitel>';
$headerTopline = '<span class="version-inline">Versie ' . h(APP_VERSION) . '</span>';
require __DIR__ . '/../shared/header.php';
?>
```

## CSS-referentie (`shared/style.css`)

### Kleurtokens (`:root`)

| Token | Waarde | Gebruik |
|---|---|---|
| `--bg` | `#ececec` | Paginabackground (`body`). |
| `--panel` | `#ffffff` | Achtergrond van `.panel`. |
| `--ink` | `#1f2328` | Hoofdtekstkleur. |
| `--muted` | `#687383` | Gedempte/secundaire tekst. |
| `--line` | `#d6dde4` | Randen (o.a. `.panel`). |
| `--accent` | `#ee305d` | Merkkleur (focus-rand, `.brand-panel::after`-streep). |
| `--accent-dark` | `#b90d32` | Donkere variant van de merkkleur. |
| `--dark-1` / `--dark-2` | `#232323` / `#3b3b3b` | Donkere achtergronden (o.a. `.brand-panel`, `.code-panel` in Stauff). |
| `--soft` | `#f4f7fa` | Zachte achtergrond (bijv. tabelkoppen). |
| `--ok` | `#237447` | Succes/positief - bedoeld voor een **lichte** achtergrond. |
| `--danger` | `#a03636` | Fout/negatief - bedoeld voor een **lichte** achtergrond. |
| `--shadow` | `0 10px 24px rgba(0,0,0,.06)` | Standaard schaduw (`.panel`, `.brand-panel`). |

`--ok`/`--danger` zijn te donker om op een donkere achtergrond (`--dark-1`/`--dark-2`) te
gebruiken - zie Stauff's `.copy-button`/`.assembly-erp-status` voor een voorbeeld van eigen,
helderdere varianten op zo'n plek (gedocumenteerd in `stauff/README.md`).

### Resets

`* { box-sizing: border-box; }`, `html { min-height: 100%; }`,
`body { margin: 0; min-height: 100%; background: var(--bg); color: var(--ink); }`,
`button, input, select { font: inherit; }`.

### Layout/header-chrome

| Klasse | Omschrijving |
|---|---|
| `.page-shell` | De centrale contentkolom (`width: min(1100px, calc(100% - 28px))`, gecentreerd). Een app met bredere content overschrijft dit in eigen `assets/style.css` (bijv. `width: min(1400px, calc(100% - 28px));`). |
| `.page-header` | Wrapper om `shared/header.php`'s `<header>`. |
| `.brand-panel` + `::after` | De zwarte header-balk met logo's/titel + de accentstreep onderaan. |
| `.brand-copy`, `.brand-logo-row`, `.brand-logo-img`, `.brand-rubix-img` | Logo-opmaak binnen de brand-panel. |
| `.header-content`, `.header-topline` | Rechterkolom van de brand-panel (titel + optionele topline-rij). |
| `.version-inline` | Kleine versietekst, meestal in `$headerTopline`. |
| `.header-home-button` (+ hover/svg) | De ronde "terug naar hoofdmenu"-knop. |
| `.header-icon-button` (+ hover/svg/`[hidden]`/`[disabled]`) | Kleine ronde icoon-knop in de topline (config/data/wijzigen/...). |
| `h1` (+ `@supports`-fallback) | Titelstijl met kleurverloop-tekst. |

### Formulierprimitieven

| Klasse | Omschrijving |
|---|---|
| `.panel` (+ `> small`) | Witte kaart met rand/schaduw - de basis voor elke sectie. |
| `.field` (+ `> span`, `small`) | Verticale label+input-groep. |
| `.compact-field` | Variant zonder vaste minimumbreedte. |
| `input, select` (+ `:focus`, `:disabled`) | Basisopmaak voor formuliervelden. |

### Responsive

`@media (max-width: 980px)` - header-panel wordt 1 kolom, "terug"-knop verplaatst naar de
topline-rij. `@media (max-width: 620px)` - kleinere logo's/titel, `.panel`-padding verkleint.

## Wat hoort in je eigen `assets/style.css`

Alleen wat **specifiek voor deze app** is: eigen secties/kaarten, stappenindicatoren,
statuspillen, tabellen, enz. - precies zoals `.section-heading`/`.eyebrow`/`.step`/`.status-pill`
nu bewust **niet** gedeeld zijn (elke app gebruikt een licht andere variant). Daarnaast mag je elke
gedeelde regel hier overschrijven (de eigen stylesheet laadt ná `shared/style.css`), bijvoorbeeld:

```css
/* Deze app heeft bredere content nodig dan de gedeelde 1100px. */
.page-shell { width: min(1400px, calc(100% - 28px)); }
```

Dupliceer nooit een gedeelde regel 1-op-1 in de eigen stylesheet - als twee apps toevallig exact
dezelfde override nodig hebben, hoort die override in `shared/style.css`, niet in allebei de eigen
bestanden.

## Versienummer

Alle apps tonen hetzelfde versienummer uit `version.php` (root), al geladen via de
`APP_VERSION`-constante in stap 2 hierboven. Een versie-ophoging na een wijziging in een subapp
betekent dus: pas `return '...'` in `version.php` (root) aan - niet een eigen, losse constante per
app.

## Checklist

- [ ] `<appnaam>/assets/style.css` aangemaakt (mag leeg beginnen).
- [ ] `<appnaam>/index.php`: kopblok (`APP_VERSION`, `h()`, `assetVersion()`) + `<head>` met
      `shared/style.css` vóór `assets/style.css`.
- [ ] `<main class="page-shell">` + `shared/header.php`-include met minimaal `$headerTitle`.
- [ ] Eigen pagina-inhoud binnen `.panel`/`.field`-structuur waar van toepassing.
- [ ] `version.php` (root) opgehoogd.
- [ ] (optioneel) tegel toegevoegd aan het hoofdscherm (`index.php`, `.tile-grid`).
- [ ] Eigen README.md voor de nieuwe app (zie de bestaande apps als voorbeeld).
