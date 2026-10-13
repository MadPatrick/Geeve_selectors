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

/** Gebruikers (syscreator) die geen medewerker zijn maar een systeemaccount ('d ADMINISTRATOR', 'D4D Gebruiker'); tellen niet mee bij Verkoop. */
const PRODUCTIVITEIT_VERKOOP_EXCLUDE = [14, 64];

/** Medewerkers die geen verkooporders invoeren: hoofdletterongevoelig deel van de naam (humres.fullname); tellen niet mee bij Verkoop. */
const PRODUCTIVITEIT_VERKOOP_EXCLUDE_NAMES = ['Anton Steenland', 'Geert Steenland', 'Monteiro Rodrigues', 'Jerzy van den Bosch'];

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

/**
 * ID-bereik [eerste, einde) van een grote tabel voor de gekozen periode (zie productiviteitFirstId). $table is een vaste
 * tabelnaam uit de code (nooit gebruikersinvoer).
 *
 * @return array{0: int, 1: int}
 */
function productiviteitIdRange(PDO $pdo, string $table, DateTimeImmutable $from, DateTimeImmutable $to): array
{
    $probeStmt = $pdo->prepare("SET NOCOUNT ON; SELECT TOP 1 CONVERT(char(19), syscreated, 120) AS t FROM {$table} WITH (NOLOCK) WHERE ID >= ? ORDER BY ID");
    $probe = function (int $id) use ($probeStmt): ?string {
        $probeStmt->execute([$id]);
        $row = $probeStmt->fetch(PDO::FETCH_NUM);
        $probeStmt->closeCursor();
        return $row === false || $row[0] === null ? null : (string) $row[0];
    };
    $maxId = (int) $pdo->query("SET NOCOUNT ON; SELECT MAX(ID) FROM {$table} WITH (NOLOCK)")->fetchColumn();
    return [
        productiviteitFirstId($probe, $from->format('Y-m-d') . ' 00:00:00', $maxId),
        productiviteitFirstId($probe, $to->modify('+1 day')->format('Y-m-d') . ' 00:00:00', $maxId), // eerste ID ná de periode
    ];
}

/** Productie: afgehandelde (geleverde) verkooporders. @return list<list<mixed>> rijen [meting, medewerker, dag, aantal] */
function productiviteitProductie(PDO $pdo, DateTimeImmutable $from, DateTimeImmutable $to): array
{
    $startParam = $from->format('Y-m-d');
    $endParam = $to->format('Y-m-d');

    // Periode -> ID-bereik van amutas (zie productiviteitFirstId); de datumfilter in de query blijft staan voor de precisie.
    [$firstId, $endId] = productiviteitIdRange($pdo, 'amutas', $from, $to);

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
 * Alleen de lopende orders (orkrg/orsrg): daar is syscreator echt degene die de order invoerde. In de orderhistorie
 * (orhkrg/orhsrg) staat de medewerker die de order heeft verplaatst/afgehandeld, dus die telt hier niet mee.
 *
 * Dag = de latere van orderdatum (orddat) en aanmaakdatum (syscreated). Wordt een offerte tot order verwerkt, dan houdt
 * Exact de oorspronkelijke aanmaakdatum aan en zet de orderdatum op de dag van verwerken; die dag telt dan. Bij een
 * rechtstreeks ingevoerde order zijn beide gelijk. Omgezette offertes tot 90 dagen oud worden gevonden.
 * Orderregels tellen op dezelfde dag en bij dezelfde medewerker als de orderkop.
 */
function productiviteitVerkoop(PDO $pdo, DateTimeImmutable $from, DateTimeImmutable $to): array
{
    $startParam = $from->format('Y-m-d');
    $endParam = $to->format('Y-m-d');

    $exclude = implode(',', array_map('intval', PRODUCTIVITEIT_VERKOOP_EXCLUDE));
    // orkrg heeft geen index op de datum: de periode wordt omgezet naar een ID-bereik (een omgezette offerte heeft een
    // ouder ID, vandaar 90 dagen extra aan de voorkant).
    [$kFirst, $kEnd] = productiviteitIdRange($pdo, 'orkrg', $from->modify('-90 days'), $to);

    $orders = 'WITH o AS (SELECT k.ordernr, k.syscreator, CONVERT(char(10), CASE WHEN CAST(k.orddat AS date) > CAST(k.syscreated AS date) THEN k.orddat ELSE k.syscreated END, 23) AS dag ' .
        "FROM orkrg k WITH (NOLOCK) WHERE k.ID >= {$kFirst} AND k.ID < {$kEnd} AND k.ord_soort = 'V' AND k.syscreator NOT IN ({$exclude})) ";
    $sql = 'SET NOCOUNT ON; ' . $orders .
        "SELECT 'O' AS meting, o.syscreator AS medewerker, o.dag AS dag, COUNT(*) AS aantal FROM o WHERE o.dag >= ? AND o.dag <= ? GROUP BY o.syscreator, o.dag " .
        'UNION ALL ' .
        "SELECT 'L', o.syscreator, o.dag, COUNT(*) FROM o INNER JOIN orsrg r WITH (NOLOCK) ON r.ordernr = o.ordernr " .
        "WHERE o.dag >= ? AND o.dag <= ? AND LTRIM(RTRIM(ISNULL(r.artcode, ''))) <> '' GROUP BY o.syscreator, o.dag";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$startParam, $endParam, $startParam, $endParam]);
    return $stmt->fetchAll(PDO::FETCH_NUM);
}

reportApi(function (array $in): array {
    $mode = (string) ($in['modus'] ?? 'productie');
    if (!in_array($mode, ['productie', 'verkoop'], true)) {
        throw new RuntimeException('Onbekende keuze.');
    }
    $overview = (string) ($in['weergave'] ?? 'overzicht') === 'overzicht';
    if ($overview) {
        // Vaste kolommen ten opzichte van vandaag: werkdagen van deze week, deze week, weken van deze maand, deze maand.
        $to = new DateTimeImmutable('today');
        $monthStart = $to->modify('first day of this month');
        $monday = $to->modify('monday this week');
        $from = min($monthStart, $monday);
    } else {
        $from = productiviteitDate($in['van'] ?? '', 'van');
        $to = productiviteitDate($in['tot'] ?? '', 'tot');
        if ($from > $to) {
            throw new RuntimeException('De datum "van" ligt na de datum "tot".');
        }
        $days = (int) $from->diff($to)->days + 1;
        if ($days > PRODUCTIVITEIT_MAX_DAYS) {
            throw new RuntimeException('Kies een periode van maximaal ' . PRODUCTIVITEIT_MAX_DAYS . ' dagen.');
        }
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

    // Verkoop: medewerkers die geen verkooporders invoeren (op naam) overslaan.
    if ($mode === 'verkoop') {
        foreach ($names as $id => $fullName) {
            foreach (PRODUCTIVITEIT_VERKOOP_EXCLUDE_NAMES as $part) {
                if (stripos($fullName, $part) !== false) {
                    unset($counts[$id]);
                    break;
                }
            }
        }
    }

    // Kolomspecificaties: [label, van, tot, is_som]. Een som-kolom telt de dagen in dat bereik op.
    $weekdays = ['zo', 'ma', 'di', 'wo', 'do', 'vr', 'za'];
    $specs = [];
    if ($overview) {
        for ($d = $monday; $d <= $to; $d = $d->modify('+1 day')) {
            if ((int) $d->format('N') <= 5) {
                $specs[] = [$weekdays[(int) $d->format('w')] . ' ' . $d->format('d-m'), $d, $d, false];
            }
        }
        $specs[] = ['Deze week', $monday, $to, true];
        for ($weekStart = $monthStart->modify('monday this week'); $weekStart <= $to; $weekStart = $weekStart->modify('+7 days')) {
            $specs[] = ['Week ' . $weekStart->format('W'), max($weekStart, $monthStart), min($weekStart->modify('+6 days'), $to), true];
        }
        $specs[] = ['Deze maand', $monthStart, $to, true];
    } else {
        for ($d = $from; $d <= $to; $d = $d->modify('+1 day')) {
            $specs[] = [$weekdays[(int) $d->format('w')] . ' ' . $d->format('d-m'), $d, $d, false];
        }
        $specs[] = ['Totaal', $from, $to, true];
    }
    $columns = array_merge(['Medewerker'], array_map(fn ($sp) => $sp[0], $specs));
    $sumColumns = array_values(array_map(fn ($sp) => $sp[0], array_filter($specs, fn ($sp) => $sp[3])));

    $sumRange = function (array $perDay, DateTimeImmutable $a, DateTimeImmutable $b): int {
        $n = 0;
        for ($d = $a; $d <= $b; $d = $d->modify('+1 day')) {
            $n += $perDay[$d->format('Y-m-d')] ?? 0;
        }
        return $n;
    };

    $employees = array_keys($counts);
    usort($employees, fn ($a, $b) => strcasecmp($names[$a] ?? ('Medewerker ' . $a), $names[$b] ?? ('Medewerker ' . $b)));

    // Per telling een eigen reeks rijen, met onderaan een totaalregel.
    $sets = [];
    $grand = [];
    foreach (['O' => 'Orders', 'L' => 'Orderregels'] as $m => $setLabel) {
        $rows = [];
        $colTotals = array_fill(0, count($specs), 0);
        foreach ($employees as $emp) {
            $perDay = $counts[$emp][$m] ?? [];
            if ($perDay === []) {
                continue;
            }
            $row = [$names[$emp] ?? ('Medewerker ' . $emp)];
            $hasValue = false;
            foreach ($specs as $i => $sp) {
                $n = $sumRange($perDay, $sp[1], $sp[2]);
                $colTotals[$i] += $n;
                $row[] = $n > 0 ? $n : null;
                $hasValue = $hasValue || $n > 0;
            }
            if ($hasValue) {
                $rows[] = $row;
            }
        }
        // Totaal van de hele periode: laatste som-kolom (Totaal / Deze maand).
        $lastSum = array_key_last(array_filter($specs, fn ($sp) => $sp[3]));
        $grand[$m] = $colTotals[$lastSum] ?? 0;
        if ($rows !== []) {
            $rows[] = array_merge(['Totaal'], array_map(fn ($n) => $n > 0 ? $n : null, $colTotals));
        }
        $sets[$setLabel] = $rows;
    }

    $period = $overview ? 'overzicht t/m ' . $to->format('d-m-Y') : $from->format('d-m-Y') . ' t/m ' . $to->format('d-m-Y');
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
        'sumColumns' => $sumColumns,
        'titles' => ['Productiviteit per medewerker - ' . $modeLabel, '{meting}, ' . $period],
        'fileTag' => $mode . '_' . ($overview ? 'overzicht_' . $to->format('Y-m-d') : $from->format('Y-m-d') . '_' . $to->format('Y-m-d')),
    ];
}, false);
