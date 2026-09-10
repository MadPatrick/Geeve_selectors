# AGENTS.md — Hoe selecties en keuzes in deze dataset worden gemaakt

Dit document legt de **methodologie** vast waarmee artikeldata in deze repo wordt gekoppeld, aangevuld en
gecorrigeerd — zowel de runtime-matching in de applicatie zelf als de manier waarop (met AI-hulp) brondata uit
PDF-catalogi in de CSV-bestanden wordt verwerkt. Het doel is dat een volgende sessie (mens of AI-agent) dezelfde
regels toepast, dezelfde valkuilen vermijdt en dezelfde afwegingen maakt zonder alles opnieuw te hoeven
uitvinden.

Voor versienummer-afspraken: zie `CLAUDE.md`. Voor de chronologische geschiedenis van elke databron en -ronde
(wat is wanneer aangevuld, met welke aantallen): zie `README.md`. Dit document (`AGENTS.md`) beschrijft de
**regels**, niet de geschiedenis.

## 1. Databestanden en hun rol

- `data/artikelnummers_staal.csv` en `data/artikelnummers_rvs.csv`: 30 kolommen, UTF-8 met BOM, **LF**-regeleinden.
  Bevatten dezelfde soort slangartikelen in twee materiaaluitvoeringen. **Elk artikel dat in de ene lijst
  voorkomt, hoort ook in de andere te staan** (zelfde `artnr`, 1-op-1) — ontbreekt een `artnr` in één van de twee,
  dan is dat een fout die hersteld moet worden (zie §6).
- `data/artikelnummers_accessoires.csv`: 13 kolommen, UTF-8 met BOM, **CRLF**-regeleinden (let op: afwijkend
  van staal/rvs). Gekoppeld aan staal/rvs op exacte `artnr`-match; niet elk artikel hoeft hier een rij te hebben.
- Na **elke** wijziging aan een van deze bestanden: verifiëren dat BOM, regeleinde-stijl en kolomaantal
  (30 resp. 13) intact zijn gebleven, en dat er geen dubbele `artnr`'s zijn ontstaan. Nooit met een tool opslaan
  die deze eigenschappen stilzwijgend verandert (bijv. `csv.writer` zonder expliciete `lineterminator`).

### Kolomschema staal/rvs (30 kolommen)
`artnr; artnm; Leverancier; Artikelnr leverancier; Werkdruk (bar); 2delig_1 - Huls; 2delig_1 - Pilaar;
2delig_1 - Persmaat (mm); 2delig_1 - Schilmaat intern (mm); 2delig_1 - Schilmaat extern (mm);
2delig_2 - Huls; 2delig_2 - Pilaar; 2delig_2 - Persmaat (mm); 2delig_2 - Schilmaat intern (mm);
2delig_2 - Schilmaat extern (mm); 1delig_1; 1delig_1 - Persmaat (mm); 1delig_1 - Insteekdiepte (mm);
1delig_1 - Schilmaat intern (mm); 1delig_1 - Schilmaat extern (mm); 1delig_2; ... (idem _2); 1delig_3; ... (idem _3)`

`1delig_N` heeft één koppelingscode (1-delige crimpkoppeling: huls en pilaar zijn één stuk). `2delig_N` heeft
**aparte** Huls- en Pilaar-codes (2-delige koppeling: los pershuls + los pilaar/nippel) en geen Insteekdiepte.

## 2. Runtime-matching in de applicatie (hoe de app zelf selecteert)

- **Staal ⨯ RVS samenvoegen**: `index.php` matcht op `articleKey()` = `'@' . strtolower(trim($artnr))` — een
  exacte, case-insensitive match op de volledige `artnr`-string. Geen fuzzy matching, geen dash-maat-logica.
  Ontbrekende tekstvelden (`artnm`/`vendor`/`supplier`/`werkdruk`) worden bij het samenvoegen aangevuld vanuit
  welk bestand die het eerst niet-leeg heeft.
- **Accessoires koppelen**: exact dezelfde `articleKey()`-match tegen `artikelnummers_accessoires.csv`.
- **Groeperen per slangtype voor het printoverzicht**: `hoseTypeInfo()` in `assets/selector.js` bepaalt het
  "type" van een artikel voor de PDF-catalogus-indeling (welke rijen samen onder één koptekst vallen):
  - Parker SR/SRI-multispiraalslangen (`SR25`, `SR29`, `SR35`, `SR45`, `SRI42`, ...) vormen altijd één
    gezamenlijke groep `"SR/SRI"`, ongeacht het cijfer erachter.
  - `PLK*ST`/`PLK*TC`/`PLK*SN` vormt een eigen speciale case (`/^(PLK)\d+(ST|TC|SN)?/i`): het cijfer direct na
    "PLK" is betekenisloze ruis in de brondata (verschilt per rij zonder informatiewaarde) en wordt genegeerd,
    zodat alle `PLK...`-varianten samen één groep `"PLK"` vormen — met en zonder `ST`/`TC`/`SN`-achtervoegsel.
  - Voor overige artikelen: het deel vóór het eerste streepje wordt ontleed als
    `(letters)(cijfers)(letters)` — met beide lettergroepen en het cijferblok elk optioneel (`\d*`, niet `\d+`),
    zodat ook pure letter-prefixes zonder cijfers (bijv. `BPK-12`, `PDH-7`) gegroepeerd worden. Een `ST`-, `TC`-
    of `SN`-achtervoegsel wordt **samengevoegd** met de kale variant (bijv. `0492ST`/`0492TC` → type `492`;
    `R42`/`R42ST`/`R42TC` → type `R42`; `301SN`/`301TC` → type `301`), andere achtervoegsels (`PU`, `RH`, `LT`,
    ...) blijven een eigen type.
  - Dit is puur presentatielogica — **niet** dezelfde matching die gebruikt wordt om brondata te koppelen (zie
    §3), al lijkt de ST/TC/SN-regel er sterk op.
- **Print-hoofdstukken en scheidingspagina's**: `renderCatalogPrintSheet(scope)` bouwt de printbare catalogus op
  uit hoofdstukken (`Accessoires`, `1-delig`, `2-delig`, `Staal`, `RVS`) via `buildChapterPages(chapterLabel,
  titleText, table)`, dat telkens een scheidingspagina (`buildDividerPage`, grote hoofdstuktitel) direct gevolgd
  door de inhoudspagina's van dat hoofdstuk teruggeeft. Elke pagina krijgt via `buildPrintHeader(chapterLabel)`
  een vetgedrukte hoofdstuknaam rechtsboven. De downloadknop biedt 4 scopes: `all` (compleet), `accessoires`,
  `staal`, `rvs` — `scope` bepaalt welke hoofdstukken worden opgebouwd.

## 3. Matching-algoritme: artikel ↔ brondocument (crimp-specs, hosecatalogi)

Dit is de kernlogica die bij **elke** ronde van brondata verwerken (perslijst-PDF's, hosecatalogi, etc.) is
gebruikt om een CSV-rij aan de juiste tabel/pagina in een extern document te koppelen. Vastgelegd na een aantal
iteraties waarin een simpelere aanpak dataverlies of foutieve matches bleek te geven — gebruik deze volgorde, niet
een eigen variant, tenzij je een nieuw probleem tegenkomt (documenteer dat dan hier).

### 3.1 Sleutel (hoseFamily) en maat bepalen
```
raw_key(tekst)  = alles vóór het eerste "-", in hoofdletters, met een leidende 0 gestript (alleen als gevolgd
                  door een cijfer: "0421" -> "421", maar "SR45" blijft "SR45")
base_key(tekst) = raw_key(tekst), met een trailing "ST" of "TC" gestript ("421SN" blijft staan, "492ST" -> "492";
                  N.B. dit strip ALLEEN ST/TC, geen andere suffixen zoals PU/RH/LT/DL)
dash(artnr)     = eerste cijferreeks ná het eerste "-" in de EIGEN artnr-kolom van de rij (dus nooit uit
                  Artikelnr leverancier!), met leidende nullen gestript ("04" -> "4", "0" + geen cijfers -> leeg)
```

### 3.2 Twee kandidaat-bronnen voor de sleutel, in deze volgorde
1. **Eerst**: `raw_key(artnr)` en `base_key(artnr)` — Geeve's **eigen** artikelnummer-kolom.
2. **Pas als dat niets oplevert**: `raw_key(Artikelnr leverancier)` en `base_key(Artikelnr leverancier)` — het
   veld met de code van de leverancier zelf (bij Parker meestal de "echte" Parker-productcode).

De maat (`dash`) komt **altijd** van de eigen `artnr`-kolom, nooit van `Artikelnr leverancier`, ook niet in stap 2.

**Waarom deze volgorde en niet andersom**: bij een deel van de Parker-slangen wijkt Geeve's interne nummering
volledig af van Parkers eigen productcode (bijv. intern `0311-XX` ⇄ Parker `421SN-X`, intern `0421-XX` ⇄ Parker
`301SN-X`). Matchen puur op de eigen `artnr`-kolom mist die rijen volledig (de crimp-spec-PDF's zijn per Parker-
productcode ingedeeld); matchen puur op `Artikelnr leverancier` kan juist mis gaan wanneer dát veld een
inconsistentie bevat (bijv. rij `0590-06` had toevallig `590TJ-6` in Artikelnr leverancier staan — een heel ander
product dan het eigen, correcte `0590`-type). Eerst de eigen kolom proberen en pas bij een misser terugvallen op
de leverancierskolom voorkomt beide fouten.

### 3.3 Uitzondering: prefix onafhankelijk van een ingebakken cijfer (bijv. PLK-familie)
Sommige artikelnummers bevatten een cijfer vlak na een merknaam-prefix dat **niets** met het slangtype te maken
heeft, maar met een andere eigenschap (bij de `PLK*TC-XX`-reeks bleek dat cijfer willekeurig per rij en niet
herleidbaar tot het bronbestand, dat overkoepelend "PLK_TC" heet). Los dit op met een specifieke regel
(`^PLK\d+(ST|TC)-` → forceer sleutel `PLK_TC`) in plaats van te proberen de generieke `raw_key`/`base_key`-logica
hierop te laten werken. Kom je een vergelijkbaar geval tegen: voeg een soortgelijke gerichte uitzondering toe en
documenteer die hier, in plaats van de generieke regels te verbuigen tot ze overal "toevallig" op werken.

## 4. Keuzeregels bij meerdere mogelijke koppelingen (1-delig)

- Per maat kunnen meerdere koppelingseries mogelijk zijn (het CSV-schema biedt 3 sloten: `1delig_1/2/3`).
- **Serie `48` krijgt, als die voor die maat beschikbaar is, altijd slot 1.** Overige series vullen slot 2 en 3,
  in de volgorde waarin ze in de brondata voorkomen (geen verdere prioriteit tussen niet-48-series).
- Reeds gevulde sloten die niet overeenkomen met een nieuwe, betrouwbaardere bron: zie §6 (aanvullen vs.
  corrigeren).

### 4.1 Combinatie-PDF's met twee koppelingseries in één tabel
Sommige crimp-spec-PDF's noemen twee series samen in de "Coupling Style"-kop (bijv. "43/48", "VS/V4") zonder dat
de maattabel zelf per rij aangeeft welke serie bij welke maat hoort — dat onderscheid staat dan alleen in de
vrije opmerkingtekst onderaan de PDF (bijv. "Series 48 for sizes -20 up to -32", "Size -4 up to -8 Fitting VS").
**Nooit** beide series voor elke maat aanbieden alsof ze allebei overal geldig zijn — lees de opmerkingtekst en
ken elke maat exact één serie toe. Dit gaf bij een eerste, te snelle verwerking 4 foutieve "48"-waarden op maten
waar eigenlijk alleen "43" gold (families 304, 426, 881) — pas toegevoegde/gecorrigeerde koppelingsdata dus altijd
tegen de brontekst, niet enkel tegen de tabelkolommen.

## 5. 2-delige koppelingen (Huls/Pilaar): VS/V4/V6/V5/WB-serie

- Deze PDF's geven **geen** Insteekdiepte, maar wel Persmaat plus een aparte "External/Internal Skive"-tabel
  onderaan (= Schilmaat extern/intern).
- **Huls en Pilaar krijgen dezelfde koppelingscode**, met één harde uitzondering: **bij serie `V4` is Pilaar
  altijd `30`** (Huls blijft `V4`). VS, V5 en V6 houden dezelfde code in beide velden. Dit is een expliciete
  instructie van de gebruiker, niet af te leiden uit de PDF's zelf — wijk hier niet vanaf zonder nieuwe
  bevestiging.
- **Huls-codering voor `V4` en `V6` (in zowel Staal als RVS)**: de kale koppelingscode `V4`/`V6` wordt in de
  Huls-kolom herschreven naar `100<code>-<maat>` (bijv. koppeling `V4`, maat `20` → `100V4-20`). In **RVS** komt
  er bovendien een `C` achter (`100V4-20C`); in **Staal** niet. Dit geldt specifiek voor `V4` en `V6` — `VS`, `V5`
  en `WB` blijven in zowel Staal als RVS gewoon hun kale code (`VS`, `V5`, `WB`) in de Huls-kolom, zonder
  `100..`-voorvoegsel. De Pilaar-waarde en alle 1-delige coderingen blijven in RVS **ongewijzigd** gelijk aan
  Staal — alleen de 2-delige Huls-kolom krijgt deze materiaalspecifieke vorm. `<maat>` is de eigen dash-maat van
  de rij, zonder padding (zoals die al in de rest van de dataset wordt gebruikt).
- Bij maten met alleen externe skiving (de "VS"-maten in een gecombineerde VS/V4-PDF) blijft Schilmaat intern
  leeg — niet invullen met "0" of een gok.

## 6. Aanvullen vs. corrigeren — welke bron is leidend

- **Leeg veld + brondata beschikbaar** → aanvullen, geen twijfel.
- **Al gevuld veld + brondata wijkt af**: numeriek vergelijken (komma/punt genormaliseerd), niet als string.
  Tolerantie: ~0,05 voor crimp-afmetingen (mm), ~0,5 voor Werkdruk (bar) — puur-numerieke opmaakverschillen
  ("36,3" vs "36,30") tellen niet als afwijking.
- **Echte afwijking**: de laatst aangeleverde, meest specifieke/directe bron is leidend, expliciet zo ingesteld
  door de gebruiker per bron:
  - Parkrimp perslijst-PDF's zijn leidend over eerder handmatig/via omschrijving ingevoerde koppelingsdata.
  - De **UK**-versie van de Parker hose-catalogus (CAT_4400/UK) is leidend over de eerder aanwezige **US**-versie.
  - Bij twijfel over welke van twee door de gebruiker aangeleverde bronnen leidend is: vraag het, ga niet zelf
    kiezen.
- **Iedere wijziging wordt zowel als "aanvulling" als "correctie" apart bijgehouden en gerapporteerd** (aantallen
  in de samenvatting aan de gebruiker en in `README.md`) — nooit stilzwijgend overschrijven zonder dat onderscheid
  te melden.
- **Nooit gokken of extrapoleren** wanneer er geen brondata is voor een specifieke rij/maat. Een leeg veld is een
  geldige, informatieve toestand in deze dataset (de UI toont ontbrekende waarden nette als `-`); een geraden
  waarde is dat niet. Bij twijfel: veld leeg laten en het gat expliciet documenteren (zie ook §7).

## 7. Wanneer NIET automatisch matchen — bekende valkuilen in brondata

Deze zijn er niet uit voorzorg opgeschreven maar omdat ze **daadwerkelijk fout gingen** bij eerdere verwerking en
pas na gerichte controle (steekproeven tegen de brontekst, kruischecks tussen kolommen) aan het licht kwamen.
Controleer hier altijd op vóórdat je een matching-resultaat toepast:

1. **Zero-padding-mismatch in de maat.** Geeve's eigen `artnr` gebruikt vaak 2-cijferige maten ("-04", "-06");
   sommige brondocumenten gebruiken geen padding ("-4", "-6"). Normaliseer (`str(int(d))`) vóór het vergelijken,
   anders missen kleine maten stelselmatig terwijl grote (al 2-cijferig, bijv. "-20") toevallig wél matchen —
   een sluipende, deels-werkende bug die makkelijk over het hoofd wordt gezien omdat een deel van de matches gewoon
   lukt.
2. **"Twin hose"-varianten.** Sommige slangtypen hebben naast de normale uitvoering een dubbele-bundel-variant
   met een Part Number dat begint met dezelfde familie+maat plus een extra `-cijfer` (bijv. `692PU-4-4` naast
   `692PU-4`). Een `raw_key()` die simpelweg alles vóór het *eerste* streepje pakt, groepeert deze abusievelijk
   samen met de normale slang. Herken en sluit twin-varianten expliciet uit (regex op een tweede `-\d` na de
   normale familie+maat) vóórdat je waarden zoals buitendiameter overneemt — een twin-slang heeft een wezenlijk
   andere (vaak ~2×) buitendiameter.
3. **Decimaalteken-inconsistentie binnen één PDF/catalogus.** Sommige brondocumenten gebruiken op de ene pagina
   een punt en op de andere een komma als decimaalteken (waarschijnlijk het gevolg van verschillende
   locale-instellingen bij het genereren van losse hoofdstukken). Een numerieke check die alleen `float()` op een
   punt-notatie toepast, laat komma-rijen stilzwijgend vallen — en dat kan een matching-fout **maskeren** in
   plaats van voorkomen (zie punt 2: de twin-hose-bug werd pas zichtbaar nádat dit was opgelost). Accepteer altijd
   beide notaties bij het parsen van brontabellen.
4. **Tabellen die toevallig op een pershuls-/koppelingstabel lijken maar het niet zijn** (bijv. schroefdraad- of
   flensmaattabellen in eenzelfde catalogus als de slangspecificaties). Verifieer een geëxtraheerde
   werkdruk-waarde altijd kruislings tegen de bijbehorende psi-waarde in dezelfde rij (1 MPa ≈ 145,038 psi,
   ruime tolerantie ~8% voor afronding) — een tabel die faalt op die check is hoogstwaarschijnlijk geen
   drukspecificatie maar een afmetingentabel, en moet genegeerd worden.
5. **Eén brondocument met intern tegenstrijdige waarden voor dezelfde combinatie.** Kwam één keer voor
   (`477ST-8`, twee verschillende drukwaarden op twee plekken in dezelfde catalogus, geen aanwijsbare reden welke
   correct is). Los dit niet zelf op met een educated guess — sla die specifieke combinatie over en meld het.

## 8. Overige vastgelegde conventies

- **Coderingen in hoofdletters**: alle waarden in `1delig_N`, `2delig_N - Huls` en `2delig_N - Pilaar` staan
  consistent in hoofdletters (ook oudere, historisch al aanwezige coderingen zoals de `1300pX-..rvs`-reeks in
  RVS).
- **Werkdruk (bar)**: geheel getal zonder decimaal als de waarde exact rond is, anders met een komma als
  decimaalteken (nooit een punt) — zie `add_werkdruk.py`-stijl-afronding: MPa × 10, PSI ÷ 14,5038.
- **Firesleeve-kolom** (in `artikelnummers_accessoires.csv`): kan zowel een `FS-F-..`- als een `9125-..`-code
  bevatten, kommagescheiden als beide van toepassing zijn (`FS-F-16,9125-16`) — de `9125-..`-code (Parker
  Pyrojacket) wordt bepaald uit de dash-maat van de slang zelf via de standaard inch/dash-conventie
  (1/4" = -04, 3/8" = -06, ... 4" = -64), **niet** uit de buitendiameter.
- **Overige accessoirecoderingen (`PolyGuard`/`ParKoil`/`Spring Guard`/`SpiralGuard`/`Texsleeve`/`Huls tex staal`/
  `Huls tex RVS`/`FS-F-..`)** hangen af van de werkelijke **Buitenmaat slang (mm)**, niet van de dash-maat — en
  dus alleen te bepalen als die kolom gevuld is. Bepaal per veld eerst of de koppeling Buitenmaat→code in de
  reeds gevulde rijen een **schone, niet-overlappende staffel** vormt (per code een aaneengesloten bereik zonder
  overlap met de buur): dat bleek zo te zijn voor `SpiralGuard`, `Texsleeve`, `Huls tex staal` en `Huls tex RVS`
  — voor die vier mag je dus een nieuwe rij met bekende Buitenmaat automatisch matchen tegen die staffel (val bij
  een waarde in een echt gat tussen twee bereiken niet automatisch terug op de dichtstbijzijnde code, tenzij het
  gat kleiner is dan ~1 mm; laat het veld anders leeg). Voor `PolyGuard`, `ParKoil`, `Spring Guard` en de losse
  `FS-F-..`-code bleken de bereiken per code juist merkbaar te **overlappen** tussen buren (bijv. `HG-075` en
  `HG-125` overlappen tussen 19,5–24,0mm) — Buitenmaat alleen is daar dus geen betrouwbare voorspeller en dat mag
  je niet automatisch invullen zonder een aanvullende regel of bron.
- **Nieuwe slangartikelen toevoegen**: altijd in **alle drie** de bestanden waar relevant — Staal, RVS (als het
  materiaal van toepassing is) én Accessoires — nooit alleen in Staal. Voeg nieuwe rijen toe aan het **einde** van
  het bestand (niet tussenvoegen op alfabetische positie) zodat de wijziging een zuiver additieve, makkelijk te
  controleren diff blijft.
- **Leverancier bij ontbrekende leveranciersnaam** (bijv. een crediteurnummer in plaats van een naam): nooit
  raden. Vraag de gebruiker om de koppeling crediteurnummer → leveranciersnaam, en registreer eventuele
  uitzonderingen/omzettingen die de gebruiker daarbij aangeeft (zoals "dit crediteurnummer staat er technisch als
  X maar moet Y worden") letterlijk zo toegepast.

## 9. Verificatie na elke wijzigingsronde

Vóór het committen, altijd controleren:
1. BOM + regeleinde-stijl (LF voor staal/rvs, CRLF voor accessoires) intact.
2. Elke rij nog exact het verwachte aantal kolommen (30 resp. 13).
3. Geen dubbele `artnr`'s ontstaan.
4. Rijaantal Staal == rijaantal RVS (1-op-1-pariteit, zie §1).
5. Steekproef: minstens een paar aanvullingen/correcties handmatig terugcontroleren tegen de brontekst van de PDF
   (niet enkel vertrouwen op het parse-resultaat) — zeker bij afwijkende/onverwachte waarden (zie §7).
6. Aantallen aanvullingen/correcties per bestand rapporteren aan de gebruiker en vastleggen in `README.md`.
