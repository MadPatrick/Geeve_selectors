<?php

declare(strict_types=1);

/**
 * Export info: voert dbo.SP_orders_naar_Excel_export (database 005_Data_Geeve) uit voor één ordernummer, zoals het Excel-sjabloon
 * "Exact Export info Order" (Power Query "export"). Eén regel per orderregel; de weergaven orders/Deme/NOV worden in de browser opgebouwd.
 */

require_once __DIR__ . '/../../inc.php';

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
    $data['customer'] = $customer;
    return $data;
});
