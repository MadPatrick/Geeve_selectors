<?php

declare(strict_types=1);

// Beveiligde tegel (Config > Tegels): zonder ontgrendelen geen gegevens.
require_once __DIR__ . '/../../../shared/tiles.php';
tileApiGuard('brutoprijzen');

/**
 * Brutoprijzen per artikelgroep: per artikel van de opgegeven groep ons artikelnummer, het leveranciersnummer en de bruto
 * verkoopprijs. Leveranciersnummer = ItemAccounts.ItemCodeAccount van de hoofdleverancier (MainAccount = 1). Bruto verkoopprijs =
 * standaard verkoopprijs (staffl, prijslijst "salesprice", nieuwste geldige; zelfde bron als Artikelmarges en Import Artikelgegevens),
 * dus niet netto/na korting. Leverancier = crediteurnummer van de hoofdleverancier (ItemAccounts.crdnr). Status = Items.Condition;
 * filter A = actief, E = vervallen, N = non-actief (aanname, de ruwe code staat in de kolom Status). De Excel ("Export naar Exact") bevat dezelfde drie kolommen. Alleen lezen.
 */

require_once __DIR__ . '/../../inc.php';

reportApi(function (array $in): array {
    $group = trim((string) ($in['groep'] ?? ''));
    if ($group === '' || preg_match('/^[A-Za-z0-9._ -]{1,20}$/', $group) !== 1) {
        throw new RuntimeException('Vul een geldige artikelgroep in.');
    }
    $status = trim((string) ($in['status'] ?? ''));
    if (!in_array($status, ['', 'A', 'E', 'N'], true)) {
        throw new RuntimeException('Kies een geldige status.');
    }
    $pdo = reportConnection('brutoprijzen');

    // Kolom Items.Condition (status) bestaat in Exact Globe; zo niet, dan blijft de status leeg en werkt het filter niet.
    $stmt = $pdo->prepare('SELECT name FROM sys.columns WHERE object_id = OBJECT_ID(?)');
    $stmt->execute(['Items']);
    $condition = null;
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $column) {
        if (strcasecmp((string) $column, 'Condition') === 0) {
            $condition = (string) $column;
        }
    }
    if ($status !== '' && $condition === null) {
        throw new RuntimeException('Kolom Condition (status) niet gevonden op tabel Items.');
    }

    $stmt = $pdo->prepare(
        'SET NOCOUNT ON; SELECT RTRIM(i.ItemCode) AS c, RTRIM(ia.ItemCodeAccount) AS l, LTRIM(RTRIM(ia.crdnr)) AS k, ' .
        ($condition !== null ? 'LTRIM(RTRIM(i.[' . $condition . '])) ' : "'' ") . 'AS s ' .
        'FROM Items i WITH (NOLOCK) ' .
        'LEFT JOIN ItemAccounts ia WITH (NOLOCK) ON ia.ItemCode = i.ItemCode AND ia.MainAccount = 1 ' .
        'WHERE LTRIM(RTRIM(i.Assortment)) = ?' .
        ($status !== '' ? ' AND LTRIM(RTRIM(i.[' . $condition . '])) = ?' : '') . ' ORDER BY i.ItemCode'
    );
    $stmt->execute($status !== '' ? [$group, $status] : [$group]);
    $items = [];
    $suppliers = [];
    $statuses = [];
    foreach ($stmt->fetchAll(PDO::FETCH_NUM) as $r) {
        $code = trim((string) $r[0]);
        if (!isset($items[$code]) || $items[$code] === '') {
            $items[$code] = trim((string) $r[1]);
            $suppliers[$code] = trim((string) $r[2]);
        }
        $statuses[$code] = trim((string) $r[3]);
    }

    $stmt = $pdo->prepare('SELECT name FROM sys.columns WHERE object_id = OBJECT_ID(?)');
    $stmt->execute(['staffl']);
    $hasValidFrom = false;
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $column) {
        if (strcasecmp((string) $column, 'validfrom') === 0) {
            $hasValidFrom = true;
        }
    }
    $sales = [];
    foreach (array_chunk(array_map('strval', array_keys($items)), 500) as $chunk) {
        $stmt = $pdo->prepare(
            "SET NOCOUNT ON; SELECT RTRIM(artcode) AS c, prijs83 AS p FROM staffl WITH (NOLOCK) WHERE prijslijst = 'salesprice' AND artcode IN ("
            . implode(',', array_fill(0, count($chunk), '?')) . ')'
            . ($hasValidFrom ? ' AND (validfrom IS NULL OR validfrom <= GETDATE()) ORDER BY validfrom' : '')
        );
        $stmt->execute($chunk);
        while ($r = $stmt->fetch(PDO::FETCH_NUM)) {
            if (is_numeric($r[1])) {
                $sales[trim((string) $r[0])] = round((float) $r[1], 4); // gesorteerd op validfrom: de laatste wint
            }
        }
    }

    $rows = [];
    $zonderLev = 0;
    $zonderPrijs = 0;
    foreach ($items as $code => $lev) {
        $price = $sales[(string) $code] ?? '';
        if ($lev === '') {
            $zonderLev++;
        }
        if ($price === '') {
            $zonderPrijs++;
        }
        $rows[] = [(string) $code, $lev, $suppliers[$code] ?? '', $price, $statuses[$code] ?? ''];
    }

    return [
        'columns' => ['Artikelnummer', 'Leveranciersnummer', 'Leverancier', 'Bruto verkoopprijs', 'Status'],
        'numeric' => [false, false, false, true, false],
        'dates' => [false, false, false, false, false],
        'currency' => ['Bruto verkoopprijs'],
        'text' => ['Artikelnummer', 'Leveranciersnummer', 'Leverancier', 'Status'],
        'rows' => $rows,
        'kop' => [
            ['label' => 'Artikelen', 'value' => (string) count($rows)],
            ['label' => 'Zonder leveranciersnummer', 'value' => (string) $zonderLev],
            ['label' => 'Zonder verkoopprijs', 'value' => (string) $zonderPrijs],
        ],
        'titles' => ['Brutoprijzen artikelgroep ' . $group, 'Artikelnummer, leveranciersnummer, leverancier (crediteurnummer, bijv. 100011 = Parker) en bruto verkoopprijs'],
        'fileTag' => 'Brutoprijzen_GR' . preg_replace('/[^A-Za-z0-9_-]+/', '_', $group) . '_' . date('Y-m-d'),
    ];
}, false);
