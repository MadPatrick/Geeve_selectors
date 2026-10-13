<?php

declare(strict_types=1);

/**
 * Productiviteit per medewerker: per medewerker en dag het aantal verkooporders en orderregels dat logistiek is afgehandeld
 * (geleverd). Een levering is een regel in het memoriaal (dagboek 90, tabel amutas) die naar een orderregel verwijst
 * (CSPickITCWOrsrgIDCWDB) en een pakbonnummer heeft (CSPickITCWDeliveryNoteCWDB). syscreator = medewerker (humres.res_id),
 * syscreated = tijdstip van de levering. Orders = aantal verschillende pakbonnen, orderregels = aantal verschillende orderregels.
 * Lees-query in database 005; productieorders (ord_soort A) zijn nog niet opgenomen.
 */

require_once __DIR__ . '/../../inc.php';

const PRODUCTIVITEIT_MAX_DAYS = 62;

function productiviteitDate(mixed $value, string $label): DateTimeImmutable
{
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', trim((string) $value));
    if ($d === false || $d->format('Y-m-d') !== trim((string) $value)) {
        throw new RuntimeException('Vul een geldige datum ' . $label . ' in.');
    }
    return $d;
}

reportApi(function (array $in): array {
    $from = productiviteitDate($in['van'] ?? '', 'van');
    $to = productiviteitDate($in['tot'] ?? '', 'tot');
    if ($from > $to) {
        throw new RuntimeException('De datum "van" ligt na de datum "tot".');
    }
    $days = (int) $from->diff($to)->days + 1;
    if ($days > PRODUCTIVITEIT_MAX_DAYS) {
        throw new RuntimeException('Kies een periode van maximaal ' . PRODUCTIVITEIT_MAX_DAYS . ' dagen.');
    }

    $pdo = reportConnection();
    $startParam = $from->format('Y-m-d');
    $endParam = $to->format('Y-m-d');

    $base = "FROM amutas s WITH (NOLOCK) WHERE LTRIM(RTRIM(s.dagbknr)) = '90' AND s.CSPickITCWOrsrgIDCWDB <> 0 " .
        "AND ISNULL(RTRIM(s.CSPickITCWDeliveryNoteCWDB), '') <> '' AND s.syscreated >= ? AND s.syscreated < DATEADD(day, 1, ?) " .
        'GROUP BY s.syscreator, CONVERT(char(10), s.syscreated, 23)';
    $sql = 'SET NOCOUNT ON; ' .
        "SELECT 'O' AS meting, s.syscreator AS medewerker, CONVERT(char(10), s.syscreated, 23) AS dag, COUNT(DISTINCT RTRIM(s.CSPickITCWDeliveryNoteCWDB)) AS aantal {$base} " .
        'UNION ALL ' .
        "SELECT 'L', s.syscreator, CONVERT(char(10), s.syscreated, 23), COUNT(DISTINCT s.CSPickITCWOrsrgIDCWDB) {$base}";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$startParam, $endParam, $startParam, $endParam]);

    // [medewerker][meting][dag] = aantal
    $counts = [];
    while ($r = $stmt->fetch(PDO::FETCH_NUM)) {
        $counts[(int) $r[1]][(string) $r[0]][(string) $r[2]] = (int) $r[3];
    }

    // Namen van de medewerkers.
    $names = [];
    $ids = array_keys($counts);
    if ($ids !== []) {
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $nm = $pdo->prepare("SET NOCOUNT ON; SELECT res_id, RTRIM(fullname) AS naam FROM humres WHERE res_id IN ({$ph})");
        $nm->execute($ids);
        foreach ($nm->fetchAll() as $row) {
            $names[(int) $row['res_id']] = trim((string) $row['naam']);
        }
    }

    // Kolommen: Medewerker, Type, Meting, een kolom per dag, Totaal.
    $weekdays = ['zo', 'ma', 'di', 'wo', 'do', 'vr', 'za'];
    $dayKeys = [];
    $columns = ['Medewerker', 'Meting'];
    for ($d = $from; $d <= $to; $d = $d->modify('+1 day')) {
        $dayKeys[] = $d->format('Y-m-d');
        $columns[] = $weekdays[(int) $d->format('w')] . ' ' . $d->format('d-m');
    }
    $columns[] = 'Totaal';

    $employees = array_keys($counts);
    usort($employees, fn ($a, $b) => strcasecmp($names[$a] ?? ('Medewerker ' . $a), $names[$b] ?? ('Medewerker ' . $b)));

    $rows = [];
    $grand = ['O' => 0, 'L' => 0];
    foreach ($employees as $emp) {
        $label = $names[$emp] ?? ('Medewerker ' . $emp);
        foreach (['O' => 'Orders', 'L' => 'Orderregels'] as $m => $mLabel) {
            $perDay = $counts[$emp][$m] ?? [];
            if ($perDay === []) {
                continue;
            }
            $row = [$label, $mLabel];
            $total = 0;
            foreach ($dayKeys as $key) {
                $n = $perDay[$key] ?? 0;
                $total += $n;
                $row[] = $n > 0 ? $n : null;
            }
            $row[] = $total;
            $grand[$m] += $total;
            $rows[] = $row;
        }
    }

    $period = $from->format('d-m-Y') . ' t/m ' . $to->format('d-m-Y');
    $kop = [
        ['label' => 'Periode', 'value' => $period],
        ['label' => 'Medewerkers', 'value' => (string) count($employees)],
        ['label' => 'Totaal', 'value' => number_format($grand['O'], 0, ',', '.') . ' orders / ' . number_format($grand['L'], 0, ',', '.') . ' regels'],
    ];

    $count = count($columns);
    return [
        'columns' => $columns,
        'numeric' => array_merge([false, false], array_fill(0, $count - 2, true)),
        'dates' => array_fill(0, $count, false),
        'rows' => $rows,
        'kop' => $kop,
        'titles' => ['Productiviteit per medewerker', 'Afgehandelde (geleverde) orders en orderregels, ' . $period],
        'fileTag' => $from->format('Y-m-d') . '_' . $to->format('Y-m-d'),
    ];
}, false);
