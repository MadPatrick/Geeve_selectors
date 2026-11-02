<?php

declare(strict_types=1);

// Beveiligde tegel (Config > Tegels): zonder ontgrendelen geen gegevens.
require_once __DIR__ . '/../../../shared/tiles.php';
tileApiGuard('export-info');

/**
 * Export info: voert dbo.SP_orders_naar_Excel_export (database 005_Data_Geeve) uit voor één ordernummer, zoals het Excel-sjabloon
 * "Exact Export info Order" (Power Query "export"). Eén regel per orderregel; de weergaven orders/Deme/NOV worden in de browser opgebouwd.
 */

require_once __DIR__ . '/../../../reports/inc.php';

const EXPORT_INFO_COLUMNS = [
    'kop_ordernr' => 'Ordernumber',
    'rgls_regel' => 'Line number',
    'Qty' => 'Quantity',
    'Artcode' => 'Artcode',
    'Item description' => 'Item description',
    'Collo nr' => 'Collo nr',
    'Collo Type' => 'Collo Type',
    'Gross weight' => 'Weight',
    'Length (cm)' => 'Length (cm)',
    'Width (cm)' => 'Width (cm)',
    'Height (cm)' => 'Height (cm)',
    'netPrice per piece' => 'Unit Price',
    'Total price' => 'Total price',
    'HS code' => 'HS code',
    'Country of Origin' => 'Country of Origin',
    'kop_docnumber' => 'Customer Reference',
];

reportApi(function (string $nummer): array {
    $raw = reportCallProcedure('[005_Data_Geeve].[dbo].[SP_orders_naar_Excel_export]', '', $nummer);
    $customer = reportFirstValue($raw, 'kop_ord_debtor_name');
    $data = reportSelect($raw, EXPORT_INFO_COLUMNS);

    // Zoals in de Power Query: één regel per regelnummer (de eerste).
    $line = array_search('Line number', $data['columns'], true);
    $seen = [];
    $rows = [];
    foreach ($data['rows'] as $row) {
        $key = (string) $row[$line];
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $rows[] = $row;
    }
    $data['rows'] = $rows;

    // Land van herkomst: staat bij jullie op het artikel (Items.UserField_03, label "Land van herkomst"); de waarde op de
    // orderregel is vaak leeg of niet goed. Artikelwaarde gaat voor, de waarde uit de procedure is de terugval.
    $art = array_search('Artcode', $data['columns'], true);
    $coo = array_search('Country of Origin', $data['columns'], true);
    $codes = [];
    foreach ($data['rows'] as $row) {
        $code = trim((string) $row[$art]);
        if ($code !== '') {
            $codes[$code] = true;
        }
    }
    $origin = [];
    if ($codes !== []) {
        $pdo = reportConnection();
        foreach (array_chunk(array_keys($codes), 500) as $chunk) {
            $in = implode(',', array_fill(0, count($chunk), '?'));
            $stmt = $pdo->prepare("SET NOCOUNT ON; SELECT RTRIM(ItemCode) AS c, RTRIM(UserField_03) AS l FROM Items WHERE ItemCode IN ({$in})");
            $stmt->execute($chunk);
            foreach ($stmt->fetchAll() as $r) {
                $origin[trim((string) $r['c'])] = trim((string) $r['l']);
            }
        }
    }
    foreach ($data['rows'] as &$row) {
        $fromItem = $origin[trim((string) $row[$art])] ?? '';
        $row[$coo] = $fromItem !== '' ? $fromItem : trim((string) $row[$coo]);
    }
    unset($row);

    $data['customer'] = $customer;
    return $data;
});
