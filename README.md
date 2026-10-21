# Geeve Hydraulics — Intranet-portal

Startpagina met tegels naar de interne apps van Geeve Hydraulics: selectors, slangkaarten,
stickers, rapporten en Excel/XML-templates. Eén PHP-portal, één repository: alle apps leven hier,
er zijn geen losse bronrepo's meer.

De .NET-app **Slangkaart.Web** (repo Rubix-BNL/slangkaart-web) staat apart en is geen onderdeel
van deze portal. Apache stuurt `/slangkaarten/` door naar die app.

**Inhoud**

1. [Apps](#1-apps)
2. [Mappen](#2-mappen)
3. [Installeren en bijwerken](#3-installeren-en-bijwerken)
4. [Centrale data](#4-centrale-data)
5. [Layout en CSS](#5-layout-en-css)
6. [Database-koppeling Exact](#6-database-koppeling-exact)
7. [Versienummer](#7-versienummer)

---

## 1. Apps

### Hoofdscherm

| Tegel | Adres | Map | Database | Wat het doet |
|---|---|---|---|---|
| Slangen fitting Selector | `/hoses/` | `hoses/` | nee | Slang en koppelingen kiezen; data in `data/hoses/` |
| Adapters Selector | `/adapters/` | `adapters/` | nee | Adapter zoeken op draad en vorm; data in `data/adapters/` |
| Slangkaarten Printen | `/slkprinten/` | `slkprinten/` | ja | Order of klant opzoeken en slangkaarten + picklijst printen (zie `slkprinten/README.md`) |
| Slangkaarten | `/slangkaarten/` | — | — | Slangkaart.Web (aparte .NET-app, via Apache) |
| Stickers | `/stickers/` | `stickers/` | — | Overzicht met stickertegels, zie hieronder |
| Stauff Selector | `/stauff/` | `stauff/` | ja | Beugelconfigurator op Exact artikelgroep 67, "Naar Exact" via Slangkaart.Web |
| Gebogen buis | `/gebogenbuis/` | `gebogenbuis/` | nee | Prijsberekening gebogen buis: klant + klantkorting (kortingstructuur van de prijslijst), artikelen met bruto prijs en vrije voorraad, montage, notities; in opbouw |
| Reports | `/reports/` | `reports/` | — | Overzicht met rapporten, zie hieronder |
| Templates | `/templates/` | `templates/` | — | Overzicht met Excel/XML-templates, zie hieronder |

Zonder tegel: **Slang configurator** (`/configurator/`), een testapp die nog afgemaakt moet
worden. Leest de slangen uit `data/hoses/` en zijn eigen `data/configurator/slangkoppelingen.csv`.

### Reports

| Tegel | Adres | Wat het doet |
|---|---|---|
| MSP report | `/reports/msp/` | MSP-factuurregels van een maand (zelfde berekening als `dbo.SP_MSP`), als Excel |
| Inkoop per Artikelgroep | `/reports/inkoop-groep/` | Ingekochte aantallen per artikel van een artikelgroep in een periode |
| Productiviteit | `/reports/productiviteit/` | Beveiligd met een toegangscode |
| Artikelmarges | `/reports/artikel-marge/` | Verkoopprijs, inkoopprijs en marge |

### Templates

| Tegel | Adres | Wat het doet |
|---|---|---|
| Klant Artikelnummers | `/klantartikel/` | Debiteuren op een prijslijst met hun klantartikelnummers; Excel in, XML (DEBTORS.xml) uit |
| Kortingstructuur | `/klantkorting/` | Kortingstructuur per prijslijst (in opbouw) |
| Inkooporder naar Excel | `/reports/inkooporder/` | Inkooporder met regels uit Exact ophalen en als Excel downloaden |
| Order of Offerte naar Excel | `/reports/order-offerte/` | Order of offerte als Excel |
| Offerte Import | `/reports/offerte-import/` | Excel-offerte controleren met Exact en als XML exporteren |
| Inkooporder Import | `/reports/inkooporder-import/` | Idem voor inkooporders |
| Export info | `/reports/export-info/` | Orderregels met collo, gewicht en afmetingen voor de exportdocumenten |
| Artikelgegevens Import | `/reports/artikel-import/` | Artikelgegevens controleren met Exact en als XML (`<Items>`) exporteren |
| Orderhistorie | `/reports/orderhistorie/` | Geleverde orderregels per klant sinds de eerste order, filter per kolom, Download Excel |

### Stickers

Alle stickers printen via de browser, één sticker per pagina. Posities en maten staan in
`stickers/assets/<sticker>.css`.

| Tegel | Adres | Formaat | Gegevens uit |
|---|---|---|---|
| Artikelen per Verkooporder | `/stickers/order/` | 102 × 54 mm | `[005_Data_Geeve].[dbo].[temp_Artikel_Stikkers_order]` |
| Artikelen per Productieorder | `/stickers/productie/` | 102 × 54 mm | stored procedure `[005_Data_Geeve].[dbo].[SP_productie_label]` (EXECUTE-recht nodig) |
| Artikelnummer | `/stickers/artikel/` | — | `[Exactrapportage].[dbo].[1002_Artikellabels]` |
| Accu's | `/stickers/accu/` | 80 × 30 mm | geen database (invulsticker) |
| Slanglabels | `/stickers/slanglabels/` | ALTEC 53000145 | `[Exactrapportage].[dbo].[1655 VW_Alle_OrdersGH]` |
| P-Touch | `/stickers/ptouch/` | 102 × 51 mm | geen database (vrije tekst, Arial Black 40 pt, schaalt kleiner) |
| Kabelmerkers Geel | — | — | nog niet gebouwd |

Barcodes zijn Code 128 (JsBarcode, lokaal in `stickers/assets/vendor`).

### Tegels instellen

In **Config** (tandwiel op het hoofdscherm) stel je per tegel in: zichtbaar, beveiligd met een
toegangscode, "in progress", eigen naam en volgorde. De lijst van alle tegels staat in
`shared/tiles.php` (`TILE_REGISTRY`).

---

## 2. Mappen

```
index.php, update.php      Hoofdscherm en Config
version.php                Eén versienummer voor alle apps
.htaccess                  Dotfiles afsluiten
shared/                    Gedeeld door alle apps - zie shared/README.md
  tokens.css               Alle CSS-variabelen (kleuren, maten, lettertypes)
  style.css                Gedeelde opmaak (importeert tokens.css)
  head.php, header.php     <head>-links en de zwarte header
  paths.php                Centrale data en adressen van de apps
  tiles.php, access.php    Tegels, toegangscodes
  secure_settings.php      Versleutelde instellingen uit Config
  slangkaart.css           Vastgelegde slangkaart-layout, zie SLANGKAART-LAYOUT.md
data/                      Centrale data (CSV's, back-ups) - NIET in git, zie hoofdstuk 4
tools/                     Beheerscripts (niet via de browser)
assets/, images/           Opmaak en logo's van het hoofdscherm
docs/                      Referentie-PDF's en voorstellen; wordt niet door de apps gelezen
hoses/ adapters/ stauff/ slkprinten/ configurator/ klantartikel/ klantkorting/
reports/ templates/ stickers/   De apps, elk met eigen index.php, assets/, api/ ...
slangkaarten/              Plaatshouder; het adres /slangkaarten/ is van Slangkaart.Web
```

---

## 3. Installeren en bijwerken

**Server:** Debian, Apache met PHP 8 in `/var/www/html` (een git-checkout van deze repo). PHP
heeft geen `mbstring`; gebruik nergens `mb_*`-functies.

**Bijwerken:** `git pull` in `/var/www/html`. Er is geen update-knop meer in Config.

**Alleen op de server, niet in git:**

| Wat | Waar |
|---|---|
| Database-inloggegevens, tegelinstellingen, toegangscode | `.settings.key` + `.settings.enc` (via Config) |
| CSV's en hun back-ups | `data/` (zie hoofdstuk 4) |
| Lokfiles van SentinelOne | `.aaa.*` (onschuldig, genegeerd) |

`.env`-bestanden worden niet meer gelezen en mogen weg. De root-`.htaccess` blokkeert alle
dotfiles voor de browser; dat blok moet blijven staan.

**Eenmalig, bij de overstap naar de centrale datamap:** volg
[`docs/UITROL-CENTRALE-DATA.md`](docs/UITROL-CENTRALE-DATA.md). Zonder die stappen weigert de
`git pull`, omdat de CSV's op de server lokaal gewijzigd zijn.

---

## 4. Centrale data

Alle CSV's staan centraal in **`data/<app>/`**, met back-ups in **`data/backups/<app>/`**:

```
data/hoses/         artikelnummers_staal.csv, _rvs.csv, _accessoires.csv
data/adapters/      artikelnummers_adapters.csv
data/configurator/  slangkoppelingen.csv (+ oude eigen artikelnummers.csv als reserve)
data/backups/<app>/ automatische back-up bij elke upload of bewerking
```

- **Niet in git:** wat op de server bewerkt wordt, wordt nooit door een `git pull` overschreven.
  Alleen `data/.htaccess` staat in git; die sluit de map af voor de browser.
- **Voor alle apps:** elke app kan elk bestand lezen via `shared/paths.php`. Slangkaart printen
  (krimpmaten) en de configurator lezen de slangen-CSV van `hoses`.
- **Geen terugval meer:** `hoses`, `adapters` en `configurator` lezen alleen nog uit `data/<app>/`
  (de oude plekken `hoses/data/` e.d. zijn uit de code gehaald, oktober 2026). Ontbreekt een bestand,
  vul `data/` dan uit een back-up of via de uploadknop. Slangkaart printen heeft nog een terugval naar
  `hoses/data/`.
- **Downloaden en uploaden** gaat via de knoppen in de app (bijv. "Gegevens" in de Slangen fitting
  Selector), niet via een adres.
- **Geschiedenis:** omdat de CSV's niet in git staan, is `data/backups/` de geschiedenis. Een
  nieuwe installatie krijgt zijn CSV's uit een back-up of via de uploadknop.

Uitleg voor ontwikkelaars: `shared/README.md`, "Centrale data".

---

## 5. Layout en CSS

Alle apps delen dezelfde huisstijl:

- **`shared/tokens.css`**: alle kleuren, maten en lettertypes als CSS-variabelen. Geen losse
  kleurwaarden in de eigen CSS van een app; een nieuwe kleur komt eerst hier als variabele.
- **`shared/style.css`**: header, panelen, formuliervelden, knoppen (incl. de Print-knop).
- **`shared/head.php`** en **`shared/header.php`**: dezelfde `<head>` en dezelfde zwarte header
  in elke app.
- **Slangkaart:** de layout van Slangkaart printen is vastgelegd als standaard
  (`shared/slangkaart.css`, beschreven in
  [`shared/SLANGKAART-LAYOUT.md`](shared/SLANGKAART-LAYOUT.md)). Elke volgende slangkaart-app
  gebruikt die.

Een nieuwe app bouwen: volg [`shared/README.md`](shared/README.md), de stap-voor-stap gids en
checklist. Afspraken over knoppen, tabellen en versienummer staan in [`CLAUDE.md`](CLAUDE.md).

**Hergebruik door een andere app** (printen, centrale CSV's, layout/CSS, valkuilen):
[`docs/HERGEBRUIK-SHARED.md`](docs/HERGEBRUIK-SHARED.md).

---

## 6. Database-koppeling Exact

Stauff, Slangkaart printen, Klant Artikelnummers, Kortingstructuur, de rapporten en de stickers
lezen uit Exact (databases `005_Data_Geeve` en `Exactrapportage` op `GEEVE-SQL-2019`) met **één
gedeeld SQL-account**, in te vullen via **Config** (`EXACT_DB_USER` / `EXACT_DB_PASSWORD`,
versleuteld opgeslagen). Slangkaart printen heeft daarnaast een eigen database "Slangkaarten"
(`DB_USER` / `DB_PASSWORD`, zie `slkprinten/README.md`).

- De PHP-apps **lezen** alleen; ze schrijven nooit naar Exact. Schrijven naar Exact (zoals
  "Naar Exact" in de Stauff Selector) gaat via Slangkaart.Web.
- Zonder ingevulde Config tonen de apps die de database nodig hebben een melding; de rest van de
  portal blijft werken.
- Stauff haalt zijn artikelen uit artikelgroep 67 (`GRV_SalesItems`, `Item Group = 67`), zie
  `stauff/README.md`.

---

## 7. Versienummer

Eén versienummer voor het hoofdscherm en alle apps, in `version.php`. Elke
pagina leest dat bestand. Opbouw `0.4.5.1`: het laatste nummer (build) gaat bij elke push naar
GitHub 1 omhoog, de eerste drie alleen als daarom gevraagd wordt.
