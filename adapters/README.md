# Adapters Selector — Geeve Hydraulics

Webapplicatie om Geeve-adapters (hydrauliekkoppelingen) te selecteren op basis van draadsoort,
draadmaat, hoek en connectietype. Zusterproject van de "Slangen fitting Selector"
(`madpatrick/Geeve_hose`) — dezelfde pagina-layout en stijl, maar met een eigen datamodel en
zoeklogica die passen bij adapters in plaats van slangen.

## Gebruik

Zet de map op een PHP-webserver (PHP 8+, geen database nodig) en open `index.php`. Er is geen
build-stap nodig.

- **Zoeken**: 2 kolommen "Aansluiting 1" / "Aansluiting 2" (draadsoort, draadmaat, connectie
  type) plus "Hoek". Aansluiting 1 en 2 zijn verwisselbaar — het maakt niet uit in welke kolom je
  een kenmerk invult, de matching probeert beide kanten. Elk veld op "Alle" laten betekent: niet
  filteren op dat kenmerk.
- De draadmaat-lijst is afhankelijk van de gekozen draadsoort (bijv. alleen BSPP-maten
  wanneer "BSPP" gekozen is).
- Resultaat: een tabel met artikelcode, kruisverwijzing (Parker cross reference), familie, hoek
  en een leesbare samenvatting van beide aansluitingen.
- **Data beheer** (`data.php`): CSV downloaden/uploaden, met automatische backup van het
  bestaande bestand in `data/backups/` bij een nieuwe upload.

## Databestand

`data/artikelnummers_adapters.csv` — puntkomma-gescheiden, UTF-8 met BOM, LF-regeleindes, 15
kolommen:

| Kolom | Betekenis |
|---|---|
| `artnr` | Artikelcode (Geeve, **staal**-uitvoering — alleen staal is nodig voor dit project) |
| `cross_ref` | Parker-kruisverwijzing (staal), leeg als het een eigen Geeve-only artikel is |
| `familie_code` | 4-cijferige productfamiliecode uit de catalogus (bijv. `2244`) |
| `familie_naam` | Naam van de productvorm (bijv. "Union", "Union elbow", "Branch tee") |
| `omschrijving` | Aansluitbeschrijving uit de catalogus (bijv. "BSPP 60° Cone end") |
| `hoek` | `recht` / `haaks` (90°) / `45°` / `T-stuk` / `kruis` / `n.v.t.` (plug/cap/wig) |
| `draadsoort_1`, `draadmaat_1`, `connectie_type_1` | Aansluiting 1: standaard, maat, buiten/binnen/wartelend |
| `draadsoort_2`, `draadmaat_2`, `connectie_type_2` | Aansluiting 2: idem (leeg bij 1-zijdige artikelen zoals pluggen/doppen) |
| `tube_od_mm`, `tube_od_inch` | Buitendiameter van de leidingzijde (alleen relevant voor de Triple-Lok/O-Lok buis-fitting-reeks) |
| `bron_pagina` | Paginanummer in de brondocument-PDF, voor traceerbaarheid |

## Herkomst van de data

Alle gegevens komen uit `docs/1._Adapters_2018_Compleet.pdf` (112 pagina's, 141
productfamilies). Alleen de **staal**-uitvoering is overgenomen (RVS/Brass-kolommen uit de
catalogus zijn niet nodig voor dit project en dus niet meegenomen).

### Uitgesloten productfamilies (7 van de 142 gevonden koppen)
Deze zijn geen 2-zijdige draad-adapters en passen niet in dit datamodel:

- `5161`/`5162` — Dowty-afdichtringen (seals), geen koppeling met twee draadzijden.
- `5130` — O-ring voor ORFS.
- `8120`, `8180` — Sleeve (huls voor Triple-Lok/O-Lok buis-fittingen, montageonderdeel).
- `8140`, `7140` — Nut (moer voor Triple-Lok/O-Lok/O-Lok-ORFS buis-fittingen, montageonderdeel).
- `8224` — Tube end reducer: enige familie met een structureel afwijkende kolomopbouw (twee
  paar buitendiameters + een "Form A/B"-variantkolom) die niet op dezelfde manier is te
  ontleden als de rest van de catalogus. Kan bij behoefte alsnog met de hand toegevoegd worden.

### Matching-methodiek (per productfamilie-tabel)
De PDF-tabellen zijn ontleed op woordpositie (x/y-coördinaten per woord, niet op ruwe
tekstlay-out) om kolommen betrouwbaar te scheiden, inclusief tabellen waarbij:
- de draadsoort-naam (bijv. "BSPP", "NPT/NPTF") in een andere kopregel staat dan de
  Steel/Stainless-materiaalrij;
- een cross-reference-code uit meerdere woorden bestaat (bijv. "1/16 HHP-S");
- een logische rij door de PDF over twee tekstregels wordt verdeeld (lettertype-rendering).

**"Hoek" en "connectie type" worden afgeleid, niet letterlijk overgenomen:**
- `hoek` volgt uit de familienaam (`tee`→T-stuk, `cross`→kruis, `plug`/`cap`/`welding socket`→
  n.v.t., `45°`→45°, `90°`/`elbow` (zonder hoekvermelding = altijd 90° in deze catalogus)
  →haaks, anders→recht).
- `connectie_type` (buiten/binnen/wartelend) wordt PER AANSLUITING afgeleid uit het deel van de
  aansluitbeschrijving dat echt bij die kant hoort — niet uit de complete beschrijving in één
  keer. De beschrijving wordt eerst in aparte stukken gesplitst (op "/"), waarna elk stuk aan
  aansluiting 1 of 2 wordt gekoppeld op basis van welke draadstandaard (BSPP/NPTF/UN-UNF/...)
  erin genoemd wordt; als beide aansluitingen dezelfde standaard delen (dus de naam alleen niet
  onderscheidt) geldt de volgorde in de PDF (eerste stuk → aansluiting 1, tweede → aansluiting
  2) — tenzij het niet-overeenkomende stuk juist een ANDERE standaard noemt (zoals bij een tak
  van een T-stuk): dan hoort dat stuk bij geen van beide en delen aansluiting 1 en 2 het ene
  stuk dat wel overeenkomt. Op het zo gevonden stuk tekst geldt: "swivel"→wartelend (een
  wartelende moer is in deze catalogus altijd vrouwelijk, maar wel een apart, los filterbaar
  aansluittype — geen "swivel" is in de data ooit met "male" gecombineerd of zonder geslachtswoord
  voorgekomen, dat is nagelopen), anders "female"→binnen, "male"→buiten, "cone end"/"flare
  end"/"tube end"→buiten; voor pluggen/doppen zonder expliciete vermelding geldt plug=buiten,
  cap=binnen. Deze stuksgewijze aanpak is nodig omdat een simpele zoekopdracht over de hele
  beschrijving voor bijv. "Swivel male stud" ("BSPP 60° cone end / BSPP 60° Female swivel end")
  anders "female"/"swivel" zou vinden en dat abusievelijk aan BEIDE aansluitingen zou toekennen,
  terwijl alleen de wartelende kant wartelend is en de
  kant met de conus buiten blijft.
- Een klein aantal families gebruikt in de tabelkop een andere naam voor dezelfde draadfamilie
  dan gebruikelijk (bijv. "NPTF" i.p.v. "NPT/NPTF", "UNF" i.p.v. "UN/UNF"); deze worden
  samengevoegd tot één draadsoort-waarde zodat de selector niet twee bijna-identieke opties
  toont voor dezelfde draad.
- Een aparte "JIS"-draadsoort wordt herkend wanneer de aansluitbeschrijving expliciet "JIS BSPP"
  noemt (families `2246`, `2219`, `2419`, `2619`, `2149`, `2119`) — dit is een eigen
  Japanse-industriestandaard-conusvariant, geen gewone BSPP, ook al gebruikt de kolomkop van de
  tabel zelf nog "BSPP" als label.
- Een aparte "JIC"-draadsoort wordt herkend wanneer de aansluitbeschrijving voor die specifieke
  aansluiting "Triple-Lok®" noemt terwijl de kolomkop "UN/UNF" als label gebruikt (45 families).
  De draad zelf (UN/UNF) is hetzelfde als bij O-Lok/ORFS-fittingen en de "Adjustable UN/UNF
  thread"-boskoppelingen, maar de aansluitconus verschilt: Triple-Lok® is Parkers eigen naam voor
  de industriestandaard 37°-conus die algemeen "JIC" heet, terwijl O-Lok/ORFS een vlak vlak met
  O-ring is (geen conus) en de boskoppeling een rechte draad met O-ring. Dit onderscheid wordt
  net als bij "connectie type" per aansluiting apart bepaald: bij familie `8223` (Male stud
  connector) is de ene kant bijvoorbeeld Triple-Lok/JIC en de andere kant, ondanks dezelfde
  nominale maat, de rechte UN/UNF-boskoppeling — dat blijft dus terecht `UN/UNF`.
- Sommige 2-zijdige families (bijv. "Swivel nut elbow": cone end ↔ female swivel end) geven in
  de catalogus maar **één** draadmaat omdat beide zijden dezelfde nominale maat delen — alleen
  het aansluittype verschilt. In dat geval is de maat naar aansluiting 2 gekopieerd en is het
  connectietype per aansluiting apart uit de beschrijving afgeleid zoals hierboven beschreven.

### Bekende afwijkingen in de brondata (met de hand gecorrigeerd)
- **`8623-10-06`**: deze rij was in de PDF over twee tekstregels gesplitst in een volgorde
  (artikelcode vóór de rest van de rij-data) die de automatische samenvoeglogica niet dekte.
  De waarden zijn met de hand overgenomen uit de omliggende context in de PDF.
- **`2221-16-16`**: de draadmaat voor aansluiting 2 werd in de PDF als "1 11" geëxtraheerd
  (koppelteken tussen "1" en "11" ontbreekt door een renderingsartefact). Op basis van het
  patroon van de omliggende rijen (en de identieke maat "1-11" die elders in dezelfde tabel wél
  correct staat) is dit gecorrigeerd naar "1-11".
- **10 artikelcodes komen tweemaal voor** met verschillende afmetingen (bijv. `8244-06-08`,
  `2224-20-24`, ...). Dit is een echte eigenschap van de brondata: dezelfde nominale
  maatcodering dekt in een paar gevallen twee net iets andere afmetingen aan de randen van
  aangrenzende maatbereiken. Beide rijen zijn bewaard.
- **Familie `7224` (Male stud connector)**: als enige van de vier vergelijkbare families
  (`7223`/`7224`/`7225`/`7226`) ontbreekt in de PDF de kolomkop-tekst voor de tweede
  draadmaat-kolom (de andere drie hebben wél twee losse standaardnamen in die kopregel). Zonder
  die kop landden beide maten van elke rij samengeplakt in één veld (bijv. `"9/16-18 1/8-28"`).
  Elke rij bevat betrouwbaar precies twee complete maat-tokens, dus deze zijn uit elkaar
  getrokken; de draadsoort van aansluiting 2 (`BSPP`) komt uit de subtitel-tekst van de familie
  ("Male BSPP thread"), die daar wél correct in staat.

## Bestanden overgenomen uit Geeve_hose
De basislayout (paginastructuur, CSS, upload/download-infrastructuur) is 1-op-1 overgenomen uit
het Geeve_hose-project. `index.php` en `assets/selector.js` zijn volledig herschreven voor het
adapters-datamodel en de 6-veldenselectie; `assets/style.css`, `data.php`, `download.php`,
`upload.php` en `inc/csv-paths.php` zijn aangepast voor één CSV-dataset in plaats van drie.
