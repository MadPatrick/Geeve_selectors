# Slangkaarten — Geeve Hydraulics

Webbased vervanger voor het NiceLabel-scherm "Slangkaarten per order": een ordernummer óf
klantnaam opzoeken, de gevonden slangregels selecteren, en van de aangevinkte regels een
slangkaart/bon tonen en printen. In tegenstelling tot de andere tegels in deze portal (`/hoses`,
`/adapters`, `/configurator`) heeft deze subapp wél een database nodig — zie "SQL-toegang"
hieronder.

## Status

De flow volgt het originele NiceLabel-scherm grotendeels 1-op-1, met 1 toevoeging (zoeken op
slangnummer, zie hieronder):

1. **Zoeken** - op ordernummer, slangnummer (artikelnummer van de slang - kolom `GHnr`, zie
   `HOSE_KEY_COLUMNS` in `inc/queries.php`), óf (een deel van) de klantnaam. Bij zoeken op
   slangnummer worden alleen artikelen uit artikelgroep 0 (echte slangen) getoond - zie
   `findArtikelItemGroepenBatch()`/`findLinesByHoseNumber()` in `inc/queries.php` en "Picklijst-
   locatie & voorraad" hieronder voor de gebruikte Exact-tabel. Het slangnummer-veld zoekt "fuzzy":
   koppeltekens/spaties/punten in zowel de zoekterm als de kolomwaarde worden genegeerd
   (`tryColumnsFuzzyLikeQuery()`), dus "482953010" vindt ook "48295-30-10".
2. **Order kiezen** (bij zoeken op klantnaam of slangnummer) - een klant kan meerdere orders
   hebben, en hetzelfde slangnummer kan in meerdere orders/klanten voorkomen (het is het
   hose-artikelnummer, geen order-unieke sleutel) - dus eerst een lijst met resultaten (nieuwste
   order eerst) om er 1 te kiezen. Bij zoeken op ordernummer wordt deze stap overgeslagen.
3. **Regels selecteren** - een overzicht van de slangregels van de gekozen order (regel, aantal,
   slangnummer, GHnm, slang type, lengte, koppeling A/B - elk koppelonderdeel in een eigen
   kolom), elk met een aangevinkte checkbox. Ordernummer en klantnaam staan in de koptekst, niet
   als kolom. De meeste velden komen uit dezelfde tabel als de kaart zelf ("2500 Slangkaarten bij
   order"); koppeling A/B worden er per regel bijgehaald uit de Zijde A/B-tabellen
   (`enrichHoseLinesWithCouplings()` in `inc/queries.php`).
4. **Printen** - alleen van de aangevinkte regels wordt de daadwerkelijke slangkaart (uit "2500
   Slangkaarten bij order", met koppelonderdelen zijde A/B) opgehaald; `window.print()` wordt
   automatisch aangeroepen zodra de pagina laadt, geen aparte preview-stap.

Structureel belangrijk: **1 rij in "2500 Slangkaarten bij order" = 1 slang**, niet 1 rij per
order - een order met "Aantal slangen" = 4 heeft dus 1 rij die aangeeft dat die slang 4x gemaakt
moet worden. Bij een order met meerdere verschillende slangtypen worden er dus meerdere kaarten
(1 per geselecteerde regel) na elkaar getoond/geprint, met een paginabreak per kaart.

## Vereisten (naast wat de portal zelf al nodig heeft)

- PDO_SQLSRV-extensie (Microsoft ODBC Driver 17/18 for SQL Server) — de andere tegels in deze
  portal hebben geen database nodig, dus deze driver is mogelijk nog niet op de server aanwezig.
  Installatie (Debian): `msodbcsql18` (via Microsoft's `packages-microsoft-prod.deb`) + PECL
  `sqlsrv`/`pdo_sqlsrv` (`pecl install sqlsrv pdo_sqlsrv`, extensies aanzetten via
  `phpenmod`/`.ini`-bestanden).
- Netwerktoegang vanaf de webserver naar `GEEVE-SQL-2019` (poort 1433).
- Een SQL Server-account met leesrechten op de database `Slangkaarten` (zie "SQL-toegang"
  hieronder).

## Configuratie

1. `cp .env.example .env` (in deze map, `slangkaarten/.env`).
2. Vul `.env` met de SQL Server-gegevens (zie "SQL-toegang" hieronder).
3. Een eigen `.htaccess` in deze map blokkeert browsertoegang tot `.env` — de portal-root's
   `.htaccess` regelt alleen IP-restrictie, geen dotfile-bescherming.

## SQL-toegang

Heb je al een SQL Server-account (login) dat de database `Slangkaarten` mag lezen? Vul die
gebruikersnaam en dat wachtwoord dan direct in `.env` in bij `DB_USER`/`DB_PASSWORD` - er hoeft
dan verder niets aangemaakt te worden, mits het account minimaal `SELECT` mag doen op:

- `[dbo].[2500 Slangkaarten bij order]`
- `[dbo].[93004 hv 3001 Slangonderdelen Zijde A]`
- `[dbo].[93004 hv 3001 Slangonderdelen Zijde B]`

Controleer dat met (log in met dat account, bijv. via `sqlcmd` of SSMS):

```sql
SELECT TOP 1 * FROM [dbo].[2500 Slangkaarten bij order];
```

Geeft dat een "permission denied"-foutmelding, of heb je nog geen bestaand account? Maak dan een
nieuwe, read-only login aan (voer dit uit op `GEEVE-SQL-2019` met een account dat rechten mag
toekennen, en pas het wachtwoord aan):

```sql
USE [Slangkaarten];
CREATE LOGIN [webapp_slangkaarten] WITH PASSWORD = 'VUL-EEN-STERK-WACHTWOORD-IN';
CREATE USER [webapp_slangkaarten] FOR LOGIN [webapp_slangkaarten];

GRANT SELECT ON [dbo].[2500 Slangkaarten bij order] TO [webapp_slangkaarten];
GRANT SELECT ON [dbo].[93004 hv 3001 Slangonderdelen Zijde A] TO [webapp_slangkaarten];
GRANT SELECT ON [dbo].[93004 hv 3001 Slangonderdelen Zijde B] TO [webapp_slangkaarten];
```

Werkt geen van beide? Dan staat er mogelijk alleen Windows-authenticatie aan op de SQL Server -
zet daar "SQL Server and Windows Authentication mode" (gemengde modus) aan, anders werkt een los
SQL-account nooit.

**Foutmelding met "self-signed certificate" of "certificate verify failed"?** ODBC Driver 18
valideert sinds kort standaard het SSL-certificaat van de server, en vrijwel elke on-prem SQL
Server gebruikt een zelfondertekend certificaat. De app zet daarom standaard
`TrustServerCertificate=yes` (zie `DB_TRUST_SERVER_CERT` in `.env.example`) - dat is binnen een
intern/vertrouwd netwerk gebruikelijk. Heeft de server een echt (CA-ondertekend) certificaat en
wil je wel strikt valideren, zet dan `DB_TRUST_SERVER_CERT=no` in `.env`.

## Database-schema

Het echte schema van "2500 Slangkaarten bij order" (definitief bevestigd via
`INFORMATION_SCHEMA.COLUMNS`) en van de Zijde A/B-tabellen staat uitgebreid gedocumenteerd
bovenaan `inc/queries.php` ("Update 5"/"Update 6"). Geen enkele kolom heeft een prefix (bijv.
`ordernr`, `GHnr`, `nm`, `Labelen`, `debnr`, `del_debnm`, ...).

## Picklijst-locatie & voorraad (Exact, database 005) - optioneel

De picklijst (laatste printpagina, zie "Status" hierboven) toont de kolommen Artikelnummer /
Locatie / Aantal / Voorraad / Besteld. Locatie en Voorraad worden opgezocht via
`findArtikelExactData()` in `index.php`, in de Exact-database "005" (zelfde server,
`GEEVE-SQL-2019`, andere database - dezelfde die `/stauff` gebruikt voor artikelgroep 67), tabel
`GRV_StockpositionsPerDay` (`ItemCode` → `Warehouse Location` resp. `Free Stock`, de huidige vrije
voorraad). De voor de hand liggende kandidaat `CSPickITItemLocations` bleek bij het uitzoeken leeg
te staan (0 rijen); `GRV_StockpositionsPerDay` is een dagelijkse voorraadmutatie-tabel (12+ miljoen
rijen, geen 1-op-1 locatie-/voorraadtabel), dus wordt de meest recente rij per artikel gebruikt
(`ORDER BY [Transaction Date] DESC`), zonder filtering op `Warehouse`. Besteld is met opzet altijd
leeg - géén databasekolom, puur ruimte om met de hand op de uitgeprinte picklijst in te vullen.

Dit is een **losse, optionele** tweede databaseverbinding (`getExactPdoConnection()` in
`inc/db.php`). De `EXACT_DB_*`-inloggegevens staan niet in de eigen `.env` van deze map, maar
centraal in de portal-root `.env` (zie `../.env.example`, 1 map hoger) - hetzelfde SQL-account als `/stauff`
gebruikt voor artikelgroep 67, dus 1x instellen voor beide subapps. Zonder ingevulde root-`.env`
(of bij een connectiefout) toont de Locatie-kolom gewoon een streepje - de rest van de
app/picklijst blijft normaal werken.

## Verzendwijze (Exact, database 005) - volledig geverifieerd

De kaart toont een "Verzendwijze"-regel (de Leveringswijze die in Exact op de order staat), opgezocht
via `findLeveringswijze()` in `index.php` in 2 stappen (beide bevestigd via `/stauff/db-test.php`'s
kolomnaam-zoekoptie, `?kolom=levwijze`):

1. De tabel `orkrg` (kolom `ordernr` voor het ordernummer) geeft de `levwijze`-code van die order
   (bijv. "001") - deze tabel heeft de code al vanaf het aanmaken van de order. (`orhkrg`, de eerst
   geprobeerde kandidaat, bleek pas een rij te krijgen zodra er een pakbon is, en bleef dus leeg voor
   nog niet geleverde orders.)
2. Die code wordt opgezocht in `ordlev` (ook kolom `levwijze`) - **niet** een ordertabel, maar een
   vertaaltabel van code naar omschrijving; de kolom die overeenkomt met de tekst op de kaart is
   `oms40_0`.

Is de order niet gevonden in `orkrg`, of de code niet in `ordlev`, dan toont de kaart een streepje
(geen foutmelding, zelfde gedrag als Locatie/Voorraad hierboven bij een missende koppeling).

## Krimpmaten (CSV-matching) - performance

Het "Krimpmaten"-kader op elke slangkaart zoekt per koppelartikel de Persmaat/Schilmaat op via
`findKrimpmaat()` in `index.php`, in 2 lokale CSV-bestanden (`../hoses/data/artikelnummers_staal.csv`
en `_rvs.csv`, ~940 regels elk) - géén database. Dit bleek de hoofdoorzaak van wisselend trage
prints: `findKrimpmaatInCsv()` las voorheen bij **elke aanroep** het bestand opnieuw open en liep
het van voor naar achter door tot de eerste regel met het juiste `artnr`, zonder enige caching -
ook niet voor exact dezelfde (slangtype, koppelartikel)-combinatie die vaker voorkomt in dezelfde
printopdracht. Bij N kaarten met in totaal M van die combinaties liep de totale tijd dus op tot M
volledige bestandsscans. Dat verklaart ook waarom het aantal kaarten geen goede voorspeller was van
de printsnelheid: een kleine, uiteenlopende order (veel verschillende, verspreid-in-het-bestand-
staande slangtypes) kon trager zijn dan een grote order met vooral identieke/vroeg-in-het-bestand
voorkomende slangtypes.

Opgelost door elke CSV 1x per paginaverzoek te parsen in een index per `artnr`
(`loadKrimpmatenCsv()`, statisch gecached), waarna elke opzoeking een simpele array-lookup is
i.p.v. een lineaire scan. Zelfde matchgedrag als voorheen (alleen de eerste rij per `artnr` telt),
geverifieerd tegen de echte CSV's met identieke resultaten voor beide implementaties.

## Zoekfilter slangnummer: alleen artikelgroep 0 (Exact, database 005)

Bij zoeken op slangnummer (stap 1, zie "Status" hierboven) worden de gevonden regels gefilterd op
artikelgroep: alleen artikelen uit **artikelgroep 0** (echte slangen) blijven over, zodat een korte
zoekterm niet ook koppelingen/andere artikelen laat matchen die toevallig hetzelfde cijferpatroon
bevatten. `findArtikelItemGroepenBatch()` (`inc/queries.php`) zoekt de artikelgroep op in
`GRV_SalesItems` (`ItemCode` → `[Item Group]`) - dezelfde tabel die `/stauff` gebruikt voor
artikelgroep 67 (zie portal-README, "Database-koppeling Exact"), 1 databaseronde voor alle
gevonden artikelen samen. Is de Exact-koppeling niet beschikbaar (lege root-`.env` of een
connectiefout), dan wordt er niet gefilterd - de zoekfunctie blijft dan werken zoals vóór deze
filter, met mogelijk ook niet-slangartikelen in de resultaten.

## Standaard: laatste 10 orders/offertes

Zolang er nog niet gezocht is (geen ordernummer, klant of slangnummer) toont de pagina direct de
laatste 10 orders/offertes (`ord_soort` V = Order, Q = Quote), in dezelfde tabel als de
klant-zoekresultaten (`renderCustomerOrdersForm()`) met een "Kiezen"-knop per rij. Zodra je zoekt
worden de zoekresultaten getoond i.p.v. deze lijst; "Wissen" brengt hem terug.

`findRecentOrders()` in `inc/queries.php` doet 1 query op `2500 Slangkaarten bij order`
(`GROUP BY ordernr`, 1 rij per order + aantal slangregels). "Laatste" = hoogste ordernummer
(`TRY_CAST(ordernr AS BIGINT) DESC`; orders en offertes delen 1 nummerreeks). Sorteren op
`MAX(syscreated)` bleek niet te werken: een oude offerte die recent is aangepast kwam dan tussen
de nieuwe orders te staan. Een databasefout laat de lijst alleen weg - de zoekformulieren blijven
gewoon werken.

De orderdatum (`orddat`) is een datum zonder tijd (altijd `00:00:00` in de database) en wordt
overal alleen als datum getoond (`formatDate()`, bijv. `2026-10-06`). Aangemaakt/Laatste gewijzigd
op de kaart behouden hun tijd.

## Printvoorbeeld uitschakelen (client-instelling)

De print-actie roept automatisch `window.print()` aan - de app zelf toont geen eigen
voorbeeldscherm. Wat de gebruiker daarna ziet is puur de standaard-printervoorbeeld van de
browser: Chrome en Edge tonen standaard een eigen "Printvoorbeeld"-venster (documentpreview +
printerkeuze in 1 scherm). Wil je in plaats daarvan alleen het kale printerkeuze-venster (zonder
documentvoorbeeld), dan is dat een browserinstelling, niet iets wat de webapp zelf kan afdwingen.
Zet op de clientmachine(s) (via Group Policy of direct in het register):

```
Chrome: HKEY_LOCAL_MACHINE\SOFTWARE\Policies\Google\Chrome
Edge:   HKEY_LOCAL_MACHINE\SOFTWARE\Policies\Microsoft\Edge
```

Waarde: DWORD `PrintPreviewDisabled` = `1`.

## Projectstructuur

```
index.php            Formulier + resultaatweergave + printlayout
assets/style.css      Vormgeving (gedeelde brand-panel/paneel-stijl, zie portal-README)
inc/config.php         .env-loader + configuratie (appConfig())
inc/db.php              PDO/SQL Server verbinding
inc/queries.php          Alle SQL-queries
.env.example              Voorbeeld-configuratie
.htaccess                 Blokkeert browsertoegang tot .env (portal-root doet dit niet)
```
