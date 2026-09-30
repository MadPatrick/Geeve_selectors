<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

require_once dirname(__DIR__) . '/inc/db.php';

/**
 * Live, fuzzy zoekfunctie op het artikelnummer (ItemCode) in de
 * Exact-database "005", beperkt tot artikelgroep 67 (Stauff-beugels en
 * -toebehoren, zie portal-README "Database-koppeling Exact"). Kiest de
 * beugel zelf (zie assets/selector.js, ui.diameterInput) - de rest van de
 * wizard haalt zijn kandidaat-artikelen uit api/exact_location_search.php,
 * eveneens rechtstreeks uit Exact (geen CSV meer, zie stauff/README.md).
 *
 * "Fuzzy": koppeltekens/spaties/punten/komma's/underscores worden zowel
 * uit de zoekterm als uit ItemCode verwijderd vóór het vergelijken -
 * zelfde patroon als tryColumnsFuzzyLikeQuery() in
 * slangkaarten/inc/queries.php, zodat bijv. "1680" ook "10168-0" vindt.
 *
 * Uitgesloten: artikelen waarvan het artikelnummer niet met een cijfer
 * begint (bijv. lasplaat/dekplaat-codes als "SP...", "GD...", "DPAS...")
 * - een diameter-zoekopdracht mag alleen beugelachtige, numeriek beginnende
 * artikelcodes opleveren.
 *
 * [Item Description] wordt meegegeven zodat de frontend die kan tonen
 * zodra de gebruiker een artikel uit de resultatenlijst kiest (de lijst
 * zelf toont alleen het artikelnummer, zie assets/selector.js).
 */
const STAUFF_ITEM_GROUP = '67';

function normalizeFuzzyTerm(string $term): string
{
    $normalized = preg_replace('/[-\s.,_]+/', '', $term) ?? $term;
    return $normalized === '' ? $term : $normalized;
}

$searchTerm = trim((string) ($_GET['q'] ?? ''));
if ($searchTerm === '') {
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

$normalizedTerm = normalizeFuzzyTerm($searchTerm);

try {
    $stmt = $pdo->prepare(
        'SELECT TOP 50 ItemCode, [Item Description] FROM GRV_SalesItems ' .
        'WHERE [Item Group] = :groep ' .
        "AND ItemCode LIKE '[0-9]%' " .
        "AND REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(ItemCode, '-', ''), ' ', ''), '.', ''), ',', ''), '_', '') " .
        'LIKE :term ' .
        'ORDER BY ItemCode'
    );
    $stmt->execute([
        'groep' => STAUFF_ITEM_GROUP,
        'term' => '%' . $normalizedTerm . '%',
    ]);
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
