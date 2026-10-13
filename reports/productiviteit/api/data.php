<?php

declare(strict_types=1);

/**
 * Productiviteit per medewerker, per dag. Drie soorten (parameter "modus"):
 *  - productie (in de pagina "Logistiek"): logistiek afgehandelde (geleverde) verkooporders. Een levering is een regel in het memoriaal (dagboek 90,
 *    tabel amutas) die naar een orderregel verwijst (CSPickITCWOrsrgIDCWDB) en een pakbonnummer heeft
 *    (CSPickITCWDeliveryNoteCWDB). Orders = aantal verschillende pakbonnen, orderregels = aantal verschillende orderregels.
 *  - verkoop: verwerkte verkooporders. Een order is verwerkt op het moment dat de eerste orderbevestiging (document type 11 in
 *    BacoDiscussions, het tabblad Bijlage van de order) is gemaakt, door degene die dat document heeft aangemaakt.
 *  - offerte: ingevoerde offertes per vertegenwoordiger (orkrg.represent_id, zoals in het orderscherm), op de dag dat de offerte is
 *    ingevoerd (syscreated). Dat zijn offertes (ord_soort Q) en offertes die inmiddels tot order zijn verwerkt.
 * Bij verkoop is de medewerker BacoDiscussions.CreatedBy (humres.res_id); bij offertes is het de vertegenwoordiger. Het antwoord bevat per telling ("Orders", "Orderregels") een eigen
 * reeks rijen ("sets"); de pagina schakelt daartussen.
 */

require_once __DIR__ . '/../../inc.php';
require_once __DIR__ . '/../../../shared/tiles.php';

// Beveiligd met de toegangscode: zonder ontgrendelde sessie geen gegevens.
tileApiGuard('productiviteit'); // beveiligde tegel: zonder ontgrendelen geen gegevens

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
 * ID-bereik [eerste, einde) van een grote tabel voor de gekozen periode (zie productiviteitFirstId). $table, $idCol en $dateCol
 * zijn vaste namen uit de code (nooit gebruikersinvoer).
 *
 * @return array{0: int, 1: int}
 */
function productiviteitIdRange(PDO $pdo, string $table, DateTimeImmutable $from, DateTimeImmutable $to, string $idCol = 'ID', string $dateCol = 'syscreated'): array
{
    $probeStmt = $pdo->prepare("SET NOCOUNT ON; SELECT TOP 1 CONVERT(char(19), {$dateCol}, 120) AS t FROM {$table} WITH (NOLOCK) WHERE {$idCol} >= ? ORDER BY {$idCol}");
    $probe = function (int $id) use ($probeStmt): ?string {
        $probeStmt->execute([$id]);
        $row = $probeStmt->fetch(PDO::FETCH_NUM);
        $probeStmt->closeCursor();
        return $row === false || $row[0] === null ? null : (string) $row[0];
    };
    $maxId = (int) $pdo->query("SET NOCOUNT ON; SELECT MAX({$idCol}) FROM {$table} WITH (NOLOCK)")->fetchColumn();
    return [
        productiviteitFirstId($probe, $from->format('Y-m-d') . ' 00:00:00', $maxId),
        productiviteitFirstId($probe, $to->modify('+1 day')->format('Y-m-d') . ' 00:00:00', $maxId), // eerste ID ná de periode
    ];
}

/**
 * CTE-onderdeel "p": de productieorders (werkorders, ord_soort A) per verkooporder. Een productieorder heeft als referentie
 * het ordernummer van de verkooporder waar hij bij hoort (orkrg.refer). De orderhistorie telt mee als die dezelfde kolommen heeft.
 */
function productiviteitProductionCte(PDO $pdo): string
{
    $hist = (int) $pdo->query("SELECT COUNT(*) FROM sys.columns WHERE object_id = OBJECT_ID('orhkrg') AND name IN ('ord_soort', 'refer')")->fetchColumn() === 2;
    $sql = "p AS (SELECT LTRIM(RTRIM(k.refer)) AS verkoop, k.ordernr AS prodnr FROM orkrg k WITH (NOLOCK) WHERE k.ord_soort = 'A' AND ISNULL(RTRIM(k.refer), '') <> ''";
    if ($hist) {
        $sql .= " UNION ALL SELECT LTRIM(RTRIM(h.refer)), h.ordernr FROM orhkrg h WITH (NOLOCK) WHERE h.ord_soort = 'A' AND ISNULL(RTRIM(h.refer), '') <> ''";
    }
    return $sql . ')';
}

/** Aantal orderregels van de productieorders bij een verkooporder, als subquery (kolom $orderCol uit de omringende query). */
function productiviteitProductionLinesSql(string $orderCol): string
{
    $count = fn (string $table): string => "(SELECT COUNT(*) FROM p INNER JOIN {$table} pr WITH (NOLOCK) ON pr.ordernr = p.prodnr " .
        "WHERE p.verkoop = {$orderCol} AND LTRIM(RTRIM(ISNULL(pr.artcode, ''))) <> '')";
    return $count('orsrg') . ' + ' . $count('orhsrg');
}

/** Logistiek: afgehandelde (geleverde) verkooporders. @return list<list<mixed>> rijen [meting, medewerker, dag, aantal] */
function productiviteitProductie(PDO $pdo, DateTimeImmutable $from, DateTimeImmutable $to): array
{
    $startParam = $from->format('Y-m-d');
    $endParam = $to->format('Y-m-d');

    // Periode -> ID-bereik van amutas (zie productiviteitFirstId); de datumfilter in de query blijft staan voor de precisie.
    [$firstId, $endId] = productiviteitIdRange($pdo, 'amutas', $from, $to);

    $where = "WHERE s.ID >= {$firstId} AND s.ID < {$endId} AND LTRIM(RTRIM(s.dagbknr)) = '90' AND s.CSPickITCWOrsrgIDCWDB <> 0 " .
        "AND ISNULL(RTRIM(s.CSPickITCWDeliveryNoteCWDB), '') <> '' AND s.syscreated >= ? AND s.syscreated < DATEADD(day, 1, ?) ";
    $base = "FROM amutas s WITH (NOLOCK) {$where}GROUP BY s.syscreator, CONVERT(char(10), s.syscreated, 23)";
    // d = de geleverde orders per medewerker en dag (orderregel via ID, alleen als het artikel overeenkomt); p = productieorders.
    $deliveredOrders = 'SELECT DISTINCT s.syscreator AS medewerker, CONVERT(char(10), s.syscreated, 23) AS dag, LTRIM(RTRIM(r.ordernr)) AS ordernr ' .
        'FROM amutas s WITH (NOLOCK) INNER JOIN orsrg r WITH (NOLOCK) ON r.ID = s.CSPickITCWOrsrgIDCWDB AND RTRIM(r.artcode) = RTRIM(s.artcode) ' . $where;
    $sql = 'SET NOCOUNT ON; WITH ' . productiviteitProductionCte($pdo) . ", d AS ({$deliveredOrders}) " .
        "SELECT 'O' AS meting, s.syscreator AS medewerker, CONVERT(char(10), s.syscreated, 23) AS dag, COUNT(DISTINCT RTRIM(s.CSPickITCWDeliveryNoteCWDB)) AS aantal {$base} " .
        'UNION ALL ' .
        "SELECT 'L', s.syscreator, CONVERT(char(10), s.syscreated, 23), COUNT(DISTINCT s.CSPickITCWOrsrgIDCWDB) {$base}";
    // Regels van productieorders bij de geleverde orders tellen bij de orderregels mee.
    foreach (['orsrg', 'orhsrg'] as $table) {
        $sql .= ' UNION ALL ' .
            "SELECT 'L', d.medewerker, d.dag, COUNT(*) FROM d INNER JOIN p ON p.verkoop = d.ordernr INNER JOIN {$table} pr WITH (NOLOCK) ON pr.ordernr = p.prodnr " .
            "WHERE LTRIM(RTRIM(ISNULL(pr.artcode, ''))) <> '' GROUP BY d.medewerker, d.dag";
    }

    // Volgorde van de ?-tekens: eerst d (2), daarna het O-deel (2) en het L-deel (2).
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$startParam, $endParam, $startParam, $endParam, $startParam, $endParam]);
    return $stmt->fetchAll(PDO::FETCH_NUM);
}

/**
 * Gemeenschappelijke query voor Verkoop en Offertes: telt orders of regels per medewerker per dag op basis van een CTE "o"
 * (ordernr, medewerker, dag, CmpWwn, yourref). Orderregels staan in orsrg (lopende orders) of orhsrg (afgehandelde orders). @return list<list<mixed>> rijen [meting, medewerker, dag, aantal]
 */
function productiviteitOrderCounts(PDO $pdo, string $cte, DateTimeImmutable $from, DateTimeImmutable $to, bool $withProduction = false): array
{
    $startParam = $from->format('Y-m-d');
    $endParam = $to->format('Y-m-d');
    $range = 'o.dag >= ? AND o.dag <= ?';
    $notEmpty = "LTRIM(RTRIM(ISNULL(r.artcode, ''))) <> ''";
    $sql = 'SET NOCOUNT ON; ' . $cte . ($withProduction ? ', ' . productiviteitProductionCte($pdo) . ' ' : '') .
        "SELECT 'O' AS meting, o.medewerker AS medewerker, o.dag AS dag, COUNT(*) AS aantal FROM o WHERE {$range} GROUP BY o.medewerker, o.dag " .
        'UNION ALL ' .
        "SELECT 'L', o.medewerker, o.dag, COUNT(*) FROM o INNER JOIN orsrg r WITH (NOLOCK) ON r.ordernr = o.ordernr WHERE {$range} AND {$notEmpty} GROUP BY o.medewerker, o.dag " .
        'UNION ALL ' .
        "SELECT 'L', o.medewerker, o.dag, COUNT(*) FROM o INNER JOIN orhsrg r WITH (NOLOCK) ON r.ordernr = o.ordernr WHERE {$range} AND {$notEmpty} GROUP BY o.medewerker, o.dag";
    $params = [$startParam, $endParam, $startParam, $endParam, $startParam, $endParam];
    if ($withProduction) {
        // Regels van productieorders die bij de verkooporder horen, tellen bij de orderregels mee.
        foreach (['orsrg', 'orhsrg'] as $table) {
            $sql .= ' UNION ALL ' .
                "SELECT 'L', o.medewerker, o.dag, COUNT(*) FROM o INNER JOIN p ON p.verkoop = o.ordernr INNER JOIN {$table} r WITH (NOLOCK) ON r.ordernr = p.prodnr " .
                "WHERE {$range} AND {$notEmpty} GROUP BY o.medewerker, o.dag";
            array_push($params, $startParam, $endParam);
        }
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_NUM);
}

/** BacoDiscussions.Type van de orderbevestiging (tabblad Bijlage van de order), waar Verkoop op rust. */
const PRODUCTIVITEIT_DOC_ORDERBEVESTIGING = 11;

/**
 * CTE "o" met per order het eerste document van een soort (BacoDiscussions, tabblad Bijlage): de medewerker die het heeft
 * aangemaakt (CreatedBy) en de dag (CreatedDate). Het eerste document per order wordt bepaald binnen de periode plus 90 dagen
 * ervoor, zodat een opnieuw verstuurde bevestiging of offerte niet nog eens meetelt. De periode wordt omgezet naar een
 * bereik van het volgnummer (HID), omdat de tabel groot is.
 */
function productiviteitDocCte(PDO $pdo, int $type, DateTimeImmutable $from, DateTimeImmutable $to): string
{
    [$first, $end] = productiviteitIdRange($pdo, 'BacoDiscussions', $from->modify('-90 days'), $to, 'HID', 'CreatedDate');
    return 'WITH c AS (SELECT LTRIM(RTRIM(OrderNumber)) AS ordernr, CreatedBy, CreatedDate, CmpWwn, RTRIM(YourRef) AS yourref, ' .
        'ROW_NUMBER() OVER (PARTITION BY LTRIM(RTRIM(OrderNumber)) ORDER BY CreatedDate, HID) AS rn ' .
        "FROM BacoDiscussions WITH (NOLOCK) WHERE HID >= {$first} AND HID < {$end} AND Type = " . (int) $type . " AND ISNULL(RTRIM(OrderNumber), '') <> ''), " .
        'o AS (SELECT ordernr, CreatedBy AS medewerker, CONVERT(char(10), CreatedDate, 23) AS dag, CmpWwn, yourref FROM c WHERE rn = 1) ';
}

/**
 * Verkoop: verwerkte verkooporders. Een order is verwerkt op het moment dat de eerste orderbevestiging is gemaakt (document
 * type 11, tabblad Bijlage), door degene die dat document heeft aangemaakt. Bij een offerte die tot order wordt verwerkt is dat
 * degene die de order heeft verwerkt, niet degene die de offerte maakte.
 */
function productiviteitVerkoop(PDO $pdo, DateTimeImmutable $from, DateTimeImmutable $to): array
{
    return productiviteitOrderCounts($pdo, productiviteitDocCte($pdo, PRODUCTIVITEIT_DOC_ORDERBEVESTIGING, $from, $to), $from, $to, true);
}

/**
 * CTE "o" voor Offertes: een offerte telt op de dag dat hij is ingevoerd (syscreated), bij de vertegenwoordiger die in de offerte
 * staat (represent_id), niet bij wie het offertedocument heeft gemaakt of de offerte heeft ingetypt. Dat zijn offertes (ord_soort
 * Q) en offertes die inmiddels tot order zijn verwerkt (ord_soort V met orderdatum later dan de aanmaakdatum). Een offerte krijgt
 * bij invoer zijn ID, dus de periode is direct een ID-bereik. Kolommen: ordernr, medewerker, dag, klant, yourref.
 */
function productiviteitOfferteCte(PDO $pdo, DateTimeImmutable $from, DateTimeImmutable $to): string
{
    [$first, $end] = productiviteitIdRange($pdo, 'orkrg', $from, $to);
    return 'WITH o AS (SELECT LTRIM(RTRIM(k.ordernr)) AS ordernr, k.represent_id AS medewerker, CONVERT(char(10), k.syscreated, 23) AS dag, ' .
        "LTRIM(RTRIM(ISNULL(k.ord_debtor_name, ''))) AS klant, LTRIM(RTRIM(ISNULL(k.refer, ''))) AS yourref " .
        "FROM orkrg k WITH (NOLOCK) WHERE k.ID >= {$first} AND k.ID < {$end} AND ISNULL(k.represent_id, 0) <> 0 " .
        "AND (k.ord_soort = 'Q' OR (k.ord_soort = 'V' AND CAST(k.orddat AS date) > CAST(k.syscreated AS date)))) ";
}

/** Offertes: zie productiviteitOfferteCte. */
function productiviteitOfferte(PDO $pdo, DateTimeImmutable $from, DateTimeImmutable $to): array
{
    return productiviteitOrderCounts($pdo, productiviteitOfferteCte($pdo, $from, $to), $from, $to);
}

/**
 * Details achter een getal: de orders (of pakbonnen) van één medewerker in een periode. $emp 0 = "Onbekend".
 *
 * @return array{columns: list<string>, rows: list<list<string|int|null>>, truncated: bool}
 */
function productiviteitDetail(PDO $pdo, string $mode, int $emp, DateTimeImmutable $from, DateTimeImmutable $to): array
{
    $limit = 500;
    $startParam = $from->format('Y-m-d');
    $endParam = $to->format('Y-m-d');

    if ($mode === 'productie') {
        [$first, $end] = productiviteitIdRange($pdo, 'amutas', $from, $to);
        // De orderregel wordt via ID gekoppeld; alleen als het artikel overeenkomt, zodat nooit een verkeerd ordernummer verschijnt.
        $inner = "SELECT TOP {$limit} RTRIM(s.CSPickITCWDeliveryNoteCWDB) AS pakbon, MIN(LTRIM(RTRIM(r.ordernr))) AS ordernr, " .
            'CONVERT(char(10), MIN(s.syscreated), 23) AS datum, COUNT(DISTINCT s.CSPickITCWOrsrgIDCWDB) AS regels ' .
            'FROM amutas s WITH (NOLOCK) LEFT JOIN orsrg r WITH (NOLOCK) ON r.ID = s.CSPickITCWOrsrgIDCWDB AND RTRIM(r.artcode) = RTRIM(s.artcode) ' .
            "WHERE s.ID >= {$first} AND s.ID < {$end} AND LTRIM(RTRIM(s.dagbknr)) = '90' AND s.CSPickITCWOrsrgIDCWDB <> 0 " .
            "AND ISNULL(RTRIM(s.CSPickITCWDeliveryNoteCWDB), '') <> '' AND s.syscreator = ? AND s.syscreated >= ? AND s.syscreated < DATEADD(day, 1, ?) " .
            'GROUP BY RTRIM(s.CSPickITCWDeliveryNoteCWDB) ORDER BY MIN(s.syscreated), RTRIM(s.CSPickITCWDeliveryNoteCWDB)';
        $sql = 'SET NOCOUNT ON; WITH ' . productiviteitProductionCte($pdo) . " SELECT g.pakbon, g.ordernr, g.datum, g.regels, (" . productiviteitProductionLinesSql('g.ordernr') . ") AS prodregels FROM ({$inner}) g ORDER BY g.datum, g.pakbon";
        $columns = ['Pakbon', 'Order', 'Datum', 'Regels', 'Productieregels'];
    } else {
        $lineCount = "(SELECT COUNT(*) FROM orsrg r WITH (NOLOCK) WHERE r.ordernr = o.ordernr AND LTRIM(RTRIM(ISNULL(r.artcode, ''))) <> '') + " .
            "(SELECT COUNT(*) FROM orhsrg r WITH (NOLOCK) WHERE r.ordernr = o.ordernr AND LTRIM(RTRIM(ISNULL(r.artcode, ''))) <> '')";
        if ($mode === 'verkoop') {
            $cte = productiviteitDocCte($pdo, PRODUCTIVITEIT_DOC_ORDERBEVESTIGING, $from, $to);
            $klant = "ISNULL(RTRIM(cm.cmp_name), '')";
            $join = 'LEFT JOIN cicmpy cm WITH (NOLOCK) ON cm.cmp_wwn = o.CmpWwn ';
        } else {
            $cte = productiviteitOfferteCte($pdo, $from, $to);
            $klant = 'o.klant';
            $join = '';
        }
        $prodSelect = '';
        if ($mode === 'verkoop') {
            $cte .= ', ' . productiviteitProductionCte($pdo) . ' ';
            $prodSelect = ', (' . productiviteitProductionLinesSql('o.ordernr') . ') AS prodregels';
        }
        $sql = 'SET NOCOUNT ON; ' . $cte . "SELECT TOP {$limit} o.ordernr, o.dag AS datum, {$klant} AS klant, ISNULL(o.yourref, '') AS referentie, {$lineCount} AS regels{$prodSelect} " .
            "FROM o {$join}WHERE o.medewerker = ? AND o.dag >= ? AND o.dag <= ? ORDER BY o.dag, o.ordernr";
        $columns = $mode === 'verkoop' ? ['Order', 'Datum', 'Klant', 'Referentie', 'Regels', 'Productieregels'] : ['Offerte', 'Datum', 'Klant', 'Referentie', 'Regels'];
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$emp, $startParam, $endParam]);
    $rows = [];
    while ($r = $stmt->fetch(PDO::FETCH_NUM)) {
        $r = array_map(fn ($v) => $v === null ? null : trim((string) $v), $r);
        if ($mode === 'productie') {
            $rows[] = [$r[0], $r[1], $r[2], (int) $r[3], (int) $r[4]];
        } elseif ($mode === 'verkoop') {
            $rows[] = [$r[0], $r[1], $r[2], $r[3], (int) $r[4], (int) $r[5]];
        } else {
            $rows[] = [$r[0], $r[1], $r[2], $r[3], (int) $r[4]];
        }
    }
    return ['columns' => $columns, 'rows' => $rows, 'truncated' => count($rows) >= $limit];
}

/** Dagen van de afgelopen week worden altijd opnieuw opgehaald; oudere dagen veranderen niet meer en worden onthouden. */
const PRODUCTIVITEIT_RECENT_DAYS = 7;

/** @return array<string, list<array{0: string, 1: int, 2: int}>> dag => lijst van [meting, medewerker, aantal] */
function productiviteitCacheRead(string $mode): array
{
    $raw = @file_get_contents(sys_get_temp_dir() . '/geeve_productiviteit_v2_' . $mode . '.json');
    $data = $raw === false ? null : json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function productiviteitCacheWrite(string $mode, array $days): void
{
    $file = sys_get_temp_dir() . '/geeve_productiviteit_v2_' . $mode . '.json';
    $tmp = $file . '.' . bin2hex(random_bytes(4));
    if (@file_put_contents($tmp, json_encode($days)) !== false) {
        @rename($tmp, $file);
    }
    @unlink($tmp); // blijft alleen bestaan als hernoemen mislukte
}

/**
 * Haalt de tellingen op voor [from, to] met een cache op de server voor dagen ouder dan een week (die veranderen niet meer),
 * zodat bij een grote periode (bijv. dit jaar) alleen de laatste week nog uit Exact komt. Ontbrekende oude dagen worden in
 * een keer opgehaald en onthouden. Zonder schrijfrechten op de tijdelijke map werkt alles gewoon, maar zonder cache.
 *
 * @return list<list<mixed>> rijen [meting, medewerker, dag, aantal]
 */
function productiviteitFetchCached(PDO $pdo, string $mode, DateTimeImmutable $from, DateTimeImmutable $to): array
{
    $fetch = fn (DateTimeImmutable $f, DateTimeImmutable $t): array => match ($mode) {
        'productie' => productiviteitProductie($pdo, $f, $t),
        'verkoop' => productiviteitVerkoop($pdo, $f, $t),
        default => productiviteitOfferte($pdo, $f, $t),
    };

    $today = new DateTimeImmutable('today');
    $recentStart = $today->modify('-' . PRODUCTIVITEIT_RECENT_DAYS . ' days');
    $oldEnd = min($to, $recentStart->modify('-1 day'));
    $out = [];

    if ($from <= $oldEnd) {
        $cache = productiviteitCacheRead($mode);
        $missing = [];
        for ($d = $from; $d <= $oldEnd; $d = $d->modify('+1 day')) {
            if (!isset($cache[$d->format('Y-m-d')])) {
                $missing[] = $d;
            }
        }
        if ($missing !== []) {
            $mFrom = $missing[0];
            $mTo = $missing[count($missing) - 1];
            $perDay = [];
            foreach ($fetch($mFrom, $mTo) as $r) {
                $perDay[(string) $r[2]][] = [(string) $r[0], (int) $r[1], (int) $r[3]];
            }
            for ($d = $mFrom; $d <= $mTo; $d = $d->modify('+1 day')) {
                $key = $d->format('Y-m-d');
                $cache[$key] = $perDay[$key] ?? [];
            }
            productiviteitCacheWrite($mode, $cache);
        }
        for ($d = $from; $d <= $oldEnd; $d = $d->modify('+1 day')) {
            $key = $d->format('Y-m-d');
            foreach ($cache[$key] ?? [] as $c) {
                $out[] = [$c[0], $c[1], $key, $c[2]];
            }
        }
    }

    if ($to >= $recentStart) {
        foreach ($fetch(max($from, $recentStart), $to) as $r) {
            $out[] = $r;
        }
    }
    return $out;
}

reportApi(function (array $in): array {
    $mode = (string) ($in['modus'] ?? 'productie');
    if (!in_array($mode, ['productie', 'verkoop', 'offerte'], true)) {
        throw new RuntimeException('Onbekende keuze.');
    }
    $overview = (string) ($in['weergave'] ?? 'overzicht') === 'overzicht';
    if ($overview) {
        // Vaste kolommen ten opzichte van vandaag: werkdagen van deze week, deze week, weken van deze maand, deze maand, dit jaar.
        $to = new DateTimeImmutable('today');
        $monthStart = $to->modify('first day of this month');
        $monday = $to->modify('monday this week');
        $yearStart = $to->modify('first day of january this year');
        $from = min($monthStart, $monday); // "Dit jaar" wordt apart (en gecachet) opgehaald, zie hieronder
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

    // Details achter een getal: de orders van één medewerker in (een deel van) de periode.
    if (isset($in['detail'])) {
        $emp = filter_var($in['detail'], FILTER_VALIDATE_INT);
        if ($emp === false || $emp < 0) {
            throw new RuntimeException('Ongeldige medewerker.');
        }
        $detailFrom = productiviteitDate($in['dvan'] ?? '', 'van');
        $detailTo = productiviteitDate($in['dtot'] ?? '', 'tot');
        if ($detailFrom > $detailTo || (int) $detailFrom->diff($detailTo)->days + 1 > 366) {
            throw new RuntimeException('Ongeldige periode.');
        }
        $name = 'Onbekend';
        if ($emp > 0) {
            $nm = $pdo->prepare('SET NOCOUNT ON; SELECT RTRIM(fullname) AS naam FROM humres WHERE res_id = ?');
            $nm->execute([$emp]);
            $row = $nm->fetch();
            $name = $row ? trim((string) $row['naam']) : 'Medewerker ' . $emp;
        }
        $range = $detailFrom == $detailTo ? $detailFrom->format('d-m-Y') : $detailFrom->format('d-m-Y') . ' t/m ' . $detailTo->format('d-m-Y');
        return ['detail' => ['title' => $name . ' - ' . $range] + productiviteitDetail($pdo, $mode, $emp, $detailFrom, $detailTo)];
    }

    // Dit jaar apart: totaal per medewerker voor 1 januari t/m vandaag (voor de kolom die de pagina daarna aanvult).
    if (isset($in['jaar'])) {
        $yFrom = $to->modify('first day of january this year');
        $year = ['Orders' => [], 'Orderregels' => []];
        $totals = ['Orders' => 0, 'Orderregels' => 0];
        foreach (productiviteitFetchCached($pdo, $mode, $yFrom, $to) as $r) {
            $set = $r[0] === 'O' ? 'Orders' : 'Orderregels';
            $year[$set][(int) $r[1]] = ($year[$set][(int) $r[1]] ?? 0) + (int) $r[3];
            $totals[$set] += (int) $r[3];
        }
        return ['year' => $year, 'yearTotals' => $totals];
    }

    $result = productiviteitFetchCached($pdo, $mode, $from, $to);

    // [medewerker][meting][dag] = aantal
    $counts = [];
    foreach ($result as $r) {
        $counts[(int) $r[1]][(string) $r[0]][(string) $r[2]] = ($counts[(int) $r[1]][(string) $r[0]][(string) $r[2]] ?? 0) + (int) $r[3];
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
        $specs[] = ['Dit jaar', $yearStart, $to, true, true]; // wordt door de pagina aangevuld (zie 'jaar')
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
    $setIds = [];
    $grand = [];
    foreach (['O' => 'Orders', 'L' => 'Orderregels'] as $m => $setLabel) {
        $rows = [];
        $ids = [];
        $colTotals = array_fill(0, count($specs), 0);
        foreach ($employees as $emp) {
            $perDay = $counts[$emp][$m] ?? [];
            if ($perDay === []) {
                continue;
            }
            $row = [$names[$emp] ?? ($emp === 0 ? 'Onbekend' : 'Medewerker ' . $emp)];
            $hasValue = false;
            foreach ($specs as $i => $sp) {
                if (!empty($sp[4])) { // nog op te halen: leeg
                    $row[] = null;
                    continue;
                }
                $n = $sumRange($perDay, $sp[1], $sp[2]);
                $colTotals[$i] += $n;
                $row[] = $n > 0 ? $n : null;
                $hasValue = $hasValue || $n > 0;
            }
            if ($hasValue) {
                $rows[] = $row;
                $ids[] = $emp;
            }
        }
        // Totaal van de hele periode: laatste som-kolom (Totaal / Deze maand).
        $sumSpecs = array_filter($specs, fn ($sp) => $sp[3]);
        $lastSum = array_key_last($sumSpecs);
        if ($overview) { // in het overzicht toont de samenvatting de maand, niet het hele jaar
            $lastSum = array_search('Deze maand', array_map(fn ($sp) => $sp[0], $specs), true);
        }
        $grand[$m] = $colTotals[$lastSum] ?? 0;
        if ($rows !== []) {
            $rows[] = array_merge(['Totaal'], array_map(fn ($n) => $n > 0 ? $n : null, $colTotals));
            $ids[] = null; // de totaalregel heeft geen details
        }
        $sets[$setLabel] = $rows;
        $setIds[$setLabel] = $ids;
    }

    $period = $overview ? 'overzicht t/m ' . $to->format('d-m-Y') : $from->format('d-m-Y') . ' t/m ' . $to->format('d-m-Y');
    $modeLabel = match ($mode) {
        'productie' => 'Logistiek (afgehandelde orders)',
        'verkoop' => 'Verkoop (verwerkte orders)',
        default => 'Offertes (ingevoerd)',
    };
    $kop = [
        ['label' => 'Periode', 'value' => $period],
        ['label' => 'Medewerkers', 'value' => (string) count($employees)],
        ['label' => $overview ? 'Deze maand' : 'Totaal', 'value' => number_format($grand['O'], 0, ',', '.') . ' orders / ' . number_format($grand['L'], 0, ',', '.') . ' regels'],
    ];

    $count = count($columns);
    return [
        'columns' => $columns,
        'numeric' => array_merge([false], array_fill(0, $count - 1, true)),
        'dates' => array_fill(0, $count, false),
        'rows' => $sets['Orders'],
        'sets' => $sets,
        'setIds' => $setIds,
        // Periode per kolom (voor de details bij een klik op een getal); de eerste kolom (naam) heeft er geen.
        'colRanges' => array_merge([null], array_map(fn ($sp) => [$sp[1]->format('Y-m-d'), $sp[2]->format('Y-m-d')], $specs)),
        'kop' => $kop,
        'hideRowCount' => true,
        'sumColumns' => $sumColumns,
        'yearPending' => $overview,
        'titles' => ['Productiviteit per medewerker - ' . $modeLabel, '{meting}, ' . $period],
        'fileTag' => $mode . '_' . ($overview ? 'overzicht_' . $to->format('Y-m-d') : $from->format('Y-m-d') . '_' . $to->format('Y-m-d')),
    ];
}, false);
