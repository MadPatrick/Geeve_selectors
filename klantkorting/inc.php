<?php

declare(strict_types=1);

// Zelfde Exact-database, verbinding en prijslijst->klanten-logica als /klantartikel.
require_once __DIR__ . '/../klantartikel/inc/db.php';
require_once __DIR__ . '/../klantartikel/inc/queries.php';

const DISCOUNT_TABLE_PATTERNS = ['%korting%', '%discount%', '%prlst%', '%pricelist%', '%prijslijst%', '%price%'];
const DISCOUNT_COLUMN_PATTERN = '%korting%|%discount%|%prijs%|%price%';

/**
 * Verkenning van het Exact-schema voor kortingen/prijslijsten: tabellen met zo'n naam
 * (met kolommen en aantal rijen) en kolommen op cicmpy die erop lijken. De exacte opbouw
 * van de kortingstructuur verschilt per installatie, dus die wordt eerst zichtbaar gemaakt.
 *
 * @return array{tables: list<array{name: string, rows: ?int, columns: list<string>}>, debtorColumns: list<string>}
 */
function exploreDiscountSchema(PDO $pdo): array
{
    $where = implode(' OR ', array_fill(0, count(DISCOUNT_TABLE_PATTERNS), 't.TABLE_NAME LIKE ?'));
    $stmt = $pdo->prepare(
        "SELECT t.TABLE_NAME FROM INFORMATION_SCHEMA.TABLES t WHERE t.TABLE_TYPE = 'BASE TABLE' AND ({$where}) ORDER BY t.TABLE_NAME"
    );
    $stmt->execute(DISCOUNT_TABLE_PATTERNS);
    $names = array_column($stmt->fetchAll(), 'TABLE_NAME');

    $tables = [];
    if ($names !== []) {
        $columns = tableColumns($pdo, $names);
        $rowCounts = [];
        try {
            $rc = $pdo->query(
                "SELECT o.name AS tabel, SUM(p.rows) AS aantal FROM sys.objects o JOIN sys.partitions p ON p.object_id = o.object_id AND p.index_id IN (0,1) WHERE o.type = 'U' GROUP BY o.name"
            );
            foreach ($rc->fetchAll() as $r) {
                $rowCounts[$r['tabel']] = (int) $r['aantal'];
            }
        } catch (Throwable) {
            // aantallen zijn alleen informatief
        }
        foreach ($names as $name) {
            $tables[] = ['name' => $name, 'rows' => $rowCounts[$name] ?? null, 'columns' => $columns[$name] ?? []];
        }
    }

    $debtor = tableColumns($pdo, ['cicmpy'])['cicmpy'];
    $debtorMatches = array_values(array_filter($debtor, static fn ($c) => preg_match('/korting|discount|prijs|pric/i', $c) === 1));

    return ['tables' => $tables, 'debtorColumns' => $debtorMatches];
}

/**
 * Omschrijving van de prijslijst (stfoms, type 'S' = staffel/kortingsprijslijst).
 */
function findPriceListDescription(PDO $pdo, string $priceList): ?string
{
    $stmt = $pdo->prepare("SELECT TOP 1 ISNULL(oms30_0, '') AS oms FROM stfoms WHERE LTRIM(RTRIM(prijslijst)) = :pl AND type = 'S'");
    $stmt->execute(['pl' => $priceList]);
    $row = $stmt->fetch();
    return $row ? trim((string) $row['oms']) : null;
}

/**
 * Kortingsregels per artikelgroep van een prijslijst (staffl, LineType 2). Regels met een
 * AccountID zijn klantspecifieke prijsafspraken (debcode), de rest geldt voor de hele prijslijst.
 * Per regel tot 10 staffels (aantal -> korting).
 *
 * @return list<array<string,mixed>>
 */
function findDiscountLines(PDO $pdo, string $priceList): array
{
    $cols = [
        'LTRIM(RTRIM(staffl.prijslijst)) AS prijslijst',
        "TRY_CAST(SUBSTRING(staffl.artcode, 21, 10) AS INT) AS ItemGroup",
        "ISNULL(i.Description_0, '') AS ItemGroupDescr",
        'LTRIM(RTRIM(cicmpy.debcode)) AS debcode',
        'cicmpy.cmp_name AS klant',
        'staffl.validfrom', 'staffl.validto', 'staffl.ID', 'staffl.kort_pbn',
        'aantal1 AS qty1', 'staffl.bedr1 AS d1', 'aantal2 AS qty2', 'staffl.bedr2 AS d2',
        'aantal3 AS qty3', 'staffl.bedr3 AS d3', 'aantal4 AS qty4', 'staffl.bedr4 AS d4',
        'aantal5 AS qty5', 'staffl.bedrag5 AS d5',
    ];
    for ($n = 6; $n <= 10; $n++) {
        $cols[] = "quantity{$n} AS qty{$n}";
        $cols[] = "staffl.price{$n} AS d{$n}";
    }
    $sql = 'SELECT ' . implode(', ', $cols) .
        ' FROM staffl INNER JOIN stfoms ON stfoms.prijslijst = staffl.prijslijst' .
        ' LEFT JOIN cicmpy ON cicmpy.cmp_wwn = staffl.AccountID AND staffl.AccountID IS NOT NULL' .
        ' LEFT JOIN ItemAssortment i ON i.Assortment = SUBSTRING(staffl.artcode, 21, 10)' .
        " WHERE stfoms.type = 'S' AND staffl.LineType = '2' AND LTRIM(RTRIM(staffl.prijslijst)) = :pl" .
        ' ORDER BY TRY_CAST(SUBSTRING(staffl.artcode, 21, 10) AS INT), cicmpy.debcode, staffl.validfrom';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['pl' => $priceList]);
    return $stmt->fetchAll();
}

function formatNumber(mixed $value): string
{
    if ($value === null || $value === '') {
        return '';
    }
    $text = rtrim(rtrim(number_format((float) $value, 4, ',', ''), '0'), ',');
    return $text === '' ? '0' : $text;
}

/** @return list<array{qty: string, discount: string}> staffels die zijn ingevuld */
function discountTiers(array $line): array
{
    $tiers = [];
    for ($n = 1; $n <= 10; $n++) {
        $d = $line["d{$n}"] ?? null;
        $q = $line["qty{$n}"] ?? null;
        if ($d === null || ($n > 1 && ((float) $d === 0.0 && (float) $q === 0.0))) {
            continue;
        }
        $tiers[] = ['qty' => formatNumber($q), 'discount' => formatNumber($d)];
    }
    return $tiers;
}

function formatDateShort(mixed $value): string
{
    if ($value === null || $value === '') {
        return '';
    }
    $ts = strtotime((string) $value);
    return $ts === false || (int) date('Y', $ts) <= 1900 || (int) date('Y', $ts) > 2999 ? '' : date('d-m-Y', $ts);
}
