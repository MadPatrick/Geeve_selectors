# Hergebruik van de gedeelde code (`shared/`) door een andere app

Dit document legt vast hoe een **andere, losstaande app** gebruik maakt van wat de Geeve Selectors-portal
al heeft: de **printfunctie**, de **centrale CSV-bestanden** en de gedeelde **layout/CSS** en hulpfuncties.
Het beschrijft alleen het bestaande gedrag; er is niets voor aangepast.

Naslag per onderdeel staat in [`shared/README.md`](../shared/README.md) (layout, data, adressen, printen) en
[`shared/SLANGKAART-LAYOUT.md`](../shared/SLANGKAART-LAYOUT.md). Dit document is de route daar doorheen voor
iemand die een nieuwe app bouwt en wil weten: wat laad ik, wat roep ik aan, wat moet er op de server staan en
waar gaat het mis.

> De code is voor dit document gelezen, niet op een tweede app uitgeprobeerd. Waar iets een afleiding uit de
> code is (en dus nog niet in de praktijk bevestigd), staat dat erbij.

## 1. Kies eerst: waar komt de app te staan?

| Situatie | Werkt zonder aanpassing? | Aanpak |
|---|---|---|
| **A. Nieuwe map in de portal** (`/var/www/html/<app>/`) | Ja, dit is de bedoelde route | Zie paragraaf 2 t/m 7. Alles hieronder gaat hiervan uit. |
| **B. PHP-app op dezelfde server, andere map** (bijv. `/var/www/andereapp/`) | Deels | PHP-functies zijn te laden via een absoluut pad (paragraaf 8). CSS en afbeeldingen moeten dan ook via een adres van de portal bereikbaar zijn. |
| **C. App op een andere server of in een andere taal** (zoals Slangkaart.Web, .NET) | Nee | De PHP-code is er niet bruikbaar. Hergebruik beperkt zich tot de CSS-variabelen (kopiëren of linken) en de layout-afspraken, zie paragraaf 9. |

Voorkeur: **A**. Alleen dan hoeft er niets te worden uitgezocht over rechten, adressen en `.htaccess`.

## 2. Wat er op de server aanwezig moet zijn

Dit staat al op de Geeve-server; een andere omgeving (testkopie, nieuwe server) moet het ook hebben.

| Nodig voor | Wat | Opmerking |
|---|---|---|
| Alles | PHP 8 (`declare(strict_types=1)`, `never`-returntype) | |
| Alles | **Geen `mb_*`-functies gebruiken** | De server heeft geen `mbstring`. Gebruik `strlen`, `substr`, `strtoupper` e.d. |
| Alles | Apache met `AllowOverride All` voor de portalmap | Zie "Valkuilen". Zonder dit worden `.htaccess`-bestanden genegeerd en zijn `.settings.key` en de CSV's te downloaden. Op de server staat dit in `/etc/apache2/conf-available/geeve-htaccess.conf` (dekt `/var/www/html` en `/var/www/test`). |
| Cache van CSS/JS | Apache `mod_headers` | De root-`.htaccess` zet `Cache-Control: no-cache`. Zonder `mod_headers` blijft de browser oude CSS tonen na een `git pull`. |
| Data | `data/` schrijfbaar voor de webserver (`www-data`) | Voor uploads, back-ups en de printerlijst-cache. |
| Printen | `smbclient` en `rpcclient` (pakket `smbclient`) | Printerlijst ophalen en opdracht in de wachtrij zetten. |
| Printen | Headless Chromium (`/usr/bin/chromium-headless-shell`, ook `/usr/bin/chromium` of omgevingsvariabele `GEEVE_CHROMIUM`) | HTML wordt PDF. |
| Printen | Ghostscript (`/usr/bin/gs`) | PDF wordt PCL XL voor A4-printers. |
| Printen (labels) | `pdftoppm` (`poppler-utils`); voor Brother QL ook `/opt/brother_ql` | Alleen voor labelprinters. |
| Printen | Lettertype Liberation (`fonts-liberation`) | Zonder dit valt de print terug op het bredere DejaVu en wordt rechts tekst afgesneden. |
| Printen | Inlogbestand `/etc/geeve/smbprint.cred` | Zie paragraaf 4. |

## 3. De shared-map inladen en een minimale app

In de portal laad je de shared-bestanden met een **relatief pad vanaf je eigen map**: `__DIR__ . '/../shared/...'`.
Een app op twee mappen diep (bijv. `reports/<app>/`) gebruikt `'/../../shared/...'`.

Minimale nieuwe app, `nieuweapp/index.php`:

```php
<?php

declare(strict_types=1);

define('APP_VERSION', is_file(__DIR__ . '/../version.php') ? (string) require __DIR__ . '/../version.php' : '0.0.0');

// shared/header.php en shared/head.php gebruiken h(), maar definieren het niet: declareer het zelf.
function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

require_once __DIR__ . '/../shared/paths.php';   // data en adressen
?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Nieuwe app | Geeve Hydraulics</title>
    <?php
    $headCss = ['nieuweapp/assets/style.css'];   // vanaf de portal-root, in laadvolgorde
    require __DIR__ . '/../shared/head.php';     // favicon + shared/style.css (met tokens.css) + $headCss
    ?>
</head>
<body>
<main class="page-shell">
    <?php
    $headerTitle = 'Nieuwe app';
    require __DIR__ . '/../shared/header.php';   // zwarte topbalk, logo's, home-knop, PC-naam
    ?>
    <section class="panel">
        <p>Hallo.</p>
    </section>
</main>
</body>
</html>
```

Daarna, optioneel: een tegel op het hoofdscherm (`TILE_REGISTRY` in `shared/tiles.php` en
`GEEVE_APP_URLS` in `shared/paths.php`). De volledige stap-voor-stap gids en checklist staan in
[`shared/README.md`](../shared/README.md); `CLAUDE.md` schrijft die ook voor.

Wat de include-bestanden doen:

| Bestand | Levert | Let op |
|---|---|---|
| `shared/paths.php` | `geeveDataCandidates()`, `findFirstReadableFile()`, `geeveDataWriteTarget()`, `geeveBackupDir()`, `geeveUrl()`, `geeveAppUrl()` | Geen afhankelijkheden; veilig overal te laden. |
| `shared/head.php` | `<link>`-regels met cache-busting (`?v=<wijzigingsdatum>`) | Laadt `paths.php` zelf. Vereist `h()`. |
| `shared/header.php` | Topbalk-markup | Vereist `h()` en `$headerTitle`. Laadt `pcnaam.php` en `sneeuw.php` zelf. `$headerImagesPath` staat standaard op `'../images/'`. |
| `shared/pcnaam.php` | `pcNaam()` (naam van de pc via reverse DNS, valt terug op het IP) | Header toont hem al; zelf aanroepen kan ook. |
| `shared/csrf.php` | `csrfToken()`, `csrfValid()` | Start een sessie. Gebruik dit voor elk formulier of elke API die iets wijzigt of print. |
| `shared/tiles.php`, `shared/access.php` | Tegel-zichtbaarheid en toegangscode (`tileApiGuard()`, `accessApiGuard()`) | Alleen nodig als de app een tegel met slot is. |

## 4. Printen

`shared/printen.php` print vanaf de server, zonder het printvenster van de browser. De keten:

```
HTML  ->  headless Chromium (PDF, met jouw print-CSS)  ->  Ghostscript (PCL XL)  ->  smbclient  ->  printserver SVNL-VWP-PS01 (RTM*-printer)
```

Labelprinters gaan via een bitmap (TSPL voor Altec, `brother_ql` voor Brother QL) in plaats van PCL.

**Printers.** Dat zijn de printershares op `SVNL-VWP-PS01` waarvan de naam met `RTM` begint. Er is niets in te
stellen: `geevePrinterList()` haalt de lijst en de drivers op en bewaart ze 30 minuten in
`data/printers/smb-printers.json`. Zebra/ZPL en onbekende drivers vallen weg.

**Inloggen.** `/etc/geeve/smbprint.cred` (eigenaar root, groep `www-data`, rechten 640), in het formaat van
`smbclient -A`:

```
username = <account>
password = <wachtwoord>
domain = <domein>
```

De omgevingsvariabele `GEEVE_SMB_CRED` kan een ander pad opgeven. **Zet nooit een wachtwoord in code, documentatie,
commit of chat.** Na een inlogfout stopt de portal met proberen tot het bestand is gewijzigd
(`data/printers/smb-geblokkeerd.json`), zodat het account niet op slot gaat. Dat account is hetzelfde als dat van de
Exact-koppeling: een verkeerd wachtwoord blokkeert dus meer dan alleen het printen.

### 4a. Printen vanuit je eigen PHP-code (aanbevolen voor een nieuwe app)

Zo doet Slangkaart printen het (`slkprinten/index.php`). De app bouwt zelf de HTML en roept `geevePrintHtml()` aan:

```php
require_once __DIR__ . '/../shared/printen.php';

$printers = geevePrinters('nieuweapp');          // [share => naam]; een tegel zonder eigen soort telt als A4

// Formulier met <select name="printer"> met die printers; na een POST (met csrfValid() gecontroleerd):
$share = (string) ($_POST['printer'] ?? '');
if (!isset($printers[$share])) {
    // onbekende printer: niet doorgaan
}

$html = '<div class="mijn-blad">...</div>';      // de inhoud van je afdrukblok
try {
    $omschrijving = geevePrintHtml(
        $html,
        ['nieuweapp/assets/print.css'],          // stylesheets vanaf de portal-root, zelfde volgorde als in de <head>
        $share,
        'Mijn afdruk',                           // naam van de opdracht in de wachtrij
        null                                     // optioneel: ['#printSheet .kaart', 268] = te hoge elementen verkleinen tot 1 A4
    );
    geeveRememberPrinter('nieuweapp', $share);   // onthoudt de keuze in cookie geeve_printer_nieuweapp
} catch (RuntimeException $e) {
    $fout = $e->getMessage();                    // leesbare melding (Chromium weg, printer onbekend, inlogfout, ...)
}
```

- `geevePrintHtml()` zet je HTML in `<div id="printSheet">` en laadt **altijd eerst `shared/style.css`**, daarna jouw
  stylesheets. Zet je print-CSS dus op `#printSheet` en in `@media print` / `@page { size: A4 }`.
- **Scripts in de HTML worden verwijderd.** De print is statisch: alles wat JavaScript opbouwt, moet je server-side of
  vooraf in de HTML zetten.
- `geevePrinterOptionsHtml($id, $printers)` geeft kant-en-klare `<option>`s, met de vorige keuze voorgeselecteerd
  (en "Printvenster van de browser" als laatste optie).
- Tegel-id's voor `geevePrinters()` en `geeveRememberPrinter()` zijn gewoon een naam. Alleen als de tegel in
  `GEEVE_PRINT_TILE_SOORT` staat als `'label'` krijg je labelprinters; anders A4.

### 4b. Printen vanuit de browser (JavaScript) via `print-api.php`

Dit is de route van de Stickers-tegels. De pagina toont een keuzelijst en `stickers/assets/direct-print.js` laat
`window.print()` het afdrukblok naar `shared/print-api.php` posten. Daarvoor moet je app **geregistreerd** zijn in
`shared/printen.php`:

1. `GEEVE_PRINT_TILES`: tegel-id => naam.
2. `GEEVE_PRINT_TILE_SOORT`: alleen als het een labelprinter is (`'label'`).
3. `GEEVE_PRINT_TILE_PAGE`: `sheet` (id van het afdrukblok), `class` (klasse in de print) en `css` (stylesheets vanaf de root).
4. `print-api.php` controleert met `tileApiGuard()` of de tegel is ontgrendeld; een tegel met slot hoort dus in `TILE_REGISTRY` te staan.
5. In de pagina: `<?= geevePrinterSelectHtml('<tegel-id>', csrfToken()) ?>` naast de printknop, en `direct-print.js` laden.

Dat is een wijziging in gedeelde code. Doe dit alleen na overleg; 4a vraagt niets te wijzigen.

### 4c. Labelprinters

Het afdrukblok wordt gerenderd op het formaat van de `@page`-regel van de app, en met `pdftoppm -mono` een bitmap op
300 dpi. Maat, draairichting en afdrukinstellingen per labeltype staan in `GEEVE_LABEL_MEDIUM`, `GEEVE_LABEL_DRAAI` en
`GEEVE_TSPL_ZWART_IS_NUL` bovenin `shared/printen.php`. Een nieuw labelformaat betekent dus een regel in die constanten
(gedeelde code) en een testprint.

## 5. Centrale CSV-bestanden lezen en schrijven

CSV's en back-ups staan buiten git in `data/<app>/` en `data/backups/<app>/`. `data/.htaccess` sluit de map af voor de
browser (alleen dat ene bestand staat in git). Een git pull raakt de data dus nooit.

```php
require_once __DIR__ . '/../shared/paths.php';

// Lezen: data/<app>/<bestand>, daarna eventuele oude plekken die je meegeeft.
$kandidaten = geeveDataCandidates('nieuweapp', 'gegevens.csv');
$bestand = findFirstReadableFile($kandidaten);        // null als het niet bestaat

// Een bestand van een andere app lezen mag; dat is de bedoeling (zelfde functie, andere app-naam):
$staal = findFirstReadableFile(geeveDataCandidates('hoses', 'artikelnummers_staal.csv'));

// Schrijven (upload of bewerking): naar het bestand dat nu gelezen wordt, of anders de centrale plek.
$doel = geeveDataWriteTarget($kandidaten);
$backupMap = geeveBackupDir($doel);                   // data/backups/nieuweapp/ - maak een back-up voor je overschrijft
```

Regels:

- **Nooit** een hard pad naar de map van een andere app (`../hoses/data/...`): altijd via `geeveDataCandidates()`.
- Zet geen CSV's in git. Download en upload gaan via een knop in de app, niet via een adres.
- Data staat standaard in `<portal>/data`; de omgevingsvariabele `GEEVE_DATA_DIR` verlegt dat naar een andere map
  (bijv. buiten de webroot).
- Kolommen worden in de bestaande apps **op naam** gelezen (zo leest de configurator de slangen-CSV van `hoses`). Lees
  een gedeelde CSV ook op kolomnaam, zodat een extra kolom niets breekt, en wijzig de kolomnamen van een gedeelde
  CSV niet zonder te kijken wie hem nog meer leest.
- `geeveDataWriteTarget()` schrijft naar de oude plek als `data/<app>/` niet aangemaakt of beschreven kan worden. Staat
  een nieuwe app nergens in de weg, dan is dat geen probleem; maar controleer de rechten (zie "Valkuilen").

## 6. CSS en layout gebruiken

Laadvolgorde, altijd: `shared/style.css` (met daarin `shared/tokens.css`), dan eventueel `shared/slangkaart.css`, dan
de eigen `assets/style.css`. `shared/head.php` doet dat in de goede volgorde.

- **Kleuren en maten komen uit CSS-variabelen** (`shared/tokens.css`): geen losse hex- of rgba-waarden in een eigen
  stylesheet. Heb je een nieuwe kleur nodig, voeg dan eerst een variabele toe aan `tokens.css`.
- **Knoppen:** alle kleuren uit de `--btn-*`-variabelen. Rand 1px en altijd donkerder dan de vulling. Groen = lichtgroen
  (`--btn-green`), rood = één rood (`--btn-red`). **Printknop**: de gedeelde `.print-btn` uit `shared/style.css`
  (markup in `shared/README.md`), geen eigen printknopstijl.
- **Tabellen:** de compacte `.ka-table` en `.ka-input` uit `klantartikel/assets/style.css` (3px celpadding,
  invoervelden 28px hoog). Dat bestand is geen onderdeel van `shared/`; laad het via `$headCss` als je die klassen wilt.
- **Slangkaart:** wil de app slangkaarten tonen of printen, laad `shared/slangkaart.css` en bouw de markup zoals in
  [`SLANGKAART-LAYOUT.md`](../shared/SLANGKAART-LAYOUT.md) (referentie: `renderHoseCard()` en `renderPicklist()` in
  `slkprinten/index.php`). Wijk niet af van die layout zonder overleg.
- **Adressen:** links naar andere apps met `geeveAppUrl('<tegel-id>')`, naar een bestand in een andere map met
  `geeveUrl('<pad vanaf de root>')`. Geen `../` naar een buurmap. Een nieuwe of verplaatste app: werk `TILE_REGISTRY`
  en `GEEVE_APP_URLS` bij.
- **Versienummer:** `version.php` in de root; nooit een eigen versienummer per app. Het laatste getal gaat bij elke push
  met een wijziging met 1 omhoog; de eerste drie alleen op verzoek van Patrick.

## 7. Overige gedeelde functies

- `pcNaam()` (`shared/pcnaam.php`): naam van de pc van de gebruiker, via reverse DNS. De Windows-login zelf komt niet bij de
  server aan. Kent de DNS het IP niet, dan is het resultaat het IP-adres. Niet geverifieerd dat de DNS op de
  Geeve-server pc-namen teruggeeft.
- `csrfToken()` / `csrfValid()` (`shared/csrf.php`): bescherming voor elk formulier of elke API die iets wijzigt.
- `tileApiGuard($tegelId)` en `accessApiGuard($scope)`: API's die bij een beveiligde tegel horen moeten dit aanroepen,
  anders is de data ook zonder de toegangscode op te halen.
- Database-inloggegevens (Exact en `DB_USER`): via `secureSettingsRead()` (`shared/secure_settings.php`), die ook
  `/etc/geeve/portal.json` combineert. Lees die nooit uit een eigen bestand en toon ze nergens.

## 8. Situatie B: een PHP-app buiten de portalmap

Dit is niet getest; het volgt uit de code.

- De PHP-bestanden zijn te laden met een absoluut pad, bijv. `require_once '/var/www/html/shared/paths.php';`.
  `geevePortalDir()` is `dirname(__DIR__)` van `shared/`, dus data, printerlijst en stylesheets worden altijd in de
  portalmap gezocht, niet bij jouw app.
- `geeveBaseUrl()` leidt het basisadres af uit het draaiende script en geeft `/` als dat script **niet** in de portalmap
  staat. `geeveUrl()` en `shared/head.php` bouwen dan adressen als `/shared/style.css`. Die bestaan alleen als de
  webserver die URL voor jouw app ook uit de portal serveert.
  Oplossing: een `Alias /shared /var/www/html/shared` (en `/images`, `/favicon.ico`) in de Apache-site van de andere
  app, of een eigen `<link>` naar het adres van de portal i.p.v. `shared/head.php`.
- `shared/header.php` gebruikt `$headerImagesPath` voor `geeve.jpg` en `rubix.jpg`: zet dat op een adres waar die
  bereikbaar zijn, en zet `$headerHomeHref` naar het hoofdmenu.
- Rechten: de gebruiker waaronder de andere app draait moet `data/<app>/` kunnen lezen (en schrijven) en `www-data`-rechten
  hebben voor de printfunctie (`/etc/geeve/smbprint.cred` is alleen leesbaar voor root en `www-data`).
- `geevePrintHtml()` leest de stylesheets uit de portalmap (`$cssFiles` zijn paden vanaf de portal-root). CSS die buiten
  de portal staat, wordt niet gevonden: zet de print-CSS dus (ook) in de portalmap.
- Eenvoudiger is bijna altijd: de app toch als map in de portal zetten (situatie A).

## 9. Situatie C: een app op een andere server of in .NET

De PHP-functies (printen, data, header) zijn daar niet bruikbaar. Wat wel gedeeld kan worden:

- **Kleuren en lettertypes:** neem de waarden uit `shared/tokens.css` over of link naar dat bestand. Zo doet
  Slangkaart.Web het (huisstijl uit CSS-variabelen, de Geeve-kleuren in `wwwroot/app.css`).
- **Slangkaart-layout:** `shared/slangkaart.css` en [`SLANGKAART-LAYOUT.md`](../shared/SLANGKAART-LAYOUT.md) zijn de
  standaard. Slangkaart.Web heeft die klassen overgenomen in zijn eigen CSS.
- **Printen:** het is nu niet mogelijk om `shared/printen.php` van buiten de portal aan te roepen: `shared/print-api.php`
  vraagt een PHP-sessie, een CSRF-token en een geregistreerde tegel. Een andere app zou een eigen printkoppeling nodig
  hebben (of een nieuw, bewust beveiligd endpoint in de portal). Dat is een ontwerpkeuze en hier niet gebouwd.
- **Data:** de CSV's zijn niet via een adres op te halen (`data/.htaccess` blokkeert dat). Een andere app heeft een
  eigen endpoint of eigen bron nodig; lees ze niet via het bestandssysteem van de portal zonder dat af te spreken.

## 10. Valkuilen

1. **`AllowOverride None`.** Staat dat voor de map, dan worden `.htaccess`-bestanden genegeerd en zijn
   `.settings.key`, `.settings.enc` en de CSV's via de browser te downloaden. Dat is op 19-10-2026 op de Geeve-server
   gebeurd en opgelost met `geeve-htaccess.conf` (`AllowOverride All`). Controleer dit op elke nieuwe omgeving of
   testkopie: probeer in de browser `/.settings.key` en `/data/` te openen; beide moeten 403 geven.
2. **Geen `mb_*`.** De server heeft geen mbstring; een `mb_strlen()` geeft een fatale fout.
3. **`h()` ontbreekt.** `head.php` en `header.php` roepen `h()` aan zonder het te definiëren; zonder jouw eigen
   `function h()` krijg je een fatale fout.
4. **Relatieve adressen op een kort of ander pad.** Gebruik `geeveUrl()` / `geeveAppUrl()` voor alles buiten je eigen
   map; `../` naar een buurmap breekt zodra de app ergens anders bereikbaar is.
5. **Verkeerde laadvolgorde van CSS.** Eigen CSS vóór `shared/style.css` laat de gedeelde regels je eigen overschrijven.
6. **Browsercache.** Zonder `mod_headers` houden browsers oude CSS vast na een `git pull`; `head.php` voegt wel `?v=` toe,
   maar `tokens.css` komt via `@import` en heeft dat niet.
7. **Print: scripts verdwijnen.** In het afdrukblok wordt alle `<script>` verwijderd; wat alleen door JavaScript in beeld
   komt, wordt niet geprint.
8. **Print: pagina's lopen over.** Een A4-print heeft 12 mm marge links/rechts en 14 mm boven/onder; de kaart is
   daardoor hoogstens 268 mm hoog. Een te hoog blok loopt over op een tweede pagina. Gebruik `$passend` of maak de
   inhoud lager.
9. **Print: tekst rechts afgesneden.** Gebeurt als `fonts-liberation` ontbreekt (val terug op DejaVu, breder) en op de
   Canon-printers aan de rechterkant. De print verkleint daarom naar het printbare vlak (fit-to-page); houd zelf ook
   ruime marges aan en test op papier.
10. **Print: absolute adressen in de CSS.** Chromium rendert vanuit `file://`. Een `url(/images/...)` in je print-CSS wijst
    daardoor naar het bestandssysteem, niet naar de website (afgeleid uit de code, niet getest). Gebruik relatieve
    paden of `data:`-adressen voor afbeeldingen in de print.
11. **Print: `@page` en media-blokken.** Alleen de eigen `@page`-regel van de app telt voor labelformaat en A4; de
    `@media print`-regels worden voor het meten van `$passend` ook op scherm toegepast. Test elke wijziging met een
    echte print.
12. **Print: account op slot.** Een verkeerd of verlopen wachtwoord in `smbprint.cred` blokkeert het printen tot het
    bestand is gewijzigd en kan het account van de Exact-koppeling op slot zetten. Wijzig het bestand dus alleen met het
    juiste wachtwoord.
13. **Print: werkmap en rechten.** Elke print maakt een tijdelijke map in `sys_get_temp_dir()` en ruimt hem op.
    De printerlijst-cache (`data/printers/`) moet schrijfbaar zijn voor `www-data`; anders wordt de printserver bij elke
    pagina opnieuw bevraagd (traag).
14. **Data buiten git.** Een nieuwe checkout of testkopie heeft geen CSV's: vul `data/` uit een back-up of de uploadknop.
15. **Kolomnamen van gedeelde CSV's.** Meerdere apps lezen dezelfde CSV; een gewijzigde kolomnaam breekt de andere app.
16. **Geen wachtwoorden in documenten.** Niet in dit document, niet in de README's, niet in voorbeeldbestanden.
    Inloggegevens staan alleen in `/etc/geeve/` en de versleutelde instellingen.

## 11. Checklist voor een app die iets van `shared/` hergebruikt

- [ ] De app staat als map in de portal (anders: paragraaf 8 of 9 doorgenomen).
- [ ] `h()` gedefinieerd; `shared/head.php` en `shared/header.php` ingeladen.
- [ ] Geen `mb_*`; geen losse kleurwaarden; knoppen volgens de `--btn-*`-regels.
- [ ] Data via `geeveDataCandidates()` / `geeveDataWriteTarget()` / `geeveBackupDir()`; geen CSV's in git.
- [ ] Links via `geeveUrl()` / `geeveAppUrl()`; tegel in `TILE_REGISTRY` en `GEEVE_APP_URLS` als de app op het hoofdscherm moet.
- [ ] Printen: printerkeuze uit `geevePrinters()`, formulier met `csrfToken()`/`csrfValid()`, afdrukblok zonder scripts,
      getest op papier op de gekozen printer.
- [ ] Beveiligde API's roepen `tileApiGuard()` of `accessApiGuard()` aan.
- [ ] `version.php` laat het laatste getal bij elke push met 1 omhoog gaan; de eerste drie alleen op verzoek.
