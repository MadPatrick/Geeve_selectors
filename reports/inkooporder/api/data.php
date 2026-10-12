<?php

declare(strict_types=1);

/**
 * Inkooporder naar Excel: voert dbo.SP_Alle_Orders_met_regelsplus_inkoop (database Exactrapportage) uit voor één ordernummer.
 * Een aantal kolommen wordt weggelaten; de debiteurnaam gaat naar de kop van de pagina ("kop") in plaats van een kolom.
 */

require_once __DIR__ . '/../../inc.php';

const INKOOP_HIDDEN_COLUMNS = [
    'GrossWeight', 'verk_prPSL', 'Tverk_prPSL', 'ink_prPSL', 'Tink_prPSL', 'gewicht_bi', 'Mat', 'serienr',
    'aantal_serienr', 'PakbonNr', 'debnr', 'magcode', 'uitgifte', 'inkordernj', 'ordernummer',
    'klartnr', 'landbest', 'totaal_bruto_prijs_inclbtw', 'prijslijst', 'besteld_door', 'del_City', 'totaal_bruto_prijs',
];

/** Kolommen die niet in de tabel maar in de kop van de pagina staan: kolom => label. */
const INKOOP_HEADER_FIELDS = ['orderdatum' => 'Orderdatum', 'fiattering' => 'Fiattering'];

const INKOOP_RENAME = ['Aant_Back' => 'Backorder', 'aantal_besteld' => 'Besteld', 'aantal_te_leveren' => 'Te leveren'];

/** Unieke, gevulde waarden van een kolom (in volgorde van voorkomen), datums als dd-mm-jjjj. */
function inkoopDistinct(array $data, string $column): array
{
    $at = array_search($column, $data['columns'], true);
    $out = [];
    if ($at === false) {
        return $out;
    }
    foreach ($data['rows'] as $row) {
        $v = trim((string) $row[$at]);
        if ($v === '') {
            continue;
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m) === 1) {
            $v = "{$m[3]}-{$m[2]}-{$m[1]}";
        }
        $out[$v] = true;
    }
    return array_map('strval', array_keys($out));
}

reportApi(function (string $nummer): array {
    $data = reportCallProcedure('[Exactrapportage].[dbo].[SP_Alle_Orders_met_regelsplus_inkoop]', '@ordernummer', $nummer);

    $kop = [];
    $names = inkoopDistinct($data, 'debiteurnaam');
    if ($names !== []) {
        $kop[] = ['label' => '', 'value' => implode(', ', $names)];
    }
    foreach (INKOOP_HEADER_FIELDS as $column => $label) {
        $values = inkoopDistinct($data, $column);
        if ($values !== []) {
            $kop[] = ['label' => $label, 'value' => implode(', ', $values)];
        }
    }

    // Totaalorderbedrag staat als bedrag in euro in de kop (waarde van de eerste gevulde regel).
    $at = array_search('Totaalorderbedrag', $data['columns'], true);
    if ($at !== false) {
        foreach ($data['rows'] as $row) {
            if (is_numeric($row[$at])) {
                $kop[] = ['label' => 'Totaalorderbedrag', 'value' => $row[$at] + 0, 'type' => 'euro'];
                break;
            }
        }
    }

    $data = reportDropColumns($data, array_merge(
        INKOOP_HIDDEN_COLUMNS,
        ['debiteurnaam', 'UwReferentie', 'aantal_te_factureren', 'aantal_gefactureerd', 'pakbon_afg', 'afl_week', 'Totaalorderbedrag', 'Totaalorderbedrag_incl btw'],
        array_keys(INKOOP_HEADER_FIELDS)
    ));
    $data = reportMoveFirst($data, ['regel', 'artnr', 'artnm', 'levartnr', 'valuta', 'bruto_prijs', 'korting', 'netto_prijs']);
    $data['currency'] = ['bruto_prijs', 'netto_prijs']; // bedragen met euroteken
    $data['percent'] = ['korting'];
    $data['columns'] = array_map(fn ($c) => INKOOP_RENAME[$c] ?? $c, $data['columns']);
    $data['kop'] = $kop;
    return $data;
});
