<?php

declare(strict_types=1);

/**
 * Instellingen (database-inloggegevens) komen uit Config in het hoofdmenu: die worden versleuteld
 * opgeslagen (zie shared/secure_settings.php) en hier als omgevingsvariabelen beschikbaar gemaakt.
 * Echte omgevingsvariabelen op de webserver hebben voorrang. Er worden geen .env-bestanden meer gelezen.
 */
if (is_file(__DIR__ . '/../../shared/secure_settings.php')) {
    require_once __DIR__ . '/../../shared/secure_settings.php';
    loadSecureSettings();
}

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
            'host'     => env('DB_HOST', env('EXACT_DB_HOST', 'GEEVE-SQL-2019')),
            'port'     => env('DB_PORT', env('EXACT_DB_PORT')),
            'name'     => env('DB_NAME', 'Slangkaarten'),
            'user'     => env('DB_USER'),
            'password' => env('DB_PASSWORD'),
            // De meeste on-prem SQL Server-installaties gebruiken een
            // zelfondertekend certificaat. ODBC Driver 18 weigert dat
            // sinds kort standaard - "yes" vertrouwt het certificaat
            // (prima binnen een intern netwerk); zet "Server-certificaat vertrouwen" in Config uit
            // als de server een echt (CA-ondertekend) certificaat heeft.
            'trustServerCertificate' => env('DB_TRUST_SERVER_CERT', env('EXACT_DB_TRUST_SERVER_CERT', 'yes')),
        ],
        // Tweede, aparte verbinding: de Exact-database "005" (zelfde server,
        // GEEVE-SQL-2019, ander doel) voor de artikellocatie op de picklijst
        // (tabel CSPickITItemLocations). EXACT_DB_* komt uit Config en is niet
        // dezelfde login als hierboven: dit zijn de inloggegevens die ook /stauff
        // gebruikt voor artikelgroep 67.
        'exactDb' => [
            'host'     => env('EXACT_DB_HOST', 'GEEVE-SQL-2019'),
            'port'     => env('EXACT_DB_PORT'),
            'name'     => env('EXACT_DB_NAME', '005'),
            'user'     => env('EXACT_DB_USER'),
            'password' => env('EXACT_DB_PASSWORD'),
            'trustServerCertificate' => env('EXACT_DB_TRUST_SERVER_CERT', 'yes'),
        ],
    ];
}
