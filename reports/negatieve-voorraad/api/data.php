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
 *  - Open productie: idem bij ord_soort = 'A' (productieorder, regels nog niet uitgegeven/ontvangen). Aanname: de regels
 *    van een productieorder zijn de benodigde onderdelen (zelfde tabellen als Productiviteit); controleer dit met een voorbeeld.
 *  - Besteld: idem bij ord_soort = 'B' (inkooporder), nog niet ontvangen.
 * Tekort = open verkoop + open productie - voorraad - besteld. Twee soorten rijen: "Tekort op order" (op een open order en tekort > 0)
 * en "Negatief zonder order" (negatieve voorraad zonder enige open verkoop- of productieorder). Artikelgroepen groter dan 900 worden weggelaten.
 */

require_once __DIR__ . '/../../inc.php';

reportApi(function (array $in): array {
    $pdo = reportConnection();

    $open = static function (string $soort): string {
        return 'SELECT s.artcode AS ItemCode, SUM(s.esr_aantal - s.aant_gelev) AS Aantal, COUNT(DISTINCT s.ordernr) AS AantalOrders ' .
            'FROM orsrg s WITH (NOLOCK) JOIN orkrg k WITH (NOLOCK) ON k.ordernr = s.ordernr ' .
            "WHERE k.ord_soort = '{$soort}' AND s.esr_aantal > s.aant_gelev " .
            'AND s.artcode IN (SELECT ItemCode FROM st) GROUP BY s.artcode';
    };

    $stmt = $pdo->prepare(
        'SET NOCOUNT ON; ' .
        'WITH st AS (' .
        'SELECT ItemCode, SUM(Quantity) AS Voorraad FROM StockBalances WITH (NOLOCK) WHERE [Date] <= GETDATE() ' .
        'GROUP BY ItemCode HAVING ROUND(SUM(Quantity), 2) < 0' .
        '), ov AS (' . $open('V') .
        '), op AS (' . $open('A') .
        '), oi AS (' . $open('B') .
        '), t AS (' .
        'SELECT st.ItemCode, st.Voorraad, ISNULL(ov.Aantal, 0) AS OpenVerkoop, ISNULL(op.Aantal, 0) AS OpenProductie, ' .
        'ISNULL(ov.AantalOrders, 0) + ISNULL(op.AantalOrders, 0) AS AantalOrders, ISNULL(oi.Aantal, 0) AS Besteld, ' .
        'ISNULL(ov.Aantal, 0) + ISNULL(op.Aantal, 0) - st.Voorraad - ISNULL(oi.Aantal, 0) AS Tekort ' .
        'FROM st LEFT JOIN ov ON ov.ItemCode = st.ItemCode LEFT JOIN op ON op.ItemCode = st.ItemCode LEFT JOIN oi ON oi.ItemCode = st.ItemCode' .
        ') ' .
        'SELECT RTRIM(t.ItemCode) AS c, RTRIM(i.Description) AS o, LTRIM(RTRIM(i.Assortment)) AS g, t.Voorraad, t.OpenVerkoop, t.OpenProductie, ' .
        't.AantalOrders, t.Besteld, t.Tekort, ' .
        "CASE WHEN t.OpenVerkoop + t.OpenProductie > 0 THEN 'Tekort op order' ELSE 'Negatief zonder order' END AS Soort " .
        'FROM t LEFT JOIN Items i WITH (NOLOCK) ON i.ItemCode = t.ItemCode ' .
        'WHERE ISNULL(TRY_CONVERT(int, LTRIM(RTRIM(i.Assortment))), 0) <= 900 ' .
        'AND (t.OpenVerkoop + t.OpenProductie = 0 OR t.Tekort > 0) ' .
        'ORDER BY Soort DESC, t.ItemCode'
    );
    $stmt->execute();

    $num = static fn ($v) => is_numeric($v) ? round((float) $v, 2) + 0 : 0;   // 2 decimalen: reken-ruis als -0,00001 wordt anders "-0"
    $rows = [];
    $zonderOrder = 0;
    $nietBesteld = 0;
    while ($r = $stmt->fetch(PDO::FETCH_NUM)) {
        $soort = (string) $r[9];
        if ($soort === 'Negatief zonder order') {
            $zonderOrder++;
        } elseif ($num($r[7]) <= 0) {
            $nietBesteld++;
        }
        $rows[] = [trim((string) $r[0]), trim((string) $r[1]), trim((string) $r[2]), $soort, $num($r[3]), $num($r[4]), $num($r[5]), (int) $r[6], $num($r[7]), $num($r[8])];
    }
    $metTekort = count($rows) - $zonderOrder;

    return [
        'columns' => ['Artikel', 'Omschrijving', 'Groep', 'Soort', 'Voorraad', 'Open verkoop', 'Open productie', 'Aantal orders', 'Besteld (open)', 'Tekort'],
        'numeric' => [false, false, false, false, true, true, true, true, true, true],
        'dates' => [false, false, false, false, false, false, false, false, false, false],
        'text' => ['Artikel', 'Groep'],
        'rows' => $rows,
        'kop' => [
            ['label' => 'Tekort op order', 'value' => (string) $metTekort],
            ['label' => 'Daarvan helemaal niet besteld', 'value' => (string) $nietBesteld],
            ['label' => 'Negatief zonder order', 'value' => (string) $zonderOrder],
        ],
        'titles' => ['Negatieve voorraad: tekort op order en negatief zonder order', 'Tekort = open verkoop + open productie - voorraad - open inkoop'],
        'fileTag' => 'Negatieve_voorraad_' . date('Y-m-d'),
    ];
}, false);
