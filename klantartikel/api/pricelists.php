<?php

declare(strict_types=1);

// Beveiligde tegel (Config > Tegels): zonder ontgrendelen geen gegevens.
require_once __DIR__ . '/../../shared/tiles.php';
tileApiGuard('klantartikel');

/** Alle prijslijsten (stfoms, elk type) met omschrijving, voor het zoekveld Prijslijst. */

require_once __DIR__ . '/../inc/db.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $rows = getPdoConnection()->query(
        "SELECT LTRIM(RTRIM(prijslijst)) AS nr, MAX(LTRIM(RTRIM(ISNULL(oms30_0, '')))) AS oms FROM stfoms GROUP BY LTRIM(RTRIM(prijslijst))"
    )->fetchAll();
    $lists = array_map(fn ($r) => ['nr' => (string) $r['nr'], 'oms' => (string) $r['oms']], $rows);
    usort($lists, fn ($a, $b) => strnatcmp($a['nr'], $b['nr']));
    echo json_encode(['ok' => true, 'lists' => $lists], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
