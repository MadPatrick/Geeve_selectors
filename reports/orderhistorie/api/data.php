<?php

declare(strict_types=1);

// Tegel-toegang (Config > Tegels): zonder ontgrendelen geen gegevens.
require_once __DIR__ . '/../../../shared/tiles.php';
tileApiGuard('orderhistorie');

/**
 * Orderhistorie: geleverde orderregels per klant, vanaf de eerste order (de oude Excel toonde alleen de laatste 2 jaar).
 * Kolommen als in die Excel: artnr, afldat, prijslijst, debnm, ordernr, refer, uw referentie, pakbon_dat, art_omsc, aant_gelev.
 *
 * Bron: de view/tabel die al deze kolommen heeft. De naam is niet vastgelegd: orderhistorieBron() zoekt hem op in
 * INFORMATION_SCHEMA (database Exactrapportage en de database van de app; bij gelijke score wint Exactrapportage, daarna de kortste naam). Staat de juiste bron er niet bij,
 * zet dan hieronder ORDERHISTORIE_BRON op de volledige naam, bijv. '[Exactrapportage].[dbo].[naam van de view]'.
 */

require_once __DIR__ . '/../../inc.php';

const ORDERHISTORIE_BRON = '';
const ORDERHISTORIE_MAX = 50000;

/** Veld in de app => mogelijke kolomnamen in de bron (kleine letters, zonder spaties, underscores en streepjes). */
const ORDERHISTORIE_KOLOMMEN = [
    'artnr' => ['artnr', 'artikelnummer', 'artcode'],
    'afldat' => ['afldat', 'afleverdatum'],
    'prijslijst' => ['prijslijst', 'prijslijstnr'],
    'debnm' => ['debnm', 'debiteurnaam', 'klantnaam'],
    'ordernr' => ['ordernr', 'ordernummer'],
    'refer' => ['refer', 'referentie'],
    'uwref' => ['uwreferentie', 'uwref', 'uwrefer'],
    'pakbon' => ['pakbondat', 'pakbondatum'],
    'omsc' => ['artomsc', 'artomschrijving', 'omschrijving'],
    'aantal' => ['aantgelev', 'aantalgeleverd'],
];
/** Optioneel: als de bron een debiteurnummer heeft, filtert de klantkeuze daarop in plaats van op de naam. */
const ORDERHISTORIE_DEBNR = ['debnr', 'debiteurnr', 'debnummer', 'debiteurnummer'];

const ORDERHISTORIE_KOPPEN = [
    'artnr' => 'Artikelnr', 'afldat' => 'Afleverdatum', 'prijslijst' => 'Prijslijst', 'debnm' => 'Debiteur', 'ordernr' => 'Ordernr',
    'refer' => 'Referentie', 'uwref' => 'Uw referentie', 'pakbon' => 'Pakbondatum', 'omsc' => 'Omschrijving', 'aantal' => 'Aantal geleverd',
];

function orderhistorieNorm(string $name): string
{
    return preg_replace('/[^a-z0-9]/', '', strtolower($name)) ?? '';
}

function orderhistorieIdent(string $name): string
{
    return '[' . str_replace(']', ']]', $name) . ']';
}

/**
 * Zoekt de bron en koppelt de kolommen.
 *
 * @return array{bron: string, kolommen: array<string,string>, debnr: ?string}
 */
function orderhistorieBron(PDO $pdo): array
{
    $candidates = [];
    foreach (['[Exactrapportage]', ''] as $db) {
        // Alleen kolommen die op de gezochte lijken, zodat de metadata-query licht blijft.
        $stmt = $pdo->query(
            'SET NOCOUNT ON; SELECT TABLE_SCHEMA, TABLE_NAME, COLUMN_NAME FROM ' . ($db !== '' ? $db . '.' : '') . 'INFORMATION_SCHEMA.COLUMNS ' .
            "WHERE COLUMN_NAME LIKE '%pakbon%' OR COLUMN_NAME LIKE '%gelev%' OR COLUMN_NAME LIKE '%omsc%' OR COLUMN_NAME LIKE '%afldat%'"
        );
        $perTable = [];
        foreach ($stmt->fetchAll() as $r) {
            $perTable[$r['TABLE_SCHEMA'] . '.' . $r['TABLE_NAME']][] = (string) $r['COLUMN_NAME'];
        }
        foreach ($perTable as $key => $names) {
            [$schema, $table] = explode('.', $key, 2);
            $candidates[] = ['db' => $db !== '' ? $db : '', 'schema' => $schema, 'table' => $table, 'names' => $names];
        }
    }
    $best = null;
    $bestScore = -1;
    foreach ($candidates as $c) {
        $score = count(orderhistorieMatch($pdo, $c));
        if ($score > $bestScore || ($score === $bestScore && $best !== null && $c['db'] === $best['db'] && strlen($c['table']) < strlen($best['table']))) {
            $best = $c;
            $bestScore = $score;
        }
    }
    if ($best === null || $bestScore < 0) {
        throw new RuntimeException('Geen bron met orderhistorie gevonden. Zet ORDERHISTORIE_BRON in reports/orderhistorie/api/data.php op de juiste view.');
    }
    return orderhistorieKies($pdo, $best);
}

/** Alle kolommen van een kandidaat ophalen en tellen welke velden erop passen. @return array<string,string> */
function orderhistorieMatch(PDO $pdo, array $c): array
{
    static $cache = [];
    $full = ($c['db'] !== '' ? $c['db'] . '.' : '') . orderhistorieIdent($c['schema']) . '.' . orderhistorieIdent($c['table']);
    if (!isset($cache[$full])) {
        $stmt = $pdo->query(
            'SET NOCOUNT ON; SELECT COLUMN_NAME FROM ' . ($c['db'] !== '' ? $c['db'] . '.' : '') . 'INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ' .
            $pdo->quote($c['schema']) . ' AND TABLE_NAME = ' . $pdo->quote($c['table'])
        );
        $cache[$full] = array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }
    $found = [];
    foreach (ORDERHISTORIE_KOLOMMEN as $field => $options) {
        foreach ($cache[$full] as $name) {
            if (in_array(orderhistorieNorm($name), $options, true)) {
                $found[$field] = $name;
                break;
            }
        }
    }
    return $found;
}

/** @return array{bron: string, kolommen: array<string,string>, debnr: ?string} */
function orderhistorieKies(PDO $pdo, array $c): array
{
    $kolommen = orderhistorieMatch($pdo, $c);
    if (count($kolommen) < count(ORDERHISTORIE_KOLOMMEN)) {
        $mist = array_diff(array_keys(ORDERHISTORIE_KOLOMMEN), array_keys($kolommen));
        throw new RuntimeException(
            'Dichtstbijzijnde bron ' . $c['table'] . ' mist de kolommen: ' . implode(', ', array_map(static fn ($f) => ORDERHISTORIE_KOPPEN[$f], $mist)) .
            '. Zet ORDERHISTORIE_BRON in reports/orderhistorie/api/data.php op de juiste view.'
        );
    }
    $debnr = null;
    $stmt = $pdo->query(
        'SET NOCOUNT ON; SELECT COLUMN_NAME FROM ' . ($c['db'] !== '' ? $c['db'] . '.' : '') . 'INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ' .
        $pdo->quote($c['schema']) . ' AND TABLE_NAME = ' . $pdo->quote($c['table'])
    );
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $name) {
        if (in_array(orderhistorieNorm((string) $name), ORDERHISTORIE_DEBNR, true)) {
            $debnr = (string) $name;
            break;
        }
    }
    return [
        'bron' => ($c['db'] !== '' ? $c['db'] . '.' : '') . orderhistorieIdent($c['schema']) . '.' . orderhistorieIdent($c['table']),
        'kolommen' => $kolommen,
        'debnr' => $debnr,
    ];
}

/** Waarde uit pdo_sqlsrv (string of DateTime) als YYYY-MM-DD. */
function orderhistorieDatum(mixed $v): ?string
{
    if ($v === null || $v === '') {
        return null;
    }
    if ($v instanceof DateTimeInterface) {
        return $v->format('Y-m-d');
    }
    return preg_match('/^(\d{4}-\d{2}-\d{2})/', (string) $v, $m) === 1 ? $m[1] : trim((string) $v);
}

function orderhistorieIso(string $value, string $label): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) !== 1 || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
        throw new RuntimeException('Vul een geldige ' . $label . ' in.');
    }
    return $value;
}

function orderhistorieLike(string $text): string
{
    return '%' . str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $text) . '%';
}

reportApi(function (array $in): array {
    $van = orderhistorieIso((string) ($in['van'] ?? ''), 'begindatum');
    $tot = orderhistorieIso((string) ($in['tot'] ?? ''), 'einddatum');
    $debnr = trim((string) ($in['debnr'] ?? ''));
    $klant = trim((string) ($in['klant'] ?? ''));
    $klantZoek = trim((string) ($in['klantzoek'] ?? ''));
    $artnr = trim((string) ($in['artnr'] ?? ''));
    $omsc = trim((string) ($in['omsc'] ?? ''));
    foreach ([$debnr, $klant, $klantZoek, $artnr, $omsc] as $text) {
        if (strlen($text) > 80) {
            throw new RuntimeException('Een zoekterm is te lang (maximaal 80 tekens).');
        }
    }
    if ($van !== '' && $tot !== '' && $van > $tot) {
        throw new RuntimeException('De begindatum ligt na de einddatum.');
    }
    if ($debnr === '' && $klant === '' && $klantZoek === '' && $van === '' && $tot === '' && $artnr === '' && $omsc === '') {
        throw new RuntimeException('Kies een klant of vul een datum, artikelnummer of omschrijving in.');
    }

    $pdo = reportConnection();
    if (ORDERHISTORIE_BRON !== '') {
        // Vaste bron: kolommen worden via een lege SELECT TOP 0 gekoppeld.
        $meta = $pdo->query('SET NOCOUNT ON; SELECT TOP 0 * FROM ' . ORDERHISTORIE_BRON);
        $names = [];
        for ($i = 0; $i < $meta->columnCount(); $i++) {
            $names[] = (string) ($meta->getColumnMeta($i)['name'] ?? '');
        }
        $kolommen = [];
        $debnrKolom = null;
        foreach (ORDERHISTORIE_KOLOMMEN as $field => $options) {
            foreach ($names as $name) {
                if (in_array(orderhistorieNorm($name), $options, true)) {
                    $kolommen[$field] = $name;
                    break;
                }
            }
        }
        foreach ($names as $name) {
            if (in_array(orderhistorieNorm($name), ORDERHISTORIE_DEBNR, true)) {
                $debnrKolom = $name;
                break;
            }
        }
        $bron = ['bron' => ORDERHISTORIE_BRON, 'kolommen' => $kolommen, 'debnr' => $debnrKolom];
        if (count($kolommen) < count(ORDERHISTORIE_KOLOMMEN)) {
            throw new RuntimeException('De bron ORDERHISTORIE_BRON mist een of meer kolommen van de orderhistorie.');
        }
    } else {
        $bron = orderhistorieBron($pdo);
    }
    $col = static fn (string $field): string => orderhistorieIdent($bron['kolommen'][$field]);

    $where = [];
    $params = [];
    if ($debnr !== '' && $bron['debnr'] !== null) {
        $where[] = 'LTRIM(RTRIM(CAST(' . orderhistorieIdent($bron['debnr']) . ' AS varchar(50)))) = ?';
        $params[] = $debnr;
    } elseif ($klant !== '') {
        $where[] = 'LTRIM(RTRIM(' . $col('debnm') . ')) = ?';
        $params[] = $klant;
    }
    if ($klantZoek !== '') {
        $where[] = $col('debnm') . ' LIKE ?';
        $params[] = orderhistorieLike($klantZoek);
    }
    if ($van !== '') {
        $where[] = $col('afldat') . ' >= CAST(? AS datetime)';
        $params[] = $van;
    }
    if ($tot !== '') {
        $where[] = $col('afldat') . ' < DATEADD(day, 1, CAST(? AS datetime))';
        $params[] = $tot;
    }
    if ($artnr !== '') {
        $where[] = $col('artnr') . ' LIKE ?';
        $params[] = orderhistorieLike($artnr);
    }
    if ($omsc !== '') {
        $where[] = $col('omsc') . ' LIKE ?';
        $params[] = orderhistorieLike($omsc);
    }

    $fields = array_keys(ORDERHISTORIE_KOPPEN);
    $select = implode(', ', array_map(static fn (string $f): string => $col($f), $fields));
    $stmt = $pdo->prepare(
        'SET NOCOUNT ON; SELECT TOP ' . (ORDERHISTORIE_MAX + 1) . ' ' . $select . ' FROM ' . $bron['bron'] . ' WITH (NOLOCK)' .
        ($where ? ' WHERE ' . implode(' AND ', $where) : '') .
        ' ORDER BY ' . $col('afldat') . ' DESC, ' . $col('ordernr') . ', ' . $col('artnr')
    );
    $stmt->execute($params);

    $dates = array_map(static fn (string $f): bool => in_array($f, ['afldat', 'pakbon'], true), $fields);
    $numeric = array_map(static fn (string $f): bool => $f === 'aantal', $fields);
    $rows = [];
    while ($r = $stmt->fetch(PDO::FETCH_NUM)) {
        foreach ($r as $i => $v) {
            if ($dates[$i]) {
                $r[$i] = orderhistorieDatum($v);
            } elseif ($v === null) {
                $r[$i] = null;
            } else {
                $v = trim((string) $v);
                $r[$i] = $numeric[$i] && is_numeric($v) ? $v + 0 : $v;
            }
        }
        $rows[] = $r;
    }
    $capped = count($rows) > ORDERHISTORIE_MAX;
    if ($capped) {
        array_pop($rows);
    }

    $kop = [];
    if ($klant !== '' || $klantZoek !== '') {
        $kop[] = ['label' => 'Klant', 'value' => $klant !== '' ? $klant : $klantZoek];
    }
    if ($van !== '' || $tot !== '') {
        $kop[] = ['label' => 'Afgeleverd', 'value' => ($van !== '' ? implode('-', array_reverse(explode('-', $van))) : 'begin') . ' t/m ' . ($tot !== '' ? implode('-', array_reverse(explode('-', $tot))) : 'nu')];
    }
    if ($capped) {
        $kop[] = ['label' => 'Let op', 'value' => 'alleen de nieuwste ' . ORDERHISTORIE_MAX . ' regels, verfijn het filter'];
    }

    return [
        'columns' => array_values(ORDERHISTORIE_KOPPEN),
        'numeric' => $numeric,
        'dates' => $dates,
        'rows' => $rows,
        'bron' => $bron['bron'],
        'kop' => $kop,
        'klantNaam' => $klant !== '' ? $klant : ($klantZoek !== '' ? $klantZoek : 'alle klanten'),
    ];
}, false);
