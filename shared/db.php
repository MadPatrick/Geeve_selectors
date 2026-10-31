<?php

declare(strict_types=1);

/**
 * Gedeelde verbinding met SQL Server (Microsoft PDO_SQLSRV, ODBC Driver 17/18), voor alle apps en rapporten. Eén plek voor
 * driver-controle, inloggegevens, DSN en foutafhandeling; elke app geeft alleen zijn eigen databaseconfig (appConfig()['db'])
 * en een hint mee voor als de inloggegevens ontbreken.
 *
 * Een mislukte verbinding wordt kort onthouden (GEEVE_DB_ONTHOUD_SECONDEN, ook voor volgende aanvragen): elke poging kan tot
 * GEEVE_DB_LOGIN_TIMEOUT seconden duren, en een klik op een tegel wachtte anders bij een onbereikbare server steeds opnieuw.
 * Dat beschermt ook het account tegen herhaald inloggen met een verkeerd wachtwoord.
 */
require_once __DIR__ . '/paths.php';
require_once __DIR__ . '/fout.php';

const GEEVE_DB_LOGIN_TIMEOUT = 10;
const GEEVE_DB_ONTHOUD_SECONDEN = 20;

if (!class_exists('DatabaseConfigException', false)) {
    final class DatabaseConfigException extends RuntimeException
    {
    }
}

function geeveDbFoutBestand(): string
{
    return geeveDataDir() . '/db/verbinding-mislukt.json';
}

/** Sleutel van een databaseconfig (een hash; het wachtwoord zit erin zodat een gecorrigeerd wachtwoord meteen weer wordt geprobeerd). */
function geeveDbSleutel(array $db): string
{
    return hash('sha256', implode('|', [(string) $db['host'], (string) ($db['port'] ?? ''), (string) $db['name'], (string) $db['user'], (string) $db['password']]));
}

/** @return array<string,array{tot:int,melding:string}> */
function geeveDbFoutenLezen(): array
{
    $json = is_readable(geeveDbFoutBestand()) ? @file_get_contents(geeveDbFoutBestand()) : false;
    $data = $json === false ? null : json_decode($json, true);
    return is_array($data) ? $data : [];
}

function geeveDbFoutOnthouden(string $sleutel, ?string $melding): void
{
    $fouten = geeveDbFoutenLezen();
    foreach ($fouten as $k => $regel) {
        if (!is_array($regel) || (int) ($regel['tot'] ?? 0) <= time()) {
            unset($fouten[$k]);
        }
    }
    if ($melding === null) {
        unset($fouten[$sleutel]);
    } else {
        $fouten[$sleutel] = ['tot' => time() + GEEVE_DB_ONTHOUD_SECONDEN, 'melding' => $melding];
    }
    $map = dirname(geeveDbFoutBestand());
    if (is_dir($map) || @mkdir($map, 0775, true)) {
        @file_put_contents(geeveDbFoutBestand(), json_encode($fouten), LOCK_EX);
    }
}

/**
 * Maakt een PDO-verbinding met SQL Server voor één databaseconfig (host, port, name, user, password, trustServerCertificate).
 * $ontbrekendHint: melding als gebruiker of wachtwoord ontbreken. $queryTimeout: optioneel, seconden per query.
 * Gooit DatabaseConfigException (een RuntimeException) met een melding die veilig op het scherm kan.
 */
function geeveSqlServerConnect(array $db, string $ontbrekendHint, ?int $queryTimeout = null): PDO
{
    static $mislukt = [];

    if (!in_array('sqlsrv', PDO::getAvailableDrivers(), true)) {
        throw new DatabaseConfigException(
            'De PDO_SQLSRV driver is niet geinstalleerd op deze PHP-omgeving. Zie slkprinten/README.md, sectie "SQL Server driver installeren".'
        );
    }
    if ($db['user'] === null || $db['password'] === null) {
        throw new DatabaseConfigException($ontbrekendHint);
    }

    $sleutel = geeveDbSleutel($db);
    if (isset($mislukt[$sleutel])) {
        throw $mislukt[$sleutel];
    }
    $eerder = geeveDbFoutenLezen()[$sleutel] ?? null;
    if (is_array($eerder) && (int) ($eerder['tot'] ?? 0) > time() && is_string($eerder['melding'] ?? null)) {
        throw $mislukt[$sleutel] = new DatabaseConfigException(
            $eerder['melding'] . ' (de verbinding is net mislukt; over ' . ((int) $eerder['tot'] - time()) . ' seconden probeert de portal het opnieuw.)'
        );
    }

    $server = $db['host'] . ($db['port'] !== null ? ',' . $db['port'] : '');
    // LoginTimeout is de PDO_SQLSRV-specifieke manier om een verbindingstimeout te zetten - PDO::ATTR_TIMEOUT wordt door deze
    // driver niet ondersteund ("unsupported attribute") en geeft dan een foutmelding.
    $dsn = "sqlsrv:Server={$server};Database={$db['name']}"
        . ";Encrypt=yes;TrustServerCertificate={$db['trustServerCertificate']};LoginTimeout=" . GEEVE_DB_LOGIN_TIMEOUT;

    try {
        $pdo = new PDO($dsn, $db['user'], $db['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (PDOException $exception) {
        $melding = 'Kan geen verbinding maken met database ' . $db['name'] . ': ' . geeveFoutTekst($exception);
        geeveDbFoutOnthouden($sleutel, $melding);
        throw $mislukt[$sleutel] = new DatabaseConfigException($melding, 0, $exception);
    }

    if ($queryTimeout !== null && defined('PDO::SQLSRV_ATTR_QUERY_TIMEOUT')) {
        $pdo->setAttribute(PDO::SQLSRV_ATTR_QUERY_TIMEOUT, $queryTimeout);
    }
    if ($eerder !== null) {
        geeveDbFoutOnthouden($sleutel, null);
    }
    return $pdo;
}
