# Werkafspraken voor Claude in deze repo

## Voorwaarde voor elke nieuwe app: altijd de gedeelde onderdelen gebruiken

Elke app draait **altijd op deze Geeve-server**, naast de portal. Daarom is dit een **voorwaarde, zonder dat Patrick het
apart hoeft te vragen**: een nieuwe app houdt vanaf de eerste regel rekening met de gedeelde settings, lay-out/CSS,
centrale CSV-map en printfunctie. Er wordt niets vanzelf geladen; de app moet dit zelf inladen/registreren:

- **Lay-out en CSS:** `shared/head.php` (met `shared/style.css` en `shared/tokens.css`) en `shared/header.php`; voor
  slangkaarten ook `shared/slangkaart.css`. Geen eigen kleuren, header of knopstijlen.
- **Versienummer:** `version.php` (root) via `APP_VERSION`.
- **Data (CSV's):** alleen via `shared/paths.php` in `data/<app>/`, niet in git.
- **Settings en toegang:** database-inloggegevens via `secureSettingsRead()` (`shared/secure_settings.php`), nooit eigen
  bestanden; tegel en toegangscode via `TILE_REGISTRY` (`shared/tiles.php`), `tileApiGuard()`/`accessApiGuard()`.
- **Printen:** `shared/printen.php` (`geevePrinters()`, `geevePrintHtml()`), geen eigen printkoppeling of eigen
  printerlijst; inlog alleen via `/etc/geeve/smbprint.cred`.
- **Adressen en registratie:** `geeveUrl()`/`geeveAppUrl()`, en `TILE_REGISTRY` + `GEEVE_APP_URLS` bijwerken.
- Server-eisen: geen `mb_*`, `AllowOverride All` voor de portalmap.

Volg [`docs/HERGEBRUIK-SHARED.md`](docs/HERGEBRUIK-SHARED.md) (inladen, voorbeeld, printen, data, valkuilen, checklist).
Wijk alleen af na overleg met Patrick.

## Nieuwe (sub)app toevoegen - gedeelde layout is de standaard

Elke nieuwe app in deze portal gebruikt **standaard, zonder dit te hoeven vragen**, de gedeelde
layout: `shared/style.css` (vóór de eigen `assets/style.css`), `shared/header.php` voor de
header/brand-panel, en het gedeelde `version.php` (root) voor `APP_VERSION` - nooit een eigen,
losse kopie van tokens/header-markup/versienummer per app.

**Volg hiervoor altijd [`shared/README.md`](shared/README.md)** - de complete stap-voor-stap gids,
checklist en volledige CSS-/`header.php`-referentie. Lees dat document bij het opzetten van een
nieuwe app, ook als er niet expliciet naar gevraagd wordt.

Wat wél per app hoort (zie `shared/README.md`, "Wat hoort in je eigen `assets/style.css`"): eigen
secties/kaarten, stappenindicatoren, statuspillen e.d. - dat blijft bewust ongedeeld, ook al lijkt
het oppervlakkig op wat een andere app heeft.

## Hergebruik van `shared/` door een andere app

Wil een (losstaande) app de printfunctie, de centrale CSV's of de gedeelde layout/CSS gebruiken, volg dan
[`docs/HERGEBRUIK-SHARED.md`](docs/HERGEBRUIK-SHARED.md): inladen, minimaal voorbeeld, printen, data lezen, vereisten en
valkuilen. Zet daar nooit wachtwoorden in.

## Data, adressen en CSS-variabelen (standaard)

- **Data** (CSV's en back-ups) staat centraal in `data/<app>/` en `data/backups/<app>/`, **niet in git**. Lezen en
  schrijven altijd via `shared/paths.php` (`geeveDataCandidates()`, `findFirstReadableFile()`,
  `geeveDataWriteTarget()`, `geeveBackupDir()`), nooit met een hard pad naar de map van een andere app. Zet geen
  CSV's terug in git.
- **Adressen:** links naar een andere app of een overzicht via `geeveAppUrl('<tegel-id>')`, naar een bestand in een
  andere map via `geeveUrl('<pad vanaf de root>')`; geen `../` naar een buurmap. Een nieuwe of verplaatste app:
  `TILE_REGISTRY` en `GEEVE_APP_URLS` bijwerken. Zie `shared/README.md`, "Adressen en links".
- **CSS-variabelen:** alle kleuren en maten komen uit `shared/tokens.css`; geen losse hex-/rgba-waarden in een eigen
  `assets/style.css`. Een nieuwe kleur = eerst een nieuwe variabele in `tokens.css`. Nieuwe apps laden hun CSS via
  `shared/head.php`.
- **Slangkaart-layout:** de layout van Slangkaart printen is de standaard voor elke slangkaart-app:
  `shared/slangkaart.css` + `shared/SLANGKAART-LAYOUT.md`. Niet per app opnieuw maken of afwijken zonder overleg.

## Tabellen: compacte regelafstand (standaard)

Datatabellen in tegels gebruiken **standaard** de gedeelde tabelopmaak `.ka-table` (met `.ka-input` voor
invoervelden in een cel) uit `klantartikel/assets/style.css`. Die heeft een compacte regelafstand:
`td` met 3px verticale padding en invoervelden in de tabel van 28px hoog (`min-height: 28px`, 2px
verticale padding). Geef nieuwe tabellen dus geen eigen, ruimere celpadding of hogere invoervelden;
eigen tabelregels per app (kolombreedtes, statuskleuren) mogen wel, de regelafstand blijft gelijk.

## Knoppen: alle kleuren uit variabelen (standaard)

**Per knopsoort staan vaste variabelen in [`docs/KNOPPEN.md`](docs/KNOPPEN.md)** (Print = `--btn-print*`, PDF = `--btn-pdf*`,
Hoofdactie = `--btn-primary*`, Tweede keuze = `--btn-secondary*`, Groen = `--btn-green*`, ...). Kies voor een nieuwe knop een
soort uit die tabel en draai daarna `php tools/check-knoppen.php`.

- **Elke knopkleur komt uit een `--btn-*`-variabele** in `shared/style.css` (`:root`): achtergrond, rand, hover en
  tekst/icoon. Geen losse hex-/rgba-waarden of `--accent-dark`/`--ok-fill` in knopregels; een nieuwe kleur = eerst een
  nieuwe `--btn-*`-variabele. Overzicht: `--btn-green` (+ `-hover`), `--btn-red` (+ `-line`), `--btn-grey` (+ `-line`),
  `--btn-white` (+ `-line`, `-hover`), `--btn-blue` (+ `-line`, keuzeschakelaar), `--btn-danger*`, `--btn-glass*`
  (header-knoppen), `--btn-save*`/`--btn-cancel*`, `--btn-config*`, `--btn-ink-light` (witte tekst op gekleurde knop).
- **Groen = lichtgroen**: alle groene knoppen (Gegevens ophalen, Wissen/Kiezen, Exporteer, Kopieer, ...) gebruiken
  `var(--btn-green)` (`#c6efd2`), zelfde als de Print-knop, met donkergroene rand en tekst. `--ok-fill` is alleen voor
  statuspillen ("ready").
- **Rand = altijd donkerder dan de vulling**: elke knop heeft dezelfde randdikte (1px) en een duidelijk zichtbare rand in
  een donkerdere tint van de eigen kleur (`--btn-*-line`); alleen de kleur verschilt per knop. Dat geldt ook voor de rode
  knop (`--btn-red-line` is donkerrood).
- **Rood = één rood**: er is geen donkerrode knop meer; rode knoppen (ook de vroegere donkerrode zoals Opzoeken, Upload,
  Ontgrendelen) gebruiken `var(--btn-red)`; omlijnde knoppen (PDF, Terug, Download) zijn wit met `--btn-red-line` en
  worden bij hover `--btn-red`.
- **Elke Print-knop** gebruikt de gedeelde `.print-btn` uit `shared/style.css`: lichtgroen, printer-icoon ervoor, tekst
  "Print" (markup in `shared/README.md`). Geen eigen printknopstijl per app.

## Browsercompatibiliteit (Patrick 2026-10-22)

Bij **elke aanpassing** aan een app of tegel moet het werken in Chrome, Firefox en Edge (Chromium) van de laatste jaren.
Internet Explorer en Edge Legacy hoeven **niet** meer te werken (Patrick 2026-10-22). Gebruik geen CSS/JS-functies die in die
browsers (of in browsers van ~2019/2020) niet werken zonder fallback. Draai vóór elke push:

- `php tools/check-browsers.php` - statische scan op bekende incompatibele patronen (pijlfuncties, `??`, `?.`, `:has`, `gap`,
  `var()` zonder fallback, `mb_*`, ...). Er staat een baseline in `tools/check-browsers-baseline.json`: nieuwe vondsten
  (exitcode 1) los je op of motiveer je; standaard `--doel=modern`, strenger kan met `--doel=edge` of `--doel=ie11`. `mb_*` mag nooit. Snel op alleen je wijzigingen: `--gewijzigd`.
- `node tools/browsertest.js` - rooktest in headless Chromium (zie het bestand voor het starten van `php -S`).
  Firefox/WebKit alleen als Playwright die lokaal heeft; IE11 en Edge Legacy zijn niet testbaar (en niet meer vereist).
- Meld in de PR wat gecontroleerd is en wat niet (met name: niet in een echte IE/Firefox/Edge getest).

## Versienummer (alle apps)

Eén gedeeld versienummer in `version.php` (root) voor het hoofdscherm en alle subapps - zie
`shared/README.md`, "Versienummer". Opbouw `0.4.5.1` = `<major>.<minor>.<patch>.<build>`:
- **Build (laatste nummer):** hoog je **automatisch met 1 op** bij elke push naar GitHub met een wijziging (één keer
  per push, ook als die meerdere commits bevat).
- **De eerste drie nummers** nooit uit jezelf: alleen als de gebruiker daar expliciet om vraagt. Bij zo'n ophoging
  begint de build weer bij 1 (bijv. 0.4.6.1).

## Git

Commits gaan direct naar `main` (geen PR), tenzij de gebruiker expliciet om een aparte branch of
PR vraagt. Altijd `git fetch origin main` + vergelijken vóór het committen; bij een conflict
rebasen, nooit force-pushen.
