<?php

declare(strict_types=1);

// Gedeelde hulpfuncties voor de rapporten die een stored procedure met één ordernummer aanroepen
// (Inkooporder naar Excel, Order of Offerte naar Excel). Zelfde SQL Server en inloggegevens (via Config) als /stauff;
// de procedure wordt met een 3-delige naam aangeroepen, dus het app-account heeft EXECUTE nodig op die procedure.
require_once __DIR__ . '/../stauff/inc/config.php';

const SP_NUMERIC_TYPES = ['int', 'bigint', 'smallint', 'tinyint', 'decimal', 'numeric', 'float', 'real', 'money', 'smallmoney'];
const SP_DATE_TYPES = ['date', 'datetime', 'datetime2', 'smalldatetime'];

function reportConnection(): PDO
{
    if (!in_array('sqlsrv', PDO::getAvailableDrivers(), true)) {
        throw new RuntimeException('De PDO_SQLSRV driver is niet geinstalleerd op deze PHP-omgeving (zie slangkaarten/README.md).');
    }

    $db = appConfig()['db'];
    if ($db['user'] === null || $db['password'] === null) {
        throw new RuntimeException('Database-inloggegevens ontbreken: vul ze in via Config in het hoofdmenu.');
    }

    $server = $db['host'] . ($db['port'] !== null ? ',' . $db['port'] : '');
    $dsn = "sqlsrv:Server={$server};Database={$db['name']};Encrypt=yes;TrustServerCertificate={$db['trustServerCertificate']};LoginTimeout=15";

    try {
        return new PDO($dsn, $db['user'], $db['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (PDOException $e) {
        throw new RuntimeException('Kan geen verbinding maken met database ' . $db['name'] . ': ' . $e->getMessage(), 0, $e);
    }
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
 * Roept een stored procedure aan met één tekstparameter en geeft de eerste resultaatset terug.
 * $procedure is een vaste, in de code bepaalde naam (nooit gebruikersinvoer).
 * $map (optioneel): bron-kolom => nieuwe naam; alleen deze kolommen, in deze volgorde.
 *
 * @param array<string,string>|null $map
 * @return array{columns: list<string>, numeric: list<bool>, dates: list<bool>, rows: list<list<string|int|float|null>>}
 */
function reportCallProcedure(string $procedure, string $param, string $value, ?array $map = null): array
{
    $pdo = reportConnection();
    $stmt = $pdo->prepare("SET NOCOUNT ON;\nEXEC {$procedure} {$param} = ?;");
    $stmt->execute([$value]);

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

/** Gedeelde API-afhandeling: POST + CSRF, roept $fetch(string $nummer) aan en geeft JSON terug. */
function reportApi(callable $fetch): void
{
    require_once __DIR__ . '/../shared/csrf.php';

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !csrfValid($_POST['csrf'] ?? null)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Ongeldig verzoek (sessie verlopen). Laad de pagina opnieuw.']);
        return;
    }

    @set_time_limit(300);
    $flags = JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PRESERVE_ZERO_FRACTION;
    try {
        $data = $fetch(reportOrderNumber($_POST['nummer'] ?? ''));
        echo json_encode(['ok' => true] + $data, $flags);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()], $flags);
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
