# Barcode inkoopontvangst: opzoeken en boeken

Tegel `barcode` (Templates, IN PROGRESS), map `templates/barcode/`.

## Wat nu werkt (alleen lezen)

Inkooporder- en artikelnummer invullen, de leverancier staat boven de velden, daaronder besteld / al ontvangen / open.
Bron: `orkrg` (kop, `ord_soort = 'B'`) en `orsrg` (regels) in database 005, dezelfde tabellen als de tegel Inkoop per
Artikelgroep. Open = `esr_aantal - aant_gelev`. De knop "Ontvangst boeken" is bewust uitgeschakeld.

Niet op de server geverifieerd (alleen geschreven, niet tegen de database gedraaid):
- of de leveranciersnaam in `orkrg.ord_debtor_name` staat; zo niet, dan valt de code terug op `cicmpy` via `crdnr`/`debnr`;
- of `aant_gelev` bij inkooporders het al ontvangen aantal is (bij verkoop is het het geleverde aantal);
- een artikel dat twee keer op dezelfde order staat wordt opgeteld.

## Kan een ontvangst geboekt worden via de Entity Service?

Waarschijnlijk wel, maar dat is **niet bevestigd**. Wat bekend is:

- Schrijven naar Exact gaat alleen via Slangkaart.Web (`POST /api/exact/schrijf`), alleen naar testserver tst19; productie
  is in de code geblokkeerd. PHP schrijft nooit zelf naar Exact.
- Die schrijfdeur laat alleen entiteiten toe die in `EntityService:Entiteiten` (appsettings.json) staan; nu alleen
  `QuotationHeader` en `QuotationLine`. Een ontvangst-entiteit toevoegen is een wijziging in slangkaart-web met review
  en uitrol door Hans (en dus een PR, waar Patrick eerst om moet vragen).
- Of Globe een Entity Service voor goederenontvangst heeft, en onder welke naam en met welke velden, weten we niet. Dat is te
  achterhalen met `$metadata` op tst19 (zoals bij QuotationHeader), alleen-lezen via een RC-sessie op de server.
  Kandidaten om te zoeken: iets met `GoodsReceipt`/`Receipt` of de order-entiteit (`PurchaseOrderLine` met een
  ontvangen-aantal). Documentatie: customers.exact.com, "Entity service: operaties per entiteit".
- Risico: een ontvangst boekt voorraad en verhoogt `aant_gelev`; ongedaan maken is in Exact lastig. Daarom eerst op tst19.
  De Entity Service op tst19 was eerder traag en gaf HTTP 500 bij meerdere regels (zie notities Offerte naar Exact);
  één regel per boeking is licht, maar blijft te testen.
- Ontbreekt een geschikte entiteit, dan blijven over: de Exact SDK/COM (alleen Windows, het oude Slangkaart-programma),
  of een Exact-import (XML via ASImport) op een Windows-pc. Rechtstreeks schrijven in SQL is geen optie.

## Wat nodig is om te boeken

1. Patrick: akkoord om op tst19 te onderzoeken (alleen `$metadata`, geen boeking).
2. Entiteitsnaam en verplichte velden (magazijn, locatie, datum, ordernummer + regelnummer) uit `$metadata`.
3. Een PR in slangkaart-web die de entiteit toevoegt (Hans), plus uitrol; daarna de knop in `barcode.js` aansluiten met
   dubbelklik-beveiliging, controle `aantal <= open` en een bevestiging.
4. Pas daarna een proefboeking op tst19, met Patrick erbij.
