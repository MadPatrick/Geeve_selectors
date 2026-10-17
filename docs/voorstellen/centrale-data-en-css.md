# Voorstel: centrale data, gedeelde media en uniforme CSS

*Geeve Intranet (Geeve_selectors), 17-10-2026. Alleen een voorstel, er is nog niets gebouwd.
Branch: `fixes/centrale-data-en-css-5v24fm`.*

*Versie 3 (17-10-2026 08:35), besluiten van Patrick:*
- *De data staat centraal in `/var/www/html/data` en gaat **uit git** (§3, §4, §7).*
- *In de browser zie je **geen mappen of submappen** in het adres (§5).*

## 1. Doel

1. Data die apps gebruiken (`.csv`, afbeeldingen, back-ups) op **één centrale plek**, zodat elke
   app dezelfde bestanden kan lezen in plaats van eigen kopieën of paden als `../hoses/data/`.
2. **Layout-instellingen en CSS uniform**: alle kleuren, maten en afstanden uit gedeelde
   CSS-variabelen, ook in de app-eigen stylesheets.
3. **Niets breekt**: elke stap werkt met terugval op de huidige paden, zodat een `git pull` op de
   server op elk moment veilig is.
4. **Nette adressen**: de browser toont `/msp/` in plaats van `/reports/msp/index.php`, en mappen
   zijn nooit als lijst te openen.
5. Slangkaart.Web (repo Rubix-BNL/slangkaart-web, van Hans) doet nu **niet** mee, maar kan later
   aansluiten op dezelfde data en dezelfde variabelen zonder dat hier iets om moet.

## 2. Hoe het nu zit

| Wat | Waar nu | Opmerking |
|---|---|---|
| Slangen-CSV's (staal, rvs, accessoires) | `hoses/data/` (terugval: `hoses/`) | In git, maar ook op de server bewerkt via "Gegevens wijzigen"/upload |
| Adapters-CSV | `adapters/data/` (terugval: `adapters/`) | Idem |
| Configurator-CSV's | `configurator/data/` | Eigen, ingekorte kopie van de slangenlijst |
| Back-ups van CSV's | `<app>/data/backups/` | Alleen op de server |
| Krimpmaat in Slangkaart printen | leest rechtstreeks `../hoses/data/*.csv` | Hard pad naar een andere app |
| Productafbeeldingen | `hoses/images/`, `adapters/images/`, `stauff/images/` | In git, via relatieve URL in de JS |
| Logo's, achtergrond | `images/` (root), `favicon.ico`, `stauff/assets/geeve-logo.svg` | `$headerImagesPath` per app (`../`, `../../`) |
| Instellingen (DB, tegels, code) | `.settings.key` / `.settings.enc` (root) | Alleen op de server; sleutelpad al instelbaar via `GEEVE_SETTINGS_KEY_FILE` |
| `.env` | root | Wordt niet meer gelezen, mag weg |
| Kleurvariabelen | `:root` in `shared/style.css` | ±60 variabelen, vooral knoppen |
| App-CSS | 21 bestanden, ±3.600 regels | ±300 losse hex-/rgba-waarden; vaak dezelfde (`#fff` 43x, `#a03636` 14x, `#cbd6df` 12x, `#fdeaee` 8x ...) |

**Bestaand risico dat dit voorstel ook oplost:** de CSV's staan in git én worden op de server
bewerkt. Zodra iemand een CSV in git wijzigt terwijl de server een eigen bewerking heeft, weigert
`git pull` ("local changes would be overwritten"), of gaat bij een harde reset de serverbewerking
verloren. Daarom gaat de data uit git (§4.1).

## 3. Voorgestelde indeling

```
/var/www/html/                     (git, zoals nu)
├── data/                          NIEUW: centrale data voor alle apps, NIET in git (.gitignore: /data/* behalve .htaccess)
│   ├── hoses/                     artikelnummers_staal.csv, _rvs.csv, _accessoires.csv
│   ├── adapters/                  artikelnummers_adapters.csv
│   ├── configurator/              artikelnummers.csv, slangkoppelingen.csv
│   ├── backups/<app>/             automatische back-ups bij opslaan/upload
│   └── .htaccess                  "Require all denied": niets in data/ is via de browser op te vragen
├── shared/
│   ├── tokens.css                 NIEUW: alleen :root-variabelen (kleur, maat, afstand, lettertype)
│   ├── style.css                  gedeelde regels, gebruikt alleen variabelen
│   ├── head.php                   NIEUW: favicon + tokens.css + style.css met cache-busting
│   ├── paths.php                  NIEUW: geeveDataFile(), geeveDataWritePath(), geeveMediaUrl(), geeveAppUrl()
│   └── header.php                 gebruikt geeveMediaUrl() i.p.v. $headerImagesPath
├── media/                         NIEUW: statische, gedeelde afbeeldingen (in git)
│   ├── brand/                     geeve.jpg, rubix.jpg, achtergrond.jpg, geeve-logo.svg, favicon.ico
│   ├── hoses/   adapters/   stauff/   productafbeeldingen
└── <app>/...                      code + eigen assets/style.css
```

Waarom zo:

- **`data/` uit git**: wat op de server bewerkt wordt, mag niet in een bestand staan dat git
  beheert, anders blokkeert `git pull` of gaat een bewerking verloren (§4.1). Met `/data/*` in
  `.gitignore` raakt een `git pull` de map nooit.
- **Geen geschiedenis meer in git** voor de CSV's. Dat vangen de automatische back-ups op
  (`data/backups/<app>/`, zoals nu al bij elke opslag of upload). Een nieuwe installatie krijgt de
  CSV's uit een back-up of via de uploadknop van de app.
- **Eén `data/.htaccess`** die alles weigert, in plaats van per app een `.htaccess` te moeten
  onthouden (hoses en adapters blokkeren `.csv` nu zo, configurator niet). Die `.htaccess` staat
  wél in git (`.gitignore`: `/data/*` plus `!/data/.htaccess`; met `/data/` zou git de uitzondering negeren), zodat de beveiliging niet van de
  server afhangt.
- **`media/` in git** voor afbeeldingen die bij de code horen. Eén plek, dus Stauff, Slangkaart
  printen of een nieuwe app kunnen dezelfde slang- of adapterafbeelding gebruiken. In de browser
  verschijnen ze onder een kort adres (`/img/...`, §5), niet onder de mapnaam.
- Later kan `data/` nog naar buiten de webroot (bijvoorbeeld `/srv/geeve-data`): dan verandert
  alleen `shared/paths.php` (via een omgevingsvariabele `GEEVE_DATA_DIR`), geen enkele app.

## 4. Centrale paden: `shared/paths.php`

Eén include die elke app gebruikt in plaats van eigen `csv-paths.php`-bestanden. Schets:

```php
// Leesvolgorde: data/<app> -> <app>/data -> <app>/ (de laatste twee = de huidige paden, als terugval).
function geeveDataDir(): string;                        // /var/www/html/data (later instelbaar)
function geeveDataFile(string $app, string $file): ?string;   // eerste leesbare kandidaat
function geeveDataWritePath(string $app, string $file): string; // de plek waar het bestand gelezen wordt
function geeveBackupDir(string $app): string;           // data/backups/<app> of <app>/data/backups
function geeveMediaUrl(string $relPath): string;         // kort adres /img/..., met ?v=mtime
function geeveAppUrl(string $tileId): string;            // kort adres van een app, bijv. /msp/ (§5)
```

- **Lezen** pakt de eerste die bestaat: `data/<app>/x.csv`, dan precies de huidige kandidaten
  (`<app>/data/x.csv`, `<app>/x.csv`). Zolang `data/` nog leeg is, verandert er dus niets.
- **Schrijven** (opslaan via "Gegevens wijzigen", upload, back-up) gaat naar de plek waar het
  bestand gelezen werd. Zo kunnen lezen en schrijven nooit verschillende bestanden pakken.
- `hoses/inc/csv-paths.php`, `adapters/inc/csv-paths.php` en `configurator/inc/csv-paths.php`
  blijven bestaan als dun laagje dat `shared/paths.php` aanroept; de rest van de code hoeft niet
  in één keer om.
- `slkprinten/index.php` vervangt `__DIR__ . '/../hoses/data/'` door
  `geeveDataFile('hoses', ...)`.
- Geen `mb_*`-functies (de server heeft geen mbstring).

### 4.1 Waarom uit git, en niet "in git maar uitgesloten van git pull"

Git kan een bestand dat het zelf beheert niet betrouwbaar overslaan bij een `pull`:
`--skip-worktree`/`--assume-unchanged` en sparse-checkout laten `git pull` alsnog vastlopen zodra
het bestand in git wijzigt, en `.gitignore` werkt alleen voor bestanden die níet in git staan.
Daarom gaat `data/` er helemaal uit.

## 5. Nette adressen: geen mappen in de browser

### 5.1 Korte adressen per app

Elke app krijgt een kort adres, gelijk aan het tegel-id waar dat kan. Apache vertaalt dat intern
(mod_rewrite in de root-`.htaccess`) naar de echte map; de browser ziet de map nooit.

| Nu in de adresbalk | Straks |
|---|---|
| `/hoses/index.php` | `/hoses/` |
| `/reports/msp/index.php` | `/msp/` |
| `/reports/offerte-import/index.php` | `/offerte-import/` |
| `/stickers/order/index.php` | `/stickers-order/` |
| `/slkprinten/index.php?diagnose=...` | `/slangkaarten-printen/?diagnose=...` |
| `/klantartikel/index.php` | `/klantartikel/` |

Volledige lijst: één kolom `slug` erbij in `TILE_REGISTRY` (`shared/tiles.php`); de tegels op het
hoofdscherm en in Reports/Templates/Stickers linken via `geeveAppUrl()`. `/slangkaarten/` blijft
van Slangkaart.Web (Apache-proxy) en wordt dus geen slug van een PHP-app.

Hoe het werkt, per app twee regels:

```apache
RewriteEngine On
RewriteRule ^msp$        /msp/ [R=301,L]
RewriteRule ^msp/(.*)$   reports/msp/$1 [L]
```

Door de afsluitende `/` blijven alle relatieve links in de apps gewoon werken:
`assets/style.css` wordt `/msp/assets/style.css` (intern `reports/msp/assets/style.css`), een
`fetch('api/x.php')` wordt `/msp/api/x.php`, en `../../images/` of `../shared/style.css` komt uit
op `/images/` en `/shared/style.css`. Er hoeft dus geen app-code om voor de eerste stap.

**Oude adressen blijven werken**, want de echte mappen bestaan nog. Pas als alles via de nieuwe
adressen loopt, kan een tweede regel bladwijzers op het oude adres doorsturen (301, alleen voor
een GET van de `index.php`, nooit voor API's of formulieren).

### 5.2 Afbeeldingen en data

- Afbeeldingen: `/img/hoses/19001.png` (intern `media/hoses/19001.png`), het logo
  `/img/brand/geeve.jpg`. Eén regel `RewriteRule ^img/(.*)$ media/$1 [L]`.
- CSV's en back-ups zijn helemaal niet via een adres te bereiken (`data/.htaccess`). Downloaden
  gaat zoals nu via de knop van de app (`download.php`), die het bestand als bijlage stuurt.

### 5.3 Geen mappenlijsten

`Options -Indexes` in de root-`.htaccess`: een adres als `/reports/` of `/hoses/images/` zonder
`index.php` toont dan "Forbidden" in plaats van een lijst met bestanden en submappen.

### 5.4 Te controleren op de server (alleen lezen)

Of Apache `mod_rewrite` aan heeft en `AllowOverride` voor `/var/www/html` `FileInfo` en
`Options` toestaat. De bestaande `.htaccess`-regels (`FilesMatch`) werken al, maar dat zegt niets
over rewrite. Zonder die rechten is het één regel in de Apache-config (door jou of de
server-Claude, met jouw OK).

## 6. Uniforme CSS en layout-instellingen

### 6.1 `shared/tokens.css`

Het `:root`-blok gaat uit `shared/style.css` naar een eigen bestand, aangevuld met de waarden die
nu als losse hex in de app-CSS staan. Indeling met vaste voorvoegsels:

| Groep | Voorbeelden | Bron |
|---|---|---|
| Merk | `--brand-accent`, `--brand-dark-1/2` | bestaand `--accent`, `--dark-*` |
| Vlakken | `--surface`, `--surface-soft`, `--surface-white` | `#f2f2f2`, `#f4f7fa`, `#fafcfd`, `#fff` |
| Lijnen | `--line`, `--line-strong`, `--line-table` | `#d6dde4`, `#c9d4de`, `#cbd6df` |
| Status | `--status-ok-*`, `--status-warn-*`, `--status-error-*` | `#e8f4ed`, `#fff3d6`/`#8a5a00`/`#e0b04a`, `#a03636`/`#f7eaea`/`#fdeaee` |
| Knoppen | `--btn-*` | bestaand, ongewijzigd (afspraak uit CLAUDE.md blijft) |
| Layout | `--page-width`, `--page-width-wide`, `--radius`, `--radius-sm`, `--gap`, `--gap-sm` | nu vaste px in style.css |
| Tabel | `--table-cell-pad-y: 3px`, `--table-input-height: 28px` | de compacte `.ka-table`-afspraak |
| Tekst | `--font-family`, `--font-size-base`, `--font-size-small` | bestaand `font-family` |

**Bestaande namen blijven werken**: `--accent`, `--panel`, `--line` enz. blijven bestaan (eventueel
als alias, `--accent: var(--brand-accent)`), zodat geen enkele huidige regel stuk gaat.

### 6.2 `shared/head.php`

Nu herhalen 31 `index.php`-bestanden dezelfde drie regels (favicon, `shared/style.css`, eigen
style) met handmatige `../` of `../../`. Een include doet dat in één keer en rekent het pad zelf
uit:

```php
$headAppCss = ['assets/style.css'];   // eigen stylesheets van de app
require __DIR__ . '/../shared/head.php';  // favicon + tokens.css + style.css + eigen css, met ?v=mtime
```

Volgorde blijft gegarandeerd: **tokens -> gedeeld -> app**. Apps die nog niet om zijn, laden
gewoon `shared/style.css`; dat importeert tijdelijk zelf `tokens.css`, zodat oude en nieuwe apps
naast elkaar werken.

### 6.3 App-CSS per app omzetten

Per app de losse hex-/rgba-waarden vervangen door tokens, één app per commit, en daarna in de
browser naast de oude versie vergelijken (screenshots van elke tegel vóór en na). Regels voor
daarna, als aanvulling op CLAUDE.md:

- Geen losse kleurwaarde meer in `<app>/assets/*.css`; een nieuwe kleur = eerst een token.
- App-eigen regels (secties, stappen, statuspillen) blijven per app, maar halen hun waarden uit tokens.
- Stickeropmaak (mm-maten voor labels) blijft per sticker; alleen kleuren gaan naar tokens.

### 6.4 Layout-instellingen via Config (optioneel, later)

Als je kleuren of breedtes zonder commit wilt kunnen aanpassen: Config schrijft een klein
`data/layout/overrides.css` (alleen `:root { --x: ...; }`), en `shared/head.php` laadt dat
als laatste via `shared/layout.php`. Zonder dat bestand gelden gewoon de waarden uit
`tokens.css`. Dit is een aparte stap; eerst moet alles via tokens lopen, anders heeft een
instelling geen effect.

## 7. Migratie in fasen (zonder iets te breken)

Elke fase is los uit te rollen met `git pull` en los terug te draaien.

| Fase | Wat | Effect op de server | Terugdraaien |
|---|---|---|---|
| 1 | `shared/paths.php` + de drie `csv-paths.php`, `save.php`/`upload.php` en `slkprinten` erop laten leunen; `data/.htaccess`; `/data/*` (behalve `.htaccess`) in `.gitignore` | Geen: `data/` is nog leeg, dus alles leest en schrijft waar het nu staat | Commit revert |
| 2 | **Serverversies veiligstellen**: de server-Claude kopieert `hoses/data/*.csv`, `adapters/data/*.csv`, `configurator/data/*.csv` naar `data/<app>/` en de back-ups naar `data/backups/<app>/` (kopiëren, niet verplaatsen) | Apps lezen en schrijven vanaf nu in `data/` | Map `data/<app>` hernoemen: alles valt terug op de oude bestanden |
| 3 | In git: `git rm --cached` van de oude CSV's, plus `.gitignore` voor `<app>/data/` | Vóór de `git pull` eenmalig `git checkout -- hoses/data adapters/data configurator/data` (de inhoud staat dan al veilig in `data/`), anders weigert de pull. De pull haalt daarna de oude kopieën weg | Bestanden staan nog in de git-geschiedenis en in `data/` |
| 4 | Server-check §5.4, dan `Options -Indexes` en de korte adressen (§5.1) als extra adres naast het oude | Oude én nieuwe adressen werken | Regels uit `.htaccess` halen |
| 5 | Tegels linken via `geeveAppUrl()`; daarna optioneel de 301 van oude naar nieuwe adressen | Adresbalk toont alleen nog korte adressen | Revert |
| 6 | `shared/tokens.css` + `shared/head.php`; `style.css` importeert tokens tijdelijk | Visueel niets | Revert |
| 7 | Per app: `head.php` gebruiken en losse kleuren naar tokens | Per app te controleren | Revert per app |
| 8 | Afbeeldingen naar `media/` (git mv) met `geeveMediaUrl()` en `/img/`; `$headerImagesPath` vervalt | Oude afbeeldingsadressen blijven werken via een rewrite | Revert |
| 9 (optioneel) | Layout-instellingen in Config (§6.4) | | |

Fase 2 en 3 horen bij één update: eerst 2 op de server, direct daarna de `git pull` van 3.

De `.settings.*`-bestanden blijven waar ze zijn (root, niet in git); ze kunnen later naar `data/`
via het bestaande `GEEVE_SETTINGS_KEY_FILE`, maar dat is geen onderdeel van dit voorstel. Het
overgebleven `.env` in de root wordt al niet meer gelezen (zie Config) en kan los van dit voorstel
verwijderd worden.

## 8. Slangkaart.Web later aansluiten

Niets in dit voorstel vraagt een wijziging in Hans' repo. Als het zover is, zijn er twee
koppelpunten, beide via een PR in Rubix-BNL/slangkaart-web:

1. **CSS**: Apache serveert `/shared/tokens.css` al op dezelfde host. Slangkaart.Web kan dat bestand
   in `App.razor` laden vóór `app.css` en zijn eigen variabelen erop laten wijzen
   (`--rood: var(--brand-...)`). Let op: de kleuren verschillen nu echt (Slangkaart.Web gebruikt
   navy `#1d1950`, rood `#aa1124` en geel `#ffe000` uit het logo; de portal roze-rood `#ee305d`
   met grijs). Welke set de huisstijl wordt, is een keuze voor jou en Hans.
2. **Data**: Slangkaart.Web importeert nu bij het opstarten JSON-momentopnamen van de selectors
   (`data/selector/2026-09-11-*.json`, zie `HERKOMST.md` daar), die met de hand van de pagina's
   zijn gehaald. Met de centrale map kan dat worden:
   - **a.** Slangkaart.Web leest direct uit `/var/www/html/data/` (de dienst krijgt alleen
     leesrechten), of
   - **b.** een klein endpoint in de portal, bijvoorbeeld `/api/selectordata?selector=hoses`,
     dat dezelfde JSON levert als de huidige momentopnamen; Slangkaart.Web haalt die op in plaats van
     een bestand mee te leveren.

   Optie b is mijn voorkeur: geen gedeelde bestandsrechten tussen twee diensten, en het formaat
   is al bekend aan beide kanten.

## 9. Vragen voor jou

1. ~~Locatie datamap~~, ~~CSV's uit git~~ en ~~geen mappen in de browser~~: besloten
   (17-10-2026): `/var/www/html/data`, niet in git, en korte adressen.
2. **Korte adressen**: akkoord met de tegel-id's als adres (`/msp/`, `/stickers-order/`), of wil
   je eigen namen per app?
3. **Configurator**: blijft dat een eigen, ingekorte kopie, of moet die voortaan de centrale
   slangen-CSV van de Slangen fitting Selector lezen?
4. **Layout via Config (fase 9)**: nodig, of is "kleuren alleen via `tokens.css` en een commit"
   genoeg?
5. **Huisstijl Slangkaart.Web**: later één gezamenlijke kleurset met Hans, of houdt elke app zijn
   eigen palet en delen we alleen de structuur van de variabelen?

## 10. Wat ik als eerste zou bouwen

Fase 1 en 6 samen: allebei onzichtbaar voor de gebruiker, allebei zonder serverwerk. Daarna fase 2
en 3 als één update met de server-Claude, dan de korte adressen, en vervolgens per app de CSS.
