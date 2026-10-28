<?php

declare(strict_types=1);

// Beveiligde tegel (Config > Tegels): zonder ontgrendelen geen gegevens.
require_once __DIR__ . '/../../../shared/tiles.php';
tileApiGuard('inkoopprijzen-gr14-gr55');

/**
 * Inkoopprijzen GR14 naast GR55: per artikel van artikelgroep 14 de standaard inkoopprijs (hoofdleverancier) naast die van het
 * bijbehorende artikel in groep 55. Koppeling op Zoekcode (Items.SearchCode): de Zoekcode van het GR55-artikel is die van het
 * GR14-artikel zonder "VZ" aan het eind. Inkoopprijs = "ink_pr per st" uit de view
 * [Exactrapportage].[dbo].[1000 artikelen + standaard verk_pr + standaard ink_pr] (zelfde bron als de tegel Artikelmarges).
 * Alleen lezen. Een artikel zonder tegenhanger krijgt een lege cel.
 */

require_once __DIR__ . '/../../inc.php';

const IP_GROUP_A = '14';
const IP_GROUP_B = '55';
const IP_SUFFIX = 'VZ';

/** Sleutel om GR14 en GR55 te koppelen: hoofdletters, zonder spaties, GR14 zonder "VZ" aan het eind. */
function inkoopprijzenKey(string $searchCode, bool $stripSuffix): string
{
    $key = strtoupper(trim($searchCode));
    if ($stripSuffix && substr($key, -strlen(IP_SUFFIX)) === IP_SUFFIX) {
        $key = rtrim(substr($key, 0, -strlen(IP_SUFFIX)));
    }
    return $key;
}

reportApi(function (array $in): array {
    $pdo = reportConnection();

    $stmt = $pdo->prepare('SELECT name FROM sys.columns WHERE object_id = OBJECT_ID(?)');
    $stmt->execute(['Items']);
    $searchColumn = null;
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $column) {
        if (strcasecmp((string) $column, 'SearchCode') === 0) {
            $searchColumn = (string) $column;
        }
    }
    if ($searchColumn === null) {
        throw new RuntimeException('Kolom SearchCode (Zoekcode) niet gevonden op tabel Items.');
    }

    // Eerst alleen de artikelen van de twee groepen (klein), daarna de prijzen uit de view in blokken per artikelnummer:
    // een join met de hele view (ca. 70.000 regels) is veel te traag.
    $stmt = $pdo->prepare(
        'SET NOCOUNT ON; SELECT LTRIM(RTRIM(i.Assortment)) AS g, RTRIM(i.[' . $searchColumn . ']) AS z, RTRIM(i.ItemCode) AS c ' .
        'FROM Items i WITH (NOLOCK) ' .
        'WHERE LTRIM(RTRIM(i.Assortment)) IN (?, ?) AND LTRIM(RTRIM(ISNULL(i.[' . $searchColumn . '], \'\'))) <> \'\' ' .
        'ORDER BY i.[' . $searchColumn . '], i.ItemCode'
    );
    $stmt->execute([IP_GROUP_A, IP_GROUP_B]);
    $items = $stmt->fetchAll(PDO::FETCH_NUM);

    $prices = [];
    $codes = array_values(array_unique(array_map(static fn ($r) => trim((string) $r[2]), $items)));
    foreach (array_chunk($codes, 500) as $chunk) {
        $stmt = $pdo->prepare(
            'SET NOCOUNT ON; SELECT RTRIM([Artnr]) AS c, [ink_pr per st] AS p FROM [Exactrapportage].[dbo].[1000 artikelen + standaard verk_pr + standaard ink_pr] WITH (NOLOCK) ' .
            'WHERE [Artnr] IN (' . implode(',', array_fill(0, count($chunk), '?')) . ')'
        );
        $stmt->execute($chunk);
        while ($r = $stmt->fetch(PDO::FETCH_NUM)) {
            $key = trim((string) $r[0]);
            if (is_numeric($r[1]) && !isset($prices[$key])) {
                $prices[$key] = round((float) $r[1], 4);
            }
        }
    }

    $a = [];
    $b = [];
    $seen = [];
    foreach ($items as $r) {
        $isA = trim((string) $r[0]) === IP_GROUP_A;
        $item = ($isA ? 'A' : 'B') . '|' . trim((string) $r[2]);
        if (!isset($seen[$item])) {
            $seen[$item] = [trim((string) $r[1]), $isA, $prices[trim((string) $r[2])] ?? null];
        }
    }
    foreach ($seen as $entry) {
        [$code, $isA, $price] = $entry;
        $key = inkoopprijzenKey($code, $isA);
        if ($isA) {
            $a[$key][] = [$code, $price];
        } else {
            $b[$key][] = [$code, $price];
        }
    }

    $rows = [];
    $keys = array_unique(array_merge(array_keys($a), array_keys($b)));
    sort($keys, SORT_STRING);
    $onlyA = 0;
    $onlyB = 0;
    foreach ($keys as $key) {
        $listA = $a[$key] ?? [];
        $listB = $b[$key] ?? [];
        $n = max(count($listA), count($listB));
        for ($i = 0; $i < $n; $i++) {
            $x = $listA[$i] ?? null;
            $y = $listB[$i] ?? null;
            if ($x === null) {
                $onlyB++;
            } elseif ($y === null) {
                $onlyA++;
            }
            $rows[] = [$x !== null ? $x[0] : $y[0], $x !== null ? $x[1] : '', $y !== null ? $y[1] : ''];
        }
    }
    $rows = array_map(static fn ($r) => [$r[0], $r[1] ?? '', $r[2] ?? ''], $rows);

    return [
        'columns' => ['Artikel', 'GR14', 'GR55'],
        'numeric' => [false, true, true],
        'dates' => [false, false, false],
        'currency' => ['GR14', 'GR55'],
        'text' => ['Artikel'],
        'rows' => $rows,
        'kop' => [
            ['label' => 'Artikelen', 'value' => (string) count($rows)],
            ['label' => 'Alleen in GR14', 'value' => (string) $onlyA],
            ['label' => 'Alleen in GR55', 'value' => (string) $onlyB],
        ],
        'titles' => ['Inkoopprijzen hoofdleverancier GR14 naast GR55', 'Artikel = Zoekcode van GR14 (GR55 = Zoekcode zonder VZ)'],
        'fileTag' => 'GR14_GR55_' . date('Y-m-d'),
    ];
}, false);
