<?php

declare(strict_types=1);

// Zelfde SQL Server, inloggegevens (via Config) en verbindingsinstellingen als /stauff, maar een andere
// database: het MSP-rapport draait een stored procedure in "005_Data_Geeve" (de Excel "MSP CLAIM"
// leest daar dbo.SP_MSP uit via Power Query; de app gebruikt de variant dbo.SP_MSP_App, zie hieronder). Database en procedure zijn te
// overschrijven met MSP_DB_NAME / MSP_PROCEDURE als omgevingsvariabele op de server.
require_once __DIR__ . '/../../stauff/inc/config.php';

const MSP_DEFAULT_DATABASE = '005_Data_Geeve';
// SP_MSP_App is dbo.SP_MSP zonder het wegschrijven van een vaste tabel (zie sql/SP_MSP_App.sql); het app-account
// heeft geen CREATE TABLE-recht en dbo.SP_MSP faalt daarom.
const MSP_DEFAULT_PROCEDURE = 'dbo.SP_MSP_App';

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

    $name = env('MSP_DB_NAME', MSP_DEFAULT_DATABASE);
    $server = $db['host'] . ($db['port'] !== null ? ',' . $db['port'] : '');
    $dsn = "sqlsrv:Server={$server};Database={$name};Encrypt=yes;TrustServerCertificate={$db['trustServerCertificate']};LoginTimeout=15";

    try {
        return new PDO($dsn, $db['user'], $db['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (PDOException $e) {
        $hint = stripos($e->getMessage(), 'Cannot open database') !== false
            ? ' | Het SQL-account uit Config (' . $db['user'] . ') heeft geen toegang tot deze database. Laat een beheerder in database ' . $name .
              ' een gebruiker voor dat account aanmaken en EXECUTE op ' . env('MSP_PROCEDURE', MSP_DEFAULT_PROCEDURE) . ' geven, of controleer of de databasenaam klopt.'
            : '';
        throw new RuntimeException('Kan geen verbinding maken met database ' . $name . ': ' . $e->getMessage() . $hint, 0, $e);
    }
}

/**
 * Voert de MSP-procedure uit en geeft kolomnamen en rijen terug.
 *
 * @return array{columns: list<string>, rows: list<list<string|int|float|null>>}
 */
function mspFetch(): array
{
    $procedure = env('MSP_PROCEDURE', MSP_DEFAULT_PROCEDURE);
    if (preg_match('/^[A-Za-z0-9_]+(\.[A-Za-z0-9_]+)?$/', (string) $procedure) !== 1) {
        throw new RuntimeException('Ongeldige procedurenaam.');
    }

    $pdo = mspConnection();
    $stmt = $pdo->query('SET NOCOUNT ON; EXECUTE ' . $procedure);

    // De procedure kan eerst resultaatloze statements geven; neem de eerste set mét kolommen.
    $guard = 0;
    while ($stmt->columnCount() === 0 && $stmt->nextRowset() && ++$guard < 10) {
        // volgende set
    }
    if ($stmt->columnCount() === 0) {
        throw new RuntimeException('De procedure gaf geen resultaat terug.');
    }

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
