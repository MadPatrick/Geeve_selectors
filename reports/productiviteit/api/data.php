<?php

declare(strict_types=1);

/**
 * Productiviteit per medewerker, per dag. Twee soorten (parameter "modus"):
 *  - productie: logistiek afgehandelde (geleverde) verkooporders. Een levering is een regel in het memoriaal (dagboek 90,
 *    tabel amutas) die naar een orderregel verwijst (CSPickITCWOrsrgIDCWDB) en een pakbonnummer heeft
 *    (CSPickITCWDeliveryNoteCWDB). Orders = aantal verschillende pakbonnen, orderregels = aantal verschillende orderregels.
 *  - verkoop: ingevoerde verkooporders (ord_soort V) uit de lopende orders (orkrg/orsrg): wie de order en de orderregels
 *    heeft aangemaakt (zie productiviteitVerkoop).
 * syscreator = medewerker (humres.res_id), syscreated = tijdstip. Het antwoord bevat per telling ("Orders", "Orderregels")
 * een eigen reeks rijen ("sets"); de pagina schakelt daartussen.
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

/**
 * Kleinste ID in amutas waarvan de boeking op of na $timestamp is gemaakt (binaire zoekopdracht op de primaire sleutel).
 * amutas is erg groot en heeft geen index op syscreated; het ID loopt op met de tijd en is wel geïndexeerd, zodat de
 * periode zo snel naar een ID-bereik wordt omgezet. $probe(int $id) geeft het tijdstip (jjjj-mm-dd uu:mm:ss) van de eerste
 * boeking met ID >= $id, of null als die er niet is.
 *
 * @param callable(int): ?string $probe
 */
function productiviteitFirstId(callable $probe, string $timestamp, int $maxId): int
{
    $lo = 1;
    $hi = $maxId + 1;
    while ($lo < $hi) {
        $mid = intdiv($lo + $hi, 2);
        $time = $probe($mid);
        if ($time === null || $time >= $timestamp) {
            $hi = $mid;
        } else {
            $lo = $mid + 1;
        }
    }
    return $lo;
}

/** Productie: afgehandelde (geleverde) verkooporders. @return list<list<mixed>> rijen [meting, medewerker, dag, aantal] */
function productiviteitProductie(PDO $pdo, DateTimeImmutable $from, DateTimeImmutable $to): array
{
    $startParam = $from->format('Y-m-d');
    $endParam = $to->format('Y-m-d');

    // Periode -> ID-bereik van amutas (zie productiviteitFirstId); de datumfilter in de query blijft staan voor de precisie.
    $probeStmt = $pdo->prepare('SET NOCOUNT ON; SELECT TOP 1 CONVERT(char(19), syscreated, 120) AS t FROM amutas WITH (NOLOCK) WHERE ID >= ? ORDER BY ID');
    $probe = function (int $id) use ($probeStmt): ?string {
        $probeStmt->execute([$id]);
        $row = $probeStmt->fetch(PDO::FETCH_NUM);
        $probeStmt->closeCursor();
        return $row === false || $row[0] === null ? null : (string) $row[0];
    };
    $maxId = (int) $pdo->query('SET NOCOUNT ON; SELECT MAX(ID) FROM amutas WITH (NOLOCK)')->fetchColumn();
    $firstId = productiviteitFirstId($probe, $startParam . ' 00:00:00', $maxId);
    $endId = productiviteitFirstId($probe, $to->modify('+1 day')->format('Y-m-d') . ' 00:00:00', $maxId); // eerste ID ná de periode

    $base = "FROM amutas s WITH (NOLOCK) WHERE s.ID >= {$firstId} AND s.ID < {$endId} AND LTRIM(RTRIM(s.dagbknr)) = '90' AND s.CSPickITCWOrsrgIDCWDB <> 0 " .
        "AND ISNULL(RTRIM(s.CSPickITCWDeliveryNoteCWDB), '') <> '' AND s.syscreated >= ? AND s.syscreated < DATEADD(day, 1, ?) " .
        'GROUP BY s.syscreator, CONVERT(char(10), s.syscreated, 23)';
    $sql = 'SET NOCOUNT ON; ' .
        "SELECT 'O' AS meting, s.syscreator AS medewerker, CONVERT(char(10), s.syscreated, 23) AS dag, COUNT(DISTINCT RTRIM(s.CSPickITCWDeliveryNoteCWDB)) AS aantal {$base} " .
        'UNION ALL ' .
        "SELECT 'L', s.syscreator, CONVERT(char(10), s.syscreated, 23), COUNT(DISTINCT s.CSPickITCWOrsrgIDCWDB) {$base}";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$startParam, $endParam, $startParam, $endParam]);
    return $stmt->fetchAll(PDO::FETCH_NUM);
}

/**
 * Verkoop: ingevoerde verkooporders (ord_soort V). @return list<list<mixed>> rijen [meting, medewerker, dag, aantal]
 *
 * Alleen de lopende orders (orkrg/orsrg): daar is syscreator/syscreated echt degene die de order invoerde. In de
 * orderhistorie (orhkrg/orhsrg) staat de medewerker die de order heeft verplaatst/afgehandeld, dus die telt hier niet mee.
 * Orderregels tellen alleen als ze zijn aangemaakt door dezelfde medewerker als de orderkop, zodat regels die het magazijn
 * later toevoegt of splitst (bijv. bij een deellevering) niet als verkoop-invoer meetellen.
 */
function productiviteitVerkoop(PDO $pdo, DateTimeImmutable $from, DateTimeImmutable $to): array
{
    $startParam = $from->format('Y-m-d');
    $endParam = $to->format('Y-m-d');

    $sql = 'SET NOCOUNT ON; ' .
        "SELECT 'O' AS meting, k.syscreator AS medewerker, CONVERT(char(10), k.syscreated, 23) AS dag, COUNT(DISTINCT k.ordernr) AS aantal " .
        "FROM orkrg k WITH (NOLOCK) WHERE k.ord_soort = 'V' AND k.syscreated >= ? AND k.syscreated < DATEADD(day, 1, ?) " .
        'GROUP BY k.syscreator, CONVERT(char(10), k.syscreated, 23) ' .
        'UNION ALL ' .
        "SELECT 'L', r.syscreator, CONVERT(char(10), r.syscreated, 23), COUNT(*) " .
        'FROM orsrg r WITH (NOLOCK) INNER JOIN orkrg k WITH (NOLOCK) ON k.ordernr = r.ordernr ' .
        "WHERE k.ord_soort = 'V' AND r.syscreator = k.syscreator AND LTRIM(RTRIM(ISNULL(r.artcode, ''))) <> '' " .
        'AND r.syscreated >= ? AND r.syscreated < DATEADD(day, 1, ?) ' .
        'GROUP BY r.syscreator, CONVERT(char(10), r.syscreated, 23)';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$startParam, $endParam, $startParam, $endParam]);
    return $stmt->fetchAll(PDO::FETCH_NUM);
}

reportApi(function (array $in): array {
    $mode = (string) ($in['modus'] ?? 'productie');
    if (!in_array($mode, ['productie', 'verkoop'], true)) {
        throw new RuntimeException('Onbekende keuze.');
    }
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
    $result = $mode === 'productie' ? productiviteitProductie($pdo, $from, $to) : productiviteitVerkoop($pdo, $from, $to);

    // [medewerker][meting][dag] = aantal
    $counts = [];
    foreach ($result as $r) {
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

    // Kolommen: Medewerker, een kolom per dag, Totaal.
    $weekdays = ['zo', 'ma', 'di', 'wo', 'do', 'vr', 'za'];
    $dayKeys = [];
    $columns = ['Medewerker'];
    for ($d = $from; $d <= $to; $d = $d->modify('+1 day')) {
        $dayKeys[] = $d->format('Y-m-d');
        $columns[] = $weekdays[(int) $d->format('w')] . ' ' . $d->format('d-m');
    }
    $columns[] = 'Totaal';

    $employees = array_keys($counts);
    usort($employees, fn ($a, $b) => strcasecmp($names[$a] ?? ('Medewerker ' . $a), $names[$b] ?? ('Medewerker ' . $b)));

    // Per telling een eigen reeks rijen, met onderaan een totaalregel.
    $sets = [];
    $grand = [];
    foreach (['O' => 'Orders', 'L' => 'Orderregels'] as $m => $setLabel) {
        $rows = [];
        $dayTotals = array_fill(0, count($dayKeys), 0);
        foreach ($employees as $emp) {
            $perDay = $counts[$emp][$m] ?? [];
            if ($perDay === []) {
                continue;
            }
            $row = [$names[$emp] ?? ('Medewerker ' . $emp)];
            $total = 0;
            foreach ($dayKeys as $i => $key) {
                $n = $perDay[$key] ?? 0;
                $total += $n;
                $dayTotals[$i] += $n;
                $row[] = $n > 0 ? $n : null;
            }
            $row[] = $total;
            $rows[] = $row;
        }
        if ($rows !== []) {
            $grand[$m] = array_sum($dayTotals);
            $rows[] = array_merge(['Totaal'], array_map(fn ($n) => $n > 0 ? $n : null, $dayTotals), [$grand[$m]]);
        } else {
            $grand[$m] = 0;
        }
        $sets[$setLabel] = $rows;
    }

    $period = $from->format('d-m-Y') . ' t/m ' . $to->format('d-m-Y');
    $modeLabel = $mode === 'productie' ? 'Productie (afgehandelde orders)' : 'Verkoop (ingevoerde orders)';
    $kop = [
        ['label' => 'Periode', 'value' => $period],
        ['label' => 'Medewerkers', 'value' => (string) count($employees)],
        ['label' => 'Totaal', 'value' => number_format($grand['O'], 0, ',', '.') . ' orders / ' . number_format($grand['L'], 0, ',', '.') . ' regels'],
    ];

    $count = count($columns);
    return [
        'columns' => $columns,
        'numeric' => array_merge([false], array_fill(0, $count - 1, true)),
        'dates' => array_fill(0, $count, false),
        'rows' => $sets['Orders'],
        'sets' => $sets,
        'kop' => $kop,
        'hideRowCount' => true,
        'titles' => ['Productiviteit per medewerker - ' . $modeLabel, '{meting}, ' . $period],
        'fileTag' => $mode . '_' . $from->format('Y-m-d') . '_' . $to->format('Y-m-d'),
    ];
}, false);
