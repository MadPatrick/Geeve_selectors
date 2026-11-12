<?php

declare(strict_types=1);

// Beveiligde tegel (Config > Tegels): zonder ontgrendelen geen gegevens.
require_once __DIR__ . '/../../../shared/tiles.php';
tileApiGuard('inkoopprijzen-gr14-gr55');

/**
 * Inkoopprijzen GR14 naast GR55 (of, met soort=rvs, GR77 naast GR28): per artikel van artikelgroep 14 de standaard inkoopprijs (hoofdleverancier) naast die van het
 * bijbehorende artikel in groep 55. Koppeling op Zoekcode (Items.SearchCode): de Zoekcode van het GR55-artikel is die van het
 * GR14-artikel met "VZ" (of "VZX") aan het eind. Inkoopprijs = ItemAccounts.PurchasePrice van de hoofdleverancier
 * (MainAccount = 1); gelijk aan "ink_pr per st" uit de view 1000 artikelen + standaard ink_pr, maar die view is veel te traag
 * (17 s per 500 artikelen). Verkoopprijs = standaard verkoopprijs (staffl, prijslijst "salesprice", nieuwste geldige; zelfde bron
 * als Import Artikelgegevens). Alleen lezen. Een artikel zonder tegenhanger krijgt een lege cel.
 */

require_once __DIR__ . '/../../inc.php';

/**
 * Twee soorten: staal (GR14 naast GR55: GR55 = GR14-Zoekcode + VZ/VZX) en RVS (GR77 naast GR28: GR77-Zoekcode eindigt op 71,
 * GR28-Zoekcode op RVS; het artikel is de Zoekcode zonder dat einde). 'a'/'b' = achtervoegsels die van de Zoekcode van groep
 * A resp. B worden gehaald om te koppelen (langste eerst).
 */
const IP_SOORTEN = [
    'staal' => ['a' => '14', 'b' => '55', 'suffixA' => [], 'suffixB' => ['VZX', 'VZ'], 'uitleg' => 'GR55 = Zoekcode met VZ of VZX erachter'],
    'rvs' => ['a' => '77', 'b' => '28', 'suffixA' => ['71'], 'suffixB' => ['RVS'], 'uitleg' => 'GR77 = Zoekcode met 71 aan het eind, GR28 = met RVS aan het eind'],
];

/** Sleutel om groep A en B te koppelen: hoofdletters, zonder spaties, zonder het achtervoegsel van de groep aan het eind. */
function inkoopprijzenKey(string $searchCode, array $suffixes): string
{
    $key = strtoupper(trim($searchCode));
    foreach ($suffixes as $suffix) {
        if (substr($key, -strlen($suffix)) === $suffix) {
            return rtrim(substr($key, 0, -strlen($suffix)));
        }
    }
    return $key;
}

reportApi(function (array $in): array {
    $soortNaam = (string) ($in['soort'] ?? 'staal');
    $soort = IP_SOORTEN[$soortNaam] ?? IP_SOORTEN['staal'];
    $groupA = $soort['a'];
    $groupB = $soort['b'];
    $pdo = reportConnection('inkoopprijzen-gr14-gr55');

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

    // Artikelen van de twee groepen met de prijs van de hoofdleverancier (ItemAccounts.MainAccount = 1), in één snelle query.
    $stmt = $pdo->prepare(
        'SET NOCOUNT ON; SELECT LTRIM(RTRIM(i.Assortment)) AS g, RTRIM(i.[' . $searchColumn . ']) AS z, RTRIM(i.ItemCode) AS c, ia.PurchasePrice AS p ' .
        'FROM Items i WITH (NOLOCK) ' .
        'LEFT JOIN ItemAccounts ia WITH (NOLOCK) ON ia.ItemCode = i.ItemCode AND ia.MainAccount = 1 ' .
        'WHERE LTRIM(RTRIM(i.Assortment)) IN (?, ?) AND LTRIM(RTRIM(ISNULL(i.[' . $searchColumn . '], \'\'))) <> \'\' ' .
        'ORDER BY i.[' . $searchColumn . '], i.ItemCode'
    );
    $stmt->execute([$groupA, $groupB]);
    $items = $stmt->fetchAll(PDO::FETCH_NUM);

    // Standaard verkoopprijs per artikel (staffl, prijslijst "salesprice"): de nieuwste geldige, in blokken per artikelnummer.
    $codes = array_values(array_unique(array_map(static fn ($r) => trim((string) $r[2]), $items)));
    $stmt = $pdo->prepare('SELECT name FROM sys.columns WHERE object_id = OBJECT_ID(?)');
    $stmt->execute(['staffl']);
    $hasValidFrom = false;
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $column) {
        if (strcasecmp((string) $column, 'validfrom') === 0) {
            $hasValidFrom = true;
        }
    }
    $sales = [];
    foreach (array_chunk($codes, 500) as $chunk) {
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

    $a = [];
    $b = [];
    $seen = [];
    foreach ($items as $r) {
        $isA = trim((string) $r[0]) === $groupA;
        $item = ($isA ? 'A' : 'B') . '|' . trim((string) $r[2]);
        $price = is_numeric($r[3]) ? round((float) $r[3], 4) : null;
        if (!isset($seen[$item])) {
            $seen[$item] = [trim((string) $r[1]), $isA, $price, $sales[trim((string) $r[2])] ?? null];
        } elseif ($seen[$item][2] === null) {
            $seen[$item][2] = $price;
        }
    }
    foreach ($seen as $entry) {
        [$code, $isA, $price, $sale] = $entry;
        $key = inkoopprijzenKey($code, $isA ? $soort['suffixA'] : $soort['suffixB']);
        if ($isA) {
            $a[$key][] = [$code, $price, $sale];
        } else {
            $b[$key][] = [$code, $price, $sale];
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
            $rows[] = [
                $x[0] ?? '', $y[0] ?? '',
                $x[1] ?? '', $y[1] ?? '', $x[2] ?? '', $y[2] ?? '',
            ];
        }
    }

    return [
        'columns' => ['Artikel GR' . $groupA, 'Artikel GR' . $groupB, 'Inkoop GR' . $groupA, 'Inkoop GR' . $groupB, 'Verkoop GR' . $groupA, 'Verkoop GR' . $groupB],
        'numeric' => [false, false, true, true, true, true],
        'dates' => [false, false, false, false, false, false],
        'currency' => ['Inkoop GR' . $groupA, 'Inkoop GR' . $groupB, 'Verkoop GR' . $groupA, 'Verkoop GR' . $groupB],
        'text' => ['Artikel GR' . $groupA, 'Artikel GR' . $groupB],
        'rows' => $rows,
        'kop' => [
            ['label' => 'Artikelen', 'value' => (string) count($rows)],
            ['label' => 'Alleen in GR' . $groupA, 'value' => (string) $onlyA],
            ['label' => 'Alleen in GR' . $groupB, 'value' => (string) $onlyB],
        ],
        'titles' => ['Inkoop- en verkoopprijzen GR' . $groupA . ' naast GR' . $groupB, 'Per regel de twee artikelen die worden vergeleken (' . $soort['uitleg'] . ')'],
        'sheet' => 'GR' . $groupA . ' naast GR' . $groupB,
        'fileTag' => 'GR' . $groupA . '_GR' . $groupB . '_' . date('Y-m-d'),
    ];
}, false);
