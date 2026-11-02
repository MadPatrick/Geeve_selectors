<?php

declare(strict_types=1);

/**
 * Roterende achtergrondfoto onder de topbalk (zie body::before in shared/style.css en shared/head.php).
 * Alle bestanden images/achtergrond_<N>.(jpg|jpeg|png|webp|gif) doen mee, op volgorde van N. De keuze gebeurt op de
 * server aan de hand van de klok: elke periode (Config > Topbalk > "Rouleer tijd achtergrond", standaard 60 minuten)
 * komt de volgende foto. Zo zien alle gebruikers dezelfde foto en is er geen cache-probleem: het adres van de foto
 * is per bestand anders en krijgt ?v=<wijzigingsdatum>.
 */

require_once __DIR__ . '/paths.php';
require_once __DIR__ . '/secure_settings.php';

const ACHTERGROND_STANDAARD_MINUTEN = 60;

/** Rouleertijd in minuten (1-1440), standaard 60. */
function achtergrondMinuten(): int
{
    $s = secureSettingsRead();
    $m = $s['ACHTERGROND_MINUTEN'] ?? null;
    return is_numeric($m) ? max(1, min(1440, (int) $m)) : ACHTERGROND_STANDAARD_MINUTEN;
}

/** Bestandsnamen (vanaf images/) van alle achtergrond_<N>.*, op volgorde van N. */
function achtergrondBestanden(): array
{
    $lijst = [];
    foreach (glob(geevePortalDir() . '/images/achtergrond_*') ?: [] as $pad) {
        $naam = basename($pad);
        if (preg_match('/^achtergrond_(\d+)\.(jpe?g|png|webp|gif)$/i', $naam, $m) === 1) {
            $lijst[(int) $m[1]] = $naam;
        }
    }
    ksort($lijst);
    return array_values($lijst);
}

/** Het bestand (vanaf images/) dat nu aan de beurt is, of null als er geen achtergronden zijn. */
function achtergrondNu(?int $tijd = null, ?int $minuten = null): ?string
{
    $lijst = achtergrondBestanden();
    if ($lijst === []) {
        return null;
    }
    $periode = ($minuten ?? achtergrondMinuten()) * 60;
    $tijd = ($tijd ?? time()) + (int) date('Z', $tijd ?? time()); // lokale klok: een nieuwe periode begint op hele uren
    return $lijst[intdiv($tijd, $periode) % count($lijst)];
}

/** Adres (met ?v=<wijzigingsdatum>) van een bestand uit images/. */
function achtergrondUrl(string $naam): string
{
    $pad = 'images/' . $naam;
    $mtime = @filemtime(geevePortalDir() . '/' . $pad);
    return geeveUrl($pad) . '?v=' . ($mtime !== false ? (string) $mtime : '0');
}

/**
 * <style>- en <script>-blok voor de <head> (leeg als er geen achtergrond is). De stijl zet de foto van dit moment via
 * --achtergrond-url voor body::before; het script wisselt de foto in een open pagina op het wisselmoment (bij geen of
 * één foto doet het niets), ook als de pagina lang openstaat.
 */
function achtergrondStijl(): string
{
    $lijst = achtergrondBestanden();
    $naam = achtergrondNu();
    if ($naam === null) {
        return '';
    }
    $urls = array_map('achtergrondUrl', $lijst);
    $css = '<style>:root { --achtergrond-url: url("' . str_replace('"', '%22', achtergrondUrl($naam)) . '"); } body::before { background-image: var(--achtergrond-url); }</style>';
    if (count($lijst) < 2) {
        return $css;
    }
    $periode = achtergrondMinuten() * 60;
    $nu = time();
    $nog = $periode - (($nu + (int) date('Z', $nu)) % $periode); // seconden tot het volgende wisselmoment
    $js = json_encode(['urls' => $urls, 'index' => (int) array_search($naam, $lijst, true), 'periode' => $periode, 'nog' => $nog]);
    return $css . '<script>(function(){var c=' . $js . ';var t=Date.now()+c.nog*1000,i=c.index,p=c.periode*1000;'
        . 'function w(){var n=Math.floor((Date.now()-t)/p)+1;if(n<1){n=1;}i=(i+n)%c.urls.length;t+=n*p;'
        . 'document.documentElement.style.setProperty("--achtergrond-url","url(\\""+c.urls[i]+"\\")");'
        . 'new Image().src=c.urls[(i+1)%c.urls.length];setTimeout(w,Math.max(1000,t-Date.now()));}'
        . 'setTimeout(w,Math.max(1000,t-Date.now()));new Image().src=c.urls[(i+1)%c.urls.length];})();</script>';
}
