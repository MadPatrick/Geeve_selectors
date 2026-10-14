# Werkafspraken voor Claude in deze repo

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

## Tabellen: compacte regelafstand (standaard)

Datatabellen in tegels gebruiken **standaard** de gedeelde tabelopmaak `.ka-table` (met `.ka-input` voor
invoervelden in een cel) uit `klantartikel/assets/style.css`. Die heeft een compacte regelafstand:
`td` met 3px verticale padding en invoervelden in de tabel van 28px hoog (`min-height: 28px`, 2px
verticale padding). Geef nieuwe tabellen dus geen eigen, ruimere celpadding of hogere invoervelden;
eigen tabelregels per app (kolombreedtes, statuskleuren) mogen wel, de regelafstand blijft gelijk.

## Knoppen: groen = lichtgroen, Print = gedeelde knop (standaard)

- **Alle groene knoppen** (Gegevens ophalen, Wissen/Kiezen, Exporteer, Kopieer, ...) krijgen dezelfde lichtgroene
  achtergrond als de Print-knop: `var(--btn-green)` (`#c6efd2`), hover `var(--btn-green-hover)`, met donkergroene rand en
  tekst (`--ok-line`/`--ok-soft-line`, `--ok-ink`). Geen eigen groentinten of `--ok-fill` voor knoppen; `--ok-fill` is
  alleen voor statuspillen ("ready").
- **Elke Print-knop** gebruikt de gedeelde `.print-btn` uit `shared/style.css`: lichtgroen, printer-icoon ervoor, tekst
  "Print" (markup in `shared/README.md`). Geen eigen printknopstijl per app.

## Versienummer (alle apps)

Eén gedeeld versienummer in `version.php` (root) voor het hoofdscherm en alle subapps - zie
`shared/README.md`, "Versienummer". **Hoog het versienummer nooit uit jezelf op**: alleen als de
gebruiker daar expliciet om vraagt (de gebruiker geeft zelf aan wanneer). Wijzig `version.php` dus
niet bij een gewone feature/fix-commit.

## Git

Commits gaan direct naar `main` (geen PR), tenzij de gebruiker expliciet om een aparte branch of
PR vraagt. Altijd `git fetch origin main` + vergelijken vóór het committen; bij een conflict
rebasen, nooit force-pushen.
