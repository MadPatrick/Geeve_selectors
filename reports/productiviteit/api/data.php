<?php

declare(strict_types=1);

/**
 * Productiviteit per medewerker, per dag. Drie soorten (parameter "modus"):
 *  - productie: logistiek afgehandelde (geleverde) verkooporders. Een levering is een regel in het memoriaal (dagboek 90,
 *    tabel amutas) die naar een orderregel verwijst (CSPickITCWOrsrgIDCWDB) en een pakbonnummer heeft
 *    (CSPickITCWDeliveryNoteCWDB). Orders = aantal verschillende pakbonnen, orderregels = aantal verschillende orderregels.
 *  - verkoop: verkooporders (ord_soort V) uit de lopende orders (orkrg/orsrg) op de dag dat ze zijn verwerkt, zie
 *    productiviteitVerkoop.
 *  - offerte: offertes op de dag dat ze zijn ingevoerd, bij degene die ze heeft ingevoerd, zie productiviteitOfferte.
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
 * Gemeenschappelijke query voor Verkoop en Offertes: telt orders of regels per medewerker per dag op basis van een CTE "o"
 * (ordernr, medewerker, dag). @return list<list<mixed>> rijen [meting, medewerker, dag, aantal]
 */
function productiviteitOrderCounts(PDO $pdo, string $cte, DateTimeImmutable $from, DateTimeImmutable $to): array
{
    $startParam = $from->format('Y-m-d');
    $endParam = $to->format('Y-m-d');
    $sql = 'SET NOCOUNT ON; ' . $cte .
        "SELECT 'O' AS meting, o.medewerker AS medewerker, o.dag AS dag, COUNT(*) AS aantal FROM o WHERE o.dag >= ? AND o.dag <= ? GROUP BY o.medewerker, o.dag " .
        'UNION ALL ' .
        "SELECT 'L', o.medewerker, o.dag, COUNT(*) FROM o INNER JOIN orsrg r WITH (NOLOCK) ON r.ordernr = o.ordernr " .
        "WHERE o.dag >= ? AND o.dag <= ? AND LTRIM(RTRIM(ISNULL(r.artcode, ''))) <> '' GROUP BY o.medewerker, o.dag";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$startParam, $endParam, $startParam, $endParam]);
    return $stmt->fetchAll(PDO::FETCH_NUM);
}

/**
 * Verkoop: verkooporders (ord_soort V) op de dag dat ze zijn verwerkt, alleen lopende orders (orkrg/orsrg).
 *
 * Een rechtstreeks ingevoerde order telt op de aanmaakdag bij degene die hem heeft ingevoerd (die verwerkt hem dan ook).
 * Een offerte die tot order is verwerkt houdt de oorspronkelijke aanmaakdatum en krijgt als orderdatum de dag van verwerken
 * (orddat later dan syscreated). Exact legt niet vast wie dat deed (user_id, represent_id en syscreator zijn de maker van de
 * offerte, sysmodifier de laatste wijziger, vaak het magazijn), dus zulke orders tellen op de verwerkingsdag onder medewerker 0
 * ("Onbekend"). Omgezette offertes tot 90 dagen oud worden gevonden (ouder ID).
 */
function productiviteitVerkoop(PDO $pdo, DateTimeImmutable $from, DateTimeImmutable $to): array
{
    [$kFirst, $kEnd] = productiviteitIdRange($pdo, 'orkrg', $from->modify('-90 days'), $to);
    $cte = 'WITH o AS (SELECT k.ordernr, ' .
        'CASE WHEN CAST(k.orddat AS date) > CAST(k.syscreated AS date) THEN 0 ELSE k.syscreator END AS medewerker, ' .
        'CONVERT(char(10), CASE WHEN CAST(k.orddat AS date) > CAST(k.syscreated AS date) THEN k.orddat ELSE k.syscreated END, 23) AS dag ' .
        "FROM orkrg k WITH (NOLOCK) WHERE k.ID >= {$kFirst} AND k.ID < {$kEnd} AND k.ord_soort = 'V') ";
    return productiviteitOrderCounts($pdo, $cte, $from, $to);
}

/**
 * Offertes: een offerte telt op de dag dat hij is ingevoerd (syscreated), bij degene die hem heeft ingevoerd (syscreator).
 * Dat zijn offertes (ord_soort Q) en offertes die inmiddels tot order zijn verwerkt (ord_soort V met orderdatum later dan de
 * aanmaakdatum). De periode is direct een ID-bereik: een offerte krijgt bij invoer zijn ID.
 */
function productiviteitOfferte(PDO $pdo, DateTimeImmutable $from, DateTimeImmutable $to): array
{
    [$kFirst, $kEnd] = productiviteitIdRange($pdo, 'orkrg', $from, $to);
    $cte = 'WITH o AS (SELECT k.ordernr, k.syscreator AS medewerker, CONVERT(char(10), k.syscreated, 23) AS dag ' .
        "FROM orkrg k WITH (NOLOCK) WHERE k.ID >= {$kFirst} AND k.ID < {$kEnd} " .
        "AND (k.ord_soort = 'Q' OR (k.ord_soort = 'V' AND CAST(k.orddat AS date) > CAST(k.syscreated AS date)))) ";
    return productiviteitOrderCounts($pdo, $cte, $from, $to);
}

reportApi(function (array $in): array {
    $mode = (string) ($in['modus'] ?? 'productie');
    if (!in_array($mode, ['productie', 'verkoop', 'offerte'], true)) {
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
    $result = match ($mode) {
        'productie' => productiviteitProductie($pdo, $from, $to),
        'verkoop' => productiviteitVerkoop($pdo, $from, $to),
        default => productiviteitOfferte($pdo, $from, $to),
    };

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
            $row = [$names[$emp] ?? ($emp === 0 ? 'Onbekend (offerte verwerkt)' : 'Medewerker ' . $emp)];
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
    $modeLabel = match ($mode) {
        'productie' => 'Productie (afgehandelde orders)',
        'verkoop' => 'Verkoop (verwerkte orders)',
        default => 'Offertes (ingevoerd)',
    };
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
