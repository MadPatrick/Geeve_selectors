<?php

declare(strict_types=1);

/**
 * PC-naam van de gebruiker, via reverse DNS van het IP-adres van de request
 * (de Windows-login zelf komt niet mee naar deze Debian-server). Het
 * domeingedeelte valt weg ("PCNL23-0042.geeve.local" -> "PCNL23-0042"). Kent
 * de DNS het IP niet, dan blijft het IP-adres staan.
 *
 * De naam wordt 1x opgezocht en daarna onthouden in de browser-cookie
 * PC_NAAM_COOKIE ("ip|naam", geldig tot de browser sluit; zie
 * pcNaamCookieScript()), zodat niet elke pagina een DNS-lookup doet. Een
 * cookie van een ander IP-adres (nieuw DHCP-adres, laptop elders) telt niet.
 *
 * Gebruikt door shared/header.php (topbalk van elke app) en slkprinten
 * (blok "Geprint" op de slangkaart).
 */
const PC_NAAM_COOKIE = 'geeve_pcnaam';

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

    $cookie = (string) ($_COOKIE[PC_NAAM_COOKIE] ?? '');
    $parts = explode('|', $cookie, 2);
    if (count($parts) === 2 && $parts[0] === $ip && preg_match('/^[A-Za-z0-9._:-]{1,64}$/', $parts[1]) === 1) {
        return $naam = $parts[1];
    }

    $host = gethostbyaddr($ip);
    if ($host === false || $host === '' || $host === $ip) {
        return $naam = $ip;
    }

    return $naam = strtoupper(explode('.', $host, 2)[0]);
}

/**
 * <script> dat de opgezochte PC-naam in de cookie zet (shared/header.php
 * wordt midden in de pagina ge-include, dus setcookie() kan daar niet meer).
 * Leeg als de cookie al klopt.
 */
function pcNaamCookieScript(): string
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $naam = pcNaam();
    $waarde = $ip . '|' . $naam;
    if ($naam === '' || ($_COOKIE[PC_NAAM_COOKIE] ?? '') === $waarde) {
        return '';
    }

    return '<script>document.cookie = ' . json_encode(PC_NAAM_COOKIE . '=' . rawurlencode($waarde) . '; path=/; SameSite=Lax') . ';</script>';
}
