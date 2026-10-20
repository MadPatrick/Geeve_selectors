<?php

declare(strict_types=1);

// Beveiligde tegel (Config > Tegels): zonder ontgrendelen geen gegevens.
require_once __DIR__ . '/../../shared/tiles.php';
tileApiGuard('gebogenbuis');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

require_once __DIR__ . '/../inc.php';

/** Zoekt debiteuren op nummer of naam. GET ?q=<tekst>  ->  {"ok":true,"rows":[{"debnr","naam","prijslijst"}]} */

$words = gbSearchWords((string) ($_GET['q'] ?? ''));
if ($words === []) {
    echo json_encode(['ok' => true, 'rows' => []]);
    exit;
}

try {
    $pdo = getPdoConnection();
    $plColumn = gbFindColumn($pdo, 'cicmpy', PRICELIST_COLUMN_CANDIDATES);
    $where = ["c.debnr IS NOT NULL", "LTRIM(RTRIM(c.debnr)) <> ''"];
    $params = [];
    foreach ($words as $word) {
        $where[] = "(c.cmp_name LIKE ? OR LTRIM(RTRIM(c.debnr)) LIKE ?)";
        $params[] = '%' . $word . '%';
        $params[] = '%' . $word . '%';
    }
    $stmt = $pdo->prepare(
        'SELECT TOP 20 LTRIM(RTRIM(c.debnr)) AS debnr, c.cmp_name AS naam, ' .
        ($plColumn !== null ? 'LTRIM(RTRIM(CAST(c.' . q($plColumn) . " AS varchar(50)))) AS prijslijst" : "CAST('' AS varchar(1)) AS prijslijst") .
        ' FROM cicmpy c WHERE ' . implode(' AND ', $where) . ' ORDER BY c.cmp_name'
    );
    $stmt->execute($params);
    $rows = array_map(static fn (array $r): array => [
        'debnr' => (string) $r['debnr'],
        'naam' => trim((string) $r['naam']),
        'prijslijst' => (string) $r['prijslijst'],
    ], $stmt->fetchAll());
    echo json_encode(['ok' => true, 'rows' => $rows], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
}
