<?php

declare(strict_types=1);

/**
 * Picklijstaantallen van slangkaarten: één bron voor de picklijst van Slangkaarten printen (slkprinten/index.php) en de
 * stickers per productieorder (stickers/productie), zodat het aantal op de sticker gelijk is aan de picklijst.
 * Vereist inc/queries.php (pick()).
 */

const SLANGTYPE_CANDIDATES = ['SlangType', 'Slang type', 'Slangtype', 'Type'];
const LENGTE_CANDIDATES = ['Lengte', 'Lengte / Prijs', 'Lengte/Prijs'];
const AANTAL_CANDIDATES = ['Aantal', 'Aantal slangen', 'AantalSlangen'];

// Losse "extra artikelen" op de order zelf (max. 2 slots) - géén
// koppelonderdeel van zijde A/B, dus een eigen kader op de kaart. Let op
// de kolomnaam-typo "AantaExtraArtikel" (zonder "l") voor slot 1 - staat
// zo echt in de database (bevestigd via INFORMATION_SCHEMA), slot 2 heet
// wel "AantalExtraArtikel2".
const EXTRA_ARTIKEL_SLOTS = [
    ['artikel' => ['ExtraArtikel'], 'aantal' => ['AantaExtraArtikel', 'AantalExtraArtikel']],
    ['artikel' => ['ExtraArtikel2'], 'aantal' => ['AantalExtraArtikel2']],
];

// Bevestigd via een echte SELECT * op beide tabellen: het artikel/
// onderdeelnummer staat in "Zijde AA" (tabel Zijde A) resp. "Zijde BB"
// (tabel Zijde B) - bijv. "10217-48-48RVS". "Coupling A"/"Coupling B"
// bleken in de praktijk altijd leeg, en "koppeling" bevat een letterlijke
// "0"-placeholder (geen echt artikelnummer) - allebei laten staan als
// fallback voor het geval een andere order ze wel gebruikt.
const ARTIKELNUMMER_CANDIDATES = ['Zijde AA', 'Zijde BB', 'Coupling A', 'Coupling B', 'koppeling', 'Artikelnummer', 'ArtikelNr', 'Artikel'];
const QTY_CANDIDATES = ['Aantal_A', 'Aantal_B', 'Qty', 'Aantal'];

/**
 * Totaal per artikelnummer over alle kaarten: de slang (lengte in meters x aantal slangen), de koppelonderdelen van zijde A/B
 * en de extra artikelen. Qty/Aantal per onderdeel staat per 1 slang, dus vermenigvuldigd met "Aantal slangen" van die kaart.
 *
 * @param list<array<string, mixed>> $hoseCards uitkomst van findHoseCardsByKeys()
 * @return array<string, float> artikelnummer => totaal aantal
 */
function picklijstAantallen(array $hoseCards): array
{
    $items = [];

    $addItem = static function (array &$items, string $artikel, string $aantalRaw, float $aantalSlangen): void {
        if ($artikel === '') {
            return;
        }

        $normalized = str_replace(',', '.', trim($aantalRaw));
        $aantal = is_numeric($normalized) ? (float) $normalized : 0.0;
        $items[$artikel] = ($items[$artikel] ?? 0.0) + $aantal * $aantalSlangen;
    };

    foreach ($hoseCards as $card) {
        $aantalSlangenRaw = str_replace(',', '.', trim(pick($card['row'], AANTAL_CANDIDATES)));
        $aantalSlangen = is_numeric($aantalSlangenRaw) ? (float) $aantalSlangenRaw : 1.0;

        // De slang zelf staat niet bij de koppelonderdelen: "aantal" is hier Lengte (per 1 slang) x aantal slangen.
        // Rijen uit de app hebben de lengte voor tabel en afdruk in mm gekregen; de picklijst blijft in meters (LengteMeterApp).
        $lengteMeter = pick($card['row'], ['LengteMeterApp']);
        $addItem($items, pick($card['row'], SLANGTYPE_CANDIDATES), $lengteMeter !== '' ? $lengteMeter : pick($card['row'], LENGTE_CANDIDATES), $aantalSlangen);

        foreach (['sideA', 'sideB'] as $side) {
            foreach ($card[$side] as $componentRow) {
                $addItem($items, pick($componentRow, ARTIKELNUMMER_CANDIDATES), pick($componentRow, QTY_CANDIDATES), $aantalSlangen);
            }
        }

        foreach (EXTRA_ARTIKEL_SLOTS as $slot) {
            $addItem($items, pick($card['row'], $slot['artikel']), pick($card['row'], $slot['aantal']), $aantalSlangen);
        }
    }

    return $items;
}
