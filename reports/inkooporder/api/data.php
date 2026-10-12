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

reportApi(function (string $nummer): array {
    $data = reportCallProcedure('[Exactrapportage].[dbo].[SP_Alle_Orders_met_regelsplus_inkoop]', '@ordernummer', $nummer);

    $kop = null;
    $at = array_search('debiteurnaam', $data['columns'], true);
    if ($at !== false) {
        foreach ($data['rows'] as $row) {
            if (trim((string) $row[$at]) !== '') {
                $kop = trim((string) $row[$at]);
                break;
            }
        }
    }

    $data = reportDropColumns($data, array_merge(INKOOP_HIDDEN_COLUMNS, ['debiteurnaam']));
    $data = reportMoveFirst($data, ['regel', 'artnr', 'artnm', 'levartnr']);
    $data['kop'] = $kop;
    return $data;
});
