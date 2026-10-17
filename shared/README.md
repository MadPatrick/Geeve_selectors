# Gedeelde layout - gids voor een nieuwe app

Dit document is de complete referentie om een **nieuwe subapp** aan de Geeve Selectors-portal toe
te voegen met dezelfde huisstijl als alle andere apps, zonder iets te kopiëren. Het hoofdscherm en
elke subapp (`hoses`, `adapters`, `configurator`, `stauff`, `slkprinten`, `stickers`) gebruiken
dit al. Zie ook de korte samenvatting in de root-`README.md` ("Gedeelde layout") - dit document
gaat dieper en is bedoeld als checklist/kopieersjabloon.

## Wat je krijgt

- **`shared/tokens.css`** - alle CSS-variabelen (kleuren, maten, lettertypes). Wordt door
  `shared/style.css` geïmporteerd; zie "CSS-variabelen" hieronder.
- **`shared/head.php`** - de `<link>`-regels voor de `<head>` (favicon, `shared/style.css`, eigen
  stylesheets), met cache-busting en absolute adressen.
- **`shared/paths.php`** - centrale data (`data/<app>/`) en korte adressen (`geeveUrl()`,
  `geeveAppUrl()`), zie "Centrale data" en "Korte adressen" hieronder.
- **`shared/slangkaart.css`** - de vastgelegde slangkaart-layout, zie
  [`SLANGKAART-LAYOUT.md`](SLANGKAART-LAYOUT.md).
- **`shared/style.css`** - CSS-resets, de header/brand-panel-chrome en de meest
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

3. **`<head>`** - gebruik `shared/head.php`. Die zet favicon, `shared/style.css` (met daarin
   `shared/tokens.css`) en daarna je eigen stylesheets, met absolute adressen en cache-busting:

   ```html
   <!doctype html>
   <html lang="nl">
   <head>
       <meta charset="utf-8">
       <meta name="viewport" content="width=device-width, initial-scale=1">
       <meta name="robots" content="noindex,nofollow">
       <title>&lt;Apptitel&gt; | Geeve Hydraulics</title>
       <?php
       $headCss = ['<appnaam>/assets/style.css'];   // vanaf de portal-root, in laadvolgorde
       require __DIR__ . '/../shared/head.php';
       ?>
   </head>
   ```

   Oudere apps hebben nog de losse `<link>`-regels (`../shared/style.css` vóór `assets/style.css`);
   dat werkt ook, maar nieuwe apps gebruiken `head.php`.

4. **`<body>`** - open `<main class="page-shell">` (of `<main class="page-shell page-shell--wide">` voor een brede selector-app) en include `shared/header.php` direct erna, met
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
   gedeelde regel. Een andere paginabreedte hoort daar **niet** bij: gebruik `page-shell--wide`. Zie "Wat hoort in je eigen
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

Een eenvoudige topline met alleen versietekst (naar `slkprinten`/`stickers`):

```php
<?php
$headerTitle = '<Apptitel>';
$headerTopline = '<span class="version-inline">Versie ' . h(APP_VERSION) . '</span>';
require __DIR__ . '/../shared/header.php';
?>
```

## CSS-referentie (`shared/style.css`)

### CSS-variabelen (`shared/tokens.css`)

Alle variabelen staan in **`shared/tokens.css`** (alleen `:root`, geen regels); `shared/style.css`
importeert dat bestand. Kijk daar voor de actuele waarden. De groepen:

| Groep | Voorbeelden | Gebruik |
|---|---|---|
| Basis | `--bg`, `--panel`, `--ink`, `--muted`, `--line`, `--soft`, `--shadow` | Pagina, panelen, tekst, randen |
| Merk | `--accent`, `--accent-dark`, `--dark-1`, `--dark-2` | Accentkleur, donkere header |
| Status | `--ok`, `--danger`, `--status-error*`, `--status-warn*`, `--status-ok-soft`, `--status-accent-soft` | Meldingen, pillen, rijkleuren |
| Vlakken | `--surface-white`, `--surface-subtle`, `--surface-input-disabled`, `--panel-glass` | Kaarten, tabelrijen, velden |
| Lijnen | `--line-input`, `--line-faint`, `--line-strong`, `*-line` | Invoervelden, scheidingslijnen, knoprand |
| Tekst | `--font-family`, `--font-print`, `--ink-print`, `--ink-disabled` | Lettertypes, printtekst |
| Vorm | `--radius-sm`, `--radius-md`, `--radius-lg`, `--radius-pill`, `--focus-ring`, `--btn-gap` | Afronding, focus, knopafstand |
| Knoppen | `--btn-*` | Alle knopkleuren, zie hieronder |

**Afspraak:** geen losse kleurwaarden in een eigen `assets/style.css`. Een kleur die je nodig hebt
staat in `tokens.css`, of komt er eerst als nieuwe variabele bij.

**Knopregel:** elke knop heeft een rand in dezelfde kleurtoon als zijn achtergrond - een
**lichtere** rand op een donkere knop, een **donkerdere** rand op een lichte knop (gebruik de
`*-line`-tokens hierboven) - en aangrenzende knoppen staan `var(--btn-gap)` (5px) uit elkaar.

`--ok`/`--danger` zijn te donker om op een donkere achtergrond (`--dark-1`/`--dark-2`) te
gebruiken - zie Stauff's `.copy-button`/`.assembly-erp-status` voor een voorbeeld van eigen,
helderdere varianten op zo'n plek (gedocumenteerd in `stauff/README.md`).

### Knopkleuren (`--btn-*`)

Alle knoppen halen hun kleuren uit de `--btn-*`-variabelen in `:root` (groen, rood, grijs, wit, blauw, danger, glass
voor de header, save/cancel, config, `--btn-ink-light`). Zie `CLAUDE.md`, "Knoppen", voor de afspraken.

### Resets

`* { box-sizing: border-box; }`, `html { min-height: 100%; }`,
`body { margin: 0; min-height: 100%; background: var(--bg); color: var(--ink); }`,
`button, input, select { font: inherit; }`.

### Layout/header-chrome

| Klasse | Omschrijving |
|---|---|
| `.page-shell` | De centrale contentkolom (`width: min(1100px, calc(100% - 28px))`, gecentreerd) - de standaard voor o.a. het hoofdscherm. |
| `.page-shell--wide` | Standaard brede variant (1400px) voor selector-apps met veel kolommen: `<main class="page-shell page-shell--wide">`. Gebruikt door hoses/adapters/stauff/slkprinten - geen eigen breedte-override meer nodig. |
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
| `.print-btn` | De Print-knop van alle apps: lichtgroen met printer-icoon. Markup: `<button type="button" class="print-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9V2h12v7"></path><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg><span>Print</span></button>`. |

### Responsive

`@media (max-width: 980px)` - header-panel wordt 1 kolom, "terug"-knop verplaatst naar de
topline-rij. `@media (max-width: 620px)` - kleinere logo's/titel, `.panel`-padding verkleint.

## Wat hoort in je eigen `assets/style.css`

Alleen wat **specifiek voor deze app** is: eigen secties/kaarten, stappenindicatoren,
statuspillen, tabellen, enz. - precies zoals `.section-heading`/`.eyebrow`/`.step`/`.status-pill`
nu bewust **niet** gedeeld zijn (elke app gebruikt een licht andere variant). Daarnaast mag je elke
gedeelde regel hier overschrijven (de eigen stylesheet laadt ná `shared/style.css`), bijvoorbeeld:

```css
/* Voorbeeld van een legitieme override: dit element heeft in deze app een eigen kleur nodig. */
.header-icon-button-save { color: var(--ok); }
```

Dupliceer nooit een gedeelde regel 1-op-1 in de eigen stylesheet - als twee apps toevallig exact
dezelfde override nodig hebben, hoort die override in `shared/style.css`, niet in allebei de eigen
bestanden.

## Centrale data (`data/`)

CSV's en hun back-ups staan centraal in **`data/<app>/`** op portal-rootniveau, bijvoorbeeld
`data/hoses/artikelnummers_staal.csv`. Die map staat **niet in git** (alleen `data/.htaccess`, die
de map voor de browser afsluit), zodat `git pull` de bestanden die op de server bewerkt worden nooit
raakt. Elke app mag elk bestand lezen: Slangkaart printen en de configurator lezen bijvoorbeeld de
slangen-CSV van `hoses`.

```php
require_once __DIR__ . '/../shared/paths.php';

// Leesvolgorde: data/hoses/x.csv, dan de oude plekken die je meegeeft.
$candidates = geeveDataCandidates('hoses', 'artikelnummers_staal.csv', [__DIR__ . '/data/artikelnummers_staal.csv']);
$file = findFirstReadableFile($candidates);          // om te lezen
$target = geeveDataWriteTarget($candidates);         // om naartoe te schrijven (upload/bewerken)
$backupDir = geeveBackupDir($target);                // data/backups/hoses/
```

Schrijven gaat altijd naar het bestand dat gelezen wordt, zodat lezen en schrijven nooit
verschillende bestanden pakken. Een nieuwe app zet zijn data meteen in `data/<appnaam>/` en heeft
geen oude plekken nodig. Afbeeldingen die bij de code horen blijven in git, in de eigen
`images/` van de app.

## Korte adressen

In de adresbalk staan geen mappen: een app heeft een kort adres zoals `/msp/` in plaats van
`/reports/msp/index.php`. Drie plekken horen bij elkaar:

1. **`GEEVE_APP_URLS`** in `shared/paths.php`: tegel-id => kort adres.
2. **`TILE_REGISTRY`** in `shared/tiles.php`: tegel-id => de echte map.
3. **De root-`.htaccess`**: per app die dieper ligt dan één map (`reports/*`, `stickers/*`) drie
   RewriteRules (oud adres doorsturen, `/` toevoegen, intern vertalen). Een app direct onder de
   root (`hoses/`) heeft geen regels nodig: het korte adres is de map.

Na een wijziging: `php tools/check-adressen.php` controleert dat de drie overeenkomen.

Links in een app:

- naar een andere app of een overzicht: `geeveAppUrl('reports')`, `geeveAppUrl('msp')`;
- naar een bestand van een andere map: `geeveUrl('reports/assets/sp-report.js')`. Gebruik geen
  `../` naar een buurmap: vanaf een kort adres wijst `../` niet meer naar de echte map;
- binnen de eigen map gewoon relatief (`assets/style.css`, `api/data.php`): dat blijft werken.

## Slangkaart-layout

De slangkaart van Slangkaart printen is de standaard voor elke app die slangkaarten toont of
print. Laad `shared/slangkaart.css` (na `shared/style.css`, vóór je eigen stylesheet) en volg de
opbouw in [`SLANGKAART-LAYOUT.md`](SLANGKAART-LAYOUT.md).

## Versienummer

Alle apps tonen hetzelfde versienummer uit `version.php` (root), al geladen via de
`APP_VERSION`-constante in stap 2 hierboven. Een versie-ophoging betekent: pas `return '...'` in `version.php` (root)
aan - niet een eigen, losse constante per app. Doe dat alleen als de gebruiker erom vraagt; het
versienummer wordt niet bij elke wijziging opgehoogd.

## Checklist

- [ ] `<appnaam>/assets/style.css` aangemaakt (mag leeg beginnen), zonder losse kleurwaarden.
- [ ] `<appnaam>/index.php`: kopblok (`APP_VERSION`, `h()`) en `<head>` via `shared/head.php`.
- [ ] `<main class="page-shell">` + `shared/header.php`-include met minimaal `$headerTitle`.
- [ ] Eigen pagina-inhoud binnen `.panel`/`.field`-structuur waar van toepassing.
- [ ] Data (CSV's) in `data/<appnaam>/` via `shared/paths.php`, niet in git.
- [ ] (optioneel) tegel: `TILE_REGISTRY` (`shared/tiles.php`), `GEEVE_APP_URLS`
      (`shared/paths.php`), zo nodig de RewriteRules in `.htaccess`, en het `<a class="tile">`-blok
      op het overzicht. Daarna `php tools/check-adressen.php`.
- [ ] Eigen README.md voor de nieuwe app (zie de bestaande apps als voorbeeld).
- [ ] `version.php` **niet** zelf ophogen; dat gebeurt alleen op verzoek (zie `CLAUDE.md`).
