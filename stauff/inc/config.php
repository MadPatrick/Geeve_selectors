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

// Gedeelde Exact-database "005"-inloggegevens staan centraal in de
// portal-root (../../.env vanaf hier), niet los per subapp - hetzelfde
// SQL-account wordt ook door /slangkaarten gebruikt (Locatie op de
// picklijst). Invullen via Config in het hoofdmenu.
// Eerst de versleutelde instellingen uit Config (hoofdmenu), daarna pas .env.
if (is_file(__DIR__ . '/../../shared/secure_settings.php')) {
    require_once __DIR__ . '/../../shared/secure_settings.php';
    loadSecureSettings();
}
loadEnvFile(__DIR__ . '/../../.env');

function env(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    return $value === false || $value === '' ? $default : $value;
}

function appConfig(): array
{
    return [
        'db' => [
            // Zie Config in het hoofdmenu - "EXACT_DB_*" (gedeeld met
            // /slangkaarten). In tegenstelling tot /slangkaarten's eigen
            // "Slangkaarten"-database is dit de Exact-database "005" -
            // zelfde server (GEEVE-SQL-2019), ander doel: het uitlezen
            // van artikelgroep 67 (Stauff).
            'host'     => env('EXACT_DB_HOST', 'GEEVE-SQL-2019'),
            'port'     => env('EXACT_DB_PORT'),
            'name'     => env('EXACT_DB_NAME', '005'),
            'user'     => env('EXACT_DB_USER'),
            'password' => env('EXACT_DB_PASSWORD'),
            // De meeste on-prem SQL Server-installaties gebruiken een
            // zelfondertekend certificaat. ODBC Driver 18 weigert dat
            // sinds kort standaard - "yes" vertrouwt het certificaat
            // (prima binnen een intern netwerk); zet EXACT_DB_TRUST_SERVER_CERT=no
            // als de server een echt (CA-ondertekend) certificaat heeft.
            'trustServerCertificate' => env('EXACT_DB_TRUST_SERVER_CERT', 'yes'),
        ],
    ];
}
