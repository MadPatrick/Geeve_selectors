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

    $stmt = $pdo->prepare(
        'SET NOCOUNT ON; SELECT LTRIM(RTRIM(i.Assortment)) AS g, RTRIM(i.[' . $searchColumn . ']) AS z, RTRIM(i.ItemCode) AS c, v.[ink_pr per st] AS p ' .
        'FROM Items i WITH (NOLOCK) ' .
        'LEFT JOIN [Exactrapportage].[dbo].[1000 artikelen + standaard verk_pr + standaard ink_pr] v WITH (NOLOCK) ON LTRIM(RTRIM(v.[Artnr])) = LTRIM(RTRIM(i.ItemCode)) ' .
        'WHERE LTRIM(RTRIM(i.Assortment)) IN (?, ?) AND LTRIM(RTRIM(ISNULL(i.[' . $searchColumn . '], \'\'))) <> \'\' ' .
        'ORDER BY i.[' . $searchColumn . '], i.ItemCode'
    );
    $stmt->execute([IP_GROUP_A, IP_GROUP_B]);

    // sleutel => lijst [Zoekcode, prijs]; per artikel de eerste prijs uit de view die gevuld is.
    $a = [];
    $b = [];
    $seen = [];
    while ($r = $stmt->fetch(PDO::FETCH_NUM)) {
        $isA = trim((string) $r[0]) === IP_GROUP_A;
        $code = trim((string) $r[1]);
        $price = is_numeric($r[3]) ? round((float) $r[3], 4) : null;
        $item = ($isA ? 'A' : 'B') . '|' . trim((string) $r[2]);
        if (isset($seen[$item])) {
            if ($price !== null && $seen[$item][2] === null) {
                $seen[$item][2] = $price;
            }
            continue;
        }
        $seen[$item] = [$code, $isA, $price];
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
