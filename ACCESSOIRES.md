# Uitleg: accessoires-selectie in de Slangen fitting Selector

Korte uitleg hoe accessoires (RVS Omvlechting, PolyGuard, Firesleeve, enz.) gekoppeld worden aan
een slangartikel, en hoe de app bepaalt welke accessoires bij een geselecteerd artikel getoond
worden.

Bron: `hoses/index.php` (inlezen/koppelen) en `hoses/assets/selector.js` (tonen). Voor de volledige
technische matching-logica van de hele selector (niet alleen accessoires), zie
`hoses/docs/selector-matching.md`.

## De koppelparameter: het artikelnummer (artnr)

Er is precies één parameter die bepaalt welke accessoires bij een slang horen: het
**artikelnummer** (`artnr`).

- `hoses/data/artikelnummers_accessoires.csv` heeft (maximaal) één rij per slangartikel.
- Die rij hoort bij een slang als de `artnr`-kolom **exact** (hoofdletterongevoelig) overeenkomt
  met de `artnr` in `artikelnummers_staal.csv` of `artikelnummers_rvs.csv`.
- Er wordt nergens gematcht op maat, buitendiameter, werkdruk of iets anders — uitsluitend op het
  artikelnummer.

## Hoe je de koppeling instelt

Om accessoires aan een slangartikel te koppelen:

1. Zoek (of maak) in `artikelnummers_accessoires.csv` de rij met exact hetzelfde artikelnummer als
   de slang in de staal- of rvs-lijst.
2. Vul de gewenste kolommen in met het artikelnummer van het bijpassende accessoire (bijv.
   `RVS Omvlechting` = `9122-10-316L`). Een kolom leeg laten betekent dat dat accessoire niet
   getoond wordt.
3. Er is geen aparte "activeer"-stap nodig — zodra de rij bestaat met het juiste `artnr`, gebruikt
   de app hem automatisch bij dat artikel.

Kolommen in het accessoires-bestand:

| Kolom | Betekenis |
|---|---|
| `artnr` | Slangartikelnummer — de koppelparameter |
| `Buitenmaat slang (mm)` | Alleen ter informatie/weergave — telt niet mee in de matching |
| `RVS Omvlechting` | Artikelnummer bijpassende rvs-omvlechting |
| `PolyGuard` / `SpiralGuard` | Artikelnummers bijpassende guards (worden samen op één regel getoond) |
| `ParKoil` / `Spring Guard` / `Firesleeve` / `Texsleeve` | Artikelnummers overige bijpassende accessoires |
| `Huls tex staal` / `Huls tex RVS` | Artikelnummers hulzen voor Texsleeve, staal en rvs |

## Hoe de app de selectie maakt

1. Bij het opstarten worden alle drie de CSV's ingelezen en per artikelnummer samengevoegd tot één
   record per slang.
2. Bestaat er voor dat artikelnummer een rij in `artikelnummers_accessoires.csv`, dan wordt die
   aan het record gehangen; zo niet, dan blijven alle accessoire-velden leeg.
3. Zodra je in de zoeker een slangartikel selecteert, toont de app het accessoires-blok alleen als
   **minstens één** van de negen accessoire-velden gevuld is — anders blijft het blok verborgen.
4. Binnen dat blok wordt elk gevuld veld apart getoond (leeg veld = niet getoond). Er wordt niets
   automatisch "gekozen" tussen meerdere mogelijke opties — elk veld toont gewoon zijn eigen
   waarde als die is ingevuld.

## Op basis waarvan bepaal je *welk* artikel je invult?

De koppeling zelf (§ hierboven) kijkt alleen naar het artikelnummer. Maar de vraag "welk
RVS-omvlechtingsartikel hoort bij déze slang" moet je één keer, vooraf, zelf beantwoorden bij het
invullen van de rij — de app rekent dit niet live uit. Voor 6 van de 9 accessoirevelden staat die
keuzetabel in de al aanwezige leverancierscatalogi in `hoses/docs/`, en die tabellen zijn stuk voor
stuk gebaseerd op de **buitendiameter van de slang** (exact de aanpak die eerder als losse,
dynamische referentielijst voor RVS Omvlechting is gebouwd en weer teruggedraaid — de brontabellen
bestaan dus nog wél, alleen wordt er nu vooraf één keer in opgezocht i.p.v. live per weergave):

| Accessoire | Selectiecriterium | Bron |
|---|---|---|
| `RVS Omvlechting` (9122-xx-316L) | Binnendiameter van de omvlechting ≥ buitendiameter slang | Was `data/artikelnummers_rvs_omvlechting.csv` (11 regels, één per maat) — inmiddels verwijderd, waarden nu direct in de accessoires-CSV |
| `Firesleeve` (9125-xx) | Binnendiameter sleeve t.o.v. buitendiameter slang | `hoses/docs/9125 Firesleeve.pdf` |
| `SpiralGuard` (9121-xxx) | Binnendiameter/wrap-range t.o.v. buitendiameter slang | `hoses/docs/9121-.._-_spiralguard_protection_guard.pdf` |
| `PolyGuard` (HG-xxx) | Binnendiameter t.o.v. buitendiameter slang | `hoses/docs/Parker HPD_4400_Catalog_hoses.pdf`, p. 301-307 ("Protective Coils, Sleeves & Guards Selection Guide", direct per Parker-hosenummer) en `Parker-Hydraulic-Hoses-CAT_4400_UK.pdf`, p. 241 |
| `ParKoil` (PG-xxx) | Binnendiameter t.o.v. buitendiameter slang | Zelfde bronnen als PolyGuard, plus p. 242 |
| `Spring Guard` (SG-xxx) | Binnendiameter t.o.v. buitendiameter slang | Zelfde bronnen als PolyGuard, plus `Parker-Hydraulic-Hoses-CAT_4400_UK.pdf` p. 236 (tabel per hoses-familie + maat) |

`Parker HPD_4400_Catalog_hoses.pdf` p. 301-307 is de meest directe bron: die tabel geeft per
specifiek Parker-hosenummer (bijv. `201-10`) meteen de juiste PolyGuard/ParKoil/Spring
Guard/Firesleeve-code, met de eigen voetnoot *"Sizes indicated are suggestions only and based on
hose O.D."* — dus ook Parker zelf bevestigt dat buitendiameter het onderliggende criterium is,
al is het per hose-artikel voorverpakt in een tabel.

**Niet gevonden:** voor `Texsleeve`, `Huls tex staal` en `Huls tex RVS` is er geen maat-/
diametertabel aangetroffen in de huidige `hoses/docs/`-catalogi. Hoe de bestaande waarden voor die
drie velden precies bepaald zijn, is op dit moment niet te herleiden uit de beschikbare
documentatie — heb je daar zelf een bron/tabel voor, dan kan die er alsnog bij.

## Kort samengevat

De hele koppeling draait om precies één ding: **hetzelfde artikelnummer in beide bestanden**. Zet
je een accessoires-rij neer met het juiste `artnr`, dan verschijnt hij automatisch bij dat
slangartikel in de selector — er is verder geen extra configuratie, drempelwaarde of berekening
bij betrokken.
