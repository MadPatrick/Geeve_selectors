<?php

declare(strict_types=1);

/**
 * Simple .env loader (no Composer dependency), zodat DB_USER/DB_PASSWORD
 * nooit in code terechtkomen. In productie kun je deze waarden ook direct
 * als echte omgevingsvariabelen op de webserver zetten - dan wordt .env
 * genegeerd voor de sleutels die al bestaan.
 *
 * Dit bestand wordt overal met require_once ingeladen; appConfig() kan
 * daarna zo vaak als nodig aangeroepen worden om de (actuele) configuratie
 * op te halen, zonder het risico dat deze file - en daarmee de functies
 * hieronder - dubbel wordt uitgevoerd.
 */
function loadEnvFile(string $path): void
{
    if (!is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
        $key = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\"'");

        if ($key !== '' && getenv($key) === false) {
            putenv("{$key}={$value}");
        }
    }
}

loadEnvFile(__DIR__ . '/../.env');

function env(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    return $value === false || $value === '' ? $default : $value;
}

function appConfig(): array
{
    return [
        'db' => [
            // Zie README.md - "SQL-toegang" voor het aanmaken/hergebruiken
            // van een SQL-server login voor deze webapp.
            'host'     => env('DB_HOST', 'GEEVE-SQL-2019'),
            'port'     => env('DB_PORT'),
            'name'     => env('DB_NAME', 'Slangkaarten'),
            'user'     => env('DB_USER'),
            'password' => env('DB_PASSWORD'),
            // De meeste on-prem SQL Server-installaties gebruiken een
            // zelfondertekend certificaat. ODBC Driver 18 weigert dat
            // sinds kort standaard - "yes" vertrouwt het certificaat
            // (prima binnen een intern netwerk); zet DB_TRUST_SERVER_CERT=no
            // als de server een echt (CA-ondertekend) certificaat heeft.
            'trustServerCertificate' => env('DB_TRUST_SERVER_CERT', 'yes'),
        ],
    ];
}
