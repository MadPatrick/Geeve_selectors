<?php

declare(strict_types=1);

/**
 * Order of Offerte naar Excel: voert dbo.SP_orders_naar_Excel (database 005_Data_Geeve) uit voor één order-/offertenummer
 * en toont dezelfde kolommen (en namen) als de Excel-sjabloon.
 */

require_once __DIR__ . '/../../inc.php';

/** Debiteurnummer waarop het land van herkomst per artikel is vastgelegd. */
const ORIGIN_DEBTOR = '35';

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
    'rgls_bdr_ev_ed_val' => 'Total Nett',
    'rgls_bdr_vat_val' => 'rgls_bdr_vat_val',
    'rgls_bdr_val' => 'Total incl. VAT',
    'rgls_gewicht_bi' => 'Weight',
];

/**
 * Vult lege velden van de orderregel aan met de artikelgegevens in database 005: statistisch nummer en nettogewicht
 * (Items) en land van herkomst (ItemAccounts van debiteur 35). Bij jullie staan deze vaak alleen op het artikel en niet op de orderregel.
 *
 * @param array{columns: list<string>, numeric: list<bool>, rows: list<list<string|int|float|null>>} $data
 * @return array{columns: list<string>, numeric: list<bool>, rows: list<list<string|int|float|null>>}
 */
function fillFromItems(array $data): array
{
    $col = array_flip($data['columns']);
    foreach (['Partnumber', 'rgls_statistnr', 'Country of Origin', 'Weight'] as $need) {
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
    $hasWeight = (int) $pdo->query("SELECT COUNT(*) FROM sys.columns WHERE object_id = OBJECT_ID('Items') AND name = 'NetWeight'")->fetchColumn() > 0;
    $weightSql = $hasWeight ? 'NetWeight' : 'NULL';
    $items = [];
    $origin = [];
    foreach (array_chunk(array_keys($codes), 500) as $chunk) {
        $in = implode(',', array_fill(0, count($chunk), '?'));
        $stmt = $pdo->prepare("SET NOCOUNT ON; SELECT RTRIM(ItemCode) AS c, RTRIM(StatisticalNumber) AS s, {$weightSql} AS w FROM Items WHERE ItemCode IN ({$in})");
        $stmt->execute($chunk);
        foreach ($stmt->fetchAll() as $r) {
            $items[(string) $r['c']] = $r;
        }
        // Land van herkomst staat op de artikelregel van debiteur 35 ("Debiteur-slangkaart") in Exact.
        $stmt = $pdo->prepare("SET NOCOUNT ON; SELECT RTRIM(ia.ItemCode) AS c, MAX(RTRIM(ia.CountryOfOrigin)) AS l FROM ItemAccounts ia INNER JOIN cicmpy cm ON cm.cmp_wwn = ia.AccountCode WHERE ia.ItemCode IN ({$in}) AND LTRIM(RTRIM(cm.debcode)) = '" . ORIGIN_DEBTOR . "' AND ia.CountryOfOrigin IS NOT NULL AND RTRIM(ia.CountryOfOrigin) <> '' GROUP BY RTRIM(ia.ItemCode)");
        $stmt->execute($chunk);
        foreach ($stmt->fetchAll() as $r) {
            $origin[(string) $r['c']] = trim((string) $r['l']);
        }
    }

    foreach ($data['rows'] as &$row) {
        $code = trim((string) $row[$col['Partnumber']]);
        $item = $items[$code] ?? null;
        if ($item !== null && trim((string) $row[$col['rgls_statistnr']]) === '' && trim((string) $item['s']) !== '') {
            $row[$col['rgls_statistnr']] = trim((string) $item['s']);
        }
        if ($item !== null && (float) $row[$col['Weight']] == 0.0 && is_numeric($item['w']) && (float) $item['w'] > 0) {
            $row[$col['Weight']] = $item['w'] + 0;
        }
        if (isset($origin[$code])) {
            $row[$col['Country of Origin']] = $origin[$code];
        }
    }
    unset($row);

    return $data;
}

reportApi(function (string $nummer): array {
    $data = fillFromItems(reportCallProcedure('[005_Data_Geeve].[dbo].[SP_orders_naar_Excel]', '@ordernr', $nummer, ORDER_OFFERTE_COLUMNS));
    $data['currency'] = ['Price', 'Price nett', 'Total Nett', 'Total incl. VAT']; // bedragen in euro tonen
    return $data;
});
