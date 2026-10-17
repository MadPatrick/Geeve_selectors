# Voorstel: centrale data, gedeelde media en uniforme CSS

*Geeve Intranet (Geeve_selectors), 17-10-2026. Alleen een voorstel, er is nog niets gebouwd.
Branch: `fixes/centrale-data-en-css-5v24fm`.*

*Versie 2 (17-10-2026 08:30): op verzoek van Patrick blijven de CSV's in git, in `/var/www/html/data`;
lokale wijzigingen op de server mogen niet door een `git pull` overschreven worden (§3 en §4.1).*

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
verloren. Wat op de server bewerkt wordt, moet dus in een bestand staan dat git niet beheert (§4.1).

## 3. Voorgestelde indeling

Alles binnen de bestaande checkout, op twee plekken:

```
/var/www/html/                     (git, zoals nu)
├── data/                          NIEUW: centrale data voor alle apps
│   ├── hoses/                     artikelnummers_staal.csv, _rvs.csv, _accessoires.csv   (in git)
│   ├── adapters/                  artikelnummers_adapters.csv                             (in git)
│   ├── configurator/              artikelnummers.csv, slangkoppelingen.csv                (in git)
│   ├── local/                     NIET in git (.gitignore): wijzigingen op de server
│   │   ├── hoses/ adapters/ configurator/   zelfde bestandsnamen, overschrijven de git-versie
│   └── backups/<app>/             NIET in git: automatische back-ups bij opslaan/upload
│   └── .htaccess                  blokkeert alles in data/ voor de browser
├── shared/
│   ├── tokens.css                 NIEUW: alleen :root-variabelen (kleur, maat, afstand, lettertype)
│   ├── style.css                  gedeelde regels, gebruikt alleen variabelen
│   ├── head.php                   NIEUW: favicon + tokens.css + style.css met cache-busting
│   ├── paths.php                  NIEUW: geeveDataFile(), geeveDataWritePath(), geeveMediaUrl()
│   └── header.php                 gebruikt geeveMediaUrl() i.p.v. $headerImagesPath
├── media/                         NIEUW: statische, gedeelde afbeeldingen (in git, via web)
│   ├── brand/                     geeve.jpg, rubix.jpg, achtergrond.jpg, geeve-logo.svg, favicon.ico
│   ├── hoses/   adapters/   stauff/   productafbeeldingen
└── <app>/...                      code + eigen assets/style.css
```

Waarom zo:

- **`data/` in git** zoals Patrick voorstelt: de versie in git is de standaardversie, met
  geschiedenis, en een verse checkout werkt meteen.
- **`data/local/` niet in git** voor wat op de server bewerkt wordt (zie §4.1 waarom dat nodig is
  om `git pull` veilig te houden).
- **Eén `data/.htaccess`** die alles weigert, in plaats van per app een `.htaccess` te moeten
  onthouden (hoses en adapters blokkeren `.csv` nu zo, configurator niet).
- **`media/` binnen de webroot en in git** voor afbeeldingen die bij de code horen: die moeten via
  een URL bereikbaar zijn en veranderen alleen met een commit. Eén plek, dus Stauff, Slangkaart
  printen of een nieuwe app kunnen dezelfde slang- of adapterafbeelding gebruiken.
- Later kan de map nog naar buiten de webroot of buiten git: dan verandert alleen `shared/paths.php`
  (eventueel via een omgevingsvariabele `GEEVE_DATA_DIR`), geen enkele app.

## 4. Centrale paden: `shared/paths.php`

Eén include die elke app gebruikt in plaats van eigen `csv-paths.php`-bestanden. Schets:

```php
// Leesvolgorde: data/local/<app> -> data/<app> -> <app>/data -> <app>/ (de laatste twee = huidige paden).
function geeveDataDir(): string;                        // /var/www/html/data (later instelbaar)
function geeveDataFile(string $app, string $file): ?string;   // eerste leesbare kandidaat
function geeveDataWritePath(string $app, string $file): string; // data/local/<app>/ als data/ bestaat, anders de huidige plek
function geeveBackupDir(string $app): string;           // data/backups/<app> of <app>/data/backups
function geeveMediaUrl(string $relPath): string;         // URL naar media/ vanaf de huidige pagina, met ?v=mtime
```

- **Lezen** pakt de eerste die bestaat: de lokale serverversie, dan de git-versie in `data/`, dan
  precies de huidige kandidaten (`<app>/data/x.csv`, `<app>/x.csv`). Zolang `data/` nog leeg is,
  verandert er dus niets.
- **Schrijven** (opslaan via "Gegevens wijzigen", upload) gaat altijd naar `data/local/<app>/`,
  nooit naar een bestand dat git beheert. De eerste keer kopieert de app daarvoor de git-versie.
  Lezen pakt daarna vanzelf die lokale versie, dus een bewerking kan niet "verdwijnen".
- `hoses/inc/csv-paths.php`, `adapters/inc/csv-paths.php` en `configurator/inc/csv-paths.php`
  blijven bestaan als dun laagje dat `shared/paths.php` aanroept; de rest van de code hoeft niet
  in één keer om.
- `slkprinten/index.php` vervangt `__DIR__ . '/../hoses/data/'` door
  `geeveDataFile('hoses', ...)`.
- Geen `mb_*`-functies (de server heeft geen mbstring).

### 4.1 Waarom `data/local/` en niet "data/ uitsluiten van git pull"

Git kan een getrackt bestand niet betrouwbaar overslaan bij een `pull`:

- `git update-index --skip-worktree` of `--assume-unchanged` werkt alleen zolang niemand het
  bestand in git wijzigt. Komt er wél een wijziging binnen, dan stopt `git pull` met "local changes
  would be overwritten" en blijft de hele update hangen.
- Sparse-checkout (`data/` niet uitchecken) is sinds git 2.37 ook niet betrouwbaar: staat het
  bestand toch in de map, dan haalt git de uitzondering er weer af.
- `.gitignore` werkt alleen voor bestanden die níet in git staan.

Daarom twee lagen: **git beheert `data/<app>/`, de server schrijft alleen in `data/local/<app>/`**
(in `.gitignore`). Een `git pull` werkt dan altijd, en raakt de serverbewerkingen nooit.

Gevolgen om te weten:

- Zodra een bestand een lokale versie heeft, zie je een nieuwere git-versie van datzelfde bestand
  niet meer. Voorstel: de datapagina van de app toont "lokale versie, gewijzigd op ..." en, als de
  git-versie nieuwer is, een melding plus een knop **"Terug naar standaardversie"** (die de lokale
  kopie naar `data/backups/` verplaatst).
- Een serverbewerking terug in git krijgen gaat zoals nu: downloaden via de app (of de server-Claude
  kopieert `data/local/x.csv` naar `data/x.csv`) en committen. Daarna kan de lokale kopie weg.

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
`data/local/layout/overrides.css` (alleen `:root { --x: ...; }`), en `shared/head.php` laadt dat
als laatste via `shared/layout.php`. Zonder dat bestand gelden gewoon de waarden uit
`tokens.css`. Dit is een aparte stap; eerst moet alles via tokens lopen, anders heeft een
instelling geen effect.

## 6. Migratie in fasen (zonder iets te breken)

Elke fase is los uit te rollen met `git pull` en los terug te draaien.

| Fase | Wat | Effect op de server | Terugdraaien |
|---|---|---|---|
| 1 | `shared/paths.php` + de drie `csv-paths.php`, `save.php`/`upload.php` en `slkprinten` erop laten leunen; `data/.htaccess` en `.gitignore`-regels voor `data/local/` en `data/backups/` | Geen: `data/` heeft nog geen CSV's, dus alles leest en schrijft waar het nu staat | Commit revert |
| 2 | **Eerst de huidige serverversies veiligstellen**: de server-Claude kopieert `hoses/data/*.csv`, `adapters/data/*.csv` en `configurator/data/*.csv` naar `data/local/<app>/` (en de back-ups naar `data/backups/<app>/`) | Apps lezen nu de lokale kopie, inhoud gelijk aan nu | `data/local/` leegmaken |
| 3 | In git: CSV's met `git mv` van `<app>/data/` naar `data/<app>/`, met als inhoud de actuele serverversie (zodat git weer bij is) | `git pull` op de server: kan pas na fase 2, want de server heeft lokale wijzigingen in `<app>/data/*.csv`; die eenmalig met `git checkout -- <app>/data` terugzetten (de inhoud staat dan al veilig in `data/local/`) | Revert; kopieën staan nog in `data/local/` |
| 4 | `shared/tokens.css` + `shared/head.php`; `style.css` importeert tokens tijdelijk | Visueel niets | Revert |
| 5 | Per app: `head.php` gebruiken en losse kleuren naar tokens | Per app te controleren | Revert per app |
| 6 | Afbeeldingen naar `media/` (git mv) met `geeveMediaUrl()`; `$headerImagesPath` vervalt | Oude URL's (`hoses/images/x.png`) blijven werken via een `.htaccess`-redirect | Revert |
| 7 (optioneel) | Datapagina's: "lokale versie"-melding en "Terug naar standaardversie" (§4.1) | | Revert |
| 8 (optioneel) | Layout-instellingen in Config (5.4) | | |

Fase 2 en 3 horen samen bij één update: eerst 2 op de server, direct daarna de `git pull` van 3.

De `.settings.*`-bestanden blijven waar ze zijn (root, niet in git); ze kunnen later naar
`data/local/` via het bestaande `GEEVE_SETTINGS_KEY_FILE`, maar dat is geen onderdeel van dit
voorstel. Het overgebleven `.env` in de root wordt al niet meer gelezen (zie Config) en kan los van
dit voorstel verwijderd worden.

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
   - **a.** Slangkaart.Web leest direct uit `/var/www/html/data/` (met dezelfde volgorde:
     `local/` eerst; de dienst krijgt alleen leesrechten), of
   - **b.** een klein endpoint in de portal, bijvoorbeeld `shared/api/selectordata.php?selector=hoses`,
     dat dezelfde JSON levert als de huidige momentopnamen; Slangkaart.Web haalt die op in plaats van
     een bestand mee te leveren.

   Optie b is mijn voorkeur: geen gedeelde bestandsrechten tussen twee diensten, en het formaat
   is al bekend aan beide kanten.

## 8. Vragen voor jou

1. ~~Locatie datamap~~ en ~~CSV's uit git~~: besloten, `/var/www/html/data` in git, met
   `data/local/` voor serverwijzigingen (17-10-2026).
2. **"Terug naar standaardversie" (fase 7)**: wil je die knop, of is "lokale versie wint altijd,
   bijwerken via downloaden en committen" genoeg?
3. **Configurator**: blijft dat een eigen, ingekorte kopie, of moet die voortaan de centrale
   slangen-CSV van de Slangen fitting Selector lezen?
4. **Layout via Config (fase 8)**: nodig, of is "kleuren alleen via `tokens.css` en een commit"
   genoeg?
5. **Huisstijl Slangkaart.Web**: later één gezamenlijke kleurset met Hans, of houdt elke app zijn
   eigen palet en delen we alleen de structuur van de variabelen?

## 9. Wat ik als eerste zou bouwen

Fase 1 en 4 samen: allebei onzichtbaar voor de gebruiker, allebei zonder serverwerk. Daarna fase 2
en 3 als één update met de server-Claude, en vervolgens per app de CSS.
