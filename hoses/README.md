# Slangen fitting Selector v0.0.5

## Bestanden
- `index.php`
- `assets/style.css`
- `assets/selector.js`
- `artikelnummers_staal.csv` in root of `data/`
- `artikelnummers_rvs.csv` in root of `data/`
- `artikelnummers_accessoires.csv` in de applicatiemap **of** `data/artikelnummers_accessoires.csv`
- `fix_csv_quotes.php` (eenmalige CSV-correctietool)

## Werking
- Eén zoekveld voor de volledige slanglijst.
- Staal en RVS worden automatisch samengevoegd op `artnr`.
- Na selectie worden de passende accessoires uit `artikelnummers_accessoires.csv` direct onder de artikelgegevens weergegeven.
- De accessoirevelden zijn: Buitenmaat slang, PolyGuard, ParKoil, Spring Guard, Firesleeve, SpiralGuard, Texsleeve, Huls Texsleeve (Staal) en Huls Texsleeve (RVS).
- Alleen als voor het geselecteerde artikel accessoiregegevens bestaan, wordt het accessoirekader weergegeven.
- Ontbrekende waarden binnen een bestaande accessoireregel worden als `-` getoond.
- 1-delige koppelingen worden vóór 2-delige koppelingen weergegeven.
- Staal en RVS blijven per materiaal gegroepeerd.
- De versie `0.0.5` staat zichtbaar in de kop.

## Werkdruk en zoeken op maat
- Beide CSV-bestanden hebben een kolom `Werkdruk (bar)`. Die waarde is automatisch geëxtraheerd uit de omschrijving
  (patroon `W.P. ... BAR`, ook bij MPa/PSI-eenheden of afgekapte omschrijvingen). Bij 578 van de 835 artikelen kon dit
  betrouwbaar worden bepaald; de overige artikelen (vooral Parker-slangen buiten de meegeleverde catalogus, zie
  `docs/Parker HPD_4400_Catalog_hoses.pdf`) hebben een lege werkdrukwaarde en moeten handmatig worden aangevuld.
- Naast "Artikel zoeken" staan twee extra velden: "Werkdruk" en "Maat". Zijn "Werkdruk" en/of "Maat" ingevuld en staat
  "Artikel zoeken" leeg, dan wordt daarop gefilterd i.p.v. op de vrije tekstzoekopdracht.
- "Maat" is de laatste cijferreeks in het artikelnummer (bijv. `04` in `0311-04`).

## Firesleeve: 9125-.. (Pyrojacket) toegevoegd als alternatief voor FS-F-..
De gebruiker leverde `docs/9125 Firesleeve.pdf` aan (Parker Pyrojacket®, een alternatief hittebeschermend
slangomhulsel naast de al aanwezige FS-F-serie). De maattabel in de PDF geeft per bestelcode de binnendiameter in
inches; die inch-maat komt 1-op-1 overeen met de dash-maat-notatie die overal in deze dataset gebruikt wordt
(bijv. 1/4" = dash `-04`, 3/8" = dash `-06`, 1" = dash `-16`, 2" = dash `-32`, ... t/m 4" = dash `-64`).

In `artikelnummers_accessoires.csv` is voor elke rij de dash-maat bepaald uit de laatste cijferreeks van `artnr`
(dezelfde conventie als "Maat" elders in dit document) en gekoppeld aan de passende `9125-..`-bestelcode. Deze code
is toegevoegd in de kolom `Firesleeve`: stond er al een `FS-F-..`-waarde, dan is de nieuwe code er met een komma
achter geplakt (bijv. `FS-F-16,9125-16`); was het veld leeg, dan is enkel de `9125-..`-code ingevuld. In totaal
zijn 746 van de 835 rijen aangepast (324 aanvullingen op een bestaande FS-F-waarde, 422 rijen die voorheen geen
Firesleeve-optie hadden). Rijen met een dash-maat buiten de 9125-reeks (bijv. `-13`, `-19`, `-25`, `-30` of
niet-standaard maten) zijn ongewijzigd gelaten.

## Koppeling accessoires
De koppeling gebeurt exact op de kolom `artnr`. Een slang met `artnr` `0462TC-04` krijgt dus de regel `0462TC-04` uit `artikelnummers_accessoires.csv`.

## Koppelingserie thermoplastische Parker-slangen (2022N/2244N/2380N/2388N/2440N/2580N/2640N)
Voor deze Parker-Polyflex-slangen (ultra-hogedruk thermoplastisch) stond het `1delig_1/2/3`-veld leeg. Uit
`docs/Parker-Thermoplastic-Catalog_4462_UK.pdf` is per slangtype de bijpassende koppelingsserie (fittingcode zoals
`8X`, `LX`, `BL`, `BS`, ...) ingevuld:
- Voor slangen uit hoofdstuk C (design factor >2:1: 2580N, 2440N, 2640N, 2648N) staat de koppelingsserie exact per
  artikel/maat in de catalogus vermeld en is 1-op-1 overgenomen.
- Voor slangen uit hoofdstuk B (design factor 4:1: 2022N, 2244N, 2380N, 2388N) geeft de catalogus alleen een
  koppelingsserie per slangfamilie (niet per maat); die is toegepast op alle maten binnen die familie.
- De Persmaat (mm) is aangevuld vanuit sectie F ("Technical Overview Hose by Inside Diameter", F-23 t/m F-25),
  kolom "Ferrule OD after crimping". Dit is één waarde per slangartikel (niet per koppelingserie) en is daarom in
  elke gevulde `1delig_N - Persmaat (mm)`-kolom van die rij gezet. Alleen bij een exacte match op
  "Artikelnr leverancier" is deze ingevuld (12 van de 34 aangevulde slangen).
- **Insteekdiepte en Schilmaat staan niet in deze catalogus** (dit zijn "crimp-on" fittingen met eigen maatvoering,
  geen Huls/Pilaar-systeem, en de catalogus geeft geen insteekdiepte) en zijn daarom bewust leeg gelaten.
- Overige Parker-thermoplastische series die niet in deze catalogus voorkomen (o.a. 2020N, 2040N/H, 2245N, 2370N,
  2390N, 2440D, 2640D, 2648N buiten de vermelde maten) zijn niet aangevuld.

## Koppelingserie via parker.com (2020N/2040N/2040H/2245N/2370N/2380F/2390N/2440D/2640D)
Voor de resterende Parker-thermoplastische series (niet aanwezig in de 4462-catalogus) is de "Compatible Fitting"
op de productpagina's van ph.parker.com opgezocht en als koppelingsserie ingevuld: 2020N→EX, 2040N/2040H→PX,
2245N→NX, 2370N→9X/NX, 2380F→NX, 2390N→8X/9X/E3(/E4), 2440D→LX, 2640D→2X. Let op de betrouwbaarheid hiervan:
- Deze bron is **niet rechtstreeks als PDF/tabel geverifieerd** (ph.parker.com/www.parker.com zijn vanuit deze
  sessie-omgeving niet rechtstreeks te benaderen); de waarden komen uit zoekresultaat-samenvattingen van Parker's
  eigen productpagina's, per slangserie (niet per maat).
- Waar 2040H/2040N al betrouwbare koppelingsdata had (koppeling `56`, geverifieerd via
  `docs/Parkrimp Wall Chart WC_4400.pdf`, maten -03 t/m -08), is die bestaande data leidend gebleven; alleen de
  maten die daar niet in voorkwamen (-02, -10, -12, -16) hebben de `PX`-waarde van parker.com gekregen.
- Persmaat, Insteekdiepte en Schilmaat zijn hierbij niet aangevuld (niet beschikbaar uit de zoekresultaten).
- Series waarvoor geen eenduidig antwoord te vinden was (1202LT, 5155, PDH-2, 2380M, 2248D, 2030T) zijn bewust
  overgeslagen.
- **Advies:** controleer deze koppelingsseries tegen de officiële Parker-documentatie voordat ze voor een
  daadwerkelijke krimp/pers-opdracht gebruikt worden.

## Koppeling + Persmaat/Insteekdiepte via de perslijst-PDF's (Parkrimp crimp specs)
De gebruiker heeft 115 Parker "Crimp Specification"-PDF's aangeleverd (verzameld in `perslijst/`), elk met
per slangtype ("Hose Style") en koppelingserie ("Coupling Style") een maattabel met
Crimp Diameter (= Persmaat) en Hose Insertion (= Insteekdiepte) per maat. Deze zijn geautomatiseerd verwerkt naar
de `1delig_1/2/3`-kolommen van beide CSV-bestanden:
- **Matching**: het slangtype-gedeelte van het artikelnummer (vóór het eerste streepje, ST/TC-achtervoegsel
  genegeerd, voorloopnullen genegeerd) gekoppeld aan de "Hose Style" uit de PDF; de maat via het cijferblok na het
  eerste streepje.
- **Prioriteit**: per maat zijn alle beschikbare koppelingseries verzameld; serie `48` komt als die bestaat altijd
  op slot 1 te staan, overige series vullen slot 2 en 3 (max. 3, zoals het CSV-schema toelaat).
- **Aanvullen vs. corrigeren**: was een slot leeg, dan is het gevuld. Stond er al iets, dan is dat alleen
  overschreven als de perslijst-PDF een écht andere waarde gaf (getallen zijn numeriek vergeleken, dus "36,3" en
  "36,30" tellen als gelijk); bij een echte afwijking is de perslijst-PDF leidend, conform de instructie. Alle 121
  van dit soort correcties zijn in de commit-geschiedenis na te lezen.
- Alleen rijen met `Leverancier` = `Parker` zijn aangepast, en alleen waar de PDF's daadwerkelijk een bruikbare
  waarde gaven (een handvol PDF's/maten had zelf geen cijfer maar "see pdf" — dat is dus terecht overgeslagen).
- Schilmaat intern/extern staat niet in deze crimp-specificaties en is niet aangevuld.

### Correctieronde: matching-bug gevonden en opgelost (RVS bleek achter te lopen op Staal)
Bij controle bleek dat RVS bij een flink aantal Parker-artikelen geen Persmaat/Insteekdiepte had terwijl Staal dat
wel had. Oorzaak was **geen RVS-specifiek probleem**: de eerste matching-ronde koppelde de perslijst-PDF's uitsluitend
via Geeve's eigen `artnr`-kolom, maar voor een deel van de Parker-slangen (bijv. intern `0311-XX`/`0421-XX`) wijkt
die kolom volledig af van Parkers eigen productcode in `Artikelnr leverancier` (bijv. `421SN-X`/`301SN-X`) — precies
de code waaronder de perslijst-PDF's zijn ingedeeld. Die rijen misten dus zowel in Staal als in RVS symmetrisch,
maar vielen in Staal minder op omdat een deel daarvan al eerder (via de thermoplastische/parker.com-rondes) gevuld
was. De matching is uitgebreid: naast `artnr` wordt nu ook `Artikelnr leverancier` als slangfamilie-sleutel geprobeerd
(de maat blijft altijd afgeleid van Geeve's eigen `artnr`, omdat één regel een tikfout in `Artikelnr leverancier`
bleek te hebben die anders de verkeerde maat had gematcht).

Bij het doorrekenen van deze uitbreiding kwamen twee brondata-eigenaardigheden aan het licht die zijn opgelost
vóórdat de update is toegepast:
- **304, 426 en 881**: deze drie PDF's vermelden de koppelingserie als gecombineerd label ("43/48") zonder dat de
  maattabel zelf onderscheid maakt; de bijbehorende opmerkingen in de PDF ("Series 48 for sizes -20 up to -32" e.d.)
  zijn alsnog toegepast om per maat de juiste serie te kiezen. Dit corrigeerde ook 4 al aanwezige, onjuiste
  `48`-waarden bij maat -12/-16 van 304 en 881 (moest `43` zijn).
- **2040N, maat -5/-6/-8**: de "Insertion"-kolom in `2040N-56.txt` toont hier een onleesbare waarde ("1" resp.
  "1-1/8" i.p.v. een mm-getal) — vermoedelijk een renderfout in Parkers eigen PDF-tool. Deze drie maten zijn bewust
  overgeslagen (bestaande data blijft ongewijzigd), omdat er geen betrouwbare bron was om te corrigeren.

Resultaat van deze correctieronde: **283 aanvullingen** (69 Staal, 214 RVS) en **112 correcties** (73 Staal, 39 RVS),
bovenop de eerdere 410/121. Alle wijzigingen zijn numeriek-tolerant vergeleken en steekproefsgewijs teruggecontroleerd
tegen de brontekst van de PDF's.

### Aanvulling: 787/797-familie, koppelingserie 43 en 77
Voor de 787- en 797-familie (GlobalCore) ontbrak de `77`-koppelingserie (Staal had 'm grotendeels al staan, RVS
niet — zie boven) en voor 787 ook de `43`-serie bij maat -4/-6. De gebruiker heeft hiervoor 3 extra
Parker-crimpspecificaties aangeleverd (`perslijst/787-43.pdf`, `perslijst/787-77.pdf`, `perslijst/797-77.pdf`),
rechtstreeks van `divapps.parker.com`. Twee daarvan (787-43, 787-77) geven de maten in **inches**; die zijn
omgerekend naar mm (× 25,4, afgerond op 2 decimalen) — een controle tegen `797-77.pdf` (native in mm, vrijwel
identieke maten als de omgerekende 787-77-waarden, wat te verwachten is omdat beide GlobalCore-slangen dezelfde
77-fitting gebruiken) bevestigde dat de omrekening klopt. Resultaat: **39 aanvullingen** (RVS, de eerder gemelde
ontbrekende Persmaat/Insteekdiepte) en **39 correcties** (Staal, kleine afrondingsverfijningen t.o.v. de al
aanwezige — kennelijk grotendeels correcte, maar op hele mm afgeronde — waarden). Het aantal resterende RVS-gaten
zonder Staal-tegenhanger is hiermee gedaald van 56 naar 19.

### Aanvulling: 721ST overgenomen van 721TC
Voor `721ST` (maat -08/-12/-16) bestaat geen eigen perslijst-PDF, maar de gebruiker gaf aan dat de gegevens van
`721TC` (dezelfde R12 Compact-serie, wel gedekt door `perslijst/721TC-71.pdf`) hiervoor gebruikt mogen worden.
Persmaat/Insteekdiepte van de corresponderende `721TC`-maten zijn overgenomen naar `721ST` in beide bestanden
(RVS was hier volledig leeg, Staal kreeg een kleine afrondingsverfijning).

### Aanvulling: 487, 590TJ, 692PU, 722/722TC en 797 (43-serie)
De gebruiker leverde 7 extra crimp-specificaties aan (`487-43`, `590TJ-55`, `692PU-46` (herupload, ongewijzigd),
`692PU-48`, `722-43`, `722TC-43`, `722TC-71`, `797-43`), die precies de op dat moment bekende RVS-gaten dekten.
Drie ervan (`487-43`, `722TC-43`, `722TC-71`, `797-43`) geven maten in inches en zijn omgerekend naar mm.

Bij het verwerken kwam een subtiele matching-valkuil aan het licht: rij `0590-06` (het "kale" `590`-type, coupling
`56`) heeft als "Artikelnr leverancier" toevallig `590TJ-6` staan — hetzelfde leverancierscode-veld als de losse
`590TJ`-rij. Met de sinds de vorige ronde toegevoegde "Artikelnr leverancier"-fallback zou dit deze rij per ongeluk
naar de `590TJ`-gegevens (coupling `55`) laten omklappen, terwijl `0590-06` een ander, al eerder geverifieerd
correct product is. Dit is opgelost door de matching-prioriteit om te draaien: eerst wordt geprobeerd te matchen op
Geeve's **eigen** artnr-kolom (die voor dit ene geval wel het juiste hosetype `590` oplevert); alleen als dat geen
resultaat geeft, valt de matching terug op "Artikelnr leverancier" (nodig voor de `0311`/`0421`-achtige gevallen
uit de vorige ronde). Dit raakte verder geen van de eerder toegepaste wijzigingen (slechts 1 rij in de hele dataset
had deze specifieke dubbele-match-situatie).

Resultaat: 21 aanvullingen en 15 correcties (voornamelijk kleine afrondingsverfijningen). Het aantal RVS-gaten
zonder Staal-tegenhanger is hiermee gedaald van 16 naar **1** (alleen de eerder uitgesloten `0334-06`/2040N-maat
-6 met de onleesbare bron-Insertion resteert nog).

## Nieuwe slangen: matching tegen aanvullende PDF's (vervolg)
Van de 134 nieuw toegevoegde slangen (zie hierboven) zaten er geen in de `487`/`590TJ`/`692PU`/`722`/`722TC`/`797`-
families, dus deze aanvullingsronde heeft geen extra rijen in de nieuwe batch gevuld. Van de 104 nieuwe
Parker-rijen zijn nog steeds maar 8 gematcht (zie hierboven); de overige ~96 (`H29`, `H31`, `PLK*`, `R35`, `R42`,
`R50`, `R56`, `F42`, `FA35`, `701`, `791TC`, `449`, `BPK`, `424`) wachten nog op de bijbehorende crimp-spec-PDF's.

- **Bekend, niet aangepakt**: 1 Parker-rij (`0334-06`) heeft in RVS nog geen Persmaat/Insteekdiepte terwijl Staal
  dat wel heeft — dit is de eerder gedocumenteerde 2040N-maat -6 met een onleesbare Insertion-waarde in de
  bron-PDF, bewust overgeslagen. Daarnaast kan losstaand hiervan nog een oudere asymmetrie bestaan buiten de
  dekking van de perslijst-PDF's; ook dat is niet automatisch gecorrigeerd omdat er geen autoritatieve bron voor was.

## Nieuwe slangen: resterende PDF's (1-delig én 2-delig VS/V4/V6/V5/WB)
De gebruiker leverde de laatste 15 crimp-specificaties aan voor de families die nog openstonden uit de
134-slangen-batch. Vier daarvan zijn "gewone" 1-delige specificaties (`424-43`, `701-70`, `791TC-79`, `F42-7079`
— bij dit laatste bestand geeft de opmerking "Fitting 70 only for size -8" aan dat maat -8 koppeling `70` krijgt
en de overige maten `79`) en zijn verwerkt via dezelfde `1delig_1/2/3`-matching als steeds: **12 aanvullingen**.

De overige 11 PDF's (`H29-V4`, `H31-VSV4`, `PLK-V4`, `PLK-V6`, `PLK-VS`, `R35-V4V6`, `R42-V4V6`, `R50-V4V6`,
`R56-V5`, `R56-VSV5`, `BPK-WB`) beschrijven **2-delige** ("pershulzen") Parkrimp-fittingen met koppelingserie `VS`,
`V4`, `V6` of `V5`: deze PDF's geven geen Insteekdiepte maar wel een Persmaat en (in een aparte tabel onderaan)
een externe en interne "Skive"-maat — dat komt overeen met de kolommen `2delig_N - Persmaat/Schilmaat intern/
Schilmaat extern`. Op verzoek van de gebruiker is de koppelingscode in zowel `Huls` als `Pilaar` ingevuld, met één
uitzondering: **bij serie `V4` is Huls `V4` en Pilaar `30`** (VS/V5/V6 krijgen wel dezelfde code in beide velden).
Bij maten met alleen externe skiving (de `VS`-maten) blijft Schilmaat intern leeg, conform de brontabel.

Drie bestanden combineren twee koppelingseries in één PDF (zoals eerder bij 304/426/881): `H31-VSV4` (VS voor
maat -4/-6/-8, V4 voor -10/-12/-16), `R35-V4V6` (V4 voor -12 t/m -20, V6 voor -24/-32), `R42-V4V6` (V4 voor -10
t/m -16, V6 voor -20 t/m -32), `R50-V4V6` (comment "USE FITTING SERIES V4 FOR SIZE -10 UP TO -16" /
"FOR HOSE R50TC-20 USE FITTING V6-20") en `R56-VSV5` (enige maat in de tabel, -4, valt op basis van de
externe-skiving-only-indicator onder `VS`, niet `V5`) — ook hier is per maat de juiste serie gekozen i.p.v. beide
te vermelden. De `PLK*TC-XX`-artikelen uit de 134-batch (bijv. `PLK28TC-32`, `PLK50TC-4`) hebben stuk voor stuk een
uniek cijfer ná "PLK" in hun artikelnummer dat niets met het slangtype te maken heeft; deze zijn daarom herkend via
een specifieke `^PLK\d+(ST|TC)-`-regel en gekoppeld aan de gezamenlijke "PLK_TC"-hosestyle uit de drie PLK-PDF's.

Resultaat: **72 aanvullingen** in de 2delig-kolommen, verdeeld over `H29`, `H31`, `PLK_TC`, `R35`, `R42`, `R50TC`,
`R56TC` en `BPK` (allemaal in `2delig_1`, aangezien deze rijen nog geen 2delig-data hadden). Alle wijzigingen
zaten uitsluitend in Staal; geen van deze hosefamilies komt voor in RVS.

## Nieuwe slangen uit `Slangenlijst_extra.csv` (134 artikelen toegevoegd aan Staal)
De gebruiker leverde een exportbestand uit het ERP-systeem aan (`Items.ItemCode`/`[Items.Description]`/
`ItemAccounts.ItemCodeAccount`/`cicmpy.crdnr`, 135 werkelijke datarijen achter 1.048.575 lege regels) met nieuwe,
nog niet in de applicatie voorkomende slangartikelen. Geen van deze artikelen bevat een RVS-aanduiding in de
omschrijving, dus alle 134 zijn toegevoegd aan `artikelnummers_staal.csv` (aan het einde van het bestand; niet
tussengevoegd in de bestaande alfabetische volgorde om de wijziging als zuiver additieve diff leesbaar te houden).
RVS-CSV is niet aangepast; de 134 zijn later ook aan de accessoires-CSV toegevoegd, zie verderop.

- **Leverancier**: het bronbestand had geen leveranciersnaam, alleen een crediteurnummer. Op basis van de door de
  gebruiker gegeven koppeling: crdnr `100011`, `100042` en `100681` → **Parker**, `100170` → **Manuli**, `100229`
  → **Interpump**. Crdnr `100072` (leverancier Eriks, 1 artikel: `0441-16RM`) is op verzoek van de gebruiker
  **overgeslagen** en dus niet toegevoegd.
- **Werkdruk**: op dezelfde manier geëxtraheerd uit de omschrijving als de rest van de dataset (patroon
  `W.P. ... BAR`/`MPa`). Bij 18 van de 134 artikelen bevat de omschrijving geen herleidbare drukwaarde (bijv.
  "MTR SLANG (4SP) TYPE TFD", "MTR SLANG (BLASTOPAK)") en is dit veld leeg gebleven, net als bij de rest van de
  dataset.
- **Persgegevens (1delig)**: voor de nieuwe Parker-artikelen is dezelfde perslijst-matching toegepast als eerder in
  dit document beschreven. Slechts **8 van de 104 nieuwe Parker-rijen** matchten op dit moment een bestaande PDF in
  `perslijst/` (de `441`-familie via `441-46.txt`/`441-48.txt`, en de `731`/`731TC`-familie via `731.txt`) en zijn
  automatisch ingevuld. Voor de overige ~96 (de series `H29`, `H31`, `PLK*`, `R35`, `R42`, `R50`, `R56`, `F42`,
  `FA35`, `701`, `791TC`, `449`, `BPK`, `424`) bestaat nog geen crimp-spec-PDF in `perslijst/` — de gebruiker gaf aan
  hiervoor extra PDF's te hebben toegevoegd, maar die waren op het moment van deze update nog niet in de
  GitHub-repository zichtbaar. Zodra die PDF's beschikbaar zijn, kan dezelfde matching opnieuw gedraaid worden om
  deze rijen alsnog aan te vullen.

## De 134 nieuwe slangen ook in de accessoires-CSV
De 134 slangen uit `Slangenlijst_extra.csv` (zie hierboven) stonden aanvankelijk alleen in
`artikelnummers_staal.csv`; op verzoek van de gebruiker zijn ze alsnog toegevoegd aan
`artikelnummers_accessoires.csv` (artnr/artnm/Leverancier/Artikelnr leverancier gelijk aan de Staal-rij).

Van de accessoirevelden is alleen **Firesleeve** ingevuld, via dezelfde dash-maat → `9125-..`-regel als eerder in
dit document (alle 134 kregen een treffer, aangezien dash-maten als -04/-06/-08/... in de Pyrojacket-reeks
voorkomen). **Buitenmaat slang, PolyGuard, ParKoil, Spring Guard, SpiralGuard, Texsleeve, Huls tex staal/RVS en
FS-F-Firesleeve zijn leeg gelaten.** Uit de bestaande data blijkt dat deze velden niet uit de dash-maat alleen af
te leiden zijn (dezelfde dash-maat geeft bij verschillende slangconstructies stelselmatig andere HG-/PG-/SG-/9121-/
TEXS-/19001-/9223-coderingen, afhankelijk van de werkelijke buitendiameter van de slang) — hiervoor is een
maattabel per product nodig die niet is aangeleverd. Dit is dus bewust leeg gelaten, net als bij ontbrekende
accessoiregegevens elders in dit bestand.

### Vervolg: SpiralGuard/Texsleeve/Huls tex nu wél ingevuld waar Buitenmaat bekend is
Na de Buitenmaat-aanvulling uit de Parker CAT_4400/UK-catalogus (zie verderop in dit document) is voor **30 van
de 134** nieuwe rijen een Buitenmaat bekend geworden. Op basis van de bestaande, al gevulde rijen in dit bestand
bleek de koppeling tussen Buitenmaat en productcode voor vier velden een schone, vrijwel niet-overlappende
staffel te zijn (per productcode een aaneengesloten Buitenmaat-bereik zonder gat of overlap met de buur):
**SpiralGuard** (`9121-..`), **Texsleeve** (`TEXS..`), **Huls tex staal** (`19001-..`) en **Huls tex RVS**
(`9223-..`). Voor deze vier velden is daarom, uitsluitend voor de 30 rijen met een bekende Buitenmaat, de
bijpassende code afgeleid uit die staffel (103 celwaarden in totaal ingevuld; een enkel veld bleef leeg omdat de
Buitenmaat net in een echt gat tussen twee bekende bereiken viel, bijv. rond 50–60mm bij Huls tex staal — daar is
bewust niet gegokt).

**PolyGuard, ParKoil, Spring Guard en de losse FS-F-Firesleeve-code blijven leeg**: voor deze vier bleek de
Buitenmaat-naar-code-koppeling in de bestaande data juist wél merkbaar te overlappen tussen aangrenzende codes
(bijv. `HG-075` en `HG-125` overlappen elkaar tussen 19,5 en 24,0mm) — de keuze hangt daar kennelijk van meer af
dan Buitenmaat alleen, en zonder een aanvullende, betrouwbare regel is dit niet automatisch in te vullen zonder
te gokken.

Voor de resterende 104 van de 134 rijen (vooral de Interpump/Manuli-artikelen en de Parker-rijen zonder
crimp-catalogusmatch) is nog steeds geen Buitenmaat bekend, en dus ook geen van deze vier velden.

## Coderingen in hoofdletters
Op verzoek van de gebruiker staan alle koppelingscoderingen (`2delig_N - Huls`, `2delig_N - Pilaar`, `1delig_N`)
consistent in hoofdletters. In `artikelnummers_staal.csv` was dit al overal het geval; in
`artikelnummers_rvs.csv` stonden 253 Huls-coderingen (o.a. de oudere `1300pX-..rvs`-reeks) nog in kleine letters
en zijn omgezet naar hoofdletters (bijv. `1300p8-24rvs` → `1300P8-24RVS`). Overige kolommen (o.a. omschrijving,
Artikelnr leverancier) zijn niet aangepast.

## Werkdruk en Buitenmaat uit de Parker CAT_4400/UK hosecatalogus
De gebruiker leverde `docs/Parker-Hydraulic-Hoses-CAT_4400_UK.pdf` aan (246 pagina's) en gaf aan dat deze **UK**-
catalogus leidend is bij afwijkingen met de eerder in de repo aanwezige **US**-catalogus
(`docs/Parker HPD_4400_Catalog_hoses.pdf`).

- **Extractie**: de catalogus bevat 197 maattabellen, waarvan 92 daadwerkelijk slang-specificaties zijn (Hose I.D./
  O.D., max. werkdruk en barstdruk in zowel MPa als psi, buigradius, gewicht) — de overige ~105 zijn
  fitting-maattabellen (DIN/BSP/Flens-aansluitingen met schroefdraad- en steekmaten) die niets met Werkdruk of
  Buitenmaat te maken hebben en dus genegeerd zijn. Onderscheid gemaakt op basis van de aanwezigheid van een
  "working"/"burst pressure"-kolomkop. Per rij is de MPa-waarde gecontroleerd tegen de bijbehorende psi-waarde
  (verhouding 1 MPa ≈ 145,038 psi, tolerantie 8%) om tabellen die toevallig ook "working"/"burst" in de buurt
  hadden staan (foutief als slangtabel herkend) uit te sluiten.
- **Twin-hose-valkuil gevonden en opgelost**: sommige slangtypen hebben naast de normale uitvoering ook een
  "Twin Hose"-variant (dubbele slangbundel, bijv. Part Number `692PU-4-4` naast het normale `692PU-4`). Beide delen
  hetzelfde begin van het Part Number, waardoor ze aanvankelijk per ongeluk als "dezelfde slang, twee metingen"
  werden behandeld — dit gaf voor de Buitenmaat een verdubbelde waarde (bijv. `692PU-4`: 13,4 mm vs. de
  twin-variant `692PU-4-4`: 28,2 mm) en zou zonder correctie een foutieve Buitenmaat-correctie hebben opgeleverd.
  Twin-hose Part Numbers (herkenbaar aan een tweede `-cijfer` direct na de normale maataanduiding) zijn daarom
  uitgesloten van deze ronde.
- **Decimaalteken-inconsistentie**: een deel van de catalogus gebruikt een komma als decimaalteken (bijv. `13,4`)
  i.p.v. een punt (`13.4`) — waarschijnlijk het gevolg van verschillende locale-instellingen bij het genereren van
  losse hoofdstukken van de PDF. Niet als zodanig herkennen zou stilzwijgend rijen laten wegvallen (en was de
  oorzaak van de hierboven genoemde twin-hose-valkuil, die pas zichtbaar werd nadat dit was opgelost). Beide
  notaties worden nu correct als getal geïnterpreteerd.

**Werkdruk (bar)**: 82 aanvullingen en 26 correcties (Staal + RVS samen), overal waar de catalogus een
eenduidige (niet-ambigue) MPa-waarde per slangfamilie/maat gaf. De correcties betreffen vooral: `0722ST`/`0722TC`
(bestaande waarde 275,8 bar was afgeleid van "4000 PSI" uit de omschrijving; de catalogus geeft de afgeronde
nominale waarde 280 bar/4000 psi), `R50TC` (bestaand 420 bar uit de omschrijving vs. 500 bar in de catalogus) en
`0441-10RM` (bestaand 350 bar — vermoedelijk per ongeluk gekopieerd van de gelijkluidende omschrijving van het
losstaande Interpump-artikel `0441-10` — vs. 192 bar in de catalogus voor Parkers eigen `441`-slang). Conform de
instructie van de gebruiker is in alle gevallen de UK-cataloguswaarde toegepast.

**Buitenmaat slang (mm)** in `artikelnummers_accessoires.csv`: 33 aanvullingen en 9 correcties (alle klein, <5%
afwijking t.o.v. de al aanwezige waarde).

Eén slangfamilie/maat (`477ST-8`) gaf op twee verschillende plekken in de catalogus een afwijkende waarde (35 vs.
38 MPa) zonder dat duidelijk is welke correct is; deze is als ambigu overgeslagen. Veel van de eerder in dit
document genoemde hosefamilies (o.a. de 20xx/22xx/23xx/24xx/25xx/26xx-thermoplastische series, `590`, `520N`,
en de `H29`/`H31`/`PLK*`/`R35`-families uit de 134-slangen-batch) komen niet voor in deze specifieke catalogus en
zijn dus niet aangevuld.

## De 134 nieuwe slangen ook in de RVS-CSV
De 134 slangen uit `Slangenlijst_extra.csv` stonden tot nu toe alleen in `artikelnummers_staal.csv`, terwijl elk
ander artikel in de dataset zowel in Staal als RVS voorkomt (1-op-1, zelfde `artnr`). Op verzoek van de gebruiker
zijn ze nu ook aan `artikelnummers_rvs.csv` toegevoegd — de volledige rij (incl. de inmiddels gevulde
Werkdruk/1delig/2delig-persgegevens) is overgenomen uit Staal, met één aanpassing:

- **1delig-coderingen en de Pilaar-kolommen blijven ongewijzigd** (zelfde koppeling, ongeacht materiaal).
- **De Huls-codering in `2delig_N - Huls` wordt voor RVS herschreven** naar het patroon
  `100<koppelingscode>-<maat>C` (bijv. `R42-20` met koppeling `V6` → `100V6-20C`), conform de door de gebruiker
  opgegeven RVS-hulscodering. `<maat>` is de eigen dash-maat van de rij (dezelfde conventie als "Maat" elders in
  dit document). Dit raakt de 72 van de 134 rijen die een 2delig-koppeling hebben (`H29`, `H31`, `PLK_TC`, `R35`,
  `R42`, `R50TC`, `R56TC`, `BPK`); de overige rijen hebben geen 2delig-gegevens en blijven dus verder identiek aan
  hun Staal-tegenhanger.

Staal en RVS staan hiermee weer 1-op-1 gelijk (966 rijen in beide bestanden).

**Aanvulling**: de gebruiker gaf aan dat de kale `V4`/`V6`-codering ook in **Staal** naar dit patroon herschreven
moet worden (zonder de `C` die alleen in RVS gebruikt wordt): `100<code>-<maat>`, bijv. `100V4-20`. Dit is
toegepast op de 57 Huls-velden met `V4` of `V6` in `artikelnummers_staal.csv`. `VS`, `V5` en `WB` blijven in
zowel Staal als RVS ongemoeid (geen `100..`-voorvoegsel). Deze regel staat nu vastgelegd in `AGENTS.md` §5.

## Print-catalogus: hoofdstukken, scheidingspagina's en gescopede download
Op verzoek van de gebruiker toont de af te drukken/te downloaden catalogus (`assets/selector.js`,
`renderCatalogPrintSheet()`) nu een duidelijke hoofdstukindeling:

- **Koptekst per pagina**: rechtsboven, vetgedrukt, staat nu de naam van het huidige hoofdstuk (`Accessoires`,
  `1-delig`, `2-delig`, `Staal`, `RVS`). Dit wordt gerenderd door `buildPrintHeader(chapterLabel)`.
- **Scheidingspagina's**: tussen elk hoofdstuk zit een eigen pagina met alleen de hoofdstuknaam in groot
  lettertype (`buildDividerPage(groupName)`), zodat een geprinte catalogus duidelijk in secties uiteenvalt.
  `buildChapterPages(chapterLabel, titleText, table)` bouwt steeds het paar [scheidingspagina, inhoudspagina's]
  als directe siblings, wat nodig was voor een correcte CSS-paginascheiding (zie hieronder).
- **4 downloadopties**: de knop "Download catalogus (PDF)" is een dropdown geworden (`.pdf-dropdown`/`.pdf-menu`)
  met **Complete catalogus**, **Accessoires**, **Staal** en **RVS**. Elke optie roept
  `renderCatalogPrintSheet(scope)` aan met de bijbehorende scope, zodat alleen de gekozen hoofdstukken in de
  afdruk/PDF terechtkomen.

**CSS-paginascheiding**: doordat elke inhoudssectie nu altijd wordt voorafgegaan door zijn eigen scheidingspagina
(voorheen was dat alleen zo voor de eerste sectie van een gedeelde `.print-chapter`-wrapper), is de oude regel
`.print-chapter > .print-section:first-child` vervangen door een algemenere adjacent-sibling-regel
`.print-divider + .print-section { break-before: auto }`. Geverifieerd met écht gegenereerde, gepagineerde PDF's
(`page.pdf()` via Playwright, niet alleen een DOM/print-media-screenshot) dat er geen ontbrekende of dubbele
paginascheidingen ontstaan voor alle 4 scopes.

*Bekende, niet-nieuwe eigenaardigheid*: de complete catalogus-PDF eindigt met één lege pagina (de losse
`.print-footer`-div die op haar eigen pagina terechtkomt). Dit bleek al aanwezig te zijn vóór deze wijziging
(geverifieerd door dezelfde test uit te voeren tegen de oude code) en is dus geen regressie van deze ronde.

## Groepering van slangtypes in de catalogus: PLK, R42 en algemene letter-prefixes
`hoseTypeInfo(artnr)` in `assets/selector.js` bepaalt hoe artikelen in de printcatalogus worden gegroepeerd per
"slangtype" (zoals eerder al gebeurde voor `SR`/`SRI`). Op verzoek van de gebruiker zijn hier twee gaten in
gedicht:

- **`PLK*TC`/`PLK*ST`**: de PDF-brondata bevat na "PLK" een cijfer dat per rij kan verschillen maar geen
  betekenisvolle informatie draagt (ruis). Zonder speciale behandeling werd elke waarde als eigen slangtype
  gezien. Er is nu een aparte case (`/^(PLK)\d+(ST|TC)?/i`) die alle `PLK...ST`/`PLK...TC`-varianten samenvoegt
  tot één type — en, net als bij SR/SRI, ook **met en zonder** de `ST`/`TC`-suffix worden samengevoegd (dus
  `R42`, `R42ST` en `R42TC` vallen onder hetzelfde type).
- **Algemene letter-prefix-groepering**: de bestaande regex verwachtte minstens één cijfer vóór het streepje
  (`(\d+)`), waardoor puur-letterlijke prefixes zoals `BPK-12`/`BPK-16` of `PDH-...` helemaal niet gegroepeerd
  werden (elke rij werd een eigen singleton-type). De regex is aangepast naar `(\d*)` (nul of meer cijfers),
  geankerd op het deel vóór het eerste streepje, zodat ook deze families correct groeperen. Geverifieerd met een
  losse Node-testscript tegen alle 966 echte artikelnummers in de dataset (o.a. `R42`/`R42ST`/`R42TC` → 1 groep,
  `BPK-*` → 1 groep van 4, `PDH-*` → 1 groep van 1, geen ongewenste samenvoegingen bij andere families).

## Groepering slangtypes: ook SN-suffix samenvoegen (bijv. 301)
De gebruiker meldde dat sommige slangtypes met een `ST`-, `TC`- of `SN`-toevoeging nog steeds los van elkaar
verschenen in de printcatalogus, met `301` als voorbeeld: `301SN-xx` en `301TC-xx` vormden twee aparte typen
(`301SN` en `301`) in plaats van één gezamenlijke groep. De samenvoegregel in `hoseTypeInfo()` controleerde
alleen op een achtervoegsel `ST` of `TC` (`/^(ST|TC)$/i`); `SN` werd niet als "samen te voegen" suffix herkend.
Dit is gecorrigeerd op alle drie de plekken waar deze samenvoegregel voorkomt (het algemene geval, de SR/SRI-case
en de PLK-case): `/^(ST|TC|SN)$/i`. `301SN-*` en `301TC-*` vallen nu samen onder één type `301`. Een controle
over de volledige dataset (alle 967 unieke artikelnummers in Staal+RVS) bevestigde dat hierna geen enkel
slangtype meer overblijft dat alleen door een `ST`/`TC`/`SN`-achtervoegsel van een ander type gescheiden is.

## CSV quoting-fix
GitHub meldde `Illegal quoting in line 16` door dubbele quotes die als inch-aanduiding in velden werden gebruikt. In de meegeleverde Staal- en RVS-CSV zijn die tekens vervangen door het Unicode inch-symbool `″`.

Voor `artikelnummers_accessoires.csv` staat `fix_csv_quotes.php` in het pakket. Plaats de accessoire-CSV in de applicatiemap of in `data/` en voer één keer uit:

```bash
php fix_csv_quotes.php
```

Het script corrigeert dezelfde quoting-problemen in alle drie de CSV-bestanden en controleert het aantal kolommen.

Plaats de complete map op een PHP-webserver. Er is geen database nodig.
