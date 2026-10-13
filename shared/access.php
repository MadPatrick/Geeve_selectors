<?php

declare(strict_types=1);

/**
 * Toegangscode voor beveiligde onderdelen (nu: Productiviteit). De code staat versleuteld in de Config-instellingen
 * (sleutel ACCESS_CODE, zie shared/secure_settings.php); is die niet ingesteld, dan is de code 1308.
 * Na een juiste code blijft het onderdeel maximaal ACCESS_IDLE_SECONDS zonder gebruik ontgrendeld; bij elk gebruik loopt die tijd
 * opnieuw. Het Reports-scherm vergrendelt het onderdeel weer (accessLock) zodra je daar terugkomt. Een pagina controleert dat met
 * accessUnlocked() en de bijbehorende API met accessApiGuard(), zodat de gegevens ook zonder de pagina niet op te halen zijn.
 */
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/secure_settings.php';

const ACCESS_DEFAULT_CODE = '1308';
const ACCESS_MAX_ATTEMPTS = 5;
const ACCESS_LOCKOUT_SECONDS = 60;
const ACCESS_IDLE_SECONDS = 900; // 15 minuten zonder gebruik: weer vergrendeld

function accessCode(): string
{
    loadSecureSettings();
    $code = getenv('ACCESS_CODE');
    return $code !== false && trim($code) !== '' ? trim($code) : ACCESS_DEFAULT_CODE;
}

function accessUnlocked(string $scope): bool
{
    csrfStartSession();
    $since = $_SESSION['access_unlocked'][$scope] ?? 0;
    if (!is_int($since) || $since <= 0 || time() - $since > ACCESS_IDLE_SECONDS) {
        unset($_SESSION['access_unlocked'][$scope]);
        return false;
    }
    $_SESSION['access_unlocked'][$scope] = time(); // bij elk gebruik loopt de tijd opnieuw
    return true;
}

function accessLock(string $scope): void
{
    csrfStartSession();
    unset($_SESSION['access_unlocked'][$scope]);
}

/** Controleert de code; na te veel foute pogingen een minuut wachten. @return 'ok'|'fout'|'wacht' */
function accessTryUnlock(string $scope, string $code): string
{
    csrfStartSession();
    $now = time();
    if (($_SESSION['access_locked_until'] ?? 0) > $now) {
        return 'wacht';
    }
    if (hash_equals(accessCode(), trim($code))) {
        $_SESSION['access_unlocked'][$scope] = time();
        $_SESSION['access_failed'] = 0;
        return 'ok';
    }
    $_SESSION['access_failed'] = ($_SESSION['access_failed'] ?? 0) + 1;
    if ($_SESSION['access_failed'] >= ACCESS_MAX_ATTEMPTS) {
        $_SESSION['access_locked_until'] = $now + ACCESS_LOCKOUT_SECONDS;
        $_SESSION['access_failed'] = 0;
    }
    usleep(400000); // vertraagt het raden van de code
    return 'fout';
}

/** Voor API-scripts: stopt met een 403 als het onderdeel niet is ontgrendeld. */
function accessApiGuard(string $scope): void
{
    if (!accessUnlocked($scope)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Vergrendeld: open de pagina en vul de toegangscode in.']);
        exit;
    }
}
