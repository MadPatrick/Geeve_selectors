<?php

declare(strict_types=1);

// Alleen lezen. Toont ALLE kolommen van de orderregels (orsrg) van één verkooporder, om te zien welk aantal-veld bij de picklijst past.
// Gebruik: /stickers/order/api/diagnose.php?nummer=36023662&artikel=77296-36-12 (artikel is optioneel). Zelfde toegang als de tegel.
require_once __DIR__ . '/../../../shared/tiles.php';
tileApiGuard('stickers-order');
require_once __DIR__ . '/../../../reports/inc.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    $nummer = reportOrderNumber($_GET['nummer'] ?? '');
    $artikel = trim((string) ($_GET['artikel'] ?? ''));
    $pdo = reportConnection('stickers-order');
    $sql = 'SET NOCOUNT ON; SELECT TOP 100 * FROM orsrg WITH (NOLOCK) WHERE LTRIM(RTRIM(ordernr)) = ?';
    $par = [$nummer];
    if ($artikel !== '') {
        $sql .= ' AND LTRIM(RTRIM(artcode)) = ?';
        $par[] = $artikel;
    }
    $st = $pdo->prepare($sql);
    $st->execute($par);
    $rows = [];
    foreach ($st->fetchAll() as $r) {
        $o = [];
        foreach ($r as $k => $v) {
            if ($v === null || $v === '' || $v === 0 || $v === 0.0 || $v === '0') {
                continue; // alleen gevulde velden
            }
            $o[$k] = $v instanceof DateTimeInterface ? $v->format('Y-m-d H:i') : (is_string($v) ? trim($v) : $v);
        }
        $rows[] = $o;
    }
    echo json_encode(['ok' => true, 'aantal_regels' => count($rows), 'regels' => $rows], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => geeveFoutTekst($e)], JSON_UNESCAPED_UNICODE);
}
