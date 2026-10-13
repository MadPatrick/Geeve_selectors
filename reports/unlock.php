<?php

declare(strict_types=1);

/**
 * Ontgrendelt een beveiligd onderdeel met de toegangscode (zie shared/access.php). POST met csrf en code;
 * antwoord: {"ok":true} of {"ok":false,"error":"..."}. Gebruikt door de popup op het Reports-scherm.
 */

require_once __DIR__ . '/../shared/access.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !csrfValid($_POST['csrf'] ?? null)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Sessie verlopen. Laad de pagina opnieuw.']);
    exit;
}

// Alleen onderdelen die hier bekend zijn kunnen worden ontgrendeld.
$scope = (string) ($_POST['scope'] ?? '');
if ($scope !== 'productiviteit') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Onbekend onderdeel.']);
    exit;
}

$outcome = accessTryUnlock($scope, (string) ($_POST['code'] ?? ''));
if ($outcome === 'ok') {
    echo json_encode(['ok' => true]);
    exit;
}
http_response_code(403);
echo json_encode(['ok' => false, 'error' => $outcome === 'wacht' ? 'Te veel foute pogingen. Wacht een minuut en probeer het opnieuw.' : 'Onjuiste code.']);
