# Slangkaart-layout (gedeelde standaard)

De slangkaart zoals **Slangkaart printen** (`slkprinten/`) hem toont en print, is de standaard
voor elke app die slangkaarten maakt, ook de toekomstige app **Slangkaarten**. Deze layout is
op 17-10-2026 vastgelegd. Wijzig hem alleen bewust en test daarna de print opnieuw.

| Wat | Waar |
|---|---|
| Opmaak (scherm, A4-print, picklijst) | [`shared/slangkaart.css`](slangkaart.css) |
| Kleuren en lettertypes | [`shared/tokens.css`](tokens.css) |
| Referentie-implementatie van de markup | `renderHoseCard()` en `renderPicklist()` in `slkprinten/index.php` |

## Zo gebruik je hem in een app

```php
<head>
    ...
    <?php
    $headCss = ['shared/slangkaart.css', '<app>/assets/style.css'];
    require __DIR__ . '/../shared/head.php';
    ?>
</head>
<body>
<main class="page-shell"> ... het scherm van de app ... </main>

<!-- Alleen in de print zichtbaar; op scherm verborgen. -->
<div id="printSheet" aria-hidden="true">
    <div class="hose-card"> ... </div>   <!-- één per slangkaart -->
    <div class="hose-card"> ... </div>
    <div class="picklist-sheet"> ... </div>  <!-- optioneel, altijd als laatste -->
</div>
</body>
```

Op scherm kun je dezelfde `.hose-card` ook buiten `#printSheet` tonen, als voorbeeld van de kaart.

## Opbouw van één kaart

Van boven naar beneden. Elke kaart is in de print precies één A4-pagina.

| # | Blok (klasse) | Inhoud | Gedrag in de print |
|---|---|---|---|
| 1 | `.card-top` | Links het merk "GEEVE **HYDRAULICS**" met `small.card-subbrand` "Slangenkaart"; rechts `.card-order-meta`: Ordernummer (groot) en de klantnaam (`.card-klant`) | Vaste hoogte, bovenaan |
| 2 | `.card-body` | Twee kolommen. Links `.card-details` met `.card-detail-row`'s: **Slangnummer** (rood, `.card-slangnummer`), Referentie, Uw Referentie, Omschrijving, Slang type, Lengte. Rechts `.card-qty-box` "Aantal slangen" (groot getal) | Vaste hoogte |
| 3 | `.card-couplings` | Drie kolommen: zijde **A** en zijde **B** (elk een `.coupling-block` met `h4` en een `.coupling-table`: Artikelnummer, Qty) en `.angle-block` met de hoek-tekening (`svg.angle-diagram`, zwarte lijnen) | **Groeit mee** en vult de ruimte tussen boven- en onderkant |
| 4 | `.card-note` | "Notitie", tekst 9px (klein, zodat lange notities passen), altijd minstens 3 regels hoog | Vaste hoogte |
| 5 | `.card-bewerkingen` | `h4` "Bewerkingen" met `.card-flags`: raster van 3 x 3 `.card-flag`'s (Labelen, Testen/spoelen, DNV Certificaat, Graveren, Pin prikken, Snijlengte, Testen, Proppen, één leeg vak `.card-flag-empty`) | Vaste hoogte |
| 6 | `.card-flags-row` | Twee kolommen van 50%: "Extra artikelen" (`.coupling-table`, Artikelnummer, Aantal) en "Krimpmaten" | Vaste hoogte |
| 7 | `.card-footer` | `.card-footer-addresses`: Ordercrediteur, Afleveradres, Verzendwijze (groot, `.card-verzendwijze`). Daaronder `.card-footer-meta`: Orderdatum, Aangemaakt, Laatste gewijzigd | Vaste hoogte, onderaan |

Een lege waarde toont een streepje (`&mdash;`), behalve de notitie (blijft leeg).

## Printregels

- **Papier:** A4 staand, marges 14 mm boven en onder en 12 mm links en rechts (`@page`).
- **Eén kaart per pagina:** `.hose-card` heeft in de print `min-height: 268mm` en altijd een
  pagina-einde erna. 268 mm is de bedrukbare hoogte min 1 mm speling, omdat Firefox mm anders
  afrondt en bij precies 269 mm een lege pagina maakte.
- **Vaste posities:** alles boven en onder de koppelingen heeft een vaste hoogte; alleen
  `.card-couplings` groeit (`flex: 1 1 auto`). Daardoor staat de footer altijd onderaan de pagina.
- **Picklijst:** `.picklist-sheet`, één eigen pagina na alle kaarten, met dezelfde kop
  (`.picklist-header`) en een `.picklist-table`: Artikelnummer, Locatie, Aantal, Voorraad,
  Besteld (leeg, om af te vinken).
- **Kleuren in de print:** `print-color-adjust: exact`, zodat de grijze vlakken en rode accenten
  ook geprint worden. Tekst in de print is `--ink-print`, lettertype `--font-print` (Arial).
- **Kaderranden in de print:** donkergrijs `#666` (`--line-print`), 1,5px alleen om de blokken (cellen 1px), op scherm lichtgrijs
  `--line`. `#printSheet` zet daarvoor `--line` opnieuw; dat geldt voor alle kaders (18-10-2026).
- **Alleen het printblad:** `.page-shell` (het scherm van de app) wordt in de print verborgen.
- **Smal scherm (onder 700px), alleen op scherm:** de kaartblokken staan onder elkaar. Dit
  geldt bewust niet in de print, omdat Firefox de printbreedte (186 mm, ongeveer 703px) soms
  net onder die grens uitrekende.

## Kleuren (uit `shared/tokens.css`)

| Gebruik | Variabele |
|---|---|
| Accentlijn onder de kop, Slangnummer, `h4`-koppen, "HYDRAULICS" | `--accent-dark` |
| Kaders en lijnen | `--line` (in de print `#666`) |
| Lijn tussen detailregels | `--line-faint` |
| Kopjes van tabellen, aantal-vak | `--soft` |
| Vakjes van Bewerkingen | `--surface-subtle` |
| Labels (kleine hoofdletters) | `--muted` |
| Waarden | `--ink` |
| Achtergrond van de kaart | `--surface-white` |
