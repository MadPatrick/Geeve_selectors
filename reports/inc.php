<?php

declare(strict_types=1);

// Gedeelde hulpfuncties voor de rapporten die een stored procedure met één ordernummer aanroepen
// (Inkooporder naar Excel, Order of Offerte naar Excel). Zelfde SQL Server en inloggegevens (via Config) als /stauff;
// de procedure wordt met een 3-delige naam aangeroepen, dus het app-account heeft EXECUTE nodig op die procedure.
require_once __DIR__ . '/../stauff/inc/config.php';
require_once __DIR__ . '/../shared/db.php';
require_once __DIR__ . '/../shared/databases.php';

const SP_NUMERIC_TYPES = ['int', 'bigint', 'smallint', 'tinyint', 'decimal', 'numeric', 'float', 'real', 'money', 'smallmoney'];
const SP_DATE_TYPES = ['date', 'datetime', 'datetime2', 'smalldatetime'];

/** Leesverbinding met de Exact-database voor een tegel (id uit TILE_REGISTRY); de database (main/test) kiest Config > Tegels. */
function reportConnection(string $tegel = ''): PDO
{
    return geeveDbVerbinding($tegel, 'exact');
}

/** Alleen cijfers (zoals in de Excel-sjabloon), anders melding. */
function reportOrderNumber(mixed $value): string
{
    $text = trim((string) $value);
    if ($text === '' || preg_match('/^\d{1,18}$/', $text) !== 1) {
        throw new RuntimeException('Vul een geldig nummer in (alleen cijfers).');
    }
    return $text;
}

/**
 * Roept een stored procedure aan met één tekstparameter (of zonder, bij $value null) en geeft de eerste resultaatset terug.
 * $procedure is een vaste, in de code bepaalde naam (nooit gebruikersinvoer).
 * $map (optioneel): bron-kolom => nieuwe naam; alleen deze kolommen, in deze volgorde.
 *
 * @param array<string,string>|null $map
 * @return array{columns: list<string>, numeric: list<bool>, dates: list<bool>, rows: list<list<string|int|float|null>>}
 */
function reportCallProcedure(string $procedure, string $param, ?string $value, ?array $map = null, string $tegel = ''): array
{
    $pdo = reportConnection($tegel);
    // Lege $param: de waarde positioneel doorgeven (zoals Excel/Power Query), zonder de parameternaam te kennen.
    // $value null: procedure zonder parameter.
    if ($value === null) {
        $stmt = $pdo->prepare("SET NOCOUNT ON;\nEXEC {$procedure};");
        $stmt->execute();
    } else {
        $stmt = $pdo->prepare($param === '' ? "SET NOCOUNT ON;\nEXEC {$procedure} ?;" : "SET NOCOUNT ON;\nEXEC {$procedure} {$param} = ?;");
        $stmt->execute([$value]);
    }

    // Eerste resultaatset met kolommen (eventuele "rows affected"-sets overslaan).
    while ($stmt->columnCount() === 0 && $stmt->nextRowset()) {
    }

    $names = [];
    $isNumeric = [];
    $isDate = [];
    for ($i = 0; $i < $stmt->columnCount(); $i++) {
        $meta = $stmt->getColumnMeta($i);
        $names[] = (string) ($meta['name'] ?? '');
        $type = strtolower((string) ($meta['sqlsrv:decl_type'] ?? ''));
        $isNumeric[] = in_array($type, SP_NUMERIC_TYPES, true);
        $isDate[] = in_array($type, SP_DATE_TYPES, true);
    }

    $pick = array_keys($names);
    $outNames = $names;
    if ($map !== null) {
        $pick = [];
        $outNames = [];
        foreach ($map as $source => $target) {
            $at = array_search($source, $names, true);
            if ($at === false) {
                throw new RuntimeException('De stored procedure geeft de kolom "' . $source . '" niet terug.');
            }
            $pick[] = $at;
            $outNames[] = $target;
        }
    }

    $rows = [];
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $out = [];
        foreach ($pick as $i) {
            $out[] = reportNormalizeValue($row[$i], $isNumeric[$i], $isDate[$i]);
        }
        $rows[] = $out;
    }

    return ['columns' => $outNames, 'numeric' => array_map(fn ($i) => $isNumeric[$i], $pick), 'dates' => array_map(fn ($i) => $isDate[$i], $pick), 'rows' => $rows];
}

function reportNormalizeValue(mixed $value, bool $numeric, bool $date = false): string|int|float|null
{
    if ($value === null) {
        return null;
    }
    if ($numeric && is_numeric($value)) {
        return $value + 0;
    }
    if ($value instanceof DateTimeInterface) {
        return $value->format('Y-m-d');
    }
    $text = rtrim((string) $value); // char-kolommen komen met opvulspaties
    // Alleen echte datumkolommen: datum-tijd uit SQL Server ("2026-07-31 00:00:00.000") als jjjj-mm-dd tonen.
    // Tekstkolommen (bijv. artikelnummers als "2213-04-06") blijven ongewijzigd.
    if ($date && preg_match('/^(\d{4}-\d{2}-\d{2})( \d{2}:\d{2}:\d{2}(\.\d+)?)?$/', $text, $m) === 1) {
        return $m[1];
    }
    return $text;
}

/** Gedeelde API-afhandeling: POST + CSRF, roept $fetch aan (met het nummer, of met de POST-velden) en geeft JSON terug. */
function reportApi(callable $fetch, bool $numberOnly = true): void
{
    require_once __DIR__ . '/../shared/csrf.php';

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !csrfValid($_POST['csrf'] ?? null)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Ongeldig verzoek (sessie verlopen). Laad de pagina opnieuw.']);
        return;
    }

    // CSRF is gecontroleerd: de sessie is niet meer nodig. Zonder dit houdt csrfValid() het sessiebestand vast tot de (soms minutenlange) query klaar is
    // en wacht elke andere aanvraag van dezelfde browser (bijv. een klik op een tegel) zolang.
    csrfReleaseSession();

    @set_time_limit(300);
    $flags = JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PRESERVE_ZERO_FRACTION;
    try {
        // $numberOnly: één ordernummer (cijfers); anders krijgt $fetch de hele POST (eigen validatie).
        $data = $fetch($numberOnly ? reportOrderNumber($_POST['nummer'] ?? '') : $_POST);
        echo json_encode(['ok' => true] + $data, $flags);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => geeveFoutTekst($e)], $flags);
    }
}

/**
 * Haalt kolommen (op exacte naam) uit het resultaat.
 *
 * @param array{columns: list<string>, numeric: list<bool>, dates: list<bool>, rows: list<list<string|int|float|null>>} $data
 * @param list<string> $names
 * @return array{columns: list<string>, numeric: list<bool>, dates: list<bool>, rows: list<list<string|int|float|null>>}
 */
function reportDropColumns(array $data, array $names): array
{
    $keep = [];
    foreach ($data['columns'] as $i => $name) {
        if (!in_array($name, $names, true)) {
            $keep[] = $i;
        }
    }
    $data['columns'] = array_map(fn ($i) => $data['columns'][$i], $keep);
    $data['numeric'] = array_map(fn ($i) => $data['numeric'][$i], $keep);
    $data['dates'] = array_map(fn ($i) => $data['dates'][$i] ?? false, $keep);
    $data['rows'] = array_map(fn ($row) => array_map(fn ($i) => $row[$i], $keep), $data['rows']);
    return $data;
}

/**
 * Zet de genoemde kolommen vooraan (in de opgegeven volgorde); de rest volgt in de oorspronkelijke volgorde.
 *
 * @param array{columns: list<string>, numeric: list<bool>, dates: list<bool>, rows: list<list<string|int|float|null>>} $data
 * @param list<string> $first
 * @return array{columns: list<string>, numeric: list<bool>, dates: list<bool>, rows: list<list<string|int|float|null>>}
 */
function reportMoveFirst(array $data, array $first): array
{
    $order = [];
    foreach ($first as $name) {
        $at = array_search($name, $data['columns'], true);
        if ($at !== false) {
            $order[] = $at;
        }
    }
    foreach (array_keys($data['columns']) as $i) {
        if (!in_array($i, $order, true)) {
            $order[] = $i;
        }
    }
    $data['columns'] = array_map(fn ($i) => $data['columns'][$i], $order);
    $data['numeric'] = array_map(fn ($i) => $data['numeric'][$i], $order);
    $data['dates'] = array_map(fn ($i) => $data['dates'][$i] ?? false, $order);
    $data['rows'] = array_map(fn ($row) => array_map(fn ($i) => $row[$i], $order), $data['rows']);
    return $data;
}

/** Eerste gevulde waarde van een kolom, of null. */
function reportFirstValue(array $data, string $column): ?string
{
    $at = array_search($column, $data['columns'], true);
    if ($at === false) {
        return null;
    }
    foreach ($data['rows'] as $row) {
        $v = trim((string) $row[$at]);
        if ($v !== '') {
            return $v;
        }
    }
    return null;
}

/**
 * Houdt alleen de kolommen uit $map over (in die volgorde) en geeft ze de nieuwe naam.
 *
 * @param array{columns: list<string>, numeric: list<bool>, dates: list<bool>, rows: list<list<string|int|float|null>>} $data
 * @param array<string,string> $map bron-kolom => nieuwe naam
 * @return array{columns: list<string>, numeric: list<bool>, dates: list<bool>, rows: list<list<string|int|float|null>>}
 */
function reportSelect(array $data, array $map): array
{
    $pick = [];
    $names = [];
    foreach ($map as $source => $target) {
        $at = array_search($source, $data['columns'], true);
        if ($at === false) {
            throw new RuntimeException('De stored procedure geeft de kolom "' . $source . '" niet terug.');
        }
        $pick[] = $at;
        $names[] = $target;
    }
    return [
        'columns' => $names,
        'numeric' => array_map(fn ($i) => $data['numeric'][$i], $pick),
        'dates' => array_map(fn ($i) => $data['dates'][$i] ?? false, $pick),
        'rows' => array_map(fn ($row) => array_map(fn ($i) => $row[$i], $pick), $data['rows']),
    ];
}

/** Zoektekst in losse woorden (max. 5), voor de klantkeuze van alle tegels. */
function reportZoekWoorden(string $tekst): array
{
    $words = array_values(array_filter(preg_split('/\s+/', trim($tekst)) ?: [], static fn (string $w): bool => $w !== ''));

    return array_slice($words, 0, 5);
}

/** LIKE-patroon '%woord%' waarin % [ en _ in de zoekterm letterlijk gelden (SQL Server). */
function reportLikePatroon(string $woord): string
{
    return '%' . str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $woord) . '%';
}

/**
 * Debiteuren zoeken op nummer of naam in Exact (cicmpy) voor de klantkeuze van Backorder en Orderhistorie. GET ?q=<tekst>
 * -> {"ok":true,"rows":[{"debnr","naam"}]}. Schrijft het JSON-antwoord en stopt; $tegel is het tegel-id van de aanroeper
 * (databasekeuze main/test in Config). De aanroeper doet zelf tileApiGuard() en zet de JSON-headers.
 */
function reportKlantenZoeken(string $tegel): void
{
    $words = reportZoekWoorden((string) ($_GET['q'] ?? ''));
    if ($words === []) {
        echo json_encode(['ok' => true, 'rows' => []]);
        exit;
    }

    try {
        $where = ['c.debnr IS NOT NULL', "LTRIM(RTRIM(c.debnr)) <> ''"];
        $params = [];
        foreach ($words as $word) {
            $like = reportLikePatroon($word);
            $where[] = '(c.cmp_name LIKE ? OR LTRIM(RTRIM(c.debnr)) LIKE ?)';
            $params[] = $like;
            $params[] = $like;
        }
        // Een getypt nummer: debiteuren waarvan het nummer ermee begint eerst, van laag naar hoog; daarna de rest op naam.
        $order = 'ORDER BY c.cmp_name';
        if (ctype_digit($words[0])) {
            $order = 'ORDER BY CASE WHEN LTRIM(RTRIM(c.debnr)) LIKE ? THEN 0 ELSE 1 END, TRY_CAST(LTRIM(RTRIM(c.debnr)) AS bigint), c.cmp_name';
            $params[] = $words[0] . '%';
        }
        $stmt = reportConnection($tegel)->prepare(
            'SET NOCOUNT ON; SELECT TOP 20 LTRIM(RTRIM(c.debnr)) AS debnr, c.cmp_name AS naam FROM cicmpy c WHERE ' . implode(' AND ', $where) . ' ' . $order
        );
        $stmt->execute($params);
        $rows = array_map(static fn (array $r): array => [
            'debnr' => (string) $r['debnr'],
            'naam' => trim((string) $r['naam']),
        ], $stmt->fetchAll());
        echo json_encode(['ok' => true, 'rows' => $rows], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => geeveFoutTekst($e)], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }
}
