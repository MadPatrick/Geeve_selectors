<?php

declare(strict_types=1);

/**
 * PC-naam van de gebruiker, via reverse DNS van het IP-adres van de request
 * (de Windows-login zelf komt niet mee naar deze Debian-server). Het
 * domeingedeelte valt weg ("PCNL23-0042.geeve.local" -> "PCNL23-0042"). Kent
 * de DNS het IP niet, dan blijft het IP-adres staan.
 *
 * De naam wordt 1x opgezocht (met een tijdslimiet van 2 seconden), op de server onthouden per IP-adres (data/pcnamen/) en in de
 * browser-cookie PC_NAAM_COOKIE ("ip|naam", een dag geldig; zie
 * pcNaamCookieScript()), zodat niet elke pagina een DNS-lookup doet. Een
 * cookie van een ander IP-adres (nieuw DHCP-adres, laptop elders) telt niet.
 *
 * Gebruikt door shared/header.php (topbalk van elke app) en slkprinten
 * (blok "Geprint" op de slangkaart).
 */
const PC_NAAM_COOKIE = 'geeve_pcnaam';
const PC_NAAM_ZOEK_SECONDEN = 2;              // langer wachten we niet op de DNS
const PC_NAAM_CACHE_SECONDEN = 86400;         // gevonden naam: een dag onthouden
const PC_NAAM_CACHE_MISLUKT_SECONDEN = 600;   // niet gevonden (IP blijft staan): 10 minuten, daarna opnieuw proberen

require_once __DIR__ . '/paths.php';
require_once __DIR__ . '/run.php';

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

    $gecached = pcNaamUitCache($ip);
    if ($gecached !== null) {
        return $naam = $gecached;
    }

    return $naam = pcNaamOpzoeken($ip);
}

/** Bestand met de opgezochte namen per IP-adres (in de centrale datamap, niet in git). */
function pcNaamCacheBestand(): string
{
    return geeveDataDir() . '/pcnamen/pcnamen.json';
}

/** @return array<string,array{naam:string,tot:int}> */
function pcNaamCacheLezen(): array
{
    $json = is_readable(pcNaamCacheBestand()) ? @file_get_contents(pcNaamCacheBestand()) : false;
    $data = $json === false ? null : json_decode($json, true);
    return is_array($data) ? $data : [];
}

function pcNaamUitCache(string $ip): ?string
{
    $regel = pcNaamCacheLezen()[$ip] ?? null;
    if (is_array($regel) && is_string($regel['naam'] ?? null) && (int) ($regel['tot'] ?? 0) > time()) {
        return $regel['naam'];
    }
    return null;
}

/**
 * Zoekt de naam op via "getent hosts" met een tijdslimiet van PC_NAAM_ZOEK_SECONDEN (gethostbyaddr() heeft geen tijdslimiet:
 * een trage of ontbrekende reverse-DNS hield de pagina dan tot de resolver-timeout op). Het resultaat gaat in de cache; een
 * mislukte poging wordt korter onthouden dan een gelukte, zodat niet elke pagina opnieuw wacht.
 */
function pcNaamOpzoeken(string $ip): string
{
    $naam = $ip;
    $geldigheid = PC_NAAM_CACHE_MISLUKT_SECONDEN;
    if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
        $resultaat = geeveRun(['/usr/bin/getent', 'hosts', $ip], PC_NAAM_ZOEK_SECONDEN);
        if ($resultaat['code'] === 0 && preg_match('/^\S+\s+(\S+)/', trim($resultaat['out']), $m) === 1 && $m[1] !== $ip) {
            $naam = strtoupper(explode('.', $m[1], 2)[0]);
            $geldigheid = PC_NAAM_CACHE_SECONDEN;
        }
    }

    $cache = pcNaamCacheLezen();
    $nu = time();
    foreach ($cache as $sleutel => $regel) {
        if (!is_array($regel) || (int) ($regel['tot'] ?? 0) <= $nu) {
            unset($cache[$sleutel]);
        }
    }
    $cache[$ip] = ['naam' => $naam, 'tot' => $nu + $geldigheid];
    $map = dirname(pcNaamCacheBestand());
    if (is_dir($map) || @mkdir($map, 0775, true)) {
        @file_put_contents(pcNaamCacheBestand(), json_encode($cache), LOCK_EX);
    }
    return $naam;
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

    // Een echte naam een dag in de browser laten staan; blijft alleen het IP-adres over (DNS kent het niet), dan alleen tot de
    // browser sluit, zodat een later wel gevonden naam snel zichtbaar wordt.
    $duur = $naam === $ip ? '' : '; max-age=86400';
    return '<script>document.cookie = ' . json_encode(PC_NAAM_COOKIE . '=' . rawurlencode($waarde) . '; path=/' . $duur . '; SameSite=Lax') . ';</script>';
}
