# STAUFF Beugelconfigurator

De STAUFF selector haalt al zijn kandidaat-artikelen (beugel én de bevestigingsdelen op
locaties 1/3/4/5) live uit de Exact-database "005" (artikelgroep 67, zie portal-README
"Database-koppeling Exact"). Er is geen CSV meer - zie "Migratie naar live Exact-data" hieronder.

## Installatie

1. Kopieer de volledige map naar een PHP-webserver (Apache/Nginx + PHP).
2. Open `index.php` in de browser.
3. Er is een databaseverbinding nodig - zonder die verbinding toont de configurator overal
   foutmeldingen (geen CSV-fallback meer).

## Migratie naar live Exact-data

Doel (**afgerond**): de statische CSV volledig vervangen door live queries op de Exact-database
"005" (artikelgroep 67), zodat de configurator altijd de actuele artikelen toont in plaats van
een handmatig bijgehouden CSV-bestand.

**Beugel kiezen.** Het diameterveld heeft een live, fuzzy zoekfunctie op het artikelnummer
(`api/exact_search.php`). Typ je een getal, dan zoekt dit endpoint in `GRV_SalesItems` naar
artikelen met:

- `[Item Group] = 67` (alleen Stauff-artikelen);
- een artikelnummer dat met een cijfer begint (sluit lasplaat/dekplaat-codes als `SP...`/`GD...`
  uit - die horen niet bij een diameter-zoekopdracht);
- een fuzzy match op het getal: koppeltekens/spaties/punten/komma's worden genegeerd aan beide
  kanten van de vergelijking, dus "1680" vindt ook "10168-0".

Klikken op een artikelnummer in de resultatenlijst (`selectExactArticle()` in
`assets/selector.js`) vult het diameterveld, onthoudt de GRx/GRxD-bouwgroep-tag uit de
Exact-omschrijving (`state.beugelGroup`, via `extractGroupTag()`) en bouwt `state.selectedClamp`
**rechtstreeks** uit dit live resultaat op (`selectClamp()`) - er is geen CSV-koppeling meer nodig
of mogelijk; elk live gevonden artikel activeert meteen Locaties 1-6, Materiaal bevestigingsdelen
en de Samenstellingscode.

**Kandidaat-artikelen (locaties 1/3/4/5 en Beugel-als-extra-regel).** `api/exact_location_search.php`
is de enige, altijd actieve bron - zie "Eigen zoekfilter per locatie" hieronder voor de
standaard-voorvoegsels en de GRx/GRxD-bouwgroepfilter.

## Onderdeel-classificatie (`PREFIX_RULES`)

Welk artikelcode-voorvoegsel bij welke vaste locatie (1/3/4/5) en welk Onderdeel-type hoort staat
in `PREFIX_RULES` in `assets/selector.js` - geverifieerd tegen alle 1282 rijen van de oude CSV:
elk voorvoegsel wijst 100% betrouwbaar naar precies 1 Onderdeel, geen kruisbesmetting.

| Voorvoegsel(s) | Locatie | Onderdeel |
|---|---|---|
| `SP`, `SPAL`, `SPV` | 1 | Lasplaat |
| `WSP` | 1 | Lasplaat (hoek) |
| `GMV`, `SM` | 1 | Glijmoer |
| `DP`, `DPAL`, `DPAS`, `GD`, `DPAD` | 4 | Dekplaat |
| `SI`, `SIP`, `SIG` | 3 | Borgplaat |
| `AF` | 5 | Stapelbout |
| `IS` | 5 | Inbusbout |
| `AS` | 5 | Zeskantbout |
| (cijfer-eerst) | 2 | Beugel |

Classificatie van een teruggekomen Exact-rij gebeurt altijd client-side (`classify()`) op de
eigen `ItemCode` - nooit op basis van welke SQL-voorvoegselclausule raakte (die overlappen
bewust, bijv. `DP%` matcht ook `DPAD...`, zodat de server een ruimere kandidatenset teruggeeft
die client-side verder verfijnd wordt).

## Bouwgroep + Enkel/Dubbel (`tagMatches()`, één mechanisme)

Er is geen apart Enkel/Dubbel-veld meer. De GRx/GRxD-tag uit de Exact-omschrijving
(`extractGroupTag()`, regex `/\bGR\d[A-Za-z0-9\/-]*\b/`) definieert **beide tegelijk**: een
kandidaat-artikel telt alleen mee als zijn eigen tag de bouwgroep van de gekozen beugel
(`state.beugelGroup`) dekt, inclusief de `D`-suffix - een Dubbel-beugel (tag eindigt op `D`) toont
dus alleen kandidaten wier eigen omschrijving dezelfde `D`-tag draagt, een Enkel-beugel alleen
kandidaten zonder `D`.

Voor de meeste Onderdeel-types is dat een exacte 1-op-1 match (1 artikel = 1 bouwgroep), maar
glijmoer-artikelen (`SM`/`GMV`) kunnen met 1 artikel meerdere bouwgroepen dekken via een
range-tag in de omschrijving, bijv.:

- `SM 1`: `GR1-8/1D` -> dekt `GR1` t/m `GR8` **en** `GR1D` (talrange zonder letter-suffix, plus
  een losse extra tag na de `/`).
- `GMV 3`: `GR3-5S` -> dekt `GR3S`, `GR4S`, `GR5S` (talrange MET letter-suffix, geldt voor elk
  nummer in de range).

`parseGroupTags()` breidt zo'n range-tag uit naar de losse GRx-waarden die hij dekt (een gewone,
niet-range tag levert gewoon zichzelf als enige resultaat op); `tagMatches()` slaagt zodra de
beugel-tag ergens in die uitbreiding voorkomt:

```js
function tagMatches(candidateTag, clampTag) {
    const b = upper(clampTag);
    if (!b) return false;
    return parseGroupTags(candidateTag).includes(b);
}
```

Omdat de SQL-query in `api/exact_location_search.php` normaal gesproken alleen rijen teruggeeft
wier omschrijving de gevraagde tag **letterlijk** bevat (zie hieronder) - wat bij een range-
omschrijving als `GR1-8/1D` nooit het geval is voor een losse tag als `GR3` - laat die query voor
de voorvoegsels `SM`/`GMV` ook elke `GRx-y`-range-omschrijving door; de daadwerkelijke (uitgebreide)
match gebeurt alsnog hier, client-side.

`isDubbelBeugel()`/`isDubbelArtikelcode()` blijven ongewijzigd bestaan voor de twee
ongerelateerde features (standaard-aantal bij Bout, vorm-afbeelding-keuze) - dat is geen
kandidaat-filter.

**Uitrol per locatie (`GROUP_FILTER_ENABLED_FOR` in `assets/selector.js`):** of deze tagfilter
daadwerkelijk toegepast wordt, staat per locatie los aan/uit - inmiddels voor **alle** locaties
(1-5) op `true`. Bevestigd (via `/stauff/db-test.php`) dat Borgplaat/Dekplaat/Bout-omschrijvingen
in Exact, net als bij de beugel zelf, een herkenbare GRx/GRxD-tag dragen.

## Verkoopprijs + voorraad per locatie (Exact, database 005)

Zodra voor een locatie (1-5) een artikel gekozen is, worden de verkoopprijs ÉN de vrije voorraad
live opgehaald uit Exact (`api/exact_prices.php`, artikelgroep 67) en samen getoond rechts in de
bijbehorende regel (`.location-price`, bijv. "€ 12,34 · 8 op voorraad"), via
`refreshLocationPrices()`/`formatPriceAndStock()` in `assets/selector.js` - die wordt aangeroepen
vanuit `updateAssemblyCode()`, dus bij elke wijziging van beugel, materiaalcode of een
locatieselectie. Een voorraad van 0 is een geldige waarde en wordt gewoon getoond ("0 op
voorraad"); alleen een onbekende/ontbrekende voorraad (geen koppeling, artikel niet gevonden)
wordt weggelaten - ontbreekt de prijs of de voorraad, dan toont de regel alleen de andere.

**Kolomnaam prijs nog niet bevestigd.** De kolomnaam voor verkoopprijs op `GRV_SalesItems` is nog
niet geverifieerd. `exact_prices.php` probeert daarom een lijst kandidaat-kolomnamen (`Sales
Price`, `SalesPrice`, `Price 1`, `Price1`, `Price`, `Verkoopprijs`, `Prijs`) totdat er 1 zonder
SQL-fout data teruggeeft - welke kolom dat was staat in de JSON-response (`"column"`). Werkt geen
van de kandidaten, dan blijft de prijs overal leeg (geen foutmelding).

**Voorraad wél al bevestigd** - zelfde, al uitgezochte formule als `findArtikelExactDataBatch()`
in `slangkaarten/index.php`: `StockBalances` is een mutatielog, de vrije voorraad is het laagste
van de FreeStock-som en de Quantity-som t/m vandaag (`[Date] <= GETDATE()`), met een vloer op 0.

## Eigen zoekfilter per locatie (locatienummer als knop, Exact live)

Locaties 1, 3, 4 en 5 hebben géén apart config-tandwiel - het **locatienummer zelf** is de knop
(`<button class="location-number" data-location="N">`) die de config-modal opent
(`locationFilterOverlay` in `index.php`). Daar kun je, `;`-gescheiden, artikelnummer-voorvoegsels
opgeven (bijv. `SP;SPAL;SPV`) - dit **overschrijft** voor die locatie de standaard-voorvoegsels
uit `PREFIXES_BY_POSITION` (afgeleid van `PREFIX_RULES` hierboven). Leeg = de standaard-
voorvoegsels voor die locatie blijven gebruikt; er is geen "CSV-fallback" meer, het live pad is
altijd actief.

`api/exact_location_search.php` zoekt artikelen (artikelgroep 67) die met 1 van de voorvoegsels
**beginnen**, gecombineerd met:

- de gekozen materiaal**familie** (Staal/RVS-switch, niet een vooraf gekozen exacte code) -
  altijd de hele familie als `;`-lijst, bijv. "W1;W2;W3" voor Staal;
- (indien `GROUP_FILTER_ENABLED_FOR` voor die locatie aan staat) de GRx/GRxD-tag van de gekozen
  beugel - zie "Bouwgroep + Enkel/Dubbel" hierboven.

Elk gevonden artikel toont in de select de gematchte combinatie (artikelnummer + bouwgroep-tag,
bijv. "SP-215 (GR10)"). Het filter wordt opgeslagen in `localStorage` (`stauffLocationFilters`),
dus 1x instellen blijft staan. Het locatienummer krijgt een rode rand (`.is-active`) zodra er een
filter voor die locatie staat.

## Extra artikelen toevoegen (vrije regels)

Naast het locatienummer staat op de rijen 1, 3, 4 en 5 een `+`-knop (`.location-add-button`).
Klikken op `+` (`addExtraItemRow()` in `assets/selector.js`) voegt direct ONDER de knop waarop
geklikt is een nieuwe, vrij te configureren regel toe:

1. **Soort** (select): dezelfde opties als de vaste locaties, **inclusief Beugel** (een 2e beugel
   binnen dezelfde bouwgroep/uitvoering, via dezelfde live Exact-bron met de `__DIGIT__`-
   voorvoegselmodus, zie `applyLocationFilterSelect()`).
2. **Artikel** (select, disabled tot een soort gekozen is): gebruikt precies dezelfde live bron
   als de vaste locatie voor die soort (`populateExtraArticleSelect()`).
3. **Aantal** (getalveld): staat standaard op **1**, behalve bij **Bout**, dan standaard **2**
   (of **1** bij een dubbele beugel) - alleen gezet bij het wisselen van soort.
4. Een `+`-knop (nog een regel eronder) en een `×`-knop om de regel te verwijderen.

Elke extra regel heeft zijn eigen "request key" (`extra-<id>`) voor de live Exact-zoekopdracht,
zodat meerdere gelijktijdige zoekopdrachten elkaar niet annuleren. Extra regels verversen
automatisch mee zodra de beugel of materiaalcode wijzigt (`refreshExtraItems()`), en hun
verkoopprijs wordt meegenomen in de batch-lookup (`refreshLocationPrices()`).

Extra regels worden **niet** meegenomen in de samenstellingscode-berekening
(`updateAssemblyCode()`) - die blijft uitsluitend gebaseerd op de vaste locaties 1-6. Ze tellen
wél mee in de totaalprijs.

## Aantal en totaalprijs

Elke locatie (1-6, en elke extra regel) heeft een eigen **aantal**-veld. Standaard staat dat op
**1**, behalve locatie 5 (Bout) die standaard op **2** staat (**1** bij een dubbele beugel,
`isDubbelBeugel()` - o.b.v. de artikelcode-"/" of de GRxD-tag, niet de CSV). Het aantal wordt
**niet** automatisch teruggezet zolang dezelfde beugel/soort gekozen blijft - alleen bij het
kiezen van een andere beugel of het legen van de samenstelling (`resetAantalFields()`).

Onder de samenstellingscode staat de **totaalprijs**: de som van (verkoopprijs × aantal) over elk
onderdeel waarvoor al een artikel gekozen is (`recomputeTotal()`), op basis van de laatst
opgehaalde prijzen (`state.lastPrices`) - een aantal wijzigen herberekent het totaal dus direct,
zonder opnieuw bij Exact te bevragen.

## Selectielogica

- Locatie 1: Lasplaat / Lasplaat (hoek) / Glijmoer
- Locatie 2: Beugel
- Locatie 3: Borgplaat
- Locatie 4: Dekplaat
- Locatie 5: Bout (stapelbout, inbusbout of zeskantbout)
- Locatie 6: gekozen materiaalcode W...

Bouwgroep en enkel/dubbel van de gekozen beugel filteren de overige locaties, zie "Bouwgroep +
Enkel/Dubbel" hierboven. Er is verder geen Serie-onderscheid (Licht/Zwaar) meer - dat veld bestaat
niet in Exact en is met opzet vervallen; shape-afbeeldingen en de locatie-1 type-rangorde werken
nu uitsluitend op bouwgroep.

## Materiaal soort

"Materiaal soort" (bovenin, dezelfde kaderdoos-stijl als "Zoeken op beugel") is uitsluitend de
Staal/RVS-switch (`materialFamilySwitch`, 2 knoppen - geen `<select>`, zie `getMaterialFamily()`/
`setMaterialFamilyValue()` in `assets/selector.js`) - dit is de **enige** extra materiaal-filter.
Er is geen apart, vooraf gekozen exacte W-code meer die de andere locaties stuurt: locaties 1, 3,
4 en 5 filteren hun kandidaten op de hele gekozen familie (`materialQueryValue()`), en de
specifieke W-code volgt uit welk artikel de gebruiker per locatie kiest:

| Code  | Omschrijving | Familie |
|-------|--------------|---------|
| W1    | CS           | Staal   |
| W2    | CS Ph        | Staal   |
| W3    | ZN           | Staal   |
| W4    | V2A          | RVS     |
| W5    | V4A          | RVS     |
| W55   | V4A CR       | RVS     |

Vaste lijst (`MATERIAL_CODES`/`metalFamily()` in `assets/selector.js`), gebruikt voor de
familiefilter - niet meer voor een eigen locatie-6-select (zie "Locatie 6" hieronder).

**Automatische selectie:** locatie 1, 4 en 5 selecteren altijd het eerste resultaat van hun eigen
vaste rangorde (locatie 1 en 5 ook op type, alle drie op materiaalcode-voorkeur binnen de gekozen
familie - zonder vooraf gekozen exacte code is dit de enige manier om standaard 1 sensible optie
te tonen). De voorkeuren verschillen bewust per locatie, zie `typeRank()`/`materialRank()` in
`renderLiveCandidates()`:

- Locatie 1 (Lasplaat/Glijmoer): type SP > SPV > WSP > SPAL > GMV/SM; materiaal **W3** (Staal) /
  **W5** (RVS).
- Locatie 4 (Dekplaat): materiaal **W2** (Staal) / **W5** (RVS) (geen eigen type-voorkeur nodig,
  er is maar 1 Dekplaat-voorvoegselgroep per bouwgroep).
- Locatie 5 (Bout): type **AS** (Zeskantbout) > IS (Inbusbout) > AF (Stapelbout); materiaal W2
  (Staal) / W5 (RVS), zelfde als locatie 4.

**Locatie 3 (Borgplaat) nooit automatisch** - bewust altijd "geen keuze" als start, ook bij maar 1
passend artikel, omdat het een optioneel onderdeel is. Zie `renderLiveCandidates()` in
`assets/selector.js`.

## Locatie 6 (automatisch, geen eigen select meer)

Locatie 6 ("Materiaal") was een eigen pulldown waarin de gebruiker zelf een W-code koos. Dat is
vervallen: de code volgt nu automatisch uit de combinatie van W-codes die de daadwerkelijk
gekozen artikelen op locatie 1, 3, 4 en 5 al dragen (`computeLocation6Code()`/
`currentMaterialByRole()` in `assets/selector.js`), volgens Stauff's officiële
materiaalcombinatietabel:

- Zijn alle aanwezige onderdelen van dezelfde W-code (bijv. alles W2), dan is dat gewoon code 6 -
  geen aparte combinatiecode nodig.
- Verschillen ze, dan moet de combinatie exact voorkomen in `MATERIAL_COMBINATION_RULES` (W10,
  W12, W13, W15-W19 - elk gekoppeld aan specifieke rollen: `WeldPlate` = Lasplaat/Lasplaat (hoek),
  `Glijmoer` = Glijmoer, `CoverPlate` = Dekplaat, `SafetyLockingPlate` = Borgplaat, `Bolts` =
  Stapel-/Inbus-/Zeskantbout). W10 is een speciaal geval ("Other metal parts"): alleen de Weld
  Plate-code ligt vast, elke andere aanwezige rol moet W3 zijn.
- Komt geen enkele regel overeen (of zijn er nog geen onderdelen gekozen), dan blijft code 6 leeg
  - geen foutmelding, zelfde gedrag als een niet-ingevulde optionele locatie.

Locatie 6 toont deze berekende code nu read-only (`location6Value`, net als `location2Value` voor
de beugel) - geen keuzemenu, aantal of eigen prijs meer (een combinatiecode is geen apart
besteld artikel).

## Samenstellingscode

De code wordt opgebouwd als:

`locatie1-locatie2-locatie3-locatie4-locatie5-locatie6`

Locatie 2 gebruikt de **beugelcode zelf**. Daarmee zitten bouwgroep, maat en beugelmateriaal direct in de samenstellingscode.

Voorbeelden:

- 15 mm, licht, PP → `215 PP`
- 15 mm, zwaar, PP → `3015 PP`

Een complete samenstelling kan bijvoorbeeld worden:

`SP-215 PP-SIG-DP-AS-W3`


## Optionele samenstelling

Locatie 2 (de beugel) is de basis van de configuratie. Locaties 1, 3, 4, 5 en 6 zijn optioneel.
Locatie 1 (Lasplaat) en locatie 4 (Dekplaat) worden standaard voorgeselecteerd zodra een passende optie beschikbaar is; de gebruiker kan ze daarna leeg maken.
Borgplaat en Bout blijven standaard leeg. De samenstellingscode bevat alleen de gekozen locaties, altijd in de volgorde 1 t/m 6.
