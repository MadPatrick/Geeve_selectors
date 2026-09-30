# STAUFF Beugelconfigurator

Eerste webversie van de STAUFF selector op basis van `data/stauff_selector.csv`. De wizard
(serie/uitvoering/materiaal/locaties) draait nog volledig op deze CSV - zie "Migratie naar live
Exact-data" hieronder voor de eerste stap richting het vervangen ervan.

## Installatie

1. Kopieer de volledige map naar een PHP-webserver (Apache/Nginx + PHP).
2. Zorg dat PHP `fgetcsv()` mag gebruiken en dat de map `data/` leesbaar is.
3. Open `index.php` in de browser.

Er is geen database nodig voor de CSV-gedreven wizard zelf. Het PHP-endpoint `api/stauff.php`
leest de CSV en stuurt JSON naar de JavaScript-configurator. Voor de live Exact-zoekfunctie bij
het diameterveld (zie hieronder) is wél een databaseverbinding nodig - zonder die verbinding
toont dat zoekveld een foutmelding, de rest van de configurator blijft normaal werken.

## Migratie naar live Exact-data (in opbouw)

Doel: de statische CSV volledig vervangen door live queries op de Exact-database "005"
(artikelgroep 67, zie portal-README "Database-koppeling Exact"), zodat de configurator altijd de
actuele artikelen toont in plaats van een handmatig bijgehouden CSV-bestand.

**Stap 1 (dit is af):** het diameterveld heeft uitsluitend nog een live, fuzzy zoekfunctie op het
artikelnummer (`api/exact_search.php`) - de oude CSV-gedreven autocomplete (een lijst bekende
diameters uit de CSV) is verwijderd. Typ je een getal, dan zoekt dit endpoint in
`GRV_SalesItems` naar artikelen met:

- `[Item Group] = 67` (alleen Stauff-artikelen);
- een artikelnummer dat met een cijfer begint (sluit lasplaat/dekplaat-codes als `SP...`/`GD...`
  uit - die horen niet bij een diameter-zoekopdracht);
- een fuzzy match op het getal: koppeltekens/spaties/punten/komma's worden genegeerd aan beide
  kanten van de vergelijking (zelfde patroon als `tryColumnsFuzzyLikeQuery()` in
  `slangkaarten/inc/queries.php`), dus "1680" vindt ook "10168-0".

De live resultatenlijst toont **uitsluitend het artikelnummer** (geen omschrijving) als klikbare
knop, in een gewoon blok náást het diameterveld (flex-buur in `.filter-grid`, géén absolute/
floating overlay - dat bleek het aanklikken van een resultaat te breken, zie git-historie).
`api/exact_search.php` geeft per rij wél de Exact-omschrijving (`[Item Description]`) mee in de
JSON - die wordt pas getoond ná het kiezen (zie hieronder), niet in de lijst zelf.

Het diameterveld staat zelf ook in een kaderdoos (`.diameter-box`) met dezelfde kaderstijl/
headerbalk (`.exact-live-header`, hergebruikt, tekst "Zoeken op beugel") als de resultatenbox
ernaast (die header toont alleen nog de statustekst, geen "Live resultaten uit Exact"-titel meer)
- dus zelfde "look" (rand, hoeken, header) en (via `align-items: stretch` op `.filter-grid`, de
default) altijd
even hoog.

**De filtervelden Serie, Uitvoering, Beugelmateriaal en Beugel zijn verwijderd** uit de
"Beugel bepalen"-sectie, met alle code die er exclusief van afhing (`rebuildClampFilters()`,
de diameter-typeahead over de CSV, de serie/uitvoering-knoppen). Die velden waren voorheen de
enige weg om `state.selectedClamp` te zetten - dat gebeurt nu via het kiezen van een live
Exact-resultaat (zie hieronder). Ook de "X regels geladen"/"X mogelijkheden"-pilletjes boven het
filterblok zijn weg (`ui.dataStatus`/`ui.resultCount`) - die hoorden bij de oude CSV-telling.

**Stap 1b (dit is af): een artikel kiezen.** Klikken op een artikelnummer in de live
resultatenlijst (`selectExactArticle()` in `assets/selector.js`) doet twee dingen:

1. Vult het diameterveld met dat artikelnummer en vervangt de resultatenlijst door 1 regel met
   het gekozen artikelnummer + de Exact-omschrijving. Opnieuw zoeken kan door het diameterveld te
   overtypen (geen apart "Wijzig"-knopje).
2. Roept `selectClamp(artikelnummer)` aan - dezelfde functie die voorheen via de (inmiddels
   verwijderde) Beugel-select liep. Staat dat artikelnummer in `data/stauff_selector.csv` (kolom
   `Artikelcode`), dan activeert dit meteen Locaties 1-6, Materiaal bevestigingsdelen (incl. de
   Staal/RVS-keuze) en de Samenstellingscode, exact zoals voorheen. Staat het er niet in, dan
   blijven die secties leeg/uitgeschakeld met een duidelijke waarschuwing i.p.v. stil te falen.

**Nog niet gebouwd (Stap 2):** dit koppelt een live Exact-artikel dus nog aan de **CSV** voor zijn
attributen (Bouwgroep/Serie/Enkel-Dubbel/Materiaal) - de CSV is voor dat deel nog niet vervangen.
Aanwijzing voor die volgende stap: de bouwgroep staat in Exact in de artikelomschrijving
(`Item Description` op `GRV_SalesItems`) met het voorvoegsel `GR` (bijv. "GR10") - dat moet
gebruikt worden om de bouwgroep rechtstreeks uit Exact te halen in plaats van via de CSV.

## Verkoopprijs per locatie (Exact, database 005)

Zodra voor een locatie (1-6) een artikel gekozen is, wordt de verkoopprijs live opgehaald uit
Exact (`api/exact_prices.php`, `GRV_SalesItems`, artikelgroep 67) en getoond rechts in de
bijbehorende regel (`.location-price`), via `refreshLocationPrices()` in `assets/selector.js` -
die wordt aangeroepen vanuit `updateAssemblyCode()`, dus bij elke wijziging van beugel,
materiaalcode of een locatieselectie.

**Kolomnaam nog niet bevestigd.** In tegenstelling tot Locatie/Voorraad in `/slangkaarten` (die al
zijn uitgezocht, zie de portal-README) is de kolomnaam voor verkoopprijs op `GRV_SalesItems` nog
niet geverifieerd. `exact_prices.php` probeert daarom een lijst kandidaat-kolomnamen (`Sales
Price`, `SalesPrice`, `Price 1`, `Price1`, `Price`, `Verkoopprijs`, `Prijs`) totdat er 1 zonder
SQL-fout data teruggeeft - welke kolom dat was staat in de JSON-response (`"column"`), zodat dat
te controleren is. Werkt geen van de kandidaten, dan blijft de prijs overal leeg (geen
foutmelding) - meld dan de echte kolomnaam terug zodat de lijst aangepast kan worden.

## Selectielogica

- Locatie 1: Lasplaat / Lasplaat (hoek) / Glijmoer
- Locatie 2: Beugel
- Locatie 3: Borgplaat
- Locatie 4: Dekplaat
- Locatie 5: Bout (stapelbout, inbusbout of zeskantbout)
- Locatie 6: gekozen materiaalcode W...

Het diameterveld is nu uitsluitend een live, fuzzy zoekveld op Exact (zie "Migratie naar live
Exact-data" hierboven) - geen CSV-typeahead meer. Zodra er weer een manier is om een beugel te
selecteren, worden bouwgroep en serie van die beugel gebruikt om de overige posities te filteren
(deze beschrijving hieronder blijft geldig voor zodra dat weer werkt).

**Enkel/Dubbel-filtering:**

- Locatie 1 Lasplaat / Lasplaat (hoek): geen filtering op Enkel/Dubbel
- Locatie 3 Borgplaat: volgt de Enkel/Dubbel-uitvoering van de beugel
- Locatie 4 Dekplaat: geen filtering op Enkel/Dubbel
- Locatie 5 Bout: geen filtering op Enkel/Dubbel
- Glijmoer behoudt zijn eigen Enkel/Dubbel-kenmerk uit de CSV

## Materiaal bevestigingsdelen

De UI groepeert de bekende STAUFF W-codes als:

- Staal: W1, W2, W3
- RVS: W4, W5, W55

Andere W-codes blijven in de CSV staan maar worden in deze eerste versie niet onder Staal/RVS aangeboden.

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
