# Slangkaarten — Geeve Hydraulics

Webbased vervanger voor het NiceLabel-scherm "Slangkaarten per order": een ordernummer óf
klantnaam opzoeken, de gevonden slangregels selecteren, en van de aangevinkte regels een
slangkaart/bon tonen en printen. In tegenstelling tot de andere tegels in deze portal (`/hoses`,
`/adapters`, `/configurator`) heeft deze subapp wél een database nodig — zie "SQL-toegang"
hieronder.

## Status

De flow volgt het originele NiceLabel-scherm 1-op-1:

1. **Zoeken** - op ordernummer óf (een deel van) de klantnaam.
2. **Order kiezen** (alleen bij zoeken op klantnaam) - een klant kan meerdere orders hebben, dus
   eerst een lijst van die orders (op ordernummer aflopend, max. 2 jaar terug, met type
   Order/Quote en aantal slangregels per order) om er 1 te kiezen. Bij zoeken op ordernummer
   wordt deze stap overgeslagen.
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
  Zie "SQL Server driver installeren" in de bronrepo
  ([`madpatrick/nicelabel`](https://github.com/MadPatrick/nicelabel)) voor de volledige
  installatie-instructies (Debian, via `msodbcsql18` + PECL `sqlsrv`/`pdo_sqlsrv`).
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

## Bijwerken

Deze map is een kopie van de bronrepo [`madpatrick/nicelabel`](https://github.com/MadPatrick/nicelabel)
(zelfde conventie als `/hoses`, `/adapters`, `/stauff`). Wijzigingen daar komen hier niet
automatisch door - kopieer de bijgewerkte `index.php`/`assets/style.css`/`inc/*.php` opnieuw naar
deze map wanneer de bronapp los is bijgewerkt. Pas bij het overzetten van `index.php` de
koptekst/`APP_VERSION`-aanpassingen niet terug (zie de portal-README, sectie "Eén gedeeld
versienummer") en laat `.env`/`.env.example`/`.gitignore`/`.htaccess` in deze map ongemoeid.

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
