<?php

declare(strict_types=1);

// Beveiligde tegel (Config > Tegels): zonder ontgrendelen geen gegevens.
require_once __DIR__ . '/../../../shared/tiles.php';
tileApiGuard('brutoprijzen');

/**
 * Brutoprijzen per artikelgroep: per artikel van de opgegeven groep ons artikelnummer, het leveranciersnummer en de bruto
 * verkoopprijs. Leveranciersnummer = ItemAccounts.ItemCodeAccount van de hoofdleverancier (MainAccount = 1). Bruto verkoopprijs =
 * standaard verkoopprijs (staffl, prijslijst "salesprice", nieuwste geldige; zelfde bron als Artikelmarges en Import Artikelgegevens),
 * dus niet netto/na korting. De Excel ("Export naar Exact") bevat dezelfde drie kolommen. Alleen lezen.
 */

require_once __DIR__ . '/../../inc.php';

reportApi(function (array $in): array {
    $group = trim((string) ($in['groep'] ?? ''));
    if ($group === '' || preg_match('/^[A-Za-z0-9._ -]{1,20}$/', $group) !== 1) {
        throw new RuntimeException('Vul een geldige artikelgroep in.');
    }
    $pdo = reportConnection('brutoprijzen');

    $stmt = $pdo->prepare(
        'SET NOCOUNT ON; SELECT RTRIM(i.ItemCode) AS c, RTRIM(ia.ItemCodeAccount) AS l ' .
        'FROM Items i WITH (NOLOCK) ' .
        'LEFT JOIN ItemAccounts ia WITH (NOLOCK) ON ia.ItemCode = i.ItemCode AND ia.MainAccount = 1 ' .
        'WHERE LTRIM(RTRIM(i.Assortment)) = ? ORDER BY i.ItemCode'
    );
    $stmt->execute([$group]);
    $items = [];
    foreach ($stmt->fetchAll(PDO::FETCH_NUM) as $r) {
        $code = trim((string) $r[0]);
        if (!isset($items[$code]) || $items[$code] === '') {
            $items[$code] = trim((string) $r[1]);
        }
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
        $rows[] = [(string) $code, $lev, $price];
    }

    return [
        'columns' => ['Artikelnummer', 'Leveranciersnummer', 'Bruto verkoopprijs'],
        'numeric' => [false, false, true],
        'dates' => [false, false, false],
        'currency' => ['Bruto verkoopprijs'],
        'text' => ['Artikelnummer', 'Leveranciersnummer'],
        'rows' => $rows,
        'kop' => [
            ['label' => 'Artikelen', 'value' => (string) count($rows)],
            ['label' => 'Zonder leveranciersnummer', 'value' => (string) $zonderLev],
            ['label' => 'Zonder verkoopprijs', 'value' => (string) $zonderPrijs],
        ],
        'titles' => ['Brutoprijzen artikelgroep ' . $group, 'Artikelnummer, leveranciersnummer (hoofdleverancier) en bruto verkoopprijs'],
        'fileTag' => 'Brutoprijzen_GR' . preg_replace('/[^A-Za-z0-9_-]+/', '_', $group) . '_' . date('Y-m-d'),
    ];
}, false);
