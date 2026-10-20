<?php

declare(strict_types=1);

// Beveiligde tegel (Config > Tegels): zonder ontgrendelen geen gegevens.
require_once __DIR__ . '/../../shared/tiles.php';
tileApiGuard('gebogenbuis');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

require_once __DIR__ . '/../inc.php';

/**
 * Korting (%) van een klant per artikelregel, uit de kortingstructuur van de prijslijst van de klant.
 * GET ?debnr=<debiteurnummer>&items=[{"groep":"67","aantal":3}, ...]
 *  ->  {"ok":true,"prijslijst":"100","items":[{"pct":25,"note":""}, ...]}   (zelfde volgorde als items)
 */

$debnr = trim((string) ($_GET['debnr'] ?? ''));
$items = json_decode((string) ($_GET['items'] ?? '[]'), true);
if ($debnr === '' || !is_array($items)) {
    echo json_encode(['ok' => false, 'error' => 'Klant of artikelen ontbreken.']);
    exit;
}

try {
    $pdo = getPdoConnection();
    $plColumn = gbFindColumn($pdo, 'cicmpy', PRICELIST_COLUMN_CANDIDATES);
    if ($plColumn === null) {
        throw new RuntimeException('Geen prijslijst-kolom gevonden op cicmpy.');
    }
    $debcodeColumn = gbFindColumn($pdo, 'cicmpy', ['debcode']);
    $stmt = $pdo->prepare(
        'SELECT TOP 1 LTRIM(RTRIM(CAST(' . q($plColumn) . " AS varchar(50)))) AS prijslijst, " .
        ($debcodeColumn !== null ? 'LTRIM(RTRIM(' . q($debcodeColumn) . "))" : "CAST('' AS varchar(1))") . ' AS debcode ' .
        "FROM cicmpy WHERE LTRIM(RTRIM(debnr)) = :debnr"
    );
    $stmt->execute(['debnr' => $debnr]);
    $customer = $stmt->fetch();
    if (!$customer) {
        throw new RuntimeException('Klant ' . $debnr . ' niet gevonden.');
    }
    $priceList = (string) $customer['prijslijst'];
    $lines = $priceList !== '' ? findDiscountLines($pdo, $priceList) : [];

    $out = [];
    foreach ($items as $item) {
        if ($priceList === '') {
            $out[] = ['pct' => 0, 'note' => 'klant heeft geen prijslijst'];
            continue;
        }
        $group = is_array($item) ? trim((string) ($item['groep'] ?? '')) : '';
        $qty = is_array($item) ? (float) str_replace(',', '.', (string) ($item['aantal'] ?? '0')) : 0.0;
        $out[] = ctype_digit($group)
            ? gbDiscountFor($lines, (string) $customer['debcode'], (int) $group, $qty)
            : ['pct' => 0, 'note' => 'artikelgroep onbekend'];
    }
    echo json_encode(['ok' => true, 'prijslijst' => $priceList, 'items' => $out], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
}
