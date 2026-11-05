<?php

declare(strict_types=1);

// Beveiligde tegel (Config > Tegels): zonder ontgrendelen geen gegevens.
require_once __DIR__ . '/../../../shared/tiles.php';
tileApiGuard('msp');

/**
 * Haalt de MSP-gegevens op met een lees-query in database 005 (zelfde berekening als dbo.SP_MSP; zie ../sql/msp_query.sql).
 * POST met csrf-sleutel; antwoord: {"ok":true,"columns":[...],"rows":[[...]]} of {"ok":false,"error":"..."}.
 */

require_once __DIR__ . '/../../../shared/csrf.php';
require_once __DIR__ . '/../inc.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !csrfValid($_POST['csrf'] ?? null)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Ongeldig verzoek (sessie verlopen). Laad de pagina opnieuw.']);
    exit;
}
csrfReleaseSession(); // sessie vrijgeven: trage verwerking mag andere tegels niet blokkeren

@set_time_limit(300);

try {
    $year = filter_var($_POST['jaar'] ?? null, FILTER_VALIDATE_INT);
    $month = filter_var($_POST['maand'] ?? null, FILTER_VALIDATE_INT);
    if ($year === false || $month === false) {
        throw new RuntimeException('Kies een maand en een jaar.');
    }
    $data = mspFetch($year, $month);
    echo json_encode(['ok' => true] + $data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PRESERVE_ZERO_FRACTION);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => geeveFoutTekst($e)], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
}
