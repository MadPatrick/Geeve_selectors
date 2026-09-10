# STAUFF Beugelconfigurator

Eerste webversie van de STAUFF selector op basis van `data/stauff_selector.csv`.

## Installatie

1. Kopieer de volledige map naar een PHP-webserver (Apache/Nginx + PHP).
2. Zorg dat PHP `fgetcsv()` mag gebruiken en dat de map `data/` leesbaar is.
3. Open `index.php` in de browser.

Er is geen database nodig. Het PHP-endpoint `api/stauff.php` leest de CSV en stuurt JSON naar de JavaScript-configurator.

## Selectielogica

- Locatie 1: Lasplaat / Lasplaat (hoek) / Glijmoer
- Locatie 2: Beugel
- Locatie 3: Borgplaat
- Locatie 4: Dekplaat
- Locatie 5: Bout (stapelbout, inbusbout of zeskantbout)
- Locatie 6: gekozen materiaalcode W...

De diameter is een type-ahead veld en gebruikt uitsluitend diameters die in de beugelregels voorkomen.
Na het kiezen van een beugel worden bouwgroep en serie gebruikt om de overige posities te filteren.

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
