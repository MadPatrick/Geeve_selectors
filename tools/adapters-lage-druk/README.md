# Adapters lage druk toevoegen

Eenmalig op de server (`/var/www/html`), na `git pull` van deze wijziging:

```bash
php tools/adapters-lage-druk/lage-druk-toevoegen.php --dry-run   # eerst bekijken
php tools/adapters-lage-druk/lage-druk-toevoegen.php
```

Het script maakt een back-up in `data/backups/adapters/` (`...-voor-lage-druk.csv`), voegt de kolom
`drukklasse` toe (bestaande artikelen = `hoog`) en voegt de 1000 artikelen uit
`artikelnummers_lage_druk.csv` toe als `laag`. Opnieuw draaien vervangt alleen de lage-drukregels.
Terugzetten: de back-up terugkopiëren naar `data/adapters/artikelnummers_adapters.csv`.

`artikelnummers_lage_druk.csv` is gemaakt met `excel-naar-csv.py` uit `Malleable gr 32.xlsx`.

## Mapping Excel -> CSV

| Excel | CSV |
|---|---|
| Artcode | `artnr` |
| ArtOmschrijving | `omschrijving` (ongewijzigd) |
| (afgeleid uit artikelcode + omschrijving) | `familie_code` (serie, bv. `270`, `3000LBS-90`), `familie_naam`, `hoek`, `draadsoort_1/2`, `draadmaat_1/2`, `connectie_type_1/2` |
| - | `drukklasse` = `laag` |
| AG (32), Credcode, ArtcodeLev | niet overgenomen (geen kolom voor; ArtcodeLev is een leverancierscode, geen Parker-kruisverwijzing) |
| - | leeg: `cross_ref`, `tube_od_mm`, `tube_od_inch`, `bron_pagina` |

Afleidregels: `3000LBS-`/`150LBS-` of "NPT" in de omschrijving = `NPT/NPTF`, anders `BSPT`. Maat uit de artikelcode
(`11/4` = 1 1/4) met spoed erbij (`1 1/4-11`) zoals bij de bestaande artikelen. Uiteinden (buiten/binnen) volgen
de ISO/EN-code in de omschrijving (A1/G1/M2/B1/C1/U11/UA11/T1 = binnen, N8/G8 = buiten, A4/G4/N4/U12/UA12 = buiten + binnen)
of de Nederlandse tekst (BIN/BUI, MALE/FEM). Lasproducten (welding socket/cap, lasbochten, flenzen, gaspijp, ...)
hebben geen draad: draadsoort/-maat/-connectie blijven leeg. Materiaal (zwart/RVS) staat alleen in `omschrijving`.
