<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

final class DatabaseConfigException extends RuntimeException
{
}

/**
 * Maakt (en hergebruikt) een PDO-verbinding met SQL Server via de
 * Microsoft PDO_SQLSRV-driver (ODBC Driver 17/18 for SQL Server). Praat
 * met de Exact-database "005" (zie inc/config.php), niet met de
 * Slangkaarten-database van /slangkaarten - andere database, zelfde
 * server en dezelfde verbindingslogica.
 */
function getPdoConnection(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    if (!in_array('sqlsrv', PDO::getAvailableDrivers(), true)) {
        throw new DatabaseConfigException(
            'De PDO_SQLSRV driver is niet geinstalleerd op deze PHP-omgeving. ' .
            'Zie slangkaarten/README.md, sectie "SQL Server driver installeren" (zelfde driver).'
        );
    }

    $config = appConfig();
    $db = $config['db'];

    if ($db['user'] === null || $db['password'] === null) {
        throw new DatabaseConfigException(
            'Database-inloggegevens ontbreken. Zet EXACT_DB_USER en ' .
            'EXACT_DB_PASSWORD via Config in het hoofdmenu ' .
            'voor de SQL-login die database "005" mag lezen.'
        );
    }

    $server = $db['host'] . ($db['port'] !== null ? ',' . $db['port'] : '');
    // LoginTimeout is de PDO_SQLSRV-specifieke manier om een verbindings-
    // timeout te zetten - PDO::ATTR_TIMEOUT wordt door deze driver niet
    // ondersteund ("unsupported attribute") en geeft dan een foutmelding.
    $dsn = "sqlsrv:Server={$server};Database={$db['name']}" .
        ";Encrypt=yes;TrustServerCertificate={$db['trustServerCertificate']};LoginTimeout=15";

    try {
        $pdo = new PDO($dsn, $db['user'], $db['password'], [
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

    return $pdo;
}
