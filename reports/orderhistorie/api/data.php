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
/** Voorkeursbron: de algemene view met alle klanten (Carl maakte daarnaast views per klant, zoals "400 orderhistorie929"). */
const ORDERHISTORIE_VOORKEUR = ['400 orderhistorie'];
/** Velden die in de bron mogen ontbreken; ze blijven dan leeg. */
const ORDERHISTORIE_OPTIONEEL = ['prijslijst', 'refer', 'uwref', 'pakbon'];

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
    'omsc' => ['artomsc', 'artomschrijving', 'omschrijving', 'artnm'],
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
    $GLOBALS['orderhistorie_kandidatenlijst'] = $candidates;
    $best = null;
    $bestScore = -1;
    $GLOBALS['orderhistorie_kandidaten'] = [];
    foreach ($candidates as $c) {
        $matched = orderhistorieMatch($pdo, $c);
        $score = count($matched) + (in_array($c['table'], ORDERHISTORIE_VOORKEUR, true) ? 100 : 0);
        if (array_diff(array_keys(ORDERHISTORIE_KOLOMMEN), ORDERHISTORIE_OPTIONEEL, array_keys($matched)) !== []) {
            $score = min($score, count($matched) - 50); // mist een verplicht veld: alleen als er niets beters is
        }
        if ($score > -45) {
            $GLOBALS['orderhistorie_kandidaten'][] = $c['table'] . ': ' . $score;
        }
        if ($score > $bestScore || ($score === $bestScore && $best !== null && $c['db'] === $best['db'] && strlen($c['table']) < strlen($best['table']))) {
            $best = $c;
            $bestScore = $score;
        }
    }
    if ($best === null) {
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
    $mist = array_diff(array_keys(ORDERHISTORIE_KOLOMMEN), ORDERHISTORIE_OPTIONEEL, array_keys($kolommen));
    if ($mist !== []) {
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

/** Kolomnamen van een view/tabel (volledige naam met haakjes, bijv. [Exactrapportage].[dbo].[naam]). @return list<string> */
function orderhistorieKolomnamen(PDO $pdo, string $full): array
{
    if (preg_match('/^\[([^\]]+)\]\.\[([^\]]+)\]\.\[(.+)\]$/', $full, $m) !== 1) {
        return [];
    }
    $stmt = $pdo->query(
        'SET NOCOUNT ON; SELECT COLUMN_NAME FROM ' . orderhistorieIdent($m[1]) . '.INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ' .
        $pdo->quote($m[2]) . ' AND TABLE_NAME = ' . $pdo->quote($m[3]) . ' ORDER BY ORDINAL_POSITION'
    );
    return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

/** Tekstrapport voor ?diagnose=1: gekozen bron, kolomkoppeling, voorbeeldregels en aantallen per klantfilter. */
function orderhistorieDiagnose(PDO $pdo, array $bron, string $debnr, string $klant): string
{
    $out = ['DIAGNOSE orderhistorie', 'Bron: ' . $bron['bron'], 'Debiteurnummer-kolom: ' . ($bron['debnr'] ?? '(geen)')];
    foreach ($bron['kolommen'] as $field => $name) {
        $out[] = '  ' . $field . ' = ' . $name;
    }
    $leeg = array_diff(array_keys(ORDERHISTORIE_KOLOMMEN), array_keys($bron['kolommen']));
    $out[] = 'Ontbrekende kolommen (blijven leeg): ' . ($leeg === [] ? '(geen)' : implode(', ', $leeg));
    if (preg_match('/^\[([^\]]+)\]\.\[([^\]]+)\]\.\[(.+)\]$/', $bron['bron'], $mm) === 1) {
        $types = $pdo->query('SET NOCOUNT ON; SELECT COLUMN_NAME + \' \' + DATA_TYPE FROM ' . orderhistorieIdent($mm[1]) . '.INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ' . $pdo->quote($mm[2]) . ' AND TABLE_NAME = ' . $pdo->quote($mm[3]) . ' ORDER BY ORDINAL_POSITION');
        $out[] = 'Kolomtypes: ' . implode(', ', $types->fetchAll(PDO::FETCH_COLUMN));
    }
    $out[] = 'Alle kolommen van de bron: ' . implode(', ', orderhistorieKolomnamen($pdo, $bron['bron']));
    foreach ($GLOBALS['orderhistorie_kandidatenlijst'] ?? [] as $c) {
        if (preg_match('/^400 (TB )?orderhistorie( laatste 2 jaar| per debiteur| per artikel| 1932| 512)?$/', $c['table']) === 1) {
            $m = orderhistorieMatch($pdo, $c);
            $mist = array_diff(array_keys(ORDERHISTORIE_KOLOMMEN), array_keys($m));
            $out[] = 'Variant "' . $c['table'] . '": mist ' . ($mist === [] ? '(niets)' : implode(', ', $mist)) . '; kolommen: ' .
                implode(', ', orderhistorieKolomnamen($pdo, '[Exactrapportage].[' . $c['schema'] . '].[' . $c['table'] . ']'));
        }
    }
    $out[] = 'Kandidaten: ' . implode(' | ', $GLOBALS['orderhistorie_kandidaten'] ?? ['(vaste bron)']);
    $cols = array_map(static fn (string $n): string => orderhistorieIdent($n), array_values($bron['kolommen']));
    if ($bron['debnr'] !== null) {
        $cols[] = orderhistorieIdent($bron['debnr']);
    }
    $run = static function (string $label, string $sql, array $params, bool $count) use ($pdo, &$out): void {
        $t = microtime(true);
        try {
            $stmt = $pdo->prepare('SET NOCOUNT ON; ' . $sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_NUM);
            $ms = (int) round((microtime(true) - $t) * 1000);
            if ($count) {
                $out[] = $label . ': ' . ($rows[0][0] ?? '?') . ' (' . $ms . ' ms)';
            } else {
                $out[] = $label . ' (' . $ms . ' ms, ' . count($rows) . ' regels):';
                foreach ($rows as $r) {
                    $out[] = '  ' . implode(' | ', array_map(static fn ($v): string => $v instanceof DateTimeInterface ? $v->format('Y-m-d') : trim((string) $v), $r));
                }
            }
        } catch (Throwable $e) {
            $out[] = $label . ': FOUT ' . $e->getMessage();
        }
    };
    $afl = orderhistorieIdent($bron['kolommen']['afldat']);
    $run('Totaal, oudste en nieuwste afleverdatum', 'SELECT CAST(COUNT(*) AS varchar(20)) + \' regels, ' . 'van \' + CONVERT(varchar(10), MIN(' . $afl . '), 105) + \' t/m \' + CONVERT(varchar(10), MAX(' . $afl . '), 105) FROM ' . $bron['bron'] . ' WITH (NOLOCK)', [], false);
    $run('Nieuwste 3 regels', 'SELECT TOP 3 ' . implode(', ', $cols) . ' FROM ' . $bron['bron'] . ' WITH (NOLOCK) ORDER BY ' . $afl . ' DESC', [], false);
    $run('Aantal regels met Wohler in de naam', 'SELECT COUNT(*) FROM ' . $bron['bron'] . ' WITH (NOLOCK) WHERE ' . orderhistorieIdent($bron['kolommen']['debnm']) . " LIKE '%WOHLER%'", [], true);
    $run('Eerste 3 regels van de bron', 'SELECT TOP 3 ' . implode(', ', $cols) . ' FROM ' . $bron['bron'] . ' WITH (NOLOCK)', [], false);
    $debCol = $bron['debnr'] !== null ? orderhistorieIdent($bron['debnr']) : null;
    $nmCol = orderhistorieIdent($bron['kolommen']['debnm']);
    if ($debnr !== '' && $debCol !== null) {
        $run('Aantal met debnr = ' . $debnr, 'SELECT COUNT(*) FROM ' . $bron['bron'] . ' WITH (NOLOCK) WHERE LTRIM(RTRIM(CAST(' . $debCol . ' AS varchar(50)))) = ?', [$debnr], true);
    }
    if ($klant !== '') {
        $run('Aantal met naam = ' . $klant, 'SELECT COUNT(*) FROM ' . $bron['bron'] . ' WITH (NOLOCK) WHERE LTRIM(RTRIM(' . $nmCol . ')) = ?', [$klant], true);
    }
    $zoek = $debnr !== '' ? $debnr : $klant;
    if ($zoek !== '') {
        $like = orderhistorieLike($zoek);
        $run('Voorbeelden waar nummer of naam "' . $zoek . '" bevat', 'SELECT DISTINCT TOP 5 ' . $nmCol . ($debCol !== null ? ', ' . $debCol : '') . ' FROM ' . $bron['bron'] . ' WITH (NOLOCK) WHERE ' . $nmCol . ' LIKE ?' . ($debCol !== null ? ' OR CAST(' . $debCol . ' AS varchar(50)) LIKE ?' : ''), $debCol !== null ? [$like, $like] : [$like], false);
    }
    return implode("\n", $out);
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
    if ($debnr === '' && $klant === '' && $klantZoek === '' && $van === '' && $artnr === '' && $omsc === '') {
        throw new RuntimeException('Kies een klant of vul een begindatum, artikelnummer of omschrijving in.');
    }

    $pdo = reportConnection();
    $msBron = 0;
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
        if (array_diff(array_keys(ORDERHISTORIE_KOLOMMEN), ORDERHISTORIE_OPTIONEEL, array_keys($kolommen)) !== []) {
            throw new RuntimeException('De bron ORDERHISTORIE_BRON mist een of meer verplichte kolommen van de orderhistorie.');
        }
    } else {
        // De gevonden bron staat in de sessie: de zoektocht in INFORMATION_SCHEMA hoeft maar één keer.
        $t0 = microtime(true);
        if (isset($_SESSION['orderhistorie_bron2']) && is_array($_SESSION['orderhistorie_bron2'])) {
            $bron = $_SESSION['orderhistorie_bron2'];
        } else {
            $bron = orderhistorieBron($pdo);
            $_SESSION['orderhistorie_bron2'] = $bron;
        }
        $msBron = (int) round((microtime(true) - $t0) * 1000);
    }
    $col = static fn (string $field): string => isset($bron['kolommen'][$field]) ? orderhistorieIdent($bron['kolommen'][$field]) : 'CAST(NULL AS varchar(1))';

    if (($in['diagnose'] ?? '') !== '') {
        unset($_SESSION['orderhistorie_bron2']);
        throw new RuntimeException(orderhistorieDiagnose($pdo, $bron, $debnr, $klant));
    }

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
        $where[] = 'TRY_CONVERT(datetime, ' . $col('afldat') . ') >= CONVERT(datetime, ?, 112)';
        $params[] = str_replace('-', '', $van);
    }
    if ($tot !== '') {
        $where[] = 'TRY_CONVERT(datetime, ' . $col('afldat') . ') < DATEADD(day, 1, CONVERT(datetime, ?, 112))';
        $params[] = str_replace('-', '', $tot);
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
    $t1 = microtime(true);
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
    $msOphalen = (int) round((microtime(true) - $t1) * 1000);
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
        'tijden' => ['bron' => $msBron, 'ophalen' => $msOphalen],
        'kop' => $kop,
        'klantNaam' => $klant !== '' ? $klant : ($klantZoek !== '' ? $klantZoek : 'alle klanten'),
    ];
}, false);
