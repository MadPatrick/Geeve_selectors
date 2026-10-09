<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

final class DatabaseConfigException extends RuntimeException
{
}

/**
 * Maakt een PDO-verbinding met SQL Server via de Microsoft PDO_SQLSRV-
 * driver (ODBC Driver 17/18 for SQL Server), voor 1 databaseconfig-array
 * (zie appConfig()). Gedeeld door getPdoConnection() en
 * getExactPdoConnection() hieronder - alleen de databasenaam/inloggegevens
 * verschillen.
 */
function connectSqlServer(array $db, string $missingCredentialsHint): PDO
{
    if (!in_array('sqlsrv', PDO::getAvailableDrivers(), true)) {
        throw new DatabaseConfigException(
            'De PDO_SQLSRV driver is niet geinstalleerd op deze PHP-omgeving. ' .
            'Zie README.md, sectie "SQL Server driver installeren".'
        );
    }

    if ($db['user'] === null || $db['password'] === null) {
        throw new DatabaseConfigException($missingCredentialsHint);
    }

    $server = $db['host'] . ($db['port'] !== null ? ',' . $db['port'] : '');
    // LoginTimeout is de PDO_SQLSRV-specifieke manier om een verbindings-
    // timeout te zetten - PDO::ATTR_TIMEOUT wordt door deze driver niet
    // ondersteund ("unsupported attribute") en geeft dan een foutmelding.
    $dsn = "sqlsrv:Server={$server};Database={$db['name']}" .
        ";Encrypt=yes;TrustServerCertificate={$db['trustServerCertificate']};LoginTimeout=15";

    try {
        return new PDO($dsn, $db['user'], $db['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (PDOException $exception) {
        throw new DatabaseConfigException(
            'Kan geen verbinding maken met de database: ' . $exception->getMessage(),
            0,
            $exception
        );
    }
}

/** Verbinding met de "Slangkaarten"-database (order-/slangkaartdata). */
function getPdoConnection(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    return $pdo = connectSqlServer(
        appConfig()['db'],
        'Database-inloggegevens ontbreken. Zet DB_USER en DB_PASSWORD ' .
        '(zie slangkaarten/README.md) voor de read-only SQL-login van deze webapp.'
    );
}

/**
 * Verbinding met de Exact-database "005" (zelfde server, ander doel: de
 * artikellocatie opzoeken voor de picklijst, zie findArtikelLocatie()).
 */
function getExactPdoConnection(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    return $pdo = connectSqlServer(
        appConfig()['exactDb'],
        'Exact-database-inloggegevens ontbreken. Zet EXACT_DB_USER en ' .
        'EXACT_DB_PASSWORD via Config in het hoofdmenu ' .
        'voor de read-only SQL-login op database "005".'
    );
}
