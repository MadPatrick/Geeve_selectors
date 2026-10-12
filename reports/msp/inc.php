<?php

declare(strict_types=1);

// Zelfde SQL Server, database "005" en inloggegevens (via Config) als /stauff. De berekening is die van de
// stored procedure dbo.SP_MSP (de Excel "MSP CLAIM"), maar dan als één lees-query (sql/msp_query.sql) die in
// "005" draait en niets in de database aanmaakt of wegschrijft: het app-account heeft dus alleen leesrechten nodig.
require_once __DIR__ . '/../../stauff/inc/config.php';

/** Kolommen waarvan de waarde als getal wordt teruggegeven (rest blijft tekst). */
const MSP_NUMERIC_COLUMNS = ['qty', 'FINartgrp', 'Invoice Price'];

function mspConnection(): PDO
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

/**
 * Voert de MSP-query uit voor de gevraagde factuurmaand en geeft kolomnamen en rijen terug.
 *
 * @return array{columns: list<string>, rows: list<list<string|int|float|null>>}
 */
function mspFetch(int $year, int $month): array
{
    if ($year < 2000 || $year > 2100 || $month < 1 || $month > 12) {
        throw new RuntimeException('Ongeldige maand of jaar.');
    }

    $sql = @file_get_contents(__DIR__ . '/sql/msp_query.sql');
    if ($sql === false || trim($sql) === '') {
        throw new RuntimeException('De MSP-query (sql/msp_query.sql) kon niet worden gelezen.');
    }

    // Alleen gecontroleerde gehele getallen worden in de query gezet.
    $sql = str_replace(['{{JAAR}}', '{{MAAND}}'], [(string) $year, (string) $month], $sql);

    $pdo = mspConnection();
    $stmt = $pdo->query("SET NOCOUNT ON;\n" . $sql);

    $columns = [];
    for ($i = 0; $i < $stmt->columnCount(); $i++) {
        $columns[] = (string) ($stmt->getColumnMeta($i)['name'] ?? '');
    }

    $rows = [];
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $rows[] = mspNormalizeRow($columns, $row);
    }

    return ['columns' => $columns, 'rows' => $rows];
}

/**
 * @param list<string> $columns
 * @param list<mixed> $row
 * @return list<string|int|float|null>
 */
function mspNormalizeRow(array $columns, array $row): array
{
    $out = [];
    foreach ($row as $i => $value) {
        $name = $columns[$i] ?? '';
        if ($value === null) {
            $out[] = null;
        } elseif (in_array($name, MSP_NUMERIC_COLUMNS, true) && is_numeric($value)) {
            $out[] = $value + 0;
        } elseif ($value instanceof DateTimeInterface) {
            $out[] = $value->format('Y-m-d');
        } else {
            $text = rtrim((string) $value); // char-kolommen komen met opvulspaties
            // Datum-tijd uit SQL Server ("2026-07-31 00:00:00.000") alleen als datum tonen.
            $out[] = preg_match('/^\d{4}-\d{2}-\d{2} 00:00:00(\.0+)?$/', $text) === 1 ? substr($text, 0, 10) : $text;
        }
    }
    return $out;
}
