<?php

declare(strict_types=1);

/**
 * Inkoop per groep: per artikel van een artikelgroep (Items.Assortment, zoals bij Kortingstructuur) het aantal dat in de
 * gekozen periode op inkooporders is besteld. Lees-query op orkrg (orderkop) + orsrg (orderregels) + Items in database 005,
 * dezelfde tabellen als de view 1655 VW_Alle_Ordersplus_verkoop_inkoop in Exactrapportage.
 */

require_once __DIR__ . '/../../inc.php';

/** orkrg.ord_soort van een inkooporder. */
const PURCHASE_ORDER_TYPE = 'K';

function inkoopGroepDate(mixed $value, string $label): DateTimeImmutable
{
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', trim((string) $value));
    if ($d === false || $d->format('Y-m-d') !== trim((string) $value)) {
        throw new RuntimeException('Vul een geldige datum ' . $label . ' in.');
    }
    return $d;
}

reportApi(function (array $in): array {
    $group = trim((string) ($in['groep'] ?? ''));
    if ($group === '' || preg_match('/^[A-Za-z0-9._ -]{1,20}$/', $group) !== 1) {
        throw new RuntimeException('Vul een geldige artikelgroep in.');
    }
    $from = inkoopGroepDate($in['van'] ?? '', 'van');
    $to = inkoopGroepDate($in['tot'] ?? '', 'tot');
    if ($from > $to) {
        throw new RuntimeException('De datum "van" ligt na de datum "tot".');
    }

    $pdo = reportConnection();
    $stmt = $pdo->prepare('SET NOCOUNT ON; SELECT TOP 1 ISNULL(RTRIM(Description_0), \'\') AS d FROM ItemAssortment WHERE LTRIM(RTRIM(Assortment)) = ?');
    $stmt->execute([$group]);
    $row = $stmt->fetch();
    if ($row === false) {
        throw new RuntimeException('Artikelgroep "' . $group . '" bestaat niet in Exact.');
    }
    $description = trim((string) $row['d']);

    // Periode: orderdatum van "van" tot en met "tot".
    $stmt = $pdo->prepare(
        'SET NOCOUNT ON; ' .
        'SELECT RTRIM(i.ItemCode) AS [Artikelnummer], RTRIM(i.Description) AS [Omschrijving], RTRIM(i.eenh_verk) AS [Eenheid], ' .
        'SUM(ISNULL(r.esr_aantal, 0)) AS [Besteld], COUNT(DISTINCT k.ordernr) AS [Aantal orders] ' .
        'FROM orkrg k WITH (NOLOCK) ' .
        'INNER JOIN orsrg r WITH (NOLOCK) ON r.ordernr = k.ordernr ' .
        'INNER JOIN Items i WITH (NOLOCK) ON i.ItemCode = r.artcode ' .
        'WHERE k.ord_soort = ? AND LTRIM(RTRIM(i.Assortment)) = ? AND k.orddat >= ? AND k.orddat < DATEADD(day, 1, ?) ' .
        'GROUP BY i.ItemCode, i.Description, i.eenh_verk ORDER BY i.ItemCode'
    );
    $stmt->execute([PURCHASE_ORDER_TYPE, $group, $from->format('Y-m-d'), $to->format('Y-m-d')]);

    $columns = ['Artikelnummer', 'Omschrijving', 'Eenheid', 'Besteld', 'Aantal orders'];
    $rows = [];
    $total = 0.0;
    while ($r = $stmt->fetch(PDO::FETCH_NUM)) {
        $qty = is_numeric($r[3]) ? $r[3] + 0 : 0;
        $total += $qty;
        $rows[] = [rtrim((string) $r[0]), rtrim((string) $r[1]), rtrim((string) $r[2]), $qty, (int) $r[4]];
    }

    $period = $from->format('d-m-Y') . ' t/m ' . $to->format('d-m-Y');
    $kop = [
        ['label' => 'Groep', 'value' => $group . ($description !== '' ? ' - ' . $description : '')],
        ['label' => 'Periode', 'value' => $period],
        ['label' => 'Totaal besteld', 'value' => rtrim(rtrim(number_format($total, 2, ',', '.'), '0'), ',')],
    ];

    return [
        'columns' => $columns,
        'numeric' => [false, false, false, true, true],
        'dates' => [false, false, false, false, false],
        'rows' => $rows,
        'text' => ['Artikelnummer'],
        'kop' => $kop,
        'titles' => ['Inkoop per groep ' . $group . ($description !== '' ? ' - ' . $description : ''), 'Periode ' . $period],
        'fileTag' => preg_replace('/[^A-Za-z0-9._-]+/', '_', $group) . '_' . $from->format('Y-m-d') . '_' . $to->format('Y-m-d'),
    ];
}, false);
