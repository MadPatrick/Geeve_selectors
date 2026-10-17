# Voorstel: centrale data, gedeelde media en uniforme CSS

*Geeve Intranet (Geeve_selectors), 17-10-2026. Alleen een voorstel, er is nog niets gebouwd.
Branch: `fixes/centrale-data-en-css-5v24fm`.*

## 1. Doel

1. Data die apps gebruiken (`.csv`, afbeeldingen, back-ups) op **één centrale plek**, zodat elke
   app dezelfde bestanden kan lezen in plaats van eigen kopieën of paden als `../hoses/data/`.
2. **Layout-instellingen en CSS uniform**: alle kleuren, maten en afstanden uit gedeelde
   CSS-variabelen, ook in de app-eigen stylesheets.
3. **Niets breekt**: elke stap werkt met terugval op de huidige paden, zodat een `git pull` op de
   server op elk moment veilig is.
4. Slangkaart.Web (repo Rubix-BNL/slangkaart-web, van Hans) doet nu **niet** mee, maar kan later
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
verloren. Data die op de server leeft, hoort niet in de git-werkmap.

## 3. Voorgestelde indeling

Drie soorten bestanden, drie plekken:

```
/var/www/html/                     (git, zoals nu)
├── shared/
│   ├── tokens.css                 NIEUW: alleen :root-variabelen (kleur, maat, afstand, lettertype)
│   ├── style.css                  gedeelde regels, gebruikt alleen variabelen
│   ├── head.php                   NIEUW: favicon + tokens.css + style.css met cache-busting
│   ├── paths.php                  NIEUW: geeveDataFile(), geeveMediaUrl(), geeveBackupDir()
│   └── header.php                 gebruikt geeveMediaUrl() i.p.v. $headerImagesPath
├── media/                         NIEUW: statische, gedeelde afbeeldingen (in git, via web)
│   ├── brand/                     geeve.jpg, rubix.jpg, achtergrond.jpg, geeve-logo.svg, favicon.ico
│   ├── hoses/   adapters/   stauff/   productafbeeldingen
└── <app>/...                      code + eigen assets/style.css

/var/www/geeve-data/               NIEUW: serverdata, NIET in git, NIET in de webroot
├── csv/
│   ├── hoses/        artikelnummers_staal.csv, _rvs.csv, _accessoires.csv
│   ├── adapters/     artikelnummers_adapters.csv
│   └── configurator/ artikelnummers.csv, slangkoppelingen.csv
├── backups/<app>/                 automatische back-ups bij opslaan/upload
├── uploads/                       (later) door gebruikers geüploade afbeeldingen
└── settings/                      (laatste, optionele fase) .settings.key / .settings.enc
```

Waarom zo:

- **Buiten de webroot** voor serverdata: niet via de browser op te halen zonder per map een
  `.htaccess` te moeten onthouden (hoses en adapters blokkeren `.csv` nu zo, configurator niet),
  en `git pull` raakt het nooit.
- **`media/` binnen de webroot en in git** voor afbeeldingen die bij de code horen: die moeten via
  een URL bereikbaar zijn en veranderen alleen met een commit. Eén plek, dus Stauff, Slangkaart
  printen of een nieuwe app kunnen dezelfde slang- of adapterafbeelding gebruiken.
- De locatie van de datamap is instelbaar met de omgevingsvariabele **`GEEVE_DATA_DIR`**
  (standaard `/var/www/geeve-data`), net als het bestaande `GEEVE_SETTINGS_KEY_FILE`.

## 4. Centrale paden: `shared/paths.php`

Eén include die elke app gebruikt in plaats van eigen `csv-paths.php`-bestanden. Schets:

```php
// Volgorde: centrale datamap -> huidige app/data -> app-root (zoals nu).
function geeveDataDir(): ?string;                       // GEEVE_DATA_DIR of /var/www/geeve-data, null als die niet bestaat
function geeveDataFile(string $app, string $file): ?string;   // eerste leesbare kandidaat
function geeveDataWritePath(string $app, string $file): string; // centraal als schrijfbaar, anders de huidige plek
function geeveBackupDir(string $app): string;           // centraal/backups/<app> of <app>/data/backups
function geeveMediaUrl(string $relPath): string;         // URL naar media/ vanaf de huidige pagina, met ?v=mtime
```

- **Lezen** probeert eerst de centrale map en valt terug op precies de huidige kandidaten
  (`<app>/data/x.csv`, dan `<app>/x.csv`). Bestaat `/var/www/geeve-data` niet, dan verandert er dus
  niets.
- **Schrijven** (opslaan, upload, back-up) gaat naar de plek waar het bestand gelezen werd. Zo kan
  een bewerking nooit "verdwijnen" doordat lezen en schrijven verschillende bestanden pakken.
- `hoses/inc/csv-paths.php`, `adapters/inc/csv-paths.php` en `configurator/inc/csv-paths.php`
  blijven bestaan als dun laagje dat `shared/paths.php` aanroept; de rest van de code hoeft niet
  in één keer om.
- `slkprinten/index.php` vervangt `__DIR__ . '/../hoses/data/'` door
  `geeveDataFile('hoses', ...)`.
- Geen `mb_*`-functies (de server heeft geen mbstring).

## 5. Uniforme CSS en layout-instellingen

### 5.1 `shared/tokens.css`

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

### 5.2 `shared/head.php`

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

### 5.3 App-CSS per app omzetten

Per app de losse hex-/rgba-waarden vervangen door tokens, één app per commit, en daarna in de
browser naast de oude versie vergelijken (screenshots van elke tegel vóór en na). Regels voor
daarna, als aanvulling op CLAUDE.md:

- Geen losse kleurwaarde meer in `<app>/assets/*.css`; een nieuwe kleur = eerst een token.
- App-eigen regels (secties, stappen, statuspillen) blijven per app, maar halen hun waarden uit tokens.
- Stickeropmaak (mm-maten voor labels) blijft per sticker; alleen kleuren gaan naar tokens.

### 5.4 Layout-instellingen via Config (optioneel, later)

Als je kleuren of breedtes zonder commit wilt kunnen aanpassen: Config schrijft een klein
`geeve-data/layout/overrides.css` (alleen `:root { --x: ...; }`), en `shared/head.php` laadt dat
als laatste via `shared/layout.php`. Zonder dat bestand gelden gewoon de waarden uit
`tokens.css`. Dit is een aparte stap; eerst moet alles via tokens lopen, anders heeft een
instelling geen effect.

## 6. Migratie in fasen (zonder iets te breken)

Elke fase is los uit te rollen met `git pull` en los terug te draaien.

| Fase | Wat | Effect op de server | Terugdraaien |
|---|---|---|---|
| 1 | `shared/paths.php` + de drie `csv-paths.php` en `slkprinten` erop laten leunen | Geen: centrale map bestaat nog niet, dus alles leest/schrijft waar het nu staat | Commit revert |
| 2 | Op de server: `/var/www/geeve-data/` aanmaken, eigenaar `www-data`, en de **huidige server-CSV's en back-ups ernaartoe kopiëren** (kopiëren, niet verplaatsen) | Apps lezen en schrijven vanaf nu centraal | Map hernoemen: alles valt terug op de oude bestanden |
| 3 | CSV's uit git halen: `git rm --cached`, `.gitignore`-regel, en een `data-voorbeeld/` met de huidige inhoud voor een nieuwe installatie | Pas uitvoeren ná fase 2. Let op: een `git pull` verwijdert dan de oude kopieën in `<app>/data/`, de centrale kopie blijft | Bestanden staan nog in de git-geschiedenis en in `geeve-data` |
| 4 | `shared/tokens.css` + `shared/head.php`; `style.css` importeert tokens tijdelijk | Visueel niets | Revert |
| 5 | Per app: `head.php` gebruiken en losse kleuren naar tokens | Per app te controleren | Revert per app |
| 6 | Afbeeldingen naar `media/` (git mv) met `geeveMediaUrl()`; `$headerImagesPath` vervalt | Oude URL's (`hoses/images/x.png`) blijven werken via een `.htaccess`-redirect of door de mappen tijdelijk te laten staan | Revert |
| 7 (optioneel) | `.settings.*` naar `geeve-data/settings/` via `GEEVE_SETTINGS_KEY_FILE` + nieuw `GEEVE_SETTINGS_DATA_FILE`, met terugval op de root | Na kopiëren werkt het centraal; oude bestanden pas weg na controle | Variabelen weghalen |
| 8 (optioneel) | Layout-instellingen in Config (5.4) | | |

Na fase 2 de oude `<app>/data/*.csv` nog **niet** weggooien; die blijven tot en met fase 3 de
terugvaloptie.

Het overgebleven `.env` in de root wordt al niet meer gelezen (zie Config) en kan los van dit
voorstel verwijderd worden.

## 7. Slangkaart.Web later aansluiten

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
   - **a.** Slangkaart.Web leest direct uit `GEEVE_DATA_DIR/csv/` (de dienst krijgt alleen
     leesrechten via een groep), of
   - **b.** een klein endpoint in de portal, bijvoorbeeld `shared/api/selectordata.php?selector=hoses`,
     dat dezelfde JSON levert als de huidige momentopnamen; Slangkaart.Web haalt die op in plaats van
     een bestand mee te leveren.

   Optie b is mijn voorkeur: geen gedeelde bestandsrechten tussen twee diensten, en het formaat
   is al bekend aan beide kanten.

## 8. Vragen voor jou

1. **Locatie datamap**: akkoord met `/var/www/geeve-data` (buiten de webroot), of liever een
   andere plek, bijvoorbeeld `/srv/geeve`?
2. **CSV's uit git (fase 3)**: wil je dat, of moeten ze in git blijven als "standaardversie"?
   Uit git is veiliger voor `git pull`; in git houdt een geschiedenis bij.
3. **Configurator**: blijft dat een eigen, ingekorte kopie, of moet die voortaan de centrale
   slangen-CSV van de Slangen fitting Selector lezen?
4. **Layout via Config (fase 8)**: nodig, of is "kleuren alleen via `tokens.css` en een commit"
   genoeg?
5. **Huisstijl Slangkaart.Web**: later één gezamenlijke kleurset met Hans, of houdt elke app zijn
   eigen palet en delen we alleen de structuur van de variabelen?

## 9. Wat ik als eerste zou bouwen

Fase 1 en 4 samen: allebei onzichtbaar voor de gebruiker, allebei zonder serverwerk, en daarna
kan elke volgende stap klein en per app.
