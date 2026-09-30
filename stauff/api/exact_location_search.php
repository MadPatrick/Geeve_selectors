<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

require_once dirname(__DIR__) . '/inc/db.php';

/**
 * Live zoekfunctie op artikelnummer voor 1 samenstellingslocatie (1, 3, 4
 * of 5 - zie de config-cog per locatierij in index.php/selector.js), als
 * alternatief voor de CSV-gedreven candidatesForPosition() in
 * assets/selector.js.
 *
 * $prefixes ("SP;SPAL;SPV", ; -gescheiden) - artikelen moeten met 1 van
 * deze voorvoegsels BEGINNEN (LIKE 'PREFIX%'), niet fuzzy/contains: de
 * gebruiker configureert hiermee zelf welke artikelcode-prefixes bij die
 * locatie horen (vroeger hardcoded in candidatesForPosition()).
 *
 * $material (optioneel, bijv. "W1") - artikelen moeten deze materiaalcode
 * ook BEVATTEN (LIKE '%MATERIAAL%'), overeenkomend met de op dat moment
 * gekozen Materiaalcode (locatie 6).
 */
const STAUFF_ITEM_GROUP = '67';

$prefixesParam = trim((string) ($_GET['prefixes'] ?? ''));
$material = trim((string) ($_GET['material'] ?? ''));

if ($prefixesParam === '') {
    echo json_encode(['ok' => true, 'count' => 0, 'rows' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$prefixes = array_values(array_unique(array_filter(
    array_map('trim', explode(';', $prefixesParam)),
    static fn(string $prefix): bool => $prefix !== ''
)));
if ($prefixes === []) {
    echo json_encode(['ok' => true, 'count' => 0, 'rows' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo = getPdoConnection();
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}

$prefixConditions = [];
$params = ['groep' => STAUFF_ITEM_GROUP];
foreach ($prefixes as $index => $prefix) {
    // LIKE-jokertekens (%, _) in een door de gebruiker ingevoerd voorvoegsel
    // moeten als letterlijke tekens behandeld worden, anders kan een
    // toevallige % of _ de query laten afwijken van "begint met".
    $escapedPrefix = str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $prefix);
    $prefixConditions[] = "ItemCode LIKE :prefix{$index}";
    $params["prefix{$index}"] = $escapedPrefix . '%';
}

$sql = 'SELECT TOP 50 ItemCode FROM GRV_SalesItems ' .
    'WHERE [Item Group] = :groep AND (' . implode(' OR ', $prefixConditions) . ')';

if ($material !== '') {
    $escapedMaterial = str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $material);
    $sql .= ' AND ItemCode LIKE :material';
    $params['material'] = '%' . $escapedMaterial . '%';
}

$sql .= ' ORDER BY ItemCode';

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'ok' => true,
    'count' => count($rows),
    'rows' => $rows,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
