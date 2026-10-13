<?php

declare(strict_types=1);

/**
 * Controleert de velden van een offerte- of inkooporder-import tegen Exact: verkoper/inkoper (humres.res_id), debiteur- of
 * crediteurnummer (cicmpy) en artikelcodes (Items). POST met csrf, verkoper, debiteur (het nummer van de debiteur of crediteur),
 * soort (debiteur of crediteur) en items[]; antwoord:
 * {"ok":true,"verkoper":{"found":bool,"name":"..."},"partij":{...},"debiteur":{...},"items":{"CODE":{"found":true,"description":"..."}}}
 * Alleen lezen; niets wordt in Exact gewijzigd.
 */

require_once __DIR__ . '/../../inc.php';
require_once __DIR__ . '/../../../shared/csrf.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !csrfValid($_POST['csrf'] ?? null)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Ongeldig verzoek (sessie verlopen). Laad de pagina opnieuw.']);
    exit;
}

$flags = JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE;
try {
    $pdo = reportConnection();
    $out = ['ok' => true];

    // Verkoper: medewerker (humres.res_id).
    $seller = trim((string) ($_POST['verkoper'] ?? ''));
    $out['verkoper'] = ['found' => false, 'name' => ''];
    if ($seller !== '' && preg_match('/^\d{1,9}$/', $seller) === 1) {
        $stmt = $pdo->prepare('SET NOCOUNT ON; SELECT TOP 1 RTRIM(fullname) AS naam FROM humres WHERE res_id = ?');
        $stmt->execute([(int) $seller]);
        $row = $stmt->fetch();
        if ($row !== false) {
            $out['verkoper'] = ['found' => true, 'name' => trim((string) $row['naam'])];
        }
    }

    // Debiteur of crediteur (leverancier): nummer of code in cicmpy.
    $creditor = (string) ($_POST['soort'] ?? 'debiteur') === 'crediteur';
    $party = trim((string) ($_POST['debiteur'] ?? ''));
    $out['partij'] = ['found' => false, 'name' => ''];
    if ($party !== '' && strlen($party) <= 20) {
        $sql = $creditor
            ? 'SET NOCOUNT ON; SELECT TOP 1 RTRIM(cmp_name) AS naam FROM cicmpy WHERE LTRIM(RTRIM(crdnr)) = ? OR LTRIM(RTRIM(crdcode)) = ?'
            : 'SET NOCOUNT ON; SELECT TOP 1 RTRIM(cmp_name) AS naam FROM cicmpy WHERE LTRIM(RTRIM(debnr)) = ? OR LTRIM(RTRIM(debcode)) = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$party, $party]);
        $row = $stmt->fetch();
        if ($row !== false) {
            $out['partij'] = ['found' => true, 'name' => trim((string) $row['naam'])];
        }
    }
    $out['debiteur'] = $out['partij']; // oude naam, voor de zekerheid

    // Artikelen: bestaan ze in Exact, met omschrijving.
    $codes = [];
    foreach ((array) ($_POST['items'] ?? []) as $code) {
        $code = trim((string) $code);
        if ($code !== '' && strlen($code) <= 30) {
            $codes[strtoupper($code)] = $code;
        }
    }
    if (count($codes) > 2000) {
        throw new RuntimeException('Te veel artikelen in één controle (maximaal 2000).');
    }
    $items = [];
    foreach (array_chunk(array_values($codes), 500) as $chunk) {
        $stmt = $pdo->prepare('SET NOCOUNT ON; SELECT RTRIM(ItemCode) AS c, RTRIM(Description) AS d FROM Items WHERE ItemCode IN (' . implode(',', array_fill(0, count($chunk), '?')) . ')');
        $stmt->execute($chunk);
        foreach ($stmt->fetchAll() as $row) {
            $items[strtoupper(trim((string) $row['c']))] = ['found' => true, 'description' => trim((string) $row['d'])];
        }
    }
    $out['items'] = (object) $items;

    echo json_encode($out, $flags);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], $flags);
}
