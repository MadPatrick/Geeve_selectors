# Geeve Hydraulics — Selectors

Startpagina met tegels naar de Geeve-selectors:

- **`/hoses`** — Slangen fitting Selector
- **`/adapters`** — Adapters Selector
- **`/klantartikel`** — Klant Artikelnummers: voer een prijslijstnummer in, krijg de debiteuren op die prijslijst en hun
  artikelen met een klantartikelnummer (`ItemAccounts.ItemCodeAccount`) uit Exact (database 005, zelfde
  inloggegevens als `/stauff`). Kolomnamen worden uit `INFORMATION_SCHEMA` afgeleid, zie `klantartikel/inc/queries.php`.
  Met XML-export in het Exact-importformaat (eExact, zoals de Excel-template "Geeve Import debiteuren").
- **`/stauff`** — Stauff Selector / beugelconfigurator, zelfde brand-panel/paneel-stijl als `/hoses`
  en `/adapters`. Heeft ook een databaseverbinding naar de Exact-database "005" (artikelgroep 67),
  zie "Database-koppeling Exact (database 005)" hieronder. Tegel op de startpagina is weer actief
  (was tijdelijk uitgeschakeld tijdens de opbouw).
- **`/configurator`** — Slang configurator, zelfde brand-panel/paneel-stijl. Bestanden staan er nog,
  maar er is momenteel geen tegel op de startpagina die hierheen linkt.
- **`/slangkaarten`** — Slangkaarten bij order - zoekt een order of klant op en print
  slangkaarten/labels van de geselecteerde regels. Heeft een eigen database nodig (SQL Server,
  database "Slangkaarten", zie `slangkaarten/README.md`). Voor de Locatie-kolom op de picklijst
  gebruikt deze subapp ook dezelfde Exact-database "005" als `/stauff`, met dezelfde (centraal
  opgeslagen) inloggegevens.
- **`/stickers`** — eigen scherm met 3 tegels: "Stickers op Artikelnummer", "Stickers op
  Artikelnummer (groot)" en "Stickers op Zakjes". Nog niet functioneel (alle 3 staan als
  "Binnenkort beschikbaar") - bestanden staan er nog, maar er is momenteel geen tegel op de
  startpagina die hierheen linkt.

`Geeve_selectors` is de enige/canonieke plek voor al deze subapps — er zijn geen losse bronrepo's
meer waar wijzigingen vandaan gekopieerd worden of naartoe teruggezet moeten worden. Wijzigingen
aan een subapp gebeuren rechtstreeks in de bijbehorende submap hier.

## Gebruik

Zet de hele map op een PHP-webserver (PHP 8+, voor de meeste tegels geen database nodig - zie
hieronder voor de uitzonderingen) en open `index.php`. Elke subapp gebruikt verder alleen eigen
relatieve paden (`assets/...`, `data/...`, eigen `images/...` voor productfoto's), met drie
uitzonderingen die uitgaan van de vaste nesting één niveau onder de root: de "terug naar
hoofdmenu"-knop (`../index.php`), het Geeve/Rubix-merklogo (`../images/geeve.jpg` en
`../images/rubix.jpg`, zie hieronder) en het gedeelde versienummer (`../version.php`, zie
hieronder). Een subapp-map los deployen buiten `Geeve_selectors` werkt dus niet identiek zonder
die drie dingen zelf te regelen (een eigen "terug"-link, eigen logo's, een hardcoded
versienummer) - dat is dan ook geen ondersteunde manier van deployen; deze portal-map is de enige
plek waar deze code leeft en gedraaid wordt.

`/slangkaarten` en `/stauff` hebben, in tegenstelling tot de andere tegels, database-inloggegevens
nodig - deels eigen (`slangkaarten/.env`, voor de "Slangkaarten"-database), deels gedeeld (de
root-`.env` hier, voor de Exact-database "005" - zie `slangkaarten/README.md` resp. "Database-
koppeling Exact (database 005)" hieronder). Zonder ingevulde `.env` toont die tegel/pagina (of
alleen de Locatie-kolom op de picklijst) een foutmelding/streepje i.p.v. te crashen; de rest van de
portal blijft gewoon werken.

## Database-koppeling Exact (database 005) - gedeeld door /stauff en /slangkaarten

`/stauff` (artikelgroep 67) en `/slangkaarten` (Locatie op de picklijst, zie
`slangkaarten/README.md`) praten allebei met dezelfde Exact-database "005" (zelfde server als de
"Slangkaarten"-database, `GEEVE-SQL-2019`), met **hetzelfde SQL-account**. Die inloggegevens staan
daarom 1x centraal, niet los per subapp:

1. **Verbinding opgezet** (dit is af): vul via Config in het hoofdmenu (versleuteld opgeslagen, `.settings.enc`)
   `EXACT_DB_USER`/`EXACT_DB_PASSWORD` in van een bestaand SQL-account dat database "005"
   mag lezen. Zowel `stauff/inc/config.php` als `slangkaarten/inc/config.php` laden deze
   root-`.env` automatisch mee (naast hun eigen subapp-`.env` voor overige instellingen) - er is
   dus maar 1 plek om deze inloggegevens te zetten of te wijzigen. De root-`.htaccess` blokkeert
   browsertoegang tot dotfiles (dus ook deze `.env`).
2. **Tabel/kolom voor groep 67 gevonden** (dit is af): `GRV_SalesItems` (kolommen `ItemCode`,
   `Item Description`, `Item Unit`, `Item Group`) koppelt elk artikel aan zijn groepscode;
   `Item Group = 67` geeft precies de Stauff-beugelartikelen. `GRV_ItemGroups` (`Item Group`,
   `Item Group Description`) bevestigt dat groep 67 = "STAUFF BEUGELS".
3. **Volledig live** (dit is af): de Stauff-selector haalt al zijn kandidaat-artikelen
   rechtstreeks uit groep 67 (`stauff/api/exact_search.php`, `stauff/api/exact_location_search.php`)
   - er is geen CSV meer (die is verwijderd, was eerder `stauff/data/stauff_selector.csv` +
   `stauff/api/stauff.php`), zie `stauff/README.md` voor de volledige migratie. `stauff/db-test.php`
   blijft staan als generiek diagnosehulpmiddel (bijv. voor de nog onbevestigde
   verkoopprijs-kolom).

## Structuur

```
index.php              Startpagina met tegels
version.php             Eén gedeeld versienummer voor hoofdscherm + alle subapps, zie hieronder
shared/style.css        Gedeelde basisopmaak (tokens, header/brand-panel-chrome, formulier-
                        primitieven) voor hoofdscherm + alle subapps, zie "Gedeelde layout"
shared/header.php       Gedeelde header/brand-panel-include, zie "Gedeelde layout"
shared/secure_settings.php  Versleutelde opslag van de Exact-database "005"-inloggegevens (EXACT_DB_*),
                        ingevuld via Config; gebruikt door /stauff en /slangkaarten
assets/style.css        Styling van alleen de startpagina (bovenop shared/style.css)
images/                 Gedeeld Geeve/Rubix-merklogo (geeve.jpg, rubix.jpg) - door alle
                        subapps gebruikt via ../images/..., één plek om bij te werken
docs/                   Bron-PDF's (catalogi, persmaatlijsten) - alleen referentiemateriaal,
                        wordt niet door de apps zelf ingelezen of gelinkt
  hoses/                Algemene hoses-referentiecatalogi (voorheen hoses/docs/*.pdf)
  perslijst/            Persmaatbladen per koppeling (voorheen hoses/perslijst/*.pdf)
  adapters/             Adapters-referentiecatalogi (voorheen adapters/docs/*.pdf)
hoses/                  Volledige Slangen fitting Selector-app (eigen assets/data/etc.)
adapters/               Volledige Adapters Selector-app (eigen assets/data + eigen
                        images/ met alleen de productfoto's per adapterfamilie)
stauff/                 Volledige Stauff Selector-app (eigen assets/data/api/etc.) - db-verbinding
                        naar Exact "005" leest EXACT_DB_* uit de root-.env hierboven
configurator/           Volledige Slang configurator-app (eigen assets/data/etc.) - bestanden
                        staan er nog, momenteel geen tegel op de startpagina
slangkaarten/           Volledige Slangkaarten-app (eigen assets/inc/etc. + eigen .env/.htaccess
                        voor de "Slangkaarten"-database; de Locatie-lookup op de picklijst leest
                        EXACT_DB_* uit de root-.env hierboven, zie slangkaarten/README.md)
stickers/               Eigen scherm met 3 tegels (nog niet functioneel - "Binnenkort
                        beschikbaar") - bestanden staan er nog, momenteel geen tegel op de
                        startpagina
```

## Gedeelde layout

Het hoofdscherm en alle subapps gebruiken dezelfde basisopmaak (kleurtokens, resets, de
header/brand-panel-chrome, en de meest gebruikte formulierprimitieven zoals `.panel`/`.field`/
`input`/`select`) - 1x onderhouden in `shared/style.css` en `shared/header.php` op portal-niveau,
i.p.v. per app gekopieerd. Dit loste een concrete inconsistentie op: elke app had vóór deze
refactor zijn eigen, licht uiteengelopen kopie van dezelfde tokens/header-stijlen (verschillende
`.page-shell`-breedtes, een enkele app zonder reponsive header-inklap op mobiel, etc.).

**Nieuwe app toevoegen?** Zie **[`shared/README.md`](shared/README.md)** voor de complete
stap-voor-stap gids + checklist + volledige CSS-/`header.php`-referentie. Hieronder alleen een
korte samenvatting.

**CSS.** Elke pagina laadt `shared/style.css` vóór zijn eigen `assets/style.css`:

```html
<link rel="stylesheet" href="../shared/style.css?v=...">  <!-- subapps -->
<link rel="stylesheet" href="assets/style.css?v=...">
```

(het hoofdscherm zelf gebruikt `shared/style.css` zonder `../`, want dat staat al op rootniveau).
Omdat de eigen stylesheet ná de gedeelde laadt, kan een app met een enkele regel een specifieke
waarde overschrijven zonder de hele regel te moeten herhalen. Een bredere pagina is geen
override meer: gebruik `<main class="page-shell page-shell--wide">`. Wat wél overal identiek is (bijv.
`.brand-panel`, `h1`, `input, select`) staat **alleen** nog in `shared/style.css` - de eigen
`assets/style.css` van elke app bevat nu alleen nog app-specifieke stijlen en zulke overrides.

**Header-HTML.** Dezelfde brand-panel/header-markup (logo's, "terug naar hoofdmenu"-knop, `<h1>`)
stond ook bijna letterlijk gekopieerd in elke `index.php`. Dat is nu `shared/header.php`, een
PHP-include die je zo gebruikt:

```php
<main class="page-shell">
    <?php
    $headerTitle = 'Stauff Selector';
    require __DIR__ . '/../shared/header.php';
    ?>
```

Variabelen (vóór het includen zetten, zie de docblock in `shared/header.php` voor de volledige
lijst):

- `$headerTitle` (verplicht) - tekst voor `<h1>`.
- `$headerImagesPath` (optioneel, default `'../images/'`) - het hoofdscherm zet hier `'images/'`.
- `$headerShowHome` (optioneel, default `true`) - het hoofdscherm zet hier `false` (dat IS het
  hoofdmenu, dus geen "terug"-knop).
- `$headerTopline` (optioneel, kale HTML) - voor een eigen icoon-knoppenrij/versietekst/statuspil
  boven de titel; bouw dit met `ob_start()`/`ob_get_clean()` vóór het includen als het meer dan 1
  regel is (zie `adapters/index.php` of `hoses/index.php` voor een voorbeeld met meerdere
  icoon-knoppen).

**Een nieuwe app aanhaken** kost dus maar 2 regels: de `<link>` naar `shared/style.css` (vóór de
eigen stylesheet) en de `require` van `shared/header.php` (met `$headerTitle` gezet) - daarmee
heeft de nieuwe app meteen dezelfde huisstijl, zonder iets te kopiëren.

## Eén gedeeld versienummer

Het hoofdscherm en alle subapps (`hoses`, `adapters`, `configurator`, `stauff`, `slangkaarten`,
`stickers`) tonen/gebruiken hetzelfde versienummer, uit `version.php` op rootniveau
(`return '0.3.0';`). Elke pagina
laadt dit via `is_file(__DIR__ . '/version.php') ? (string) require __DIR__ . '/version.php' : '...'`
(root-pagina's) resp. `__DIR__ . '/../version.php'` (subapp-pagina's) i.p.v. een eigen losse
`APP_VERSION`-constante te declareren. Een versie-ophoging hoeft dus nog maar op één plek: pas het
`return '...'` in `version.php` aan. `/stauff` is qua opmaak omgezet naar deze gedeelde
brand-panel/paneel-stijl; de selectielogica in `assets/selector.js` en `api/stauff.php` bleef
daarbij ongewijzigd.

## `hoses/data/` lokaal op de server beschermen tegen `git pull`

De CSV's in `hoses/data/` (`artikelnummers_staal.csv`, `artikelnummers_rvs.csv`,
`artikelnummers_accessoires.csv`) worden op de productieserver soms rechtstreeks bewerkt
(bijgewerkte artikelnummers/materialen), los van wat er in de repo staat. Een gewone `git pull` zou
die lokale aanpassingen zonder waarschuwing overschrijven zodra de bestanden ook in de repo
wijzigen.

Oplossing: `git update-index --skip-worktree` - dit is **lokaal aan de checkout** (staat niet in
een commit, wordt niet meegenomen door `git pull`/`push`/clone), dus dit moet 1x uitgevoerd worden
op de machine waar de portal daadwerkelijk draait/gepulld wordt, niet in een losse ontwikkel-
checkout. Git blijft de bestanden gewoon tracken, maar negeert voortaan lokale wijzigingen eraan
bij `checkout`/`pull`/`merge`:

```bash
git update-index --skip-worktree hoses/data/artikelnummers_accessoires.csv
git update-index --skip-worktree hoses/data/artikelnummers_rvs.csv
git update-index --skip-worktree hoses/data/artikelnummers_staal.csv
```

Terugdraaien (bijv. om een keer bewust wél de reponversie te pullen): zelfde commando's met
`--no-skip-worktree` i.p.v. `--skip-worktree`. Controleren welke bestanden momenteel op
skip-worktree staan: `git ls-files -v | grep '^S'`.
