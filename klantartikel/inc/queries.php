<?php

declare(strict_types=1);

/**
 * Klant-artikelnummers uit Exact Globe (database 005).
 *
 * Koppeling: debiteur (cicmpy) -> prijslijst (kolom op cicmpy) en
 * artikel-relatie (ItemAccounts: ItemCode + ItemCodeAccount = het
 * artikelnummer van de klant). De exacte kolomnamen verschillen per
 * Exact-installatie en zijn niet in code vastgelegd: ze worden bij gebruik
 * uit INFORMATION_SCHEMA afgeleid (zie detectSchema()). Lukt dat niet, dan
 * toont de pagina welke kolommen er wel zijn, zodat de lijst kandidaten
 * hieronder uitgebreid kan worden.
 */

const PRICELIST_COLUMN_CANDIDATES = ['PriceList', 'Prijslijst', 'prijslijst', 'prlst', 'PrLst', 'pricelist'];
// kolom op ItemAccounts => kolom op cicmpy waarmee die gejoind wordt
const ITEMACCOUNT_LINK_CANDIDATES = [
    'Account' => 'cmp_wwn',
    'cmp_wwn' => 'cmp_wwn',
    'debnr'   => 'debnr',
    'crdnr'   => 'crdnr',
];
const ITEMACCOUNT_CODE_COLUMN = 'ItemCodeAccount';
const MAX_ARTICLE_ROWS = 5000;

/** @return array<string,list<string>> tabelnaam => kolomnamen */
function tableColumns(PDO $pdo, array $tables): array
{
    $in = implode(',', array_fill(0, count($tables), '?'));
    $stmt = $pdo->prepare(
        "SELECT TABLE_NAME, COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME IN ({$in}) ORDER BY TABLE_NAME, ORDINAL_POSITION"
    );
    $stmt->execute($tables);
    $out = array_fill_keys($tables, []);
    foreach ($stmt->fetchAll() as $row) {
        $out[$row['TABLE_NAME']][] = $row['COLUMN_NAME'];
    }
    return $out;
}

function findColumn(array $columns, array $candidates): ?string
{
    foreach ($candidates as $candidate) {
        foreach ($columns as $column) {
            if (strcasecmp($column, $candidate) === 0) {
                return $column;
            }
        }
    }
    return null;
}

/**
 * @return array{pricelist: string, itemLink: string, debtorLink: string, itemCode: string, itemColumns: list<string>, debtorColumns: list<string>}
 */
function detectSchema(PDO $pdo, ?string $priceListColumnOverride = null): array
{
    $cols = tableColumns($pdo, ['cicmpy', 'ItemAccounts', 'Items']);
    $debtor = $cols['cicmpy'];
    $item = $cols['ItemAccounts'];

    if ($debtor === []) {
        throw new RuntimeException('Tabel cicmpy (debiteuren) niet gevonden in de database.');
    }
    if ($item === []) {
        throw new RuntimeException('Tabel ItemAccounts (artikel-relaties) niet gevonden in de database.');
    }

    $pricelist = $priceListColumnOverride !== null && $priceListColumnOverride !== ''
        ? findColumn($debtor, [$priceListColumnOverride])
        : findColumn($debtor, PRICELIST_COLUMN_CANDIDATES);
    if ($pricelist === null) {
        $like = array_values(array_filter($debtor, static fn ($c) => preg_match('/prijs|pric|prlst/i', $c) === 1));
        throw new RuntimeException(
            'Geen prijslijst-kolom gevonden op cicmpy. Kolommen die op prijs lijken: ' .
            ($like === [] ? '(geen)' : implode(', ', $like)) .
            '. Voeg de juiste naam toe aan PRICELIST_COLUMN_CANDIDATES in klantartikel/inc/queries.php of gebruik ?pcol=<kolom>.'
        );
    }

    $itemLink = null;
    $debtorLink = null;
    foreach (ITEMACCOUNT_LINK_CANDIDATES as $itemCol => $debtorCol) {
        $a = findColumn($item, [$itemCol]);
        $b = findColumn($debtor, [$debtorCol]);
        if ($a !== null && $b !== null) {
            $itemLink = $a;
            $debtorLink = $b;
            break;
        }
    }
    $codeColumn = findColumn($item, [ITEMACCOUNT_CODE_COLUMN]);
    $itemCode = findColumn($item, ['ItemCode']);
    if ($itemLink === null || $codeColumn === null || $itemCode === null) {
        throw new RuntimeException(
            'Kan ItemAccounts niet aan cicmpy koppelen. Kolommen op ItemAccounts: ' . implode(', ', $item) . '.'
        );
    }

    return [
        'pricelist'     => $pricelist,
        'itemLink'      => $itemLink,
        'debtorLink'    => $debtorLink,
        'itemCode'      => $itemCode,
        'codeColumn'    => $codeColumn,
        'itemColumns'   => $item,
        'debtorColumns' => $debtor,
        'hasItems'      => $cols['Items'] !== [],
    ];
}

function q(string $identifier): string
{
    return '[' . str_replace(']', ']]', $identifier) . ']';
}

/** @return list<array<string,mixed>> debiteuren op deze prijslijst */
function findCustomersByPriceList(PDO $pdo, array $schema, string $priceList): array
{
    $p = q($schema['pricelist']);
    $stmt = $pdo->prepare(
        "SELECT LTRIM(RTRIM(debnr)) AS debnr, cmp_name AS naam, LTRIM(RTRIM(CAST(c.{$p} AS varchar(50)))) AS prijslijst " .
        "FROM cicmpy c WHERE LTRIM(RTRIM(CAST(c.{$p} AS varchar(50)))) = :pl AND debnr IS NOT NULL AND LTRIM(RTRIM(debnr)) <> '' " .
        'ORDER BY cmp_name'
    );
    $stmt->execute(['pl' => $priceList]);
    return $stmt->fetchAll();
}

/** @return list<array<string,mixed>> artikelen met klantartikelnummer, voor alle debiteuren op de prijslijst */
function findCustomerArticles(PDO $pdo, array $schema, string $priceList): array
{
    $p = q($schema['pricelist']);
    $link = 'ia.' . q($schema['itemLink']) . ' = c.' . q($schema['debtorLink']);
    $code = 'ia.' . q($schema['codeColumn']);
    $itemCode = 'ia.' . q($schema['itemCode']);
    $descJoin = $schema['hasItems'] ? "LEFT JOIN Items i ON i.ItemCode = {$itemCode}" : '';
    $descCol = $schema['hasItems'] ? 'i.Description' : "CAST('' AS varchar(1))";
    $stmt = $pdo->prepare(
        'SELECT TOP ' . (MAX_ARTICLE_ROWS + 1) . " LTRIM(RTRIM(c.debnr)) AS debnr, c.cmp_name AS klant, {$itemCode} AS artikel, " .
        "{$descCol} AS omschrijving, {$code} AS klantartikel " .
        "FROM ItemAccounts ia JOIN cicmpy c ON {$link} {$descJoin} " .
        "WHERE LTRIM(RTRIM(CAST(c.{$p} AS varchar(50)))) = :pl AND {$code} IS NOT NULL AND LTRIM(RTRIM({$code})) <> '' " .
        'ORDER BY c.cmp_name, ' . $itemCode
    );
    $stmt->execute(['pl' => $priceList]);
    return $stmt->fetchAll();
}

/**
 * Diagnose als er wel klanten maar geen artikelen zijn: welke kolommen heeft
 * ItemAccounts, hoeveel rijen koppelen er per mogelijke koppeling, hoeveel
 * hebben een ingevuld klantartikelnummer, en een paar voorbeeldrijen.
 *
 * @return array{columns: list<string>, links: list<array{link: string, total: string, filled: string}>, sample: list<array<string,mixed>>, error: ?string}
 */
function diagnoseItemAccounts(PDO $pdo, array $schema, string $priceList): array
{
    $p = q($schema['pricelist']);
    $result = ['columns' => $schema['itemColumns'], 'links' => [], 'sample' => [], 'error' => null];
    $code = 'ia.' . q($schema['codeColumn']);
    try {
        foreach (ITEMACCOUNT_LINK_CANDIDATES as $itemCol => $debtorCol) {
            $a = findColumn($schema['itemColumns'], [$itemCol]);
            $b = findColumn($schema['debtorColumns'], [$debtorCol]);
            if ($a === null || $b === null) {
                continue;
            }
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) AS totaal, SUM(CASE WHEN {$code} IS NOT NULL AND LTRIM(RTRIM({$code})) <> '' THEN 1 ELSE 0 END) AS gevuld " .
                'FROM ItemAccounts ia JOIN cicmpy c ON LTRIM(RTRIM(CAST(ia.' . q($a) . ' AS varchar(64)))) = LTRIM(RTRIM(CAST(c.' . q($b) . " AS varchar(64)))) " .
                "WHERE LTRIM(RTRIM(CAST(c.{$p} AS varchar(50)))) = :pl"
            );
            $stmt->execute(['pl' => $priceList]);
            $row = $stmt->fetch();
            $result['links'][] = [
                'link'   => "ItemAccounts.{$a} = cicmpy.{$b}",
                'total'  => (string) ($row['totaal'] ?? 0),
                'filled' => (string) ($row['gevuld'] ?? 0),
            ];
        }
        $result['sample'] = $pdo->query('SELECT TOP 5 * FROM ItemAccounts ia WHERE ' . $code . " IS NOT NULL AND LTRIM(RTRIM({$code})) <> ''")->fetchAll();
    } catch (Throwable $e) {
        $result['error'] = $e->getMessage();
    }
    return $result;
}
