<?php

declare(strict_types=1);

// Beveiligde tegel (Config > Tegels): zonder ontgrendelen geen gegevens.
require_once __DIR__ . '/../../../shared/tiles.php';
tileApiGuard('offerte-import');

/**
 * Box qty en magazijnlocatie. Box qty = Bst.gr. (ItemAccounts.PurchaseOrderSize) van de hoofdleverancier (MainAccount = 1) per artikel. Apart van de controle
 * (validate.php), zodat de controle niet op deze opzoeking wacht. POST met csrf en items[]; antwoord: {"ok":true,"box":{"CODE":10}}
 * (artikelen zonder hoofdleverancier of met 0 ontbreken) en "loc":{"CODE":"A-01-02"} (voorrd.maglok, magazijn 1). Alleen lezen.
 */

require_once __DIR__ . '/../../../reports/inc.php';
require_once __DIR__ . '/../../../shared/csrf.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !csrfValid($_POST['csrf'] ?? null)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Ongeldig verzoek (sessie verlopen). Laad de pagina opnieuw.']);
    exit;
}
session_write_close();

$flags = JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE;
try {
    @set_time_limit(120);
    $codes = [];
    foreach ((array) ($_POST['items'] ?? []) as $code) {
        $code = trim((string) $code);
        if ($code !== '' && strlen($code) <= 30) {
            $codes[strtoupper($code)] = $code;
        }
    }
    if (count($codes) > 2000) {
        throw new RuntimeException('Te veel artikelen in één verzoek (maximaal 2000).');
    }
    $pdo = reportConnection('offerte-import');
    $box = [];
    foreach (array_chunk(array_values($codes), 300) as $chunk) {
        $stmt = $pdo->prepare(
            'SET NOCOUNT ON; SELECT RTRIM(ItemCode) AS c, MAX(PurchaseOrderSize) AS m FROM ItemAccounts WITH (NOLOCK)'
            . ' WHERE MainAccount = 1 AND ItemCode IN (' . implode(',', array_fill(0, count($chunk), 'CAST(? AS varchar(30))')) . ') GROUP BY RTRIM(ItemCode)'
        );
        $stmt->execute($chunk);
        foreach ($stmt->fetchAll() as $row) {
            if ((float) $row['m'] > 0) {
                $box[strtoupper(trim((string) $row['c']))] = (float) $row['m'];
            }
        }
    }
    // Locatie in het magazijn: voorrd.maglok, de vaste locatie per artikel in magazijn 1 (zelfde bron als Slangkaarten printen).
    $loc = [];
    try {
        foreach (array_chunk(array_values($codes), 300) as $chunk) {
            $stmt = $pdo->prepare(
                'SET NOCOUNT ON; SELECT RTRIM(artcode) AS c, RTRIM(maglok) AS l FROM voorrd WITH (NOLOCK)'
                . " WHERE magcode = '1' AND artcode IN (" . implode(',', array_fill(0, count($chunk), 'CAST(? AS varchar(30))')) . ')'
            );
            $stmt->execute($chunk);
            foreach ($stmt->fetchAll() as $row) {
                $l = trim((string) ($row['l'] ?? ''));
                if ($l !== '') {
                    $loc[strtoupper(trim((string) $row['c']))] = $l;
                }
            }
        }
    } catch (Throwable $e) {
        // locatie blijft leeg
    }
    echo json_encode(['ok' => true, 'box' => (object) $box, 'loc' => (object) $loc], $flags);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => geeveFoutTekst($e)], $flags);
}
