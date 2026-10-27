<?php

declare(strict_types=1);

/**
 * Centrale paden voor de hele portal - zie shared/README.md ("Centrale data" en "Adressen en links").
 *
 *  - Data (CSV's en hun back-ups) staat centraal in data/<app>/ op portal-rootniveau. Die map staat NIET in git
 *    (alleen data/.htaccess, die de map voor de browser afsluit), zodat een git pull op de server de bestanden die daar
 *    bewerkt worden nooit raakt. De oude plekken (<app>/data/, <app>/) blijven als terugval werken.
 *  - Adressen: elke app heeft een kort adres zonder mappen (bijv. /msp/ i.p.v. /reports/msp/index.php); de vertaling naar
 *    de echte map staat in de root-.htaccess. geeveUrl() en geeveAppUrl() bouwen absolute adressen vanaf de portal-root,
 *    zodat een link ook klopt vanaf een kort adres (waar relatieve ../-paden niet meer naar de echte map wijzen).
 *
 * Geen mb_*-functies: de server heeft geen mbstring.
 */

/** Map van de portal-root (bevat index.php, shared/, data/ ...). */
function geevePortalDir(): string
{
    return dirname(__DIR__);
}

/** Centrale datamap; later desgewenst buiten de webroot via de omgevingsvariabele GEEVE_DATA_DIR. */
function geeveDataDir(): string
{
    $custom = getenv('GEEVE_DATA_DIR');
    return $custom !== false && $custom !== '' ? rtrim($custom, '/') : geevePortalDir() . '/data';
}

/**
 * Alle plekken waar een databestand mag staan, in leesvolgorde: eerst centraal (data/<app>/<bestand>), dan de opgegeven
 * oude plekken. Het eerste element is ook waar een upload naartoe schrijft.
 *
 * @param list<string> $fallbacks
 * @return list<string>
 */
function geeveDataCandidates(string $app, string $file, array $fallbacks = []): array
{
    return array_merge([geeveDataDir() . '/' . $app . '/' . $file], $fallbacks);
}

/** @param list<string> $candidates */
function findFirstReadableFile(array $candidates): ?string
{
    foreach ($candidates as $candidate) {
        if (is_file($candidate) && is_readable($candidate)) {
            return $candidate;
        }
    }

    return null;
}

/**
 * Waar een upload of bewerking een databestand wegschrijft: het bestand dat nu gelezen wordt (zodat lezen en schrijven
 * nooit verschillende bestanden pakken), of als er nog geen is de centrale plek - tenzij die map niet aan te maken of niet
 * schrijfbaar is, dan de eerste oude plek (zoals voorheen).
 *
 * @param list<string> $candidates uit geeveDataCandidates()
 */
function geeveDataWriteTarget(array $candidates): string
{
    $existing = findFirstReadableFile($candidates);
    if ($existing !== null) {
        return $existing;
    }
    $central = $candidates[0];
    $dir = dirname($central);
    if ((is_dir($dir) || @mkdir($dir, 0775, true)) && is_writable($dir)) {
        return $central;
    }
    return $candidates[1] ?? $central;
}

/**
 * Back-upmap voor een databestand: data/backups/<app>/ als het bestand centraal staat, anders (oude plek) de map
 * backups/ naast het bestand, zoals voorheen.
 */
function geeveBackupDir(string $dataFile): string
{
    $central = geeveDataDir() . '/';
    if (strncmp($dataFile, $central, strlen($central)) === 0) {
        $app = strtok(substr($dataFile, strlen($central)), '/');
        return geeveDataDir() . '/backups/' . $app;
    }
    return dirname($dataFile) . '/backups';
}

/**
 * Basisadres van de portal in de browser, met afsluitende "/" (op de Geeve-server "/"). Afgeleid uit het script dat nu
 * draait, zodat het ook klopt als de portal ooit in een submap staat.
 */
function geeveBaseUrl(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $base = '/';
    $scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    $scriptFile = realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? ''));
    $root = realpath(geevePortalDir());
    if ($scriptName !== '' && $scriptFile !== false && $root !== false && strncmp($scriptFile, $root . '/', strlen($root) + 1) === 0) {
        $relative = substr($scriptFile, strlen($root)); // bijv. "/reports/msp/index.php"
        if (substr($scriptName, -strlen($relative)) === $relative) {
            $base = substr($scriptName, 0, -strlen($relative)) . '/';
        }
    }
    return $base;
}

/** Absoluut adres van een bestand vanaf de portal-root, bijv. geeveUrl('reports/assets/sp-report.js'). */
function geeveUrl(string $path): string
{
    return geeveBaseUrl() . ltrim($path, '/');
}

/**
 * Adres van een app: tegel-id uit TILE_REGISTRY (shared/tiles.php) of een van de vaste namen hieronder => de map
 * vanaf de portal-root.
 */
const GEEVE_APP_URLS = [
    'home' => '',
    'hoses' => 'hoses/',
    'adapters' => 'adapters/',
    'slangkaarten' => 'slkprinten/', // tegel-id 'slangkaarten', maar /slangkaarten/ is van Slangkaart.Web (Apache-proxy)
    'slangkaarten-app' => 'slangkaarten/index.php', // map slangkaarten/ deelt het adres met de Apache-proxy naar Slangkaart.Web
    'stickers' => 'stickers/',
    'stauff' => 'stauff/',
    'reports' => 'reports/',
    'templates' => 'templates/',
    'configurator' => 'configurator/',
    'klantartikel' => 'klantartikel/',
    'klantkorting' => 'klantkorting/',
    'msp' => 'reports/msp/',
    'inkoop-groep' => 'reports/inkoop-groep/',
    'productiviteit' => 'reports/productiviteit/',
    'artikel-marge' => 'reports/artikel-marge/',
    'inkooporder' => 'reports/inkooporder/',
    'order-offerte' => 'reports/order-offerte/',
    'offerte-import' => 'reports/offerte-import/',
    'inkooporder-import' => 'reports/inkooporder-import/',
    'export-info' => 'reports/export-info/',
    'artikel-import' => 'reports/artikel-import/',
    'orderhistorie' => 'reports/orderhistorie/',
    'productieorders' => 'reports/productieorders/',
    'slangkaart-netto' => 'reports/slangkaart-netto/',
    'stickers-order' => 'stickers/order/',
    'stickers-productie' => 'stickers/productie/',
    'stickers-artikel' => 'stickers/artikel/',
    'stickers-artikel-groot' => 'stickers/accu/',
    'stickers-slanglabels' => 'stickers/slanglabels/',
    'stickers-ptouch' => 'stickers/ptouch/',
];

function geeveAppUrl(string $id): string
{
    return geeveUrl(GEEVE_APP_URLS[$id] ?? '');
}
