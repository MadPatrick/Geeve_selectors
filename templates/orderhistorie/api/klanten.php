<?php

declare(strict_types=1);

// Tegel-toegang (Config > Tegels): zonder ontgrendelen geen gegevens.
require_once __DIR__ . '/../../../shared/tiles.php';
tileApiGuard('orderhistorie');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

require_once __DIR__ . '/../../../reports/inc.php';

/** Zoekt debiteuren op nummer of naam. GET ?q=<tekst>  ->  {"ok":true,"rows":[{"debnr","naam"}]} */

$words = array_values(array_filter(preg_split('/\s+/', trim((string) ($_GET['q'] ?? ''))) ?: [], static fn (string $w): bool => $w !== ''));
$words = array_slice($words, 0, 5);
if ($words === []) {
    echo json_encode(['ok' => true, 'rows' => []]);
    exit;
}

try {
    $where = ['c.debnr IS NOT NULL', "LTRIM(RTRIM(c.debnr)) <> ''"];
    $params = [];
    foreach ($words as $word) {
        // % en _ in de zoekterm letterlijk nemen.
        $like = '%' . str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $word) . '%';
        $where[] = '(c.cmp_name LIKE ? OR LTRIM(RTRIM(c.debnr)) LIKE ?)';
        $params[] = $like;
        $params[] = $like;
    }
    // Een getypt nummer: debiteuren waarvan het nummer ermee begint eerst, van laag naar hoog; daarna de rest op naam.
    $order = 'ORDER BY c.cmp_name';
    if (ctype_digit($words[0])) {
        $order = 'ORDER BY CASE WHEN LTRIM(RTRIM(c.debnr)) LIKE ? THEN 0 ELSE 1 END, TRY_CAST(LTRIM(RTRIM(c.debnr)) AS bigint), c.cmp_name';
        $params[] = $words[0] . '%';
    }
    $stmt = reportConnection()->prepare(
        'SET NOCOUNT ON; SELECT TOP 20 LTRIM(RTRIM(c.debnr)) AS debnr, c.cmp_name AS naam FROM cicmpy c WHERE ' . implode(' AND ', $where) . ' ' . $order
    );
    $stmt->execute($params);
    $rows = array_map(static fn (array $r): array => [
        'debnr' => (string) $r['debnr'],
        'naam' => trim((string) $r['naam']),
    ], $stmt->fetchAll());
    echo json_encode(['ok' => true, 'rows' => $rows], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => geeveFoutTekst($e)], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
}
