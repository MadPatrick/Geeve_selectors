<?php

declare(strict_types=1);

// Beveiligde tegel (Config > Tegels): zonder ontgrendelen geen gegevens.
require_once __DIR__ . '/../../../shared/tiles.php';
tileApiGuard('negatieve-voorraad');

/**
 * Negatieve voorraad zonder (voldoende) bestelling: artikelen met een negatieve voorraad die op een open verkooporder staan en
 * waarvan het tekort niet door open inkooporders wordt gedekt. Alleen lezen, in drie bronnen:
 *  - Voorraad: StockBalances.Quantity tot en met nu (zelfde bron als de voorraad in Stauff).
 *  - Open verkoop: orsrg (esr_aantal - aant_gelev, alleen > 0) bij orkrg.ord_soort = 'V'.
 *  - Besteld: idem bij ord_soort = 'B' (inkooporder), nog niet ontvangen.
 * Tekort = open verkoop - voorraad - besteld; alleen artikelen met tekort > 0.
 */

require_once __DIR__ . '/../../inc.php';

reportApi(function (array $in): array {
    $pdo = reportConnection();

    $stmt = $pdo->prepare(
        'SET NOCOUNT ON; ' .
        'WITH st AS (' .
        'SELECT ItemCode, SUM(Quantity) AS Voorraad FROM StockBalances WITH (NOLOCK) WHERE [Date] <= GETDATE() ' .
        'GROUP BY ItemCode HAVING SUM(Quantity) < 0' .
        '), ov AS (' .
        'SELECT s.artcode AS ItemCode, SUM(s.esr_aantal - s.aant_gelev) AS OpenVerkoop, COUNT(DISTINCT s.ordernr) AS AantalOrders ' .
        'FROM orsrg s WITH (NOLOCK) JOIN orkrg k WITH (NOLOCK) ON k.ordernr = s.ordernr ' .
        "WHERE k.ord_soort = 'V' AND s.esr_aantal > s.aant_gelev " .
        'AND s.artcode IN (SELECT ItemCode FROM st) GROUP BY s.artcode' .
        '), oi AS (' .
        'SELECT s.artcode AS ItemCode, SUM(s.esr_aantal - s.aant_gelev) AS Besteld ' .
        'FROM orsrg s WITH (NOLOCK) JOIN orkrg k WITH (NOLOCK) ON k.ordernr = s.ordernr ' .
        "WHERE k.ord_soort = 'B' AND s.esr_aantal > s.aant_gelev " .
        'AND s.artcode IN (SELECT ItemCode FROM st) GROUP BY s.artcode' .
        ') ' .
        'SELECT RTRIM(st.ItemCode) AS c, RTRIM(i.Description) AS o, LTRIM(RTRIM(i.Assortment)) AS g, st.Voorraad AS v, ' .
        'ov.OpenVerkoop AS ov, ov.AantalOrders AS n, ISNULL(oi.Besteld, 0) AS b, ' .
        'ov.OpenVerkoop - st.Voorraad - ISNULL(oi.Besteld, 0) AS t ' .
        'FROM st JOIN ov ON ov.ItemCode = st.ItemCode ' .
        'LEFT JOIN oi ON oi.ItemCode = st.ItemCode ' .
        'LEFT JOIN Items i WITH (NOLOCK) ON i.ItemCode = st.ItemCode ' .
        'WHERE ov.OpenVerkoop - st.Voorraad - ISNULL(oi.Besteld, 0) > 0 ' .
        'ORDER BY st.ItemCode'
    );
    $stmt->execute();

    $num = static fn ($v) => is_numeric($v) ? round((float) $v, 4) + 0 : 0;
    $rows = [];
    $nietBesteld = 0;
    while ($r = $stmt->fetch(PDO::FETCH_NUM)) {
        $besteld = $num($r[6]);
        if ($besteld <= 0) {
            $nietBesteld++;
        }
        $rows[] = [trim((string) $r[0]), trim((string) $r[1]), trim((string) $r[2]), $num($r[3]), $num($r[4]), (int) $r[5], $besteld, $num($r[7])];
    }

    return [
        'columns' => ['Artikel', 'Omschrijving', 'Groep', 'Voorraad', 'Open verkoop', 'Aantal orders', 'Besteld (open)', 'Tekort'],
        'numeric' => [false, false, false, true, true, true, true, true],
        'dates' => [false, false, false, false, false, false, false, false],
        'text' => ['Artikel', 'Groep'],
        'rows' => $rows,
        'kop' => [
            ['label' => 'Artikelen met tekort', 'value' => (string) count($rows)],
            ['label' => 'Helemaal niet besteld', 'value' => (string) $nietBesteld],
            ['label' => 'Onvoldoende besteld', 'value' => (string) (count($rows) - $nietBesteld)],
        ],
        'titles' => ['Negatieve voorraad zonder (voldoende) bestelling', 'Tekort = open verkoop - voorraad - open inkoop'],
        'fileTag' => 'Negatieve_voorraad_' . date('Y-m-d'),
    ];
}, false);
