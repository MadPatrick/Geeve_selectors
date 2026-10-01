<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

require_once dirname(__DIR__) . '/inc/db.php';

/**
 * Zoekt de verkoopprijs + vrije voorraad op in de Exact-database "005"
 * (artikelgroep 67) voor een lijst artikelnummers, in enkele
 * databaserondes (niet 1 per artikel).
 *
 * De kolomnaam voor verkoopprijs is nog niet bevestigd (in tegenstelling
 * tot Voorraad, die al is uitgezocht voor /slangkaarten - zie
 * findArtikelExactDataBatch() in slangkaarten/index.php). Daarom wordt
 * hier, net als tryColumnsQuery() in slangkaarten/inc/queries.php, een
 * lijst kandidaat-kolomnamen geprobeerd totdat er 1 zonder SQL-fout
 * teruggeeft; welke dat was staat in de response ("column"), zodat dat
 * bevestigd/aangepast kan worden. Lukt geen van de kandidaten, dan blijft
 * de prijs leeg (geen foutmelding) - de rest van de configurator blijft
 * normaal werken.
 *
 * Voorraad gebruikt dezelfde, al bevestigde formule als
 * findArtikelExactDataBatch() in slangkaarten/index.php: StockBalances is
 * een mutatielog, de vrije voorraad is het laagste van de FreeStock-som en
 * de Quantity-som t/m vandaag, met een vloer op 0.
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
    echo json_encode(['ok' => false, 'error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
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
            "SELECT ItemCode, [{$column}] AS Price FROM GRV_SalesItems " .
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
    $stmt = $pdo->prepare(
        'WITH agg AS (' .
        'SELECT ItemCode, ' .
        'SUM(CASE WHEN [Date] <= GETDATE() THEN Quantity END) AS CurrentQuantity, ' .
        'SUM(CASE WHEN [Date] <= GETDATE() THEN FreeStock END) AS FreeQuantity ' .
        'FROM StockBalances WITH (NOLOCK) ' .
        "WHERE ItemCode IN ({$inClause}) " .
        'GROUP BY ItemCode' .
        ') SELECT ItemCode, ' .
        'CASE WHEN FreeQuantity > CurrentQuantity ' .
        'THEN (CASE WHEN CurrentQuantity < 0 THEN 0 ELSE CurrentQuantity END) ' .
        'ELSE (CASE WHEN FreeQuantity < 0 THEN 0 ELSE FreeQuantity END) END AS VrijeVoorraad ' .
        'FROM agg'
    );
    $stmt->execute($codeParams);
    while (($row = $stmt->fetch()) !== false) {
        $stock[(string) $row['ItemCode']] = $row['VrijeVoorraad'] !== null ? trim((string) $row['VrijeVoorraad']) : '';
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
