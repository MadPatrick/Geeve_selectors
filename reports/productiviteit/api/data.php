<?php

declare(strict_types=1);

/**
 * Productiviteit per medewerker: per medewerker en dag het aantal orders en orderregels dat is aangemaakt, voor
 * verkoop (ord_soort V) en inkoop (ord_soort B) apart. Lees-query op orkrg/orsrg (open orders) en orhkrg/orhsrg
 * (afgehandelde orders, de historie) in database 005. syscreator = medewerker (humres.res_id), syscreated = tijdstip.
 */

require_once __DIR__ . '/../../inc.php';

const PRODUCTIVITEIT_TYPES = ['V' => 'Verkoop', 'B' => 'Inkoop'];
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
    $types = array_keys(PRODUCTIVITEIT_TYPES);
    $in2 = implode(',', array_fill(0, count($types), '?'));
    $startParam = $from->format('Y-m-d');
    $endParam = $to->format('Y-m-d');

    // Open orders (orkrg/orsrg) en afgehandelde orders (orhkrg/orhsrg) samen.
    $headers = "SELECT ordernr, ord_soort, syscreator, syscreated FROM orkrg WITH (NOLOCK) UNION ALL SELECT ordernr, ord_soort, syscreator, syscreated FROM orhkrg WITH (NOLOCK)";
    $lines = "SELECT ordernr, artcode, syscreator, syscreated FROM orsrg WITH (NOLOCK) UNION ALL SELECT ordernr, artcode, syscreator, syscreated FROM orhsrg WITH (NOLOCK)";

    $sql = 'SET NOCOUNT ON; ' .
        "SELECT 'O' AS meting, k.ord_soort AS soort, k.syscreator AS medewerker, CONVERT(char(10), k.syscreated, 23) AS dag, COUNT(DISTINCT k.ordernr) AS aantal " .
        "FROM ({$headers}) k WHERE k.ord_soort IN ({$in2}) AND k.syscreated >= ? AND k.syscreated < DATEADD(day, 1, ?) " .
        'GROUP BY k.ord_soort, k.syscreator, CONVERT(char(10), k.syscreated, 23) ' .
        'UNION ALL ' .
        "SELECT 'L', k.ord_soort, r.syscreator, CONVERT(char(10), r.syscreated, 23), COUNT(*) " .
        "FROM ({$lines}) r INNER JOIN ({$headers}) k ON k.ordernr = r.ordernr " .
        "WHERE k.ord_soort IN ({$in2}) AND LTRIM(RTRIM(ISNULL(r.artcode, ''))) <> '' AND r.syscreated >= ? AND r.syscreated < DATEADD(day, 1, ?) " .
        'GROUP BY k.ord_soort, r.syscreator, CONVERT(char(10), r.syscreated, 23)';

    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge($types, [$startParam, $endParam], $types, [$startParam, $endParam]));

    // [medewerker][soort][meting][dag] = aantal
    $counts = [];
    while ($r = $stmt->fetch(PDO::FETCH_NUM)) {
        $counts[(int) $r[2]][(string) $r[1]][(string) $r[0]][(string) $r[3]] = (int) $r[4];
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
    $columns = ['Medewerker', 'Type', 'Meting'];
    for ($d = $from; $d <= $to; $d = $d->modify('+1 day')) {
        $dayKeys[] = $d->format('Y-m-d');
        $columns[] = $weekdays[(int) $d->format('w')] . ' ' . $d->format('d-m');
    }
    $columns[] = 'Totaal';

    $employees = array_keys($counts);
    usort($employees, fn ($a, $b) => strcasecmp($names[$a] ?? ('Medewerker ' . $a), $names[$b] ?? ('Medewerker ' . $b)));

    $rows = [];
    $grand = ['V' => ['O' => 0, 'L' => 0], 'B' => ['O' => 0, 'L' => 0]];
    foreach ($employees as $emp) {
        $label = $names[$emp] ?? ('Medewerker ' . $emp);
        foreach (PRODUCTIVITEIT_TYPES as $code => $typeLabel) {
            foreach (['O' => 'Orders', 'L' => 'Orderregels'] as $m => $mLabel) {
                $perDay = $counts[$emp][$code][$m] ?? [];
                if ($perDay === []) {
                    continue;
                }
                $row = [$label, $typeLabel, $mLabel];
                $total = 0;
                foreach ($dayKeys as $key) {
                    $n = $perDay[$key] ?? 0;
                    $total += $n;
                    $row[] = $n > 0 ? $n : null;
                }
                $row[] = $total;
                $grand[$code][$m] += $total;
                $rows[] = $row;
            }
        }
    }

    $period = $from->format('d-m-Y') . ' t/m ' . $to->format('d-m-Y');
    $kop = [['label' => 'Periode', 'value' => $period], ['label' => 'Medewerkers', 'value' => (string) count($employees)]];
    foreach (PRODUCTIVITEIT_TYPES as $code => $typeLabel) {
        $kop[] = ['label' => $typeLabel, 'value' => number_format($grand[$code]['O'], 0, ',', '.') . ' orders / ' . number_format($grand[$code]['L'], 0, ',', '.') . ' regels'];
    }

    $count = count($columns);
    return [
        'columns' => $columns,
        'numeric' => array_merge([false, false, false], array_fill(0, $count - 3, true)),
        'dates' => array_fill(0, $count, false),
        'rows' => $rows,
        'kop' => $kop,
        'titles' => ['Productiviteit per medewerker', 'Orders en orderregels aangemaakt, ' . $period],
        'fileTag' => $from->format('Y-m-d') . '_' . $to->format('Y-m-d'),
    ];
}, false);
