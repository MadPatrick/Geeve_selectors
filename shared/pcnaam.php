<?php

declare(strict_types=1);

/**
 * PC-naam van de gebruiker, via reverse DNS van het IP-adres van de request
 * (de Windows-login zelf komt niet mee naar deze Debian-server). Het
 * domeingedeelte valt weg ("PCNL23-0042.geeve.local" -> "PCNL23-0042"). Kent
 * de DNS het IP niet, dan blijft het IP-adres staan. 1x per request opgezocht.
 *
 * Gebruikt door shared/header.php (topbalk van elke app) en slkprinten
 * (blok "Geprint" op de slangkaart).
 */
function pcNaam(): string
{
    static $naam = null;
    if ($naam !== null) {
        return $naam;
    }

    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    if ($ip === '') {
        return $naam = '';
    }

    $host = gethostbyaddr($ip);
    if ($host === false || $host === '' || $host === $ip) {
        return $naam = $ip;
    }

    return $naam = strtoupper(explode('.', $host, 2)[0]);
}
