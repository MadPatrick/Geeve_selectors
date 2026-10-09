<?php

declare(strict_types=1);

/** Controleert of een artikelgroep (ItemAssortment) in Exact bestaat. GET ?code=<groep> */

require_once __DIR__ . '/../inc.php';

header('Content-Type: application/json; charset=utf-8');

$code = trim((string) ($_GET['code'] ?? ''));
if ($code === '' || strlen($code) > 20) {
    echo json_encode(['found' => false, 'code' => $code, 'description' => '']);
    exit;
}

try {
    echo json_encode(lookupItemGroup(getPdoConnection(), $code), JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['found' => false, 'code' => $code, 'description' => '', 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
