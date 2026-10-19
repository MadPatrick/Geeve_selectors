<?php

declare(strict_types=1);

/**
 * Versleutelde instellingen (o.a. de Exact-database inloggegevens), in te
 * vullen via Config in het hoofdmenu. Opslag: AES-256-GCM in ".settings.enc"
 * met een willekeurige sleutel in ".settings.key" (beide dotfiles in de
 * portal-root: niet leesbaar als tekst, niet via de browser op te halen
 * door .htaccess en niet in git door .gitignore).
 *
 * Let op: de sleutel staat op dezelfde server als het versleutelde bestand.
 * Dit beschermt tegen meelezen/per ongeluk kopieren van het bestand, niet
 * tegen iemand met leestoegang tot de hele map. Voor dat laatste: zet
 * .settings.key buiten de webroot via de omgevingsvariabele
 * GEEVE_SETTINGS_KEY_FILE.
 */

const SECURE_SETTINGS_KEYS = [
    'EXACT_DB_HOST',
    'EXACT_DB_PORT',
    'EXACT_DB_NAME',
    'EXACT_DB_USER',
    'EXACT_DB_PASSWORD',
    'EXACT_DB_TRUST_SERVER_CERT',
    // Eigen database van /slkprinten (database "Slangkaarten"); server en certificaat volgen de Exact-instellingen.
    'DB_USER',
    'DB_PASSWORD',
    // Toegangscode voor beveiligde onderdelen (Productiviteit); zonder waarde geldt 1308 (zie shared/access.php).
    'ACCESS_CODE',
    // Per tegel: zichtbaar / beveiligd / in progress (JSON, zie shared/tiles.php); ingesteld via Config > Tegels.
    'TILE_FLAGS',
];

/** Sleutels die uit het serverbestand komen (zie secureSettingsFilePath()); die velden verdwijnen dan uit Config. */
const SECURE_SETTINGS_FILE_KEYS = [
    'EXACT_DB_HOST',
    'EXACT_DB_PORT',
    'EXACT_DB_NAME',
    'EXACT_DB_USER',
    'EXACT_DB_PASSWORD',
    'EXACT_DB_TRUST_SERVER_CERT',
    'DB_USER',
    'DB_PASSWORD',
];

/**
 * Het instellingenbestand van de server, buiten de webmap (standaard /etc/geeve/portal.json, of het pad uit de
 * omgevingsvariabele GEEVE_INSTELLINGEN). JSON ({"EXACT_DB_USER": "..."}) of "SLEUTEL = waarde"-regels (.ini), met de namen uit SECURE_SETTINGS_FILE_KEYS;
 * eigendom van root, leesbaar voor de webserver, alleen op de server te wijzigen.
 */
function secureSettingsFilePath(): string
{
    $custom = getenv('GEEVE_INSTELLINGEN');
    return $custom !== false && $custom !== '' ? $custom : '/etc/geeve/portal.json';
}

/** De waarden uit het serverbestand, of null als dat bestand er niet is (dan geldt het versleutelde bestand in Config). */
function secureSettingsFileValues(): ?array
{
    static $values = false;
    if ($values === false) {
        $path = secureSettingsFilePath();
        $values = null;
        if (is_readable($path)) {
            $parsed = substr($path, -5) === '.json'
                ? json_decode((string) @file_get_contents($path), true)
                : @parse_ini_file($path, false, INI_SCANNER_RAW);
            if (is_array($parsed)) {
                $values = [];
                foreach (SECURE_SETTINGS_FILE_KEYS as $k) {
                    if (isset($parsed[$k]) && is_scalar($parsed[$k]) && trim((string) $parsed[$k]) !== '') {
                        $values[$k] = trim((string) $parsed[$k], " \t\"'");
                    }
                }
            }
        }
    }
    return $values;
}

function secureSettingsKeyPath(): string
{
    $custom = getenv('GEEVE_SETTINGS_KEY_FILE');
    return $custom !== false && $custom !== '' ? $custom : __DIR__ . '/../.settings.key';
}

function secureSettingsDataPath(): string
{
    return __DIR__ . '/../.settings.enc';
}

function secureSettingsKey(bool $create): ?string
{
    $path = secureSettingsKeyPath();
    if (is_readable($path)) {
        $raw = trim((string) file_get_contents($path));
        $key = base64_decode($raw, true);
        return $key !== false && strlen($key) === 32 ? $key : null;
    }
    if (!$create) {
        return null;
    }
    $key = random_bytes(32);
    if (@file_put_contents($path, base64_encode($key), LOCK_EX) === false) {
        return null;
    }
    @chmod($path, 0600);
    return $key;
}

/** Alleen wat versleuteld in .settings.enc staat (voor opslaan in Config). @return array<string,string> */
function secureSettingsReadStored(): array
{
    $path = secureSettingsDataPath();
    if (!is_readable($path) || !function_exists('openssl_decrypt')) {
        return [];
    }
    $key = secureSettingsKey(false);
    $blob = base64_decode(trim((string) file_get_contents($path)), true);
    if ($key === null || $blob === false || strlen($blob) < 29) {
        return [];
    }
    $plain = openssl_decrypt(
        substr($blob, 28),
        'aes-256-gcm',
        $key,
        OPENSSL_RAW_DATA,
        substr($blob, 0, 12),
        substr($blob, 12, 16)
    );
    $data = $plain === false ? null : json_decode($plain, true);
    if (!is_array($data)) {
        return [];
    }
    $out = [];
    foreach (SECURE_SETTINGS_KEYS as $k) {
        if (isset($data[$k]) && is_string($data[$k])) {
            $out[$k] = $data[$k];
        }
    }
    return $out;
}

/**
 * De instellingen zoals de apps ze gebruiken: .settings.enc, met daarover de waarden uit het serverbestand (dat gaat voor).
 *
 * @return array<string,string>
 */
function secureSettingsRead(): array
{
    return array_merge(secureSettingsReadStored(), secureSettingsFileValues() ?? []);
}

/** Laatste foutreden van secureSettingsWrite(), voor in de melding. */
function secureSettingsLastError(): string
{
    return $GLOBALS['secureSettingsError'] ?? '';
}

/** @param array<string,string> $values */
function secureSettingsWrite(array $values): bool
{
    $GLOBALS['secureSettingsError'] = '';
    if (!function_exists('openssl_encrypt')) {
        $GLOBALS['secureSettingsError'] = 'De PHP openssl-extensie ontbreekt op deze server.';
        return false;
    }
    $key = secureSettingsKey(true);
    if ($key === null) {
        $phpUser = function_exists('posix_getpwuid') && function_exists('posix_geteuid')
            ? (posix_getpwuid(posix_geteuid())['name'] ?? get_current_user())
            : get_current_user();
        $dir = realpath(dirname(secureSettingsKeyPath())) ?: dirname(secureSettingsKeyPath());
        $GLOBALS['secureSettingsError'] = "Kon de sleutel niet aanmaken/lezen in {$dir}. PHP draait als \"{$phpUser}\" en moet daar kunnen schrijven. Draai eenmalig op de server: sudo chown {$phpUser}: {$dir}  (of: sudo touch {$dir}/.settings.key {$dir}/.settings.enc && sudo chown {$phpUser}: {$dir}/.settings.*)";
        return false;
    }
    $clean = [];
    $fromFile = secureSettingsFileValues() !== null;
    foreach (SECURE_SETTINGS_KEYS as $k) {
        if ($fromFile && in_array($k, SECURE_SETTINGS_FILE_KEYS, true)) {
            continue; // beheerd op de server, niet meer in .settings.enc
        }
        if (isset($values[$k]) && $values[$k] !== '') {
            $clean[$k] = $values[$k];
        }
    }
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt(
        (string) json_encode($clean, JSON_UNESCAPED_UNICODE),
        'aes-256-gcm',
        $key,
        OPENSSL_RAW_DATA,
        $iv,
        $tag
    );
    if ($cipher === false) {
        $GLOBALS['secureSettingsError'] = 'Versleutelen mislukt.';
        return false;
    }
    $path = secureSettingsDataPath();
    if (@file_put_contents($path, base64_encode($iv . $tag . $cipher), LOCK_EX) === false) {
        $GLOBALS['secureSettingsError'] = 'Kon ' . $path . ' niet schrijven. Geef de gebruiker waaronder PHP draait schrijfrechten op dit bestand of de map.';
        return false;
    }
    @chmod($path, 0600);
    return true;
}

/**
 * Zet de opgeslagen instellingen als omgevingsvariabelen (alleen sleutels
 * die nog niet gezet zijn, dus echte omgevingsvariabelen op de server gaan voor).
 */
function loadSecureSettings(): void
{
    foreach (secureSettingsRead() as $key => $value) {
        if (getenv($key) === false) {
            putenv("{$key}={$value}");
        }
    }
}
