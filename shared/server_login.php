<?php

declare(strict_types=1);

/**
 * Inloggen op de Config-pagina met een serveraccount (lid van de groep "sudo").
 * De controle zelf gebeurt door het root-hulpscript uit server-setup/ (via sudo); zie server-setup/README.md.
 */
const GEEVE_AUTH_HELPER = '/usr/local/sbin/geeve-auth';
const LOGIN_MAX_FAILURES = 8;
const LOGIN_FAILURE_WINDOW = 300;
const LOGIN_IDLE_SECONDS = 1800;

/** Is het hulpscript geinstalleerd? Zo niet, dan blijft de pagina open (met waarschuwing). */
function serverLoginAvailable(): bool
{
    return is_file(GEEVE_AUTH_HELPER);
}

function loginFailureFile(): string
{
    return sys_get_temp_dir() . '/geeve_login_failures.json';
}

/** @return list<int> tijdstippen van recente mislukte pogingen */
function recentLoginFailures(): array
{
    $data = json_decode((string) @file_get_contents(loginFailureFile()), true);
    $now = time();
    return array_values(array_filter(is_array($data) ? $data : [], static fn ($t) => is_int($t) && $t > $now - LOGIN_FAILURE_WINDOW));
}

function loginBlocked(): bool
{
    return count(recentLoginFailures()) >= LOGIN_MAX_FAILURES;
}

function recordLoginFailure(): void
{
    $failures = recentLoginFailures();
    $failures[] = time();
    @file_put_contents(loginFailureFile(), json_encode($failures), LOCK_EX);
}

/** Controleert gebruikersnaam en wachtwoord via het hulpscript (invoer via stdin, niet via argumenten). */
function serverLoginCheck(string $user, string $password): bool
{
    if ($user === '' || $password === '' || !function_exists('proc_open') || !serverLoginAvailable()) {
        return false;
    }
    if (str_contains($user, "\n") || str_contains($password, "\n")) {
        return false;
    }

    $process = @proc_open(
        ['sudo', '-n', GEEVE_AUTH_HELPER],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes
    );
    if (!is_resource($process)) {
        return false;
    }
    fwrite($pipes[0], $user . "\n" . $password);
    fclose($pipes[0]);
    stream_get_contents($pipes[1]);
    stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return proc_close($process) === 0;
}
