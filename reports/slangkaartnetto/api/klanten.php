<?php

declare(strict_types=1);

// Tegel-toegang (Config > Tegels): zonder ontgrendelen geen gegevens.
require_once __DIR__ . '/../../../shared/tiles.php';
tileApiGuard('slangkaart-netto');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

require_once __DIR__ . '/../../inc.php';

/**
 * Zoekt klanten (Debiteur + DebiteurNaam) met een slangkaart met netto afspraak. GET ?q=<tekst>  ->  {"ok":true,"rows":[{"debnr","naam"}]}
 * Losse woorden in willekeurige volgorde; een getypt nummer: klantnummers die ermee beginnen eerst (100, 1000, 1001, ...), exact bovenaan.
 */

$words = array_values(array_filter(preg_split('/\s+/', trim((string) ($_GET['q'] ?? ''))) ?: [], static fn (string $w): bool => $w !== ''));
$words = array_slice($words, 0, 5);
if ($words === []) {
    echo json_encode(['ok' => true, 'rows' => []]);
    exit;
}

try {
    $where = ['PrijsBerekend > 0', "LTRIM(RTRIM(Debiteur)) <> ''"];
    $params = [];
    foreach ($words as $word) {
        // % en _ in de zoekterm letterlijk nemen.
        $like = '%' . str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $word) . '%';
        $where[] = '(DebiteurNaam LIKE ? OR LTRIM(RTRIM(Debiteur)) LIKE ?)';
        $params[] = $like;
        $params[] = $like;
    }
    $order = 'ORDER BY naam';
    if (ctype_digit($words[0])) {
        // Exact nummer eerst, dan nummers die ermee beginnen (laag naar hoog), dan de rest.
        $order = 'ORDER BY CASE WHEN debnr = ? THEN 0 WHEN debnr LIKE ? THEN 1 ELSE 2 END, TRY_CAST(debnr AS bigint), naam';
        $params[] = $words[0];
        $params[] = $words[0] . '%';
    }
    // DebiteurNaam staat per kaart (kan per kaart verschillen, bijv. bij kontant verkoop): per klantnummer de meest voorkomende naam tonen.
    $stmt = reportConnection()->prepare(
        'SET NOCOUNT ON; SELECT TOP 20 debnr, naam FROM (' .
        'SELECT debnr, naam, ROW_NUMBER() OVER (PARTITION BY debnr ORDER BY aantal DESC, naam) AS rn FROM (' .
        'SELECT LTRIM(RTRIM(Debiteur)) AS debnr, LTRIM(RTRIM(DebiteurNaam)) AS naam, COUNT(*) AS aantal ' .
        'FROM [Slangkaarten].[dbo].[Slangkaarten] WITH (NOLOCK) WHERE ' . implode(' AND ', $where) . ' ' .
        'GROUP BY LTRIM(RTRIM(Debiteur)), LTRIM(RTRIM(DebiteurNaam))) g) k WHERE rn = 1 ' . $order
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
