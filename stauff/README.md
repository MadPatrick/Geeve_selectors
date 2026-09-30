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

De live resultaten tonen **uitsluitend het artikelnummer** (geen omschrijving) als klikbare knop
in een eigen paneel ("Live resultaten uit Exact"), op de plek waar voorheen de "Bouwgroep/
Diameter/Serie/Uitvoering"-infobox stond. Klikken vult het diameterveld met dat artikelnummer;
er is nog geen koppeling naar de rest van de wizard (zie "Stap 2" hieronder).

**De filtervelden Serie, Uitvoering, Beugelmateriaal en Beugel zijn verwijderd** uit de
"Beugel bepalen"-sectie, met alle code die er exclusief van afhing (`rebuildClampFilters()`,
de diameter-typeahead over de CSV, de serie/uitvoering-knoppen). Die velden waren de enige weg
om `state.selectedClamp` te zetten.

**Nog niet gebouwd (Stap 2):** de secties Locaties 1-6, Materiaal bevestigingsdelen en
Samenstellingscode blijven in de pagina staan, maar permanent uitgeschakeld ("Kies eerst een
beugel") totdat er een nieuwe manier komt om een beugel te selecteren - de onderliggende functies
(`selectClamp()`, `candidatesForPosition()`, `updateAssemblyCode()`, enz.) zijn intact gelaten
voor die volgende stap, alleen niet meer aangesloten op een UI-element. Aanwijzing voor die
volgende stap: de bouwgroep staat in Exact in de artikelomschrijving (`Item Description` op
`GRV_SalesItems`) met het voorvoegsel `GR` (bijv. "GR10") - dat moet gebruikt worden bij het
kiezen van de beugel in plaats van de CSV's `Bouwgroep`-kolom.

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
