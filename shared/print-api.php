<?php

declare(strict_types=1);

/**
 * Direct printen vanuit de browser: de pagina stuurt het afdrukblok (HTML) van een tegel hierheen, de server rendert
 * het (zelfde print-CSS) en stuurt het naar de gekozen printer van die tegel. Zie shared/printen.php en
 * stickers/assets/direct-print.js.
 *
 * POST (JSON): {"tile": "<tegel-id>", "printer": "<wachtrij>", "html": "<inhoud van het afdrukblok>", "csrf": "<token>"}
 * Antwoord (JSON): {"ok": true, "job": "...", "printer": "<naam>"} of {"ok": false, "error": "..."}
 */

require_once __DIR__ . '/tiles.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/printen.php';

header('Content-Type: application/json; charset=utf-8');

function printApiFail(int $status, string $message): never
{
    http_response_code($status);
    echo json_encode(['ok' => false, 'error' => $message]);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    printApiFail(405, 'Alleen POST.');
}
$body = json_decode((string) file_get_contents('php://input', false, null, 0, 3_000_000), true);
if (!is_array($body)) {
    printApiFail(400, 'Ongeldig verzoek.');
}
if (!csrfValid($body['csrf'] ?? null)) {
    printApiFail(403, 'Sessie verlopen: laad de pagina opnieuw.');
}
$tile = (string) ($body['tile'] ?? '');
$page = GEEVE_PRINT_TILE_PAGE[$tile] ?? null;
if ($page === null || !isset(GEEVE_PRINT_TILES[$tile])) {
    printApiFail(400, 'Onbekende tegel.');
}
tileApiGuard($tile);
$queue = (string) ($body['printer'] ?? '');
$printers = geevePrinters($tile);
if (!isset($printers[$queue])) {
    printApiFail(400, 'Deze printer is niet beschikbaar voor deze tegel.');
}
$html = $body['html'] ?? null;
if (!is_string($html) || trim($html) === '' || strlen($html) > 2_500_000) {
    printApiFail(400, 'Er is niets om te printen.');
}

try {
    $job = geevePrintHtml($html, $page['css'], $queue, GEEVE_PRINT_TILES[$tile], null, $page['class']);
} catch (RuntimeException $e) {
    printApiFail(500, $e->getMessage());
}
echo json_encode(['ok' => true, 'job' => $job, 'printer' => $printers[$queue]]);
