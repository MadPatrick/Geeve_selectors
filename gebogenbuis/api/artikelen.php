<?php

declare(strict_types=1);

// Beveiligde tegel (Config > Tegels): zonder ontgrendelen geen gegevens.
require_once __DIR__ . '/../../shared/tiles.php';
tileApiGuard('gebogenbuis');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

require_once __DIR__ . '/../inc.php';

/**
 * Zoekt verkoopartikelen op artikelnummer of omschrijving, met bruto verkoopprijs en vrije voorraad.
 * GET ?q=<tekst>  ->  {"ok":true,"priceColumn":"...","rows":[{"code","omschrijving","groep","prijs","voorraad"}]}
 */

$words = gbSearchWords((string) ($_GET['q'] ?? ''));
if ($words === []) {
    echo json_encode(['ok' => true, 'rows' => []]);
    exit;
}

try {
    $pdo = getPdoConnection();
    $priceColumn = gbPriceColumn($pdo);
    $where = [];
    $params = [];
    foreach ($words as $word) {
        $where[] = '(' . gbStripSql('ItemCode') . ' LIKE ? OR [Item Description] LIKE ?)';
        $params[] = '%' . (preg_replace('/[-\s.,_]+/', '', $word) ?: $word) . '%';
        $params[] = '%' . $word . '%';
    }
    $stmt = $pdo->prepare(
        'SELECT TOP 20 ItemCode, [Item Description] AS Omschrijving, [Item Group] AS Groep' .
        ($priceColumn !== null ? ', [' . str_replace(']', ']]', $priceColumn) . '] AS Prijs' : '') .
        ' FROM GRV_SalesItems WHERE ' . implode(' AND ', $where) . ' ORDER BY ItemCode'
    );
    $stmt->execute($params);
    $found = $stmt->fetchAll();

    $codes = array_map(static fn (array $r): string => trim((string) $r['ItemCode']), $found);
    try {
        $stock = gbFreeStock($pdo, $codes);
    } catch (Throwable $e) {
        $stock = []; // voorraad blijft leeg; artikel en prijs werken gewoon door
    }

    $rows = [];
    foreach ($found as $r) {
        $code = trim((string) $r['ItemCode']);
        $rows[] = [
            'code' => $code,
            'omschrijving' => trim((string) $r['Omschrijving']),
            'groep' => trim((string) $r['Groep']),
            'prijs' => isset($r['Prijs']) && $r['Prijs'] !== null ? (float) $r['Prijs'] : null,
            'voorraad' => $stock[$code] ?? '',
        ];
    }
    echo json_encode(['ok' => true, 'priceColumn' => $priceColumn, 'rows' => $rows], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
}
