<?php

declare(strict_types=1);

/**
 * Programma's starten zonder shell, met tijdslimiet. Gedeeld door shared/printen.php (smbclient, Chromium, ghostscript)
 * en shared/pcnaam.php (opzoeken van de PC-naam), zodat een trage externe stap de pagina nooit onbeperkt ophoudt.
 */

/** Voert een programma uit zonder shell; geeft ['code', 'out', 'err']. Code 124 = tijdslimiet. */
function geeveRun(array $command, int $timeoutSeconds, array $env = []): array
{
    $environment = array_merge(['PATH' => '/usr/local/bin:/usr/bin:/bin'], $env);
    $process = @proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $environment);
    if (!is_resource($process)) {
        return ['code' => 127, 'out' => '', 'err' => 'Programma niet gevonden: ' . $command[0]];
    }
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $out = '';
    $err = '';
    $deadline = microtime(true) + $timeoutSeconds;
    while (true) {
        $out .= (string) stream_get_contents($pipes[1]);
        $err .= (string) stream_get_contents($pipes[2]);
        $status = proc_get_status($process);
        if (!$status['running']) {
            $out .= (string) stream_get_contents($pipes[1]);
            $err .= (string) stream_get_contents($pipes[2]);
            $code = (int) $status['exitcode'];
            break;
        }
        if (microtime(true) > $deadline) {
            proc_terminate($process, 9);
            $code = 124;
            $err .= 'Tijdslimiet van ' . $timeoutSeconds . ' seconden overschreden.';
            break;
        }
        usleep(50000);
    }
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);
    return ['code' => $code, 'out' => $out, 'err' => $err];
}
