<?php

declare(strict_types=1);

// Beveiligde tegel (Config > Tegels): zonder ontgrendelen geen gegevens.
require_once __DIR__ . '/../../shared/tiles.php';
tileApiGuard('klantartikel');

/**
 * Controleert of een artikelnummer in Exact bestaat en geeft de omschrijving terug.
 * GET ?code=<artikelnummer>  ->  {"found":bool,"code":"...","description":"..."}
 * Faalt zacht (found:false + error) zodat de pagina bruikbaar blijft.
 */

require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/queries.php';

header('Content-Type: application/json; charset=utf-8');

$code = trim((string) ($_GET['code'] ?? ''));
if ($code === '' || strlen($code) > 60) {
    echo json_encode(['found' => false, 'code' => $code, 'description' => '']);
    exit;
}

try {
    echo json_encode(lookupItem(getPdoConnection(), $code), JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['found' => false, 'code' => $code, 'description' => '', 'error' => geeveFoutTekst($e)], JSON_UNESCAPED_UNICODE);
}
