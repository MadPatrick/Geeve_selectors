<?php

declare(strict_types=1);

/**
 * Order of Offerte naar Excel: voert dbo.SP_orders_naar_Excel (database 005_Data_Geeve) uit voor één order-/offertenummer
 * en toont dezelfde kolommen (en namen) als de Excel-sjabloon.
 */

require_once __DIR__ . '/../../inc.php';

const ORDER_OFFERTE_COLUMNS = [
    'rgls_regel' => 'Line',
    'rgls_esr_aantal' => 'Amount',
    'rgls_artcode' => 'Partnumber',
    'rgls_oms45' => 'Description',
    'rgls_prijs83' => 'Price',
    'rgls_korting' => 'Discount',
    'rgls_prijs_n' => 'Price nett',
    'rgls_aant_gelev' => 'Delivered',
    'rgls_aant_fakt' => 'Invoiced',
    'rgls_statistnr' => 'rgls_statistnr',
    'rgls_landoorspr' => 'Country of Origin',
    'rgls_bdr_ev_ed_val' => 'Total Netto VAT',
    'rgls_bdr_vat_val' => 'rgls_bdr_vat_val',
    'rgls_bdr_val' => 'Total Nett',
    'rgls_gewicht_bi' => 'Weight',
];

/**
 * Vult lege velden van de orderregel aan met de artikelkaart (Items in database 005): statistisch nummer. In Exact staan deze bij jullie vaak alleen op het artikel en niet op de orderregel.
 *
 * @param array{columns: list<string>, numeric: list<bool>, rows: list<list<string|int|float|null>>} $data
 * @return array{columns: list<string>, numeric: list<bool>, rows: list<list<string|int|float|null>>}
 */
function fillFromItems(array $data): array
{
    $col = array_flip($data['columns']);
    foreach (['Partnumber', 'rgls_statistnr'] as $need) {
        if (!isset($col[$need])) {
            return $data;
        }
    }

    $codes = [];
    foreach ($data['rows'] as $row) {
        $code = trim((string) $row[$col['Partnumber']]);
        if ($code !== '') {
            $codes[$code] = true;
        }
    }
    if ($codes === []) {
        return $data;
    }

    $pdo = reportConnection();
    $items = [];
    foreach (array_chunk(array_keys($codes), 500) as $chunk) {
        $stmt = $pdo->prepare('SET NOCOUNT ON; SELECT RTRIM(ItemCode) AS c, RTRIM(StatisticalNumber) AS s FROM Items WHERE ItemCode IN (' . implode(',', array_fill(0, count($chunk), '?')) . ')');
        $stmt->execute($chunk);
        foreach ($stmt->fetchAll() as $r) {
            $items[(string) $r['c']] = $r;
        }
    }

    foreach ($data['rows'] as &$row) {
        $item = $items[trim((string) $row[$col['Partnumber']])] ?? null;
        if ($item === null) {
            continue;
        }
        if (trim((string) $row[$col['rgls_statistnr']]) === '' && trim((string) $item['s']) !== '') {
            $row[$col['rgls_statistnr']] = trim((string) $item['s']);
        }
    }
    unset($row);

    return $data;
}

reportApi(fn (string $nummer) => fillFromItems(reportCallProcedure('[005_Data_Geeve].[dbo].[SP_orders_naar_Excel]', '@ordernr', $nummer, ORDER_OFFERTE_COLUMNS)));
