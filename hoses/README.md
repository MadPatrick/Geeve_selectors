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

## Buitenmaten en 2-delige koppelingen uit de actuele documentatiemap

Op basis van de PDF's in `docs/hoses/` zijn de drie CSV's opnieuw brongericht bijgewerkt. Voor Buitenmaat zijn
alleen exacte product-/maatregels uit de catalogustabellen gebruikt; er is niet vanuit de binnendiameter of een
naastgelegen maat geëxtrapoleerd.

- **Buitenmaat slang**: 37 lege velden in `artikelnummers_accessoires.csv` zijn aangevuld. Het gaat om de
  Interpump-reeksen `0441`/`0442`, `0446`, `0447` en `0449-16` uit `IMM Hydraulic-hose.pdf`, Parker `2580N` uit
  catalogus 4462, `424` en `F42` uit catalogus 4400 US en de elf `PLK...TC`-maten uit catalogus 4400 UK. Er zijn
  geen bestaande buitenmaten overschreven.
- **Huls/Pilaar Parker**: `Perslijst_Geeve_2018.pdf` (documentdatum 9 maart 2021) is als directe bron gebruikt
  voor de 2-delige combinaties. De tabellen voor Staal en RVS zijn afzonderlijk verwerkt. Staal kreeg waar de
  bron twee hulzen noemt beide combinaties; bij RVS zijn oudere `1300P...RVS`-alternatieven vervangen door de
  actuele `13001-..RVS`/`13002-..RVS`/`23000-..RVS`-codes uit de RVS-tabellen.
- Voor `H29`, `H31`, `R35` en `R42` is de al aanwezige Parlock/VS-combinatie behouden en de expliciet genoemde
  Interlock-combinatie als tweede mogelijkheid toegevoegd. Dit geldt ook voor ST/TC-dekvarianten; twin-slangen
  met een tweede dash-maat zijn bewust niet afgeleid van de enkele-slangtabellen.
- Resultaat fittingvelden: **Staal 91 gewijzigde rijen** (352 lege velden aangevuld, 36 bestaande waarden
  gecorrigeerd en 6 verouderde waarden verwijderd; daarbinnen 112 Huls- en 92 Pilaar-veldwijzigingen) en
  **RVS 189 gewijzigde rijen** (230 aanvullingen, 289 correcties en 276 verwijderingen; daarbinnen 300 Huls- en
  119 Pilaar-veldwijzigingen). Verwijderingen betreffen oude tweede RVS-combinaties die niet in de nieuwe
  materiaal-specifieke tabel voorkomen.
- De recent toegevoegde regels `0441-20` en `H31-20`, waarin accessoirevelden per ongeluk in de fittingkolommen
  terechtgekomen waren, zijn hersteld naar 30 kolommen. `H31-20` gebruikt nu de expliciete H31/4SP-regel uit de
  perslijst; bij `0441-20` zijn de foutief geplakte fittingwaarden verwijderd.

### Correctie Parker R13/R15: pilaar conform huls

Op expliciete instructie van de gebruiker zijn de 2-delige koppelingen van Parker R13- en R15-slangen
gecorrigeerd: bij een `100V4-<maat>`-huls staat voortaan Pilaar `V4`, en bij `100V6-<maat>` blijft Pilaar `V6`.
De reeds ingevulde tweede combinaties zijn daarnaast gewijzigd van `Z34000-<maat>` naar `Z37000-<maat>`
(in RVS met suffix `RVS`), steeds met Pilaar `30`. Lege tweede combinaties zijn niet zonder maat-/persgegevens
ingevuld. Dit corrigeerde per bestand 11 rijen: 11 Pilaar-velden en 6 tweede Huls-velden, dus 22 unieke rijen en
34 veldwijzigingen over Staal en RVS samen.

Na de update bevatten Staal en RVS elk 967 unieke artikelen met volledige 1-op-1-pariteit. BOM en regeleinden
zijn behouden (LF voor Staal/RVS, CRLF voor Accessoires); elke rij heeft respectievelijk 30 of 14 kolommen.

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

(Inmiddels vervangen door een structurele fix: alle drie de CSV's zijn omgezet naar echt komma-gescheiden,
correct gequote volgens RFC4180 — zie het "Illegal quoting"-punt hieronder.)

## Illegal quoting (structurele fix)
Zelfde klasse fout als hierboven, opnieuw opgetreden na latere aanvullingen: GitHub's webpreview neemt voor elk
`.csv`-bestand altijd komma als scheidingsteken aan, ongeacht het werkelijke scheidingsteken. Doordat
`artikelnummers_staal.csv`, `_rvs.csv` en `_accessoires.csv` `;`-gescheiden waren, gaf elk veld met een losse
komma of aanhalingsteken een foutieve "Illegal quoting" of kolomaantal-waarschuwing in GitHub's eigen preview (de
PHP-loader las het bestand zelf altijd al correct). Permanent opgelost door alle drie de bestanden echt
komma-gescheiden te maken (RFC4180, `"..."` met `""` voor een letterlijk aanhalingsteken). `hoses/index.php` en
`hoses/upload.php` lezen nu `,` i.p.v. `;`. Dezelfde fix was al eerder op de adapters-CSV toegepast.

## RVS-huls en MM-huls dubbel gevuld bij de 0441-familie (TFDM4SP)
Bij het aanvullen van de koppelingdata voor de 0441-familie (Interpump TFDM4SP, zie hierboven) waren zowel de
RVS-huls (`23000-<maat>RVS`) als de Staal/MM-huls (`23000-<maat>MM`) in **beide** bestanden gezet —
`artikelnummers_staal.csv` en `artikelnummers_rvs.csv` toonden dus allebei zowel de RVS- als de MM-uitvoering.
Dat is onjuist: een materiaalspecifieke huls hoort alleen in het bestand van dat materiaal te staan. Gecorrigeerd
voor 0441-04/06/08/10/12/16/24: `artikelnummers_staal.csv` toont nu alleen de MM-huls (verplaatst naar
`2delig_1`, `2delig_2` leeggemaakt); `artikelnummers_rvs.csv` toont nu alleen de RVS-huls (`2delig_2`
leeggemaakt). Voor `0441-24` is geen MM-huls bekend, dus die rij heeft in Staal nu geen 2-delige koppeling meer
(was voorheen ten onrechte de RVS-huls). Overige artikelfamilies met dezelfde hulzenreeks gecontroleerd: alleen
`372`/`372TC` gebruikt `23000-..`-hulzen, en die had het al goed (uitsluitend de MM-variant, geen duplicatie).
Regel vastgelegd in `AGENTS.md` §5 zodat dit niet opnieuw gebeurt bij een volgende materiaalspecifieke huls-reeks.

## Buitenmaat slang aangevuld uit leveranciersdatasheets (Calseyde/Dicsa/Xtraflex/Hansaflex)
Op verzoek van de gebruiker zijn ontbrekende `Buitenmaat slang (mm)`-velden in `artikelnummers_accessoires.csv`
opgezocht op basis van leverancier + artikelnummer leverancier, uitsluitend met door de gebruiker aangeleverde
PDF-datasheets in `docs/hoses/` als bron (geen ongeverifieerde webresultaten — "nooit gokken", zie `AGENTS.md`
§6). Elke match is bevestigd door de werkdruk (W.P.) in de bestaande omschrijving te vergelijken met de werkdruk
bij hetzelfde artikelnummer in de datasheet; bij een afwijkende werkdruk is de rij overgeslagen i.p.v. gegokt.

- **23 rijen ingevuld** uit `Calseyde R7.pdf`/`Calseyde vhp 046.pdf` (5 rijen), `Dicsa R14.pdf`/`Dicsa
  Thermoplastic.pdf` (5 rijen) en `XtraflexCatalog.pdf` (13 rijen; bij min/max-bereiken zonder één vaste waarde is
  het midden van het bereik gebruikt, afgerond op 1 decimaal).
- Bijvangst: een typefout in `Artikelnr leverancier` bij `0811-12` (`TB1020` → `T1B020`) ontdekt en gecorrigeerd,
  bevestigd via een exacte match van de werkdruk (103 bar).
- Bewust overgeslagen: de Dicsa `0811-05`/`0811-10`/`0811-16`-rijen (datasheet betreft een ander productvariant,
  "R14 FHL light") en Xtraflex `0815-05` (eigen omschrijving in de CSV wijst met zijn werkdruk op een ander
  artikelnummer dan het ingevulde `Artikelnr leverancier` — interne tegenstrijdigheid, niet zelf opgelost).
- **3 rijen ingevuld** uit `Hansaflex NY800NC.pdf`: `0328-04`/`0328-06`/`0328-08` (NY806/NY810/NY813-reeks). De
  overige ~14 Hansaflex-rijen in dit bestand gebruiken andere Hansaflex-productreeksen (`NY1xx`, `NYZ7xx`) die
  niet in dit specifieke datasheet voorkomen, plus de TWIN-variant `0328-08D` die niet in de enkele-slangtabel
  staat — die zijn bewust leeg gelaten in afwachting van aanvullende documentatie.
- Resterend: circa 200 rijen over ~20 andere leveranciers (o.a. Peters, Trelleborg, Flowtech, Manuli, JB
  Hydraulics) hebben nog geen Buitenmaat, in afwachting van door de gebruiker aan te leveren datasheets.

## 0503-familie (3TE), Parker-vendorcorrecties en opschoning verouderde artikelen
Op verzoek van de gebruiker aangevuld/gecorrigeerd in `artikelnummers_accessoires.csv` (en waar van toepassing
ook `Leverancier`/`Artikelnr leverancier` in `_staal.csv`/`_rvs.csv`):

- **3TE-familie (genormeerd)**: `0503-05`/`-20`/`-24`/`-32` zijn EN 854/3TE-hulzen, een DIN-genormeerd hosetype
  waarvan de buitenmaat ongeacht merk/leverancier gelijk is. De bestaande vendors (Landefeld/Dietzel/Eriks)
  hebben geen eigen datasheet in `docs/hoses/`; op aangeven van de gebruiker is daarom de volledige EN 854/3TE-
  maattabel uit `GH Pressmassliste.pdf` (Rubrik 20, enige bron met alle vier maten in één consistente tabel)
  gebruikt: 16,6 / 42,5 / 49,4 / 62,2 mm.
- **Parker-vendorcorrectie**: `221FR-8`/`221FR-12`/`2245D-03V32` (voorheen ten onrechte op leverancier
  Hydrasun) en `0563TJ-4`/`-6`/`-8` (voorheen ten onrechte op leverancier "Coliflower") zijn eigenlijk
  Parker-artikelen. Leverancier aangepast naar `Parker`.
  - `221FR-8`/`221FR-12`: Buitenmaat 20/27 mm uit `Parker HPD_4400_Catalog_hoses.pdf` en
    `Parker-Hydraulic-Hoses-CAT_4400_UK.pdf` (beide catalogi identiek), bevestigd doordat de exacte inch-maat
    in de tabel (13/32 resp. 5/8) letterlijk overeenkomt met de bestaande omschrijving.
  - `0563TJ-4`/`-6`/`-8`: Buitenmaat 11,9/16,3/19,5 mm uit `CATALOG_4660-Thermoplastic_Hose.pdf` (Parker
    TOUGHJACKET 563TJ-serie), bevestigd via een exacte werkdrukmatch (210 bar = 3.045 psi/21,0 MPa). Ook
    `Artikelnr leverancier` gecorrigeerd van `0563TJ-x` naar het echte Parker-partnummer `563TJ-x`.
  - `2245D-03V32`: alleen de leverancier is gecorrigeerd. Er is geen exacte match gevonden — de dichtstbijzijnde
    Parker-reeks in `CATALOG_4660-Thermoplastic_Hose.pdf` is `2245N` (andere lettercode én geen dash-maat
    `-03V32` in die tabel), dus dit is bewust **niet** als bevestigde match behandeld en de Buitenmaat is leeg
    gelaten.
  - Bijvangst: `527BA-4` (al langer leverancier Parker) had al een `CG`-persfitting die exact overeenkomt met
    de fitting-serie in `CATALOG_4660-Thermoplastic_Hose.pdf`; Buitenmaat aangevuld met 13,2 mm.
- **Verwijderd** (verouderd, op verzoek van de gebruiker) uit alle drie de CSV's: `0347-06D/LIN`, `0328-12`,
  `0427-04`, `0511-56`, `0628-04PP`, `550H-6-6-6`, `AIR63MM`, `AIR19MMBLAUW`, `AIR25MMGEEL` (9 artikelen; Staal
  en RVS gaan hiermee van 967 naar 958 rijen, Accessoires van 970 naar 961, met behoud van 1-op-1-pariteit
  tussen Staal en RVS).

## Vaste sorteervolgorde 2-delige koppelingen: 13002-huls altijd boven 13001-huls
Op verzoek van de gebruiker toont de selector een `13002-<maat>`-huls voortaan altijd vóór een
`13001-<maat>`-huls binnen dezelfde materiaalkolom (Staal/RVS), ongeacht of die combinatie in de CSV toevallig
in `2delig_1` of `2delig_2` staat. Dit is opgelost in `index.php` (`buildArticles()`) met een `usort()` op de
`$combos`-array direct na het inlezen: huls bevat `13002` → rang 0, bevat `13001` → rang 1, overige hulzen →
rang 2 (behouden relatieve volgorde). Er is bewust niet gekozen voor het handmatig omwisselen van `2delig_1`/
`2delig_2` in de ~86 betrokken CSV-rijen (o.a. `0311-*`, `0426-*`, `0436-*`, `0462-*`, `0462ST-*`, `0462TC-*`,
`0477-*`, `0477TC-*`, `421SN-*`, `441RH-*`): een code-regel geldt automatisch ook voor toekomstige rijen en kan
niet per ongeluk weer omgedraaid worden bij een volgende CSV-bewerking. Geverifieerd in de browser bij `0311-04`
en `0426-04`: 2-delige koppelingen Staal toont nu `13002-04MM` boven `13001-04MM`.

## 0328 (SAE 100R8): R8 in omschrijving, Transferoil als leverancier, perslijst- en Buitenmaat-data compleet
Op verzoek van de gebruiker, met `docs/hoses/0328-r8_antiabrasion.pdf` (Transfer Oil S.p.A., serie "075 - R8
Antiabrasion") als brondocument voor leverancier/OD en `Perslijst_Geeve_2018.pdf` (tabellen "(0328) SAE 100R8"
Staal en "0328 - R8" RVS) voor de persgegevens. Leverancier is aanvankelijk als `Transoil` vastgelegd en later
gecorrigeerd naar de juiste bedrijfsnaam `Transferoil` (zie verderop in dit document):

- **Omschrijving**: `R8` toegevoegd aan `artnm` in alle 3 CSV's voor de 8 rijen waar dit nog ontbrak
  (`0328-04/-04D/-05/-05D/-06/-06D/-08/-08D`; `0328-03` had het al).
- **Leverancier**: voor de 5 enkele-slang-maten (`0328-03/-04/-05/-06/-08`) is de werkdruk van elke rij exact
  gematcht met een Transferoil-partnummer uit het datasheet (0751/0752/0753/0754/0755 → 350/350/300/280/245 bar) -
  Leverancier gewijzigd naar `Transferoil` en `Artikelnr leverancier` naar het bijbehorende Transferoil-partnummer.
  De 4 TWIN-varianten (`-04D/-05D/-06D/-08D`) zijn **bewust ongemoeid gelaten** qua leverancier: het datasheet
  bevat geen aparte twin-partnummers, dus is de bestaande leverancier (Dicsa/Calseyde/Hansaflex) niet vervangen
  door een gegokt Transferoil-nummer.
- **Persgegevens Staal**: `0328-03` en `0328-08` bevatten een foutieve `2delig_1`-huls (`17001-03`/`17001-08`) -
  dat is de hulscode van de `(0347) SAE 100R7`-tabel, niet van de `(0328) SAE 100R8`-tabel. Gecorrigeerd naar de
  juiste R8-huls (`17003-03`/`17003-08`) met de bijbehorende persmaat. Voor `-04D/-05D/-06D` (correcte huls,
  ontbrekende persmaat) is de persmaat aangevuld; voor `-08D` was zowel de huls fout als de persmaat leeg, beide
  gecorrigeerd/aangevuld.
- **Persgegevens RVS**: voor `-04/-05/-06/-08` (en hun D-varianten) was de RVS-huls (`17001-xxRVS`) al correct
  maar de persmaat leeg - aangevuld uit de RVS R8-tabel. Voor `0328-03` bestaat in de Perslijst geen `-03`-maat
  in zowel de R7- als de R8 RVS-tabel; de bestaande RVS-koppelingdata voor deze rij (`17001-03RVS` /
  `1300PF-R7-03RVS`) kwam nergens in het brondocument voor en is daarom verwijderd in plaats van gegokt.
- **Buitenmaat slang**: aangevuld uit het Transferoil-datasheet voor `-03` (8,9 mm) en de vier TWIN-varianten
  (zelfde OD als hun single-hose tegenhanger: 11,5/13,4/15,5/19,9 mm). De al aanwezige waarden voor `-04/-06/-08`
  (uit een eerdere Hansaflex-match) kwamen exact overeen met het Transferoil-datasheet - extra bevestiging dat het
  om dezelfde slang gaat.

## Leverancier Transoil hernoemd naar Transferoil
De juiste bedrijfsnaam is `Transferoil`, niet `Transoil`. Gecorrigeerd voor de 5 `0328`-rijen (`-03/-04/-05/-06/-08`)
die in de vorige ronde op `Transoil` waren gezet, in alle 3 CSV's.

## 0347 (SAE 100R7): zelfde aanpak als 0328, met Transferoil crimping-datasheets
Op verzoek van de gebruiker, dezelfde werkwijze als bij `0328` toegepast op de `0347`-familie (14 rijen), nu met
zowel het productdatasheet als de crimping-datasheet van Transferoil (`docs/hoses/0347-R7_ANTIABRASION*.pdf`,
serie "066 - R7 Antiabrasion") en `Perslijst_Geeve_2018.pdf` (tabellen "(0347) SAE 100R7" Staal/RVS):

- **Omschrijving**: `R7` toegevoegd aan `artnm` in alle 3 CSV's voor de 9 rijen waar dit nog ontbrak (`-02/-03/
  -03D/-04D/-04Q/-05/-05D/-06D/-08D`; `-04/-06/-08/-10` hadden het al). `0347-025` (5/32", 240 bar) valt buiten de
  Transferoil-reeks (geen match op maat of werkdruk) en is bewust **niet** als R7 gelabeld.
- **Leverancier**: voor de 7 basismaten (`-02/-03/-04/-05/-06/-08/-10`) is Transferoil bevestigd via exacte
  werkdruk-/maatmatch met het datasheet (0660-0666), voor `-04` bovendien via het reeds aanwezige, met de
  Transferoil-code samengestelde leveranciersartikelnummer (`R7-DN06-0662`). Leverancier gewijzigd naar
  `Transferoil`, Artikelnr leverancier naar het bijbehorende partnummer. De TWIN/QUAT-varianten (`-03D/-04D/-04Q/
  -05D/-06D/-08D`) zijn net als bij 0328 bewust ongemoeid gelaten qua leverancier (geen apart Transferoil-nummer
  in het datasheet).
- **Persgegevens**: in tegenstelling tot 0328 was hier vrijwel alle Staal-persmaat al correct ingevuld (matcht de
  Perslijst). Alleen de ontbrekende persmaat van de TWIN/QUAT-varianten (Staal én RVS) is aangevuld - niet uit de
  Perslijst, maar overgenomen van de reeds aanwezige waarde bij de bijbehorende single-hose rij in hetzelfde
  bestand, om geen nieuw, extern getal naast de bestaande data te zetten. `0347-02` heeft geen RVS-koppelingdata
  (de Perslijst RVS R7-tabel begint pas bij `-04`) en is zo gelaten. De bestaande, niet-lege `16001-xx`-hulzen
  (tweede combinatie, en de enige combinatie bij `-10`) komen in geen van de brondocumenten voor en zijn
  ongewijzigd gelaten.
  **Update, op verzoek van de gebruiker**: de RVS-persmaten die afweken van de Geeve Perslijst (`-05/-06/-08/-10`)
  zijn alsnog aangepast, nu naar de Transferoil-fabrikantswaarde uit de crimping-datasheet (A316L-ferrule
  "crimping diameter") in plaats van de generieke Perslijst-waarde: `-05` 15,7 → 17,1 mm, `-06` 18,7 → 18,6 mm,
  `-08` 21,7 → 22,9 mm, `-10` 26,7 → 26,5 mm. Meegenomen: de TWIN-varianten die dezelfde waarde als hun
  single-hose zusterrij overnemen (`-05D`, `-06D`, `-08D`) en de secundaire `1300PF-R7-xxRVS`-combinatie bij
  `-06` en `-10`, die steeds gelijk aan de primaire combinatie werd gehouden.
- **Buitenmaat slang**: aangevuld voor `-02/-03/-03D/-04D/-04Q/-05D/-06D/-08D/-10` uit het Transferoil-datasheet.
  De al aanwezige waarden voor `-04/-05/-06/-08` kwamen exact overeen - zelfde bevestiging als bij 0328.

## SX35LT/SX42LT: persgegevens overgenomen van SX35/SX42 (R13/R15-Interlock)
Op verzoek van de gebruiker de ontbrekende 1-delige koppelingdata voor `SX35LT-12/-16/-20` en `SX42LT-12/-16/-20`
aangevuld uit `Perslijst_Geeve_2018.pdf` (tabellen "R13 (SX35) Serie 73" en "R15 (SX42) Serie 77 / **Serie 73"):
de LT-uitvoering (Low Temperature) is qua koppeling/persmaat identiek aan de gewone SX35/SX42, alleen het
rubbercompound verschilt. Ingevuld in zowel `artikelnummers_staal.csv` als `artikelnummers_rvs.csv` (deze
koppeling-serietabel maakt geen materiaalonderscheid, net als bij de eerder al aanwezige 0387-familie met
hetzelfde tabeltype):

- `SX35LT-12/-16/-20`: koppelingserie `73`, persmaat 36,3/44,2/54,6 mm, insteekdiepte 48/51/64 mm.
- `SX42LT-12`: koppelingserie `73` (uitzondering, gemarkeerd `**` in de Perslijst), persmaat 36,3 mm, insteekdiepte
  48 mm - gelijk aan `SX35LT-12`. `SX42LT-16/-20`: koppelingserie `77`, persmaat 39,4/50,8 mm, insteekdiepte
  54,1/63,8 mm.

## Slangtype 550H verwijderd
Op verzoek van de gebruiker volledig verwijderd uit alle 3 CSV's: `550H-3`, `550H-3-3`, `550H-4`, `550H-4-4`,
`550H-6`, `550H-6-6`, `550H-8`, `550H-12` (8 artikelen). Staal en RVS gaan hiermee van 958 naar 950 rijen
(pariteit behouden), Accessoires van 961 naar 953.

## 811S: Huls (Staal) aangevuld uit Parker CAT_4400/UK
De door de gebruiker eerst aangeleverde `Parker 811S.pdf` (export van Parkers online crimp-tool) bleek leeg - alle
kolommen (Crimper Die, Crimp Diameter, Crimp Length, Approx Setting, Hose Insertion) waren onbeschreven. Op
aanwijzing van de gebruiker is in plaats daarvan `Parker-Hydraulic-Hoses-CAT_4400_UK.pdf` gebruikt (pagina Ce-1,
"100IF No-Skive shell, 2piece - Series IF"), waar Parker zelf ook naar verwijst voor de crimpmaten
("Crimp Diameters please find on www.parker.com/crimpsource-euro" - dus ook deze catalogus geeft geen
persmaat/Schilmaat, alleen het schaalnummer):

- **2delig_1 - Huls** in `artikelnummers_staal.csv` gevuld met het bevestigde Parker-schaalnummer voor `811S-40/
  -48/-56/-64/-80/-96` (`100IF-40/-48/-56/-64/-80/-96`), bevestigd doordat de DN/inch/mm-maten in de fittingtabel
  exact overeenkomen met de bestaande hose-omschrijvingen.
- **`811S-32` bewust overgeslagen**: in dezelfde tabel bestaat alleen `100IF-32-TUBE`, en die variant vermeldt in
  de "Hose Type"-kolom expliciet alleen `EZ-Form; Carburite 10` - niet `811S`. Er is dus geen bevestigde
  100IF-schaal voor deze maat.
- **Pilaar/Persmaat/Schilmaat blijven leeg** voor alle zes ingevulde maten: dit is een 2-delig systeem (schaal +
  los te bepalen pilaar/stam), maar geen van de twee documenten geeft de bijbehorende pilaarcode of crimpmaat.
- **RVS niet aangevuld**: er is geen aparte RVS/A316L-uitvoering van de `100IF`-schaal gevonden in de catalogus,
  dus is `artikelnummers_rvs.csv` ongewijzigd gelaten.
- **Buitenmaat slang** in `artikelnummers_accessoires.csv` bleek al voor alle 7 maten correct ingevuld (64/75/90/
  106/116/142/172 mm) - dit kwam exact overeen met de O.D.-kolom uit dezelfde CAT_4400/UK-tabel (pagina Cab-37),
  dus geen wijziging nodig.

## 811 / 811S: Persmaat aangevuld uit "CRIMP DIMENSIONS"-tabel (docs/perslijst/811S-IF.pdf, 811-IF.jpg)
De gebruiker leverde alsnog een werkende crimptabel aan ("CRIMP DIMENSIONS; for variable crimper only" - per
Cover OD-bereik een Crimp ø A). Voor elke maat is de rij gekozen waarvan het Cover OD-bereik de al aanwezige
Buitenmaat (Cover OD) van die slang bevat:

- **`artikelnummers_staal.csv`**: `2delig_1 - Persmaat (mm)` aangevuld voor `811S-40/-48/-56/-64/-80/-96`
  (81,0 / 93,6 / 112,0 / 121,0 / 146,0 / 174,3 mm) bij de al aanwezige `100IF-xx`-huls uit de vorige ronde. RVS
  niet aangevuld (geen materiaalonderscheid in dit document, en zoals eerder gemeld geen bevestigde RVS-variant
  van de `100IF`-schaal gevonden).
- **`811-40`/`811-48`** (de gewone, niet-S-uitvoering) delen dezelfde crimpmaten (81,0 / 93,6 mm) - dit staat ook
  los bevestigd in `811-IF.jpg`. Deze twee maten hadden al een `1delig_1 = 48`-optie (andere koppelingserie,
  zonder persmaat); als tweede, aanvullende optie is nu `1delig_2 = IF` met de bijbehorende persmaat toegevoegd,
  in zowel Staal als RVS - dezelfde structuur die al bestond bij `811-16` (`1delig_1 = 48` + `1delig_2 = IF`).
  Insteekdiepte blijft bij deze nieuwe `IF`-optie leeg: geen van beide documenten geeft die maat.
- **Let op, niet zelf aangepast:** `811S` gebruikt voor de `IF`-huls een volledig partnummer (`100IF-40`) in
  `2delig_1 - Huls`, terwijl `811` (in de al bestaande data, bijv. `811-16`) hetzelfde fittingtype als kale code
  `IF` in een `1delig`-veld vastlegt. Dit is een bestaande inconsistentie tussen de twee slangfamilies, niet iets
  wat in deze ronde is geïntroduceerd of opgelost.

## 0442 (TFD04SP) en 0446 (TFDR4SH): persgegevens overgenomen van een zusterreeks
Op verzoek van de gebruiker, per geval een expliciet aangewezen zusterreeks:

- **`0442-06`** (`TFD04SP-06`, geen eigen persgegevens): koppelingdata 1-op-1 overgenomen van `0441-06`
  (`TFDM4SP-06`) - dezelfde Interpump 4SP-slang, alleen een andere leverancierscode-variant (`TFD04SP` i.p.v.
  `TFDM4SP`). Huls, Pilaar, Persmaat en Schilmaat extern gekopieerd naar zowel Staal (`23000-06MM`) als RVS
  (`1300P3-06RVS`).
- **`0446` (TFDR4SH), hele reeks (-12/-16/-20/-24/-32)**: op verzoek van de gebruiker de crimpgegevens van de
  bijbehorende `TFDR015`-maat overgenomen uit `docs/hoses/IMM Crimping-chart-R12.10.pdf` (tabel "HyGreen R15
  (TFDG015-TFDR015)"), in plaats van TFDR4SH's eigen tabel in hetzelfde document ("HyGreen 4SH"). Huls = het
  Interpump-ferrulenummer uit die tabel (`004N-12` voor -12, `0013-16/-20/-24/-32` voor de overige maten),
  Persmaat = de opgegeven "Ø Pressatura"/swaging-diameter, Schilmaat intern/extern = de "Int."/"Est."-kolommen.
  Toegepast in zowel Staal als RVS (het document maakt geen materiaalonderscheid). Pilaar blijft leeg: de tabel
  noemt alleen "Inter-lock" als bevestigingstype, geen kort Geeve-achtig serienummer.

## 0442 (TFD04SP): rest van de familie en Werkdruk aangevuld vanuit 0441 (TFDM4SP)
Vervolg op de eerdere `0442-06`-aanvulling: op verzoek van de gebruiker ("werkdrukken kan ook bijwerken") is
dezelfde 0441→0442-overname doorgetrokken naar de rest van de familie én naar het `Werkdruk (bar)`-veld, dat in
de eerste ronde bewust was overgeslagen (scope was toen alleen "crimping gegevens").

- **`0442-08/-10/-12/-16`**: Werkdruk + volledige 2-delige koppeling (Huls, Pilaar, Persmaat, Schilmaat extern)
  gekopieerd van de gelijknamige maat in `0441`, in zowel Staal als RVS.
- **`0442-24`**: Werkdruk (185 bar) gekopieerd; koppelingdata alleen in RVS (`1300P3-24RVS`) - `0441-24` heeft
  zelf geen Staal-koppelingdata om van te kopiëren (een bestaand gat in de bronrij, niet in deze ronde ontstaan).
- **`0442-20`**: niets aangevuld - `0441-20` (`TFDM4SPN20`) heeft zelf geen Werkdruk of koppelingdata.
- **`0442-06`**: alsnog Werkdruk (445 bar) toegevoegd naast de koppelingdata uit de vorige ronde.

Ontbrekende_persgegevens.xlsx opnieuw gegenereerd na deze ronde (152 → 148), conform de vaste stap in
`AGENTS.md` §9.

## Omschrijvingen "MTR SLANG ..." uniform gecorrigeerd naar het `<TYPE> <mm>MM (<fractie>) DN<nn> ... W.P. <bar> BAR`-patroon
Op verzoek van de gebruiker, met als voorbeeld een al bestaande, correcte rij (`4SP 50.8MM (2) DN50 OUT.SKIVE
W.P. 172 BAR`). Alle nog resterende placeholder-omschrijvingen van het type "MTR SLANG (4SP/R13/R15)" (13 rijen,
in alle 3 CSV's) herschreven naar hetzelfde patroon - `R12`, Jackmaster, Blastopak en soortgelijke andere
"MTR SLANG ..."-rijen zijn bewust **niet** aangepast, die vallen buiten de door de gebruiker genoemde typen.

- **`0442-06/-08/-10/-12/-16/-20/-24`** (4SP): mm/fractie/DN per maat overgenomen uit het al gevestigde patroon
  van de identieke `0441`-familie (bijv. `-12` → `19.1MM (3/4) DN20`), stijl `OUT.SKIVE` (geen spatie, zoals
  overal in de 4SP-familie). W.P. toegevoegd waar de Werkdruk-kolom een waarde heeft (`-06/-08/-10/-12/-16/-24`);
  bij `-20` (geen bekende Werkdruk) is de W.P.-clausule weggelaten in plaats van gegokt.
- **`0449-04/-06/-08/-40/-48`** (R13): mm/fractie/DN en de `IN-/OUT. SKIVE`-stijl overgenomen van de al correcte
  `0449-20/-24/-32`-rijen in dezelfde familie. Voor `-40`/`-48` (geen sibling in `0449` zelf) is de exacte
  precedent gebruikt van `R35TC-40`/`R35TC-48` - dezelfde R13/R35-reeks, elders in dit bestand - die letterlijk
  `63.5MM (21/2) DN63` resp. `76MM (3) DN76` gebruiken (dus `76MM`, niet de theoretische `76.2MM`). W.P.
  toegevoegd bij `-04` (al aanwezige Werkdruk 690 bar); bij `-06/-08/-40/-48` (geen bekende Werkdruk) weggelaten.
  *Zijdelings gevonden, niet zelf toegepast*: `R35TC-40`/`R35TC-48` tonen beide W.P. 350 BAR, en `0449`'s eigen
  `-20/-24/-32` ook allemaal 350 bar - een sterke aanwijzing dat ook `0449-06/-08/-40/-48` 350 bar zijn, maar dit
  raakt de `Werkdruk (bar)`-kolom (een apart datagat) en viel buiten de scope van deze ronde (omschrijvingen).
- **`0480-32`** (R15): mm/fractie/DN/`IN-/OUT. SKIVE` naar hetzelfde patroon; de merkspecifieke toevoeging
  "MANULI DIAMONDSPIR" is vervallen (staat al in de Leverancier-kolom als `Manuli`).

### Vervolg: Werkdruk 350 bar alsnog ingevuld voor 0449-06/-08/-40/-48
Op bevestiging van de gebruiker alsnog toegepast: `Werkdruk (bar)` = `350` voor `0449-06`, `0449-08`, `0449-40`
en `0449-48`, op basis van de hierboven genoemde precedent (`0449-20/-24/-32` en `R35TC-40/-48` zijn allemaal 350
bar). De omschrijving is in alle 3 CSV's meteen aangevuld met de bijbehorende `W.P. 350 BAR`-clausule, zodat
tekst en Werkdruk-kolom weer overeenkomen.

## 0441-20/0442-20 uitgezocht en gecorrigeerd (Werkdruk was fout, niet alleen leeg)
Op verzoek van de gebruiker uitgezocht via `docs/hoses/IMM Hydraulic-hose.pdf` (volledige "Hypress 4SP
(TFDM4SP)"-tabel, met alle maten t/m -32) en `docs/hoses/IMM Crimping-chart-R12.10.pdf` (dezelfde tabel met
Ferrule/Swaging Ø/Schilmaat):

- **Werkdruk was niet leeg maar fout** bij `0441-20`: de omschrijving zei "W.P. 280 BAR" (en zelfs de maat klopte
  niet: "25.4MM (1) DN32" - een mismatch tussen 1"-maat en DN32). De catalogus geeft voor dash -20 echter
  **210 bar** (niet 280 - dat hoort bij dash -16). Gecorrigeerd naar `Werkdruk (bar)` = `210` en omschrijving
  `4SP 31.8MM (1-1/4) DN32 OUT.SKIVE W.P. 210 BAR`, in alle 3 CSV's. `0442-20` kreeg dezelfde correctie (was nog
  leeg, geen bestaande foutieve waarde).
- **2-delige koppeling (Staal) aangevuld** voor beide rijen: Huls `23000-20MM`, Pilaar `10`, Persmaat `51,2` mm,
  Schilmaat extern `54` mm - Persmaat/Schilmaat rechtstreeks uit de crimping-chart (ferrule `0009-20`); de
  Huls-code `23000-20MM` volgt het al gevestigde, uitzonderingsloze patroon `23000-<maat>MM` dat voor alle andere
  0441/0442-maten al gebruikt wordt (04/06/08/10/12/16/24), dus niet los geverifieerd tegen een eigen Geeve-bron
  maar wel een zeer sterke, consistente extrapolatie van een bestaand patroon.
- **RVS bewust niet aangevuld**: de crimping-chart maakt geen materiaalonderscheid en de bestaande RVS-Persmaten
  van dit type wijken meetbaar af van de Staal-waarden bij dezelfde maat (bijv. -12: Staal 33,8 vs RVS 34,9) - er
  is dus geen betrouwbare manier om een RVS-specifieke Persmaat af te leiden uit deze bron. Werkdruk (210 bar) is
  wel materiaalonafhankelijk en is wel aangevuld in RVS.

Ontbrekende_persgegevens.xlsx opnieuw gegenereerd (blijft 148 - beide rijen verschuiven van categorie
"Staal + RVS" naar "Alleen RVS", geen netto afname).

## Persgegevens aangevuld vanuit "de PH perslijst" (GH Pressmassliste.pdf) voor 4SP/R13/R15
Op verzoek van de gebruiker: voor de resterende Interpump 4SP/4SH/R13/R15-slangen zonder persgegevens is
`docs/hoses/GH Pressmassliste.pdf` (distributeurstabel, paginakoppen `www.ph-hydraulik.de`) als bron gebruikt,
uitsluitend waar nog niets was ingevuld. Voor 4SH (0446) was al alles aangevuld in een eerdere ronde (IMM
Crimping-chart), dus daar is niets veranderd.

- **0441-24/0442-24 (Staal)**: Huls `23000-24MM`, Pilaar `10`, Persmaat `58.0` mm, Schilmaat extern `56` mm - uit
  de "P3 - schälen"-tabel (Rubrik 26, `P3-DN 38`/1-1/2"/dash-24: Pressdurchmesser 58,0 / Schällänge-La 56,0). RVS
  had voor deze maat al bestaande data (Persmaat 61,4/Schilmaat ext 55,0) en is ongemoeid gelaten.
- **0441-32 (Staal, RVS bewust niet aangevuld)**: Huls `23000-32MM`, Pilaar `10`, Persmaat `71.0` mm, Schilmaat
  extern `66` mm - uit dezelfde P3-tabel (`P3-4SP-DN 51`/2"/dash-32: 71,0/66,0). RVS blijft leeg: de PH-tabel
  maakt geen materiaalonderscheid en de bestaande 0441-familie laat op andere maten meetbare Staal/RVS-verschillen
  zien (bv. -16: Staal 42,3 vs RVS 40,7), dus zonder een RVS-specifieke bron is een RVS-waarde hier gokken.
- **0447-12/-16/-20/-24 (R15, Staal + RVS identiek)**: Huls `P7-I-DN19`/`P7-I-DN25`/`P7-I-DN31`/`P7-I-DN38`,
  Persmaat `34,5`/`41,5`/`54,0`/`64,0` mm, Schilmaat intern `16`/`20`/`20`/`27` mm, extern `42`/`60`/`62`/`82` mm
  - uit de Interlock P7-I-tabel (Rubrik 29, kolommen Li/La/Pressdurchmesser). Zelfde waarde in Staal en RVS,
  net als bij de eerder aangevulde 0446-familie: de Interlock-fassung is niet materiaalspecifiek gecodeerd.
  **0447-10 en 0447-32 blijven leeg**: de PH-tabel voor P7-I dekt alleen DN19 t/m DN38 (dash -12 t/m -24), er is
  geen DN16 (dash -10) of DN51 (dash -32) rij voor deze reeks - niet extrapoleren, dus bewust opengelaten.
- **0449-16 (R13, Staal + RVS identiek)**: Huls `P6-I-DN25`, Persmaat `42,0` mm, Schilmaat intern `20` mm,
  extern `60` mm - uit de Interlock P6-I-tabel (Rubrik 28).

**Kanttekening bij de PH-tabel als bron voor de 4SP-familie (0441/0442)**: de eerder (via IMM) vastgelegde
Persmaat-waarden voor dash-04 t/m -20 komen niet exact overeen met de PH-tabel (bv. dash-16: vastgelegd 42,3 vs
PH 42,0; dash-20: vastgelegd 51,2 vs PH 52,6) - dit geldt voor bijna de hele familie, niet specifiek voor -20,
en is dus normale spreiding tussen verschillende fabrikanten/tabellen voor dezelfde DIN/SAE-norm, geen aanwijzing
dat de eerder vastgelegde -20 waarde fout is. De reeds gecommitte `0441-20`/`0442-20`-waarden zijn daarom
ongewijzigd gelaten.

Ontbrekende_persgegevens.xlsx opnieuw gegenereerd na deze ronde (zie onderaan repo-root).

## FA35-12-DL verwijderd; 580N/588N-56/-58 gecontroleerd (geen nieuwe data)
Op verzoek van de gebruiker is `FA35-12-DL` volledig verwijderd uit alle 3 CSV's (stond zonder koppelingdata in
Staal/RVS en met accessoire-data in de accessoires-lijst); 949 artikelen resterend.

De gebruiker gaf aan een reeks nieuwe Parker-perslijsten te hebben toegevoegd. Van de genoemde bestanden bleken
alleen `580N-56.pdf`, `580N-58.pdf`, `588N-56.pdf` en `588N-58.pdf` daadwerkelijk aanwezig in
`docs/perslijst/Parker/`. Gecontroleerd: deze vier tabellen dekken uitsluitend de maten -8/-10/-12/-16, die al
identiek in de CSV's stonden (bevestigt dat dit de oorspronkelijke bron was). De echte gaten in deze families
(`580N-04`, `580N-06`, `588N-4`, `588N-06`) hebben geen -04/-06-rij in deze specifieke tabellen, dus daar kon niets
mee aangevuld worden. De overige door de gebruiker genoemde bestanden (`FA35-V4/V6/VS.pdf`, `R35TC.pdf`,
`RS35TC-48.pdf`, `811S.pdf`, `221FR-26.pdf`, `431-43.pdf`) en een bron voor `563TJ` staan nog niet in de repo -
nog niet verwerkt, wachten op upload.

Ontbrekende_persgegevens.xlsx opnieuw gegenereerd (141, was 142 - FA35-12-DL verdwijnt uit de lijst).

## Resterende Parker-perslijsten verwerkt (FA35, 221FR, 431, R35TC-40/-48, 563TJ)
De eerder ontbrekende bestanden zijn geupload (`docs/perslijst/`, root-niveau) en verwerkt. Regel van de
gebruiker toegepast: crimp-/schilgegevens zijn gelijk voor Staal en RVS (identieke waarde in beide CSV's), `V4`,
`V6`, `VS` zijn 2-delig (Huls = Pilaar = de code zelf), de rest is 1-delig.

- **FA35-6/-8 (Staal+RVS)**: Huls/Pilaar `VS`, Persmaat `23,5`/`26,5` mm, Schilmaat extern `28`/`32` mm (geen
  Schilmaat intern - uit `FA35-VS.pdf`). **FA35-12/-16/-20**: Huls/Pilaar `V4`, Persmaat `33,9`/`42,5`/`50,2` mm,
  Schilmaat intern `15`/`17`/`21,5` mm, extern `52`/`65`/`74` mm - uit `FA35-V4.pdf`. Deze waarden matchen exact de
  al bestaande `100V4-16/-20`-koppelingen bij `R35TC`/`0449`, wat de bron bevestigt. `FA35-V6.pdf` (dash-24/-32)
  is gecontroleerd maar niet toegepast: er bestaat geen `FA35-24`/`FA35-32`-artikel in de dataset.
- **221FR-8/-12 (Staal+RVS)**: Huls `26`, Persmaat `18,30`/`24,50` mm, Insteekdiepte `21`/`22` mm - uit
  `221FR-26.pdf`, zelfde "26 Series"-stijl als de al bestaande `221FR-5/-6/-16`. Werkdruk `35` bar aangevuld
  (extrapolatie: alle 3 bekende maten in deze familie zijn uniform 35 bar).
- **431-4/-6/-8/-12/-16 (Staal+RVS)**: Huls `43`, Persmaat en Insteekdiepte uit `431-43.pdf` - **dit document is
  in inches** (koptekst "All units in inches"), omgerekend naar mm (×25,4): Persmaat `16,64`/`21,21`/`24,26`/
  `31,88`/`40,64` mm, Insteekdiepte `21`/`29`/`33`/`38`/`44` mm (afgerond op hele mm). Werkdruk niet aangevuld:
  niet in dit document vermeld en geen bestaande familiewaarde om van te extrapoleren.
- **R35TC-40/-48 (Staal+RVS)**: Huls `100V6-40`/`100V6-48`, Pilaar `V6`, Persmaat `94`/`99,6` mm, Schilmaat
  intern `30`/`22` mm, extern `99`/`75` mm - uit `R35TC.pdf` (dash-48 bevestigd door `RS35TC-48.pdf`), zelfde
  `100V6-<maat>`/`V6`-conventie als de al bestaande `R35TC-24/-32`-siblings. Dit vult de laatste 2 gaten in de
  R35TC-familie.
- **0563TJ-4/-6/-8 (Staal+RVS)**: Huls `55`, Persmaat `13,46`/`17,15`/`20,45` mm, Insteekdiepte `30`/`33`/`40`
  mm - uit `563TJ-55_US.pdf` (US-eenheden, inches, omgerekend ×25,4). Van de 3 geuploade 563TJ-documenten (43/55/56
  series) is bewust voor de **55-serie** gekozen: dit is dezelfde stijl als de al bestaande `0590TJ-06/-08`
  (ook een "Tough Jacket"-slang), en de omgerekende waarden komen na afronding exact overeen met die twee
  bestaande rijen (bv. dash-8: 20,447mm -> 20,45, identiek aan `0590TJ-08`s Persmaat) - sterke bevestiging dat dit
  de juiste serie is.

**Niet verwerkt / open punten voor de gebruiker:**
- **811S.pdf**: dekt de maten -40/-48/-56/-64/-80/-96, elk met **twee** Cover-OD-rijen en dus twee mogelijke
  Crimp-maten. Volgens de nieuwe instructie moet steeds de eerste rij worden gebruikt, maar de al eerder
  vastgelegde waarden blijken een **wisselend** mengsel van rij 1 en rij 2 te zijn (-40/-48 komen overeen met rij
  2, -56/-64/-80/-96 met rij 1). De ontbrekende maat `811S-32` staat niet eens in deze tabel (die begint pas bij
  -40), dus hier viel sowieso niets nieuws mee aan te vullen. Aan de gebruiker voorgelegd of de bestaande
  -40/-48/-96 gecorrigeerd moesten worden naar "altijd eerste rij" - **antwoord: laten zoals het is**, dus geen
  wijziging. De "eerste rij"-regel geldt wel voor eventuele toekomstige 811S-aanvullingen (bv. als er ooit een
  -32-tabel opduikt).

Ontbrekende_persgegevens.xlsx opnieuw gegenereerd na deze ronde.

## Resterende 2-delige koppelingsgaten in 0441/0442 (4SP), 0447/0480 (R15) en 0449 (R13)
Op verzoek van de gebruiker: gebruik de familie-brede 4SP/R15/R13-persgegevens voor alle nog lege 2-delige
koppelingen in deze prefixen, voor zowel Staal als RVS - ook waar de Leverancier/Artikelnr leverancier van de
specifieke rij niet Interpump is (Parker/Manuli-rijen krijgen dezelfde generieke koppeling als hun Interpump- of
Parker-V-serie-zusterrijen in dezelfde maat).

- **0441-20/0441-32/0442-20 (RVS)**: RVS was nog leeg terwijl Staal al gevuld was. RVS krijgt nu **dezelfde**
  waarde als Staal (Huls `1300P3-<maat>RVS`, Persmaat/Schilmaat extern identiek aan de Staal-rij) - dit wijkt af
  van de eerdere aanpak (waar RVS bewust leeg werd gelaten omdat RVS-Persmaten elders in de familie meetbaar
  afwijken van Staal), maar volgt nu expliciet de instructie van de gebruiker om staal/rvs gelijk te trekken.
- **0447-10/0447-32 en 0480-32 (R15, Staal+RVS)**: de PH-perslijst (vorige ronde) dekte deze maten niet. Nu
  gevuld uit `docs/hoses/IMM Crimping-chart-R12.10.pdf`, tabel "HyGreen R15" (Interlock-fassung `0013-10`/`0013-32`,
  Persmaat `29,5`/`77,9` mm, Schilmaat intern `15`/`30` mm, extern `47`/`87` mm). `0480-32` (Manuli, R15 DN50)
  krijgt dezelfde `0013-32`-waarden als `0447-32`: expliciet door de gebruiker gevraagd ("0447- en 480-" samen),
  ondanks dat dit een ander merk is dan Interpump - de generieke Interlock-fassung past op dezelfde slangmaat.
- **0449-04/-06/-08 en 0449-40/-48 (R13, Staal+RVS)**: deze rijen zijn Parker- resp. Manuli-gesourced met eigen
  unieke leveranciersartikelnummers (geen Interpump `TFDR013-xx`), maar volgen dezelfde Parker V-serie-conventie
  die al bestond bij de zusterrijen `0449-20/-24/-32` (Huls/Pilaar `100V4`/`100V6`) en die deze ronde ook is
  toegepast op `FA35`/`R35TC-40/-48`:
  - `0449-06`/`0449-08`: Huls/Pilaar `VS`, Persmaat `23,5`/`26,5` mm, Schilmaat extern `28`/`32` mm - exact gelijk
    aan de zonet gevulde `FA35-6/-8`.
  - `0449-04`: Huls/Pilaar `VS`, Persmaat `19,8` mm, Schilmaat extern `29` mm - overgenomen van de **al bestaande**
    `H31-04` (4SP-familie, dezelfde VS-fassung bij dash-04), aangezien er geen apart FA35/R13-document voor
    dash-04 is.
  - `0449-40`/`0449-48`: Huls `100V6-40`/`100V6-48`, Pilaar `V6`, Persmaat `94`/`99,6` mm, Schilmaat intern
    `30`/`22` mm, extern `99`/`75` mm - exact gelijk aan de zonet gevulde `R35TC-40/-48`.
  - **Kanttekening**: de VS-fassung heeft van huis uit geen Schilmaat-intern-waarde (alleen extern), terwijl de
    omschrijving van `0449-04/-06/-08` "IN-/OUT. SKIVE" vermeldt (dus in principe ook intern schillen). Dit is
    dezelfde VS-fassung die al langer zo (zonder Schilmaat intern) in de dataset staat bij `H31-04/-06/-08` (4SP)
    en nu ook bij `FA35-6/-8`, dus consistent toegepast - maar het is geen 100% technische bevestiging dat VS de
    juiste fassung is voor een echte in-/out.-skive R13-toepassing. Graag laten weten als dit gecorrigeerd moet
    worden.

Ontbrekende_persgegevens.xlsx opnieuw gegenereerd na deze ronde.

## RVS 4SP-Persmaat herzien naar PH-perslijst; R13 Staal Z-serie huls toegevoegd
Op verzoek van de gebruiker herzien: voor RVS moet bij 4SP/R13/R15 de Persmaat uit de PH-perslijst komen, met
behoud van de bestaande Geeve-huisstijl voor de Huls-naam (dus niet PH's eigen `P3-DN..`/`P6-I-DN..`-notatie
overnemen als Huls-waarde).

- **4SP (0441/0442), RVS Persmaat volledig herzien naar de PH `P3`-tabel** (Rubrik 26), Huls-naam ongewijzigd
  (`1300P3-<maat>RVS`): dash-04 `19.4`->`19.5`, -06 `22.0`->`23.2`, -08 `25.9`->`26.5`, -10 `29.2`->`29.5`, -12
  `34.9`->`34.0`, -16 `40.7`->`42.0`, -20 `51,2`->`52.6` (dit lost meteen ook de eerder gesignaleerde
  komma/punt-inconsistentie op -20 op), -24 `61.4`->`58.0`, -32 ongewijzigd (was al `71.0`, matcht PH exact). Dit
  vervangt de oudere, niet met PH overeenkomende RVS-bron die al vóór deze sessie in de dataset stond.
- **R13 (0449) en R15 (0447/0480): geen wijziging mogelijk/nodig.** Uitgezocht welke rijen daadwerkelijk PH-data
  gebruiken: `0449-16` (Interlock `P6-I-DN25`) en `0447-12/-16/-20/-24` (Interlock `P7-I-DN..`) zijn al rechtstreeks
  uit PH gehaald in een eerdere ronde en kwamen dus al overeen. De overige R13/R15-rijen (`0449-04/-06/-08` met
  `VS`, `0449-20/-24/-32/-40/-48` met `100V4`/`100V6`, `0447-10/-32`+`0480-32` met `0013-..`) gebruiken een
  **andere fassung-serie** dan PH's Interlock-tabel (`P3`/`P6-I`/`P7-I`) - de PH-perslijst heeft geen tabel voor de
  V4/V6/VS-serie. Zonder de Huls-naam te wijzigen (zoals afgesproken) zou een PH-Persmaat hier een technisch
  inconsistente combinatie opleveren (Huls van fassung X met een Persmaat van fassung Y), dus deze zijn bewust
  ongewijzigd gelaten.

**R13 (0449) Staal: tweede 2-delige koppeling (Z-serie + 30-serie pilaar) toegevoegd**, analoog aan de al
bestaande `0449-32` (`Z37000-32`/Pilaar `30`) en de zusterfamilie R15/`R42TC` die hetzelfde Z-seriepatroon
gebruikt:
- `0449-20`: `2delig_2` = Huls `Z37000-20`, Pilaar `30`, Persmaat `54,2` mm, Schilmaat intern `22` mm, extern `74`
  mm (waarden overgenomen van `R42TC-20`, dezelfde Z37000-20-fassung).
- `0449-24`: `2delig_2` = Huls `Z37000-24`, Pilaar `30`, Persmaat `60,5` mm, Schilmaat intern `22,5` mm, extern
  `81` mm (van `R42TC-24`).
- **Niet toegevoegd** (geen betrouwbare precedent/bron voor een Z-serie-alternatief op deze maten): `0449-04/-06/-08`
  (VS-fassung, geen Z-serie-variant elders in de dataset gevonden), `0449-16` (huidige Huls is de Interlock
  `P6-I-DN25`, een structureel andere primaire fassung dan de V4/Z34000-opzet van de rest van de familie - een
  Z-serie hier toevoegen zou een niet-eerder-bevestigde combinatie zijn), `0449-40/-48` (de Z-serie in het
  R15-precedent (`R42(TC)`) gaat niet verder dan dash-32).

Ontbrekende_persgegevens.xlsx opnieuw gegenereerd na deze ronde (geen wijziging in het aantal - deze ronde was
uitsluitend correctie/aanvulling van al aanwezige koppelingen, geen nieuwe gaten gevuld of ontstaan).

## IMM-coderingen vervangen door Geeve Perslijst; RVS 4SP-fix vorige ronde teruggedraaid
Op verzoek van de gebruiker uitgezocht welke 2-delige Huls-waarden nog een IMM-coderingen waren (`004N-xx`,
`0013-xx`, uit `docs/hoses/IMM Crimping-chart-R12.10.pdf`). Dit bleek uitsluitend te gaan om `0446-12/-16/-20/-24/-32`
(4SH) en `0447-10/-32`, `0480-32` (R15), in zowel Staal als RVS.

**Belangrijke correctie eerst**: bij het uitzoeken bleek `docs/perslijst/Geeve/Geeve Perslijst_2018.pdf` (Geeve's
**eigen** perslijst, niet eerder als los document doorzocht) de oorspronkelijke bron te zijn van de RVS
4SP-Persmaten (0441/0442-04 t/m -24) die twee rondes geleden per abuis met PH-perslijst-waarden zijn overschreven.
Dat was dus een verkeerde "correctie" - teruggedraaid naar de Geeve Perslijst-waarden (welke, op dash-20 na,
identiek zijn aan wat er vóór die ronde al stond):
- RVS Persmaat 04-24: `19.4/22.0/25.9/29.2/34.9/40.7/-/61.4` (dash-20 uitgezonderd).
- **dash-20 is wel degelijk gecorrigeerd** naar een nieuwe, andere waarde dan zowel de oorspronkelijke placeholder
  (51,2) als de foutieve PH-fix (52,6): Geeve Perslijst geeft `54,3` mm voor `1300P3-20RVS` - dit is de nu
  correcte waarde.
- dash-32 heeft geen RVS-regel in dit document; ongewijzigd gelaten (blijft `71.0`, wat toevallig al met PH
  overeenkwam).

**IMM-coderingen vervangen** door de Geeve Perslijst-eigen "Interlock"-tabellen (Koppeling Serie `30`, Huls-serie
`Z34000`/`34000`), Staal en RVS apart uit de betreffende Staal-/RVS-secties van het document:
- **4SH (0446-12/-16/-20/-24/-32)**: Huls `34000-12/-16/-20/-24/-32` (Staal) / `Z34000-12RVS`...`-32RVS` (RVS),
  Pilaar `30`. Persmaat/Schilmaat ook licht bijgesteld t.o.v. de IMM-waarden (bv. -12: Persmaat 33,2 -> 33,9).
- **R15 (0447-32, 0480-32)**: Huls `Z37000-32` (Staal) / `Z37000-32RVS` (RVS), Pilaar `30`. Let op: dit is een
  *andere* Persmaat dan de gelijknamige `Z37000-32` bij R13 (`0449-32`: 77,3 mm) - de R15-tabel geeft 77,9 mm
  (Staal) / 78,3 mm (RVS). Niet zomaar hetzelfde cijfer tussen R13 en R15 aannemen, ook al is de Huls-naam gelijk.
- **R15 (0447-10)**: **geen Geeve-Perslijst-dekking voor dash-10/DN16** - de IMM-waarde is verwijderd zonder
  vervanging, dus deze rij is weer leeg. Dit is een bewuste keuze (geen bron = geen data, in lijn met "alle IMM
  weg"), maar betekent wel een nieuwe/hernieuwde lege plek in de dataset. Graag een bron aandragen als die er is.
- `0447-12/-16/-20/-24` (Huls `P7-I-DN..`, uit de PH-perslijst) zijn **niet** aangepast: dat zijn geen
  IMM-coderingen en vielen buiten de vraag.

Ontbrekende_persgegevens.xlsx opnieuw gegenereerd (104, was 113 v/e vorige combinatie van deze en de
verwijderronde hieronder).

## 10 artikelen verwijderd (AIR-luchtslangen en 7200-serie)
Op verzoek van de gebruiker verwijderd uit alle 3 CSV's: `AIR10MMGEEL 15 BAR`, `AIR13MMGEEL 15 BAR`,
`AIR13MMGEEL 20 BAR`, `AIR19MMGEEL 15 BAR`, `AIR19MMGEEL 20 BAR`, `AIR19MMGRIJS`, `AIR25MMGEEL 20 BAR`,
`7200-06LGR`, `7200-06ORA`, `7200-04TWIN`. 939 artikelen resterend (was 949).

Ontbrekende_persgegevens.xlsx opnieuw gegenereerd na deze ronde (zie boven, 104 - alle 10 verwijderde artikelen
stonden zonder koppelingdata in de lijst).

## 0447-10 hersteld
Vorige ronde is `0447-10` (R15, dash-10) leeggemaakt omdat er geen Geeve-Perslijst-dekking voor deze maat was en
de IMM-huls-code weg moest. De gebruiker gaf aan dat de eerder gebruikte meetwaarden (29,5mm Persmaat, Schilmaat
intern 15,0 / extern 47,0 - oorspronkelijk uit `docs/hoses/IMM Crimping-chart-R12.10.pdf`) wel bruikbaar zijn en
enkel de Huls-naam hoefde te veranderen, niet de cijfers. Hersteld met de Geeve-conventie die ook voor de rest van
de R15/4SH-familie gebruikt is: Huls `Z34000-10` (Staal) / `Z34000-10RVS` (RVS), Pilaar `30`, dezelfde
Persmaat/Schilmaat als voorheen.

Ook nagevraagd of de RVS-Huls van de rest van de familie (0446, 0447-32, 0480-32) naar PH-perslijst-notatie
(`P4-I-DN..`/`P7-I-DN..`) moest - **nee**: de Geeve-eigen notatie (`Z34000-xxRVS`/`Z37000-xxRVS`, al toegepast in
de vorige ronde) is en blijft correct.

Ontbrekende_persgegevens.xlsx opnieuw gegenereerd (103, was 104).

## Huls Texsleeve (Staal/RVS) herberekend voor de hele dataset
De gebruiker heeft twee bronbestanden toegevoegd in `docs/hoses/`: `Huls 19001.xlsx` (Staal, "HULS TEXSLEEVE
STEEL <binnenmaat>X<wanddikte>X<lengte>") en `Huls 9223.xlsx` (RVS, "MARKING FERRULE <binnenmaat> MM I.D."). Bij
de eerste upload bleek `Huls 19001.xlsx` per ongeluk dezelfde inhoud als `Huls 9223.xlsx` te bevatten (geen enkele
19001-code) - na navraag opnieuw geüpload met de juiste 19001-data.

**Selectieregel** (van de gebruiker, toegepast op alle 942 rijen in `artikelnummers_accessoires.csv`):
1. Crimpmaat = Persmaat van de koppeling. Bij meerdere ingevulde Persmaten voor hetzelfde artikel (2delig_1/2,
   1delig_1/2/3) wordt het gemiddelde genomen.
2. Gezochte huls = de kleinste huls in de tabel waarvan de binnenmaat groter is dan crimpmaat + 4mm.
3. Bij een "overlap" (twee hulzen met dezelfde binnenmaat, bv. 9223-019/9223-020 of 9223-023/9223-027) wordt de
   huls met het hoogste artikelnummer (grotere/zwaardere uitvoering) gekozen.
4. Huls Texsleeve (Staal) wordt berekend uit de **Staal**-Persmaat, Huls Texsleeve (RVS) uit de **RVS**-Persmaat -
   elk apart, uit de bijbehorende rij in `artikelnummers_staal.csv`/`artikelnummers_rvs.csv` (zelfde artnr).

**Toegepast**: 1264 veldwijzigingen (van de 2x942 mogelijke Staal/RVS-velden). Rijen zonder enige Persmaat
(271 velden) zijn **niet aangeraakt** - voor die artikelen is er geen crimpmaat om de regel op toe te passen, dus
blijft de bestaande waarde staan. 4 velden zijn leeggemaakt omdat de crimpmaat te groot is voor de Staal-tabel
(die stopt bij 85mm binnenmaat): `0424-40`, `0462TC-40`, `811-40`, `811S-40` (allemaal ca. 81-82mm crimpmaat,
drempel 85-86mm, geen passende 19001-huls beschikbaar). De RVS-tabel (tot 127mm) had geen out-of-range-gevallen.

Dit is een forse herziening t.o.v. de eerder aanwezige Huls Texsleeve-waarden, die kennelijk niet volgens deze
exacte regel waren bepaald (bv. `FA35-20` had `19001-52`, wat met crimpmaat 50,2mm niet aan de "+4mm"-marge
voldeed - nu `19001-55`).

Plaats de complete map op een PHP-webserver. Er is geen database nodig.
