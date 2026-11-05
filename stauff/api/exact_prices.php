<?php

declare(strict_types=1);

// Beveiligde tegel (Config > Tegels): zonder ontgrendelen geen gegevens.
require_once __DIR__ . '/../../shared/tiles.php';
tileApiGuard('stauff');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

require_once dirname(__DIR__) . '/inc/db.php';

/**
 * Zoekt de verkoopprijs + vrije voorraad op in de Exact-database "005"
 * (artikelgroep 67) voor een lijst artikelnummers, in enkele
 * databaserondes (niet 1 per artikel).
 *
 * De kolomnaam voor verkoopprijs is nog niet bevestigd (in tegenstelling
 * tot Voorraad, die al is uitgezocht voor /slkprinten - zie
 * findArtikelExactDataBatch() in slkprinten/index.php). Daarom wordt
 * hier, net als tryColumnsQuery() in slkprinten/inc/queries.php, een
 * lijst kandidaat-kolomnamen geprobeerd totdat er 1 zonder SQL-fout
 * teruggeeft; welke dat was staat in de response ("column"), zodat dat
 * bevestigd/aangepast kan worden. Lukt geen van de kandidaten, dan blijft
 * de prijs leeg (geen foutmelding) - de rest van de configurator blijft
 * normaal werken.
 *
 * Voorraad = beschikbare voorraad (Patrick, 16-10-2026): de voorraad
 * (som van Quantity in StockBalances t/m vandaag, alle magazijnen) min het
 * nog niet geleverde deel van open verkooporders (orkrg.ord_soort = 'V').
 * Mag negatief zijn: 320 AL had 12 op voorraad en 22 in order, dus -10.
 * FreeStock in StockBalances is gelijk aan Quantity en trekt orders niet
 * af; offertes (soort Q) en inkooporders tellen niet mee.
 */
const STAUFF_ITEM_GROUP = '67';

const PRICE_COLUMN_CANDIDATES = [
    'Sales Price',
    'SalesPrice',
    'Price 1',
    'Price1',
    'Price',
    'Verkoopprijs',
    'Prijs',
];

$codesParam = trim((string) ($_GET['codes'] ?? ''));
if ($codesParam === '') {
    echo json_encode(['ok' => true, 'prices' => [], 'column' => null], JSON_UNESCAPED_UNICODE);
    exit;
}

$codes = array_values(array_unique(array_filter(
    array_map('trim', explode(';', $codesParam)),
    static fn(string $code): bool => $code !== ''
)));
if ($codes === []) {
    echo json_encode(['ok' => true, 'prices' => [], 'column' => null], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo = getPdoConnection();
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => geeveFoutTekst($exception)], JSON_UNESCAPED_UNICODE);
    exit;
}

$placeholders = [];
$codeParams = [];
foreach ($codes as $index => $code) {
    $placeholders[] = ":code{$index}";
    $codeParams["code{$index}"] = $code;
}
$inClause = implode(', ', $placeholders);

$prices = [];
$usedColumn = null;
foreach (PRICE_COLUMN_CANDIDATES as $column) {
    try {
        $stmt = $pdo->prepare(
            "SELECT ItemCode, " . geeveSqlNaam($column) . " AS Price FROM GRV_SalesItems " .
            "WHERE [Item Group] = :groep AND ItemCode IN ({$inClause})"
        );
        $stmt->execute(['groep' => STAUFF_ITEM_GROUP] + $codeParams);
        $rows = $stmt->fetchAll();
        $usedColumn = $column;
        foreach ($rows as $row) {
            $prices[(string) $row['ItemCode']] = $row['Price'] !== null ? (string) $row['Price'] : '';
        }
        break;
    } catch (Throwable $exception) {
        continue;
    }
}

$stock = [];
try {
    // Tweede set parameters: pdo_sqlsrv staat hergebruik van een benoemde parameter niet toe.
    $orderPlaceholders = [];
    $orderParams = [];
    foreach ($codes as $index => $code) {
        $orderPlaceholders[] = ":ocode{$index}";
        $orderParams["ocode{$index}"] = $code;
    }
    $orderInClause = implode(', ', $orderPlaceholders);
    $stmt = $pdo->prepare(
        'WITH st AS (' .
        'SELECT ItemCode, SUM(CASE WHEN [Date] <= GETDATE() THEN Quantity END) AS Voorraad ' .
        'FROM StockBalances WITH (NOLOCK) ' .
        "WHERE ItemCode IN ({$inClause}) " .
        'GROUP BY ItemCode' .
        '), oo AS (' .
        'SELECT s.artcode AS ItemCode, SUM(s.esr_aantal - s.aant_gelev) AS OpenOrders ' .
        'FROM orsrg s WITH (NOLOCK) JOIN orkrg k WITH (NOLOCK) ON k.ordernr = s.ordernr ' .
        "WHERE k.ord_soort = 'V' AND s.esr_aantal > s.aant_gelev AND s.artcode IN ({$orderInClause}) " .
        'GROUP BY s.artcode' .
        ') SELECT st.ItemCode, ISNULL(st.Voorraad, 0) - ISNULL(oo.OpenOrders, 0) AS Beschikbaar ' .
        'FROM st LEFT JOIN oo ON oo.ItemCode = st.ItemCode'
    );
    $stmt->execute($codeParams + $orderParams);
    while (($row = $stmt->fetch()) !== false) {
        $stock[trim((string) $row['ItemCode'])] = $row['Beschikbaar'] !== null ? trim((string) $row['Beschikbaar']) : '';
    }
} catch (Throwable $exception) {
    // Voorraad blijft leeg (geen foutmelding) - prijs werkt gewoon door.
}

echo json_encode([
    'ok' => true,
    'prices' => $prices,
    'stock' => $stock,
    'column' => $usedColumn,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
