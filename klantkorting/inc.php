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
