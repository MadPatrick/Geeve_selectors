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
// [kolom op ItemAccounts, kolom op cicmpy] - in volgorde geprobeerd; de eerste die rijen oplevert wordt gebruikt.
// Het Exact-importbestand koppelt ItemAccount aan <Account code="debcode"/>, vandaar debcode eerst.
const ITEMACCOUNT_LINK_CANDIDATES = [
    // ItemAccounts.AccountCode bevat een GUID (zie diagnose); cicmpy.cmp_wwn / ID is dezelfde GUID.
    ['AccountCode', 'cmp_wwn'],
    ['AccountCode', 'ID'],
    ['AccountCode', 'debcode'],
    ['AccountCode', 'AccountCode'],
    ['Account', 'debcode'],
    ['Account', 'cmp_wwn'],
    ['Account', 'debnr'],
    ['Account', 'AccountCode'],
    ['cmp_wwn', 'cmp_wwn'],
    ['debnr', 'debnr'],
    ['crdnr', 'crdnr'],
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
 * @return array<string,mixed>
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

    $links = [];
    foreach (ITEMACCOUNT_LINK_CANDIDATES as [$itemCol, $debtorCol]) {
        $x = findColumn($item, [$itemCol]);
        $y = findColumn($debtor, [$debtorCol]);
        if ($x !== null && $y !== null) {
            $links[] = [$x, $y];
        }
    }
    $codeColumn = findColumn($item, [ITEMACCOUNT_CODE_COLUMN]);
    $itemCode = findColumn($item, ['ItemCode']);
    if ($links === [] || $codeColumn === null || $itemCode === null) {
        throw new RuntimeException(
            'Kan ItemAccounts niet aan cicmpy koppelen. Kolommen op ItemAccounts: ' . implode(', ', $item) . '.'
        );
    }

    return [
        'pricelist'     => $pricelist,
        'links'         => $links,
        'accountCode'   => findColumn($debtor, ['AccountCode']),
        'debcode'       => findColumn($debtor, ['debcode']),
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
        'SELECT LTRIM(RTRIM(debnr)) AS debnr, cmp_name AS naam, ' .
        ($schema['accountCode'] !== null ? 'LTRIM(RTRIM(c.' . q($schema['accountCode']) . ')) ' : "CAST('' AS varchar(1)) ") . 'AS relatienr, ' .
        ($schema['debcode'] !== null ? 'LTRIM(RTRIM(c.' . q($schema['debcode']) . ')) ' : "CAST('' AS varchar(1)) ") . 'AS debcode ' .
        "FROM cicmpy c WHERE LTRIM(RTRIM(CAST(c.{$p} AS varchar(50)))) = :pl AND debnr IS NOT NULL AND LTRIM(RTRIM(debnr)) <> '' " .
        'ORDER BY cmp_name'
    );
    $stmt->execute(['pl' => $priceList]);
    return $stmt->fetchAll();
}

/**
 * Probeert de mogelijke koppelingen tot er artikelen zijn.
 *
 * @return array{0: list<array<string,mixed>>, 1: ?array{0:string,1:string}}
 */
function findCustomerArticlesAny(PDO $pdo, array $schema, string $priceList, ?string $debnr = null, ?int $limit = MAX_ARTICLE_ROWS + 1): array
{
    foreach ($schema['links'] as $link) {
        $rows = findCustomerArticles($pdo, $schema, $priceList, $link, $debnr, $limit);
        if ($rows !== []) {
            return [$rows, $link];
        }
    }
    return [[], null];
}

/** @return list<array<string,mixed>> artikelen met klantartikelnummer, voor alle debiteuren op de prijslijst */
function findCustomerArticles(PDO $pdo, array $schema, string $priceList, array $link, ?string $debnr = null, ?int $limit = MAX_ARTICLE_ROWS + 1): array
{
    $p = q($schema['pricelist']);
    $join = 'LTRIM(RTRIM(CAST(ia.' . q($link[0]) . ' AS varchar(64)))) = LTRIM(RTRIM(CAST(c.' . q($link[1]) . ' AS varchar(64))))';
    $code = 'ia.' . q($schema['codeColumn']);
    $itemCode = 'ia.' . q($schema['itemCode']);
    $descJoin = $schema['hasItems'] ? "LEFT JOIN Items i ON i.ItemCode = {$itemCode}" : '';
    $descCol = $schema['hasItems'] ? 'i.Description' : "CAST('' AS varchar(1))";
    $stmt = $pdo->prepare(
        'SELECT ' . ($limit !== null ? "TOP {$limit} " : '') . "LTRIM(RTRIM(c.debnr)) AS debnr, c.cmp_name AS klant, {$itemCode} AS artikel, " .
        "{$descCol} AS omschrijving, {$code} AS klantartikel " .
        "FROM ItemAccounts ia JOIN cicmpy c ON {$join} {$descJoin} " .
        "WHERE LTRIM(RTRIM(CAST(c.{$p} AS varchar(50)))) = :pl AND {$code} IS NOT NULL AND LTRIM(RTRIM({$code})) <> '' " .
        ($debnr !== null ? 'AND LTRIM(RTRIM(c.debnr)) = :debnr ' : '') .
        'ORDER BY c.cmp_name, ' . $itemCode
    );
    $params = ['pl' => $priceList];
    if ($debnr !== null) {
        $params['debnr'] = $debnr;
    }
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/** @return array<string,int> debiteurnr => aantal artikelen met klantartikelnummer */
function countCustomerArticles(PDO $pdo, array $schema, string $priceList, array $link): array
{
    $p = q($schema['pricelist']);
    $join = 'LTRIM(RTRIM(CAST(ia.' . q($link[0]) . ' AS varchar(64)))) = LTRIM(RTRIM(CAST(c.' . q($link[1]) . ' AS varchar(64))))';
    $code = 'ia.' . q($schema['codeColumn']);
    $stmt = $pdo->prepare(
        "SELECT LTRIM(RTRIM(c.debnr)) AS debnr, COUNT(*) AS aantal FROM ItemAccounts ia JOIN cicmpy c ON {$join} " .
        "WHERE LTRIM(RTRIM(CAST(c.{$p} AS varchar(50)))) = :pl AND {$code} IS NOT NULL AND LTRIM(RTRIM({$code})) <> '' " .
        'GROUP BY LTRIM(RTRIM(c.debnr))'
    );
    $stmt->execute(['pl' => $priceList]);
    $out = [];
    foreach ($stmt->fetchAll() as $row) {
        $out[trim((string) $row['debnr'])] = (int) $row['aantal'];
    }
    return $out;
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
        foreach ($schema['links'] as [$a, $b]) {
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

function xmlEscape(string $value): string
{
    $escaped = htmlspecialchars($value, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    // Alles buiten ASCII als &#nnn; (zelfde als de Excel-template "Geeve Import debiteuren").
    return mb_encode_numericentity($escaped, [0x80, 0x10FFFF, 0, 0x1FFFFF], 'UTF-8');
}

/**
 * Exact Globe-importbestand (eExact XML), identiek van opzet aan wat de Excel-template
 * "Geeve Import debiteuren - artikelcodes klant" maakt: per debiteur een <Account> met
 * <Debtor> en daarin alle <ItemAccount>-regels.
 *
 * @param list<array<string,mixed>> $customers
 * @param list<array<string,mixed>> $articles
 */
function buildExactXml(array $customers, array $articles): string
{
    $byDebtor = [];
    foreach ($articles as $a) {
        $byDebtor[trim((string) $a['debnr'])][] = $a;
    }

    $xml = "<?xml version=\"1.0\" ?>\r\n" .
        "<eExact xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xsi:noNamespaceSchemaLocation=\"eExact-Schema.xsd\">\r\n" .
        "<Accounts>\r\n";
    foreach ($customers as $c) {
        $debnr = trim((string) $c['debnr']);
        if (!isset($byDebtor[$debnr])) {
            continue;
        }
        $debcode = trim((string) ($c['debcode'] ?? ''));
        $xml .= '<Account code="' . xmlEscape(trim((string) ($c['relatienr'] ?? ''))) . "\" status=\"A\" type=\"C\">\r\n" .
            '  <Name>' . xmlEscape((string) $c['naam']) . "</Name>\r\n" .
            '  <Debtor number="' . xmlEscape($debnr) . '" code="' . xmlEscape($debcode) . "\">\r\n" .
            "    <ItemAccounts>\r\n";
        foreach ($byDebtor[$debnr] as $a) {
            $xml .= "      <ItemAccount>\r\n" .
                '        <Account code="' . xmlEscape($debcode) . "\"/>\r\n" .
                '        <ItemCode>' . xmlEscape(trim((string) $a['artikel'])) . "</ItemCode>\r\n" .
                '        <ItemCodeAccount>' . xmlEscape(trim((string) $a['klantartikel'])) . "</ItemCodeAccount>\r\n" .
                "      </ItemAccount>\r\n";
        }
        $xml .= "    </ItemAccounts>\r\n  </Debtor>\r\n</Account>\r\n";
    }
    return $xml . "</Accounts>\r\n</eExact>\r\n";
}

/**
 * Geconsolideerd: elke combinatie artikel + klantartikelnummer 1x, ongeacht bij hoeveel
 * klanten op de prijslijst die voorkomt (kolom "klanten"). Heeft een artikel bij verschillende
 * klanten een ander klantartikelnummer, dan staat het artikel meerdere keren (1x per nummer).
 *
 * @return list<array<string,mixed>>
 */
function findConsolidatedArticles(PDO $pdo, array $schema, string $priceList, array $link, ?int $limit = MAX_ARTICLE_ROWS + 1): array
{
    $p = q($schema['pricelist']);
    $join = 'LTRIM(RTRIM(CAST(ia.' . q($link[0]) . ' AS varchar(64)))) = LTRIM(RTRIM(CAST(c.' . q($link[1]) . ' AS varchar(64))))';
    $code = 'ia.' . q($schema['codeColumn']);
    $itemCode = 'ia.' . q($schema['itemCode']);
    $descJoin = $schema['hasItems'] ? "LEFT JOIN Items i ON i.ItemCode = {$itemCode}" : '';
    $descCol = $schema['hasItems'] ? 'i.Description' : "CAST('' AS varchar(1))";
    $stmt = $pdo->prepare(
        'SELECT ' . ($limit !== null ? "TOP {$limit} " : '') . "LTRIM(RTRIM({$itemCode})) AS artikel, {$descCol} AS omschrijving, " .
        "LTRIM(RTRIM({$code})) AS klantartikel, COUNT(DISTINCT c.debnr) AS klanten " .
        "FROM ItemAccounts ia JOIN cicmpy c ON {$join} {$descJoin} " .
        "WHERE LTRIM(RTRIM(CAST(c.{$p} AS varchar(50)))) = :pl AND {$code} IS NOT NULL AND LTRIM(RTRIM({$code})) <> '' " .
        "GROUP BY LTRIM(RTRIM({$itemCode})), {$descCol}, LTRIM(RTRIM({$code})) " .
        'ORDER BY LTRIM(RTRIM(' . $itemCode . ')), LTRIM(RTRIM(' . $code . '))'
    );
    $stmt->execute(['pl' => $priceList]);
    return $stmt->fetchAll();
}

/**
 * Zoekt een artikel in Exact (exacte match op ItemCode, hoofdletterongevoelig door de collation).
 *
 * @return array{found: bool, code: string, description: string}
 */
function lookupItem(PDO $pdo, string $code): array
{
    $stmt = $pdo->prepare('SELECT TOP 1 LTRIM(RTRIM(ItemCode)) AS code, Description AS description FROM Items WHERE LTRIM(RTRIM(ItemCode)) = :code');
    $stmt->execute(['code' => trim($code)]);
    $row = $stmt->fetch();
    return $row
        ? ['found' => true, 'code' => (string) $row['code'], 'description' => trim((string) $row['description'])]
        : ['found' => false, 'code' => trim($code), 'description' => ''];
}

/**
 * Zet de (bewerkte) artikelregels om naar XML: elke regel komt bij alle klanten op de prijslijst.
 *
 * @param list<array<string,mixed>> $customers
 * @param list<array{artikel: string, klantartikel: string}> $rows
 */
function buildExactXmlForRows(array $customers, array $rows): string
{
    $articles = [];
    foreach ($customers as $c) {
        foreach ($rows as $r) {
            $articles[] = ['debnr' => $c['debnr'], 'artikel' => $r['artikel'], 'klantartikel' => $r['klantartikel']];
        }
    }
    return buildExactXml($customers, $articles);
}
