<?php

declare(strict_types=1);

// Beveiligde tegel (Config > Tegels): zonder ontgrendelen geen gegevens.
require_once __DIR__ . '/../../../shared/tiles.php';
tileApiGuard('stickers-productie');

/**
 * Productiestickers per verkooporder: de productieorderregels van één verkooporder uit de stored procedure
 * [005_Data_Geeve].[dbo].[SP_productie_label] @ordernr (dezelfde als de oude rapportbouwer), in de volgorde van de procedure.
 * Zelfde SQL Server en inloggegevens (via Config) als /stauff en de rapporten.
 */

require_once __DIR__ . '/../../../reports/inc.php';

/**
 * Veld => mogelijke kolomnamen uit de procedure (vergeleken zonder hoofdletters, spaties en underscores), zodat zowel
 * "ARTIKEL OMSCHRIJVING" als "PO_ARTIKEL_OMSCHRIJVING" werkt.
 */
const PRODUCTIE_COLUMNS = [
    'ordernr' => ['ordernr', 'poordernr'],
    'project' => ['moederproject', 'pomoederproject'],
    'debiteur' => ['orddebtorname', 'podebtorname', 'debtorname'],
    'locatie' => ['locatie', 'powarehouselocation', 'warehouselocation'],
    'looplocatie' => ['looplocatie', 'polooplocatie'],
    'artikel' => ['artikel', 'poartikel'],
    'omschrijving' => ['artikelomschrijving', 'poartikelomschrijving'],
    'aantal' => ['aantal', 'poprodtot', 'prodtot'],
];

reportApi(function (string $nummer): array {
    $data = reportCallProcedure('[005_Data_Geeve].[dbo].[SP_productie_label]', '@ordernr', $nummer);

    $key = fn (string $name): string => strtolower(str_replace([' ', '_'], '', $name));
    $index = [];
    foreach ($data['columns'] as $i => $name) {
        $index[$key($name)] = $i;
    }
    $pick = [];
    foreach (PRODUCTIE_COLUMNS as $field => $names) {
        foreach ($names as $name) {
            if (isset($index[$name])) {
                $pick[$field] = $index[$name];
                break;
            }
        }
        if (!isset($pick[$field]) && $field !== 'project') {
            throw new RuntimeException('SP_productie_label geeft geen kolom voor "' . $field . '" terug (wel: ' . implode(', ', $data['columns']) . ').');
        }
    }

    $rows = [];
    foreach ($data['rows'] as $row) {
        $out = [];
        foreach (array_keys(PRODUCTIE_COLUMNS) as $field) {
            $v = isset($pick[$field]) ? $row[$pick[$field]] : null;
            $out[$field] = $v === null ? '' : trim((string) $v);
        }
        $rows[] = $out;
    }

    return ['rows' => $rows];
});
