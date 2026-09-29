# Geeve Hydraulics — Selectors

Startpagina met tegels naar de Geeve-selectors:

- **`/hoses`** — Slangen fitting Selector
- **`/adapters`** — Adapters Selector
- **`/stauff`** — Stauff Selector / beugelconfigurator, zelfde brand-panel/paneel-stijl als `/hoses`
  en `/adapters`. Heeft sinds kort ook een eigen databaseverbinding naar de Exact-database "005"
  (artikelgroep 67) - nog in opbouw, zie "Database-koppeling Stauff (Exact, database 005)"
  hieronder.
- **`/configurator`** — Slang configurator, zelfde brand-panel/paneel-stijl. Bestanden staan er nog,
  maar er is momenteel geen tegel op de startpagina die hierheen linkt.
- **`/slangkaarten`** — Slangkaarten bij order - zoekt een order of klant op en print
  slangkaarten/labels van de geselecteerde regels. Heeft een eigen database nodig (SQL Server,
  database "Slangkaarten", zie `slangkaarten/README.md`). Voor de Locatie-kolom op de picklijst
  gebruikt deze subapp ook, optioneel, dezelfde Exact-database "005" als `/stauff`.
- **`/stickers`** — nieuw, eigen scherm met 3 tegels: "Stickers op Artikelnummer", "Stickers op
  Artikelnummer (groot)" en "Stickers op Zakjes". Nog niet functioneel - alle 3 staan als
  "Binnenkort beschikbaar", dit is puur de navigatiestructuur.

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

`/slangkaarten` en `/stauff` hebben, in tegenstelling tot de andere tegels, een eigen `.env` nodig
(SQL Server-inloggegevens) - zie `slangkaarten/README.md` resp. de sectie hieronder voor de
configuratie-instructies. Zonder ingevulde `.env` toont die tegel/pagina een foutmelding i.p.v. te
crashen; de rest van de portal blijft gewoon werken.

## Database-koppeling Stauff (Exact, database 005)

`/stauff` krijgt een live SQL Server-verbinding naar de Exact-database "005" (zelfde server als
`/slangkaarten`, `GEEVE-SQL-2019`, andere database) om artikelgroep 67 uit te lezen. Dit staat nog
in de opbouwfase:

1. **Verbinding opgezet** (dit is af): `stauff/inc/config.php` + `stauff/inc/db.php` (zelfde
   PDO/SQL Server-patroon als `/slangkaarten`), plus `stauff/.env.example`/`.gitignore`/`.htaccess`.
   Kopieer `.env.example` naar `.env` in `stauff/` en vul `DB_USER`/`DB_PASSWORD` in van een
   bestaand SQL-account dat database "005" mag lezen.
2. **Tabel/kolom voor groep 67 gevonden** (dit is af): `GRV_SalesItems` (kolommen `ItemCode`,
   `Item Description`, `Item Unit`, `Item Group`) koppelt elk artikel aan zijn groepscode;
   `Item Group = 67` geeft precies de Stauff-beugelartikelen. `GRV_ItemGroups` (`Item Group`,
   `Item Group Description`) bevestigt dat groep 67 = "STAUFF BEUGELS". Deze artikelcodes komen
   overeen met die in `stauff/data/stauff_selector.csv` (die CSV is dus in feite al de
   groep-67-lijst, handmatig aangevuld met geparste diameter/bouwgroep/materiaal-kolommen voor de
   selector). `stauff/db-test.php` blijft voorlopig staan (nog nodig voor stap 3 hieronder), maar
   het zoeken naar de juiste tabel/kolom zelf is afgerond.
3. **Zoekfilter (later)**: groep 67 verwerken in de Diameter-zoeklogica (`api/stauff.php`/
   `assets/selector.js`) - "bij intype van diameter gaan we in groep zoeken". Dit is nog niet
   gebouwd; `db-test.php` kan dan weer verwijderd worden.

## Structuur

```
index.php              Startpagina met tegels
version.php             Eén gedeeld versienummer voor hoofdscherm + alle subapps, zie hieronder
assets/style.css        Styling van alleen de startpagina
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
stauff/                 Volledige Stauff Selector-app (eigen assets/data/api/etc.)
configurator/           Volledige Slang configurator-app (eigen assets/data/etc.) - bestanden
                        staan er nog, momenteel geen tegel op de startpagina
slangkaarten/           Volledige Slangkaarten-app (eigen assets/inc/etc. + eigen .env/.htaccess,
                        want als enige subapp met een database-verbinding, zie
                        slangkaarten/README.md)
stickers/               Nieuw, eigen scherm met 3 tegels (nog niet functioneel - "Binnenkort
                        beschikbaar")
```

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
