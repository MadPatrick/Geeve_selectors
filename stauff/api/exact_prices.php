<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

require_once dirname(__DIR__) . '/inc/db.php';

/**
 * Zoekt de verkoopprijs op in de Exact-database "005" (GRV_SalesItems,
 * artikelgroep 67) voor een lijst artikelnummers, in 1 databaseronde.
 *
 * De kolomnaam voor verkoopprijs is nog niet bevestigd (in tegenstelling
 * tot Locatie/Voorraad in /slangkaarten, die al zijn uitgezocht - zie
 * portal-README). Daarom wordt hier, net als tryColumnsQuery() in
 * slangkaarten/inc/queries.php, een lijst kandidaat-kolomnamen geprobeerd
 * totdat er 1 zonder SQL-fout teruggeeft; welke dat was staat in de
 * response ("column"), zodat dat bevestigd/aangepast kan worden. Lukt
 * geen van de kandidaten, dan blijft de prijs leeg (geen foutmelding) -
 * de rest van de configurator blijft normaal werken.
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
$params = ['groep' => STAUFF_ITEM_GROUP];
foreach ($codes as $index => $code) {
    $placeholders[] = ":code{$index}";
    $params["code{$index}"] = $code;
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
        $stmt->execute($params);
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

echo json_encode([
    'ok' => true,
    'prices' => $prices,
    'column' => $usedColumn,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
