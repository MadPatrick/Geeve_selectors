<?php

declare(strict_types=1);

/**
 * Kleine hulpfuncties die elke pagina gebruikt. Elke pagina had hier eerst een eigen kopie van; nu laadt ze dit bestand
 * (require_once, bovenaan de pagina). shared/head.php en shared/header.php gebruiken h() ook.
 */

if (!function_exists('h')) {
    /** Tekst veilig in HTML zetten (ook in attributen). */
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('assetVersion')) {
    /**
     * Cache-busting: de wijzigingsdatum van een bestand (pad vanaf de map van de opgevraagde pagina, zoals 'assets/style.css'),
     * zodat elke aanpassing automatisch een nieuwe URL krijgt. Bestaat het bestand niet, dan het versienummer (APP_VERSION).
     */
    function assetVersion(string $relativePath): string
    {
        $script = (string) ($_SERVER['SCRIPT_FILENAME'] ?? '');
        $pagina = $script !== '' && is_file($script) ? $script : (string) (get_included_files()[0] ?? __FILE__);
        $mtime = @filemtime(dirname($pagina) . '/' . $relativePath);
        return $mtime !== false ? (string) $mtime : (defined('APP_VERSION') ? (string) APP_VERSION : '0');
    }
}

if (!function_exists('geeveDuizendtal')) {
    /**
     * Getal voor WEERGAVE in Nederlandse notatie: punt als duizendtal, komma als decimaal (2.000 / 1.234,5).
     * Nooit gebruiken voor waarden die naar Exact, CSV of zoekvelden gaan. Geen mb_*.
     * $minDec/$maxDec: minimaal/maximaal aantal decimalen (overbodige nullen vallen weg tot $minDec).
     * Zelfde uitkomst als GeeveGetal.duizendtal() in shared/getal.js.
     */
    function geeveDuizendtal(float $n, int $minDec = 0, int $maxDec = 0): string
    {
        $tekst = number_format(abs($n), $maxDec, '.', '');
        $delen = explode('.', $tekst);
        $dec = $delen[1] ?? '';
        while (strlen($dec) > $minDec && substr($dec, -1) === '0') {
            $dec = substr($dec, 0, -1);
        }
        $uit = number_format((float) $delen[0], 0, '', '.') . ($dec !== '' ? ',' . $dec : '');

        return ($n < 0 && (float) $tekst != 0.0 ? '-' : '') . $uit;
    }
}

if (!function_exists('geeveVersie')) {
    /**
     * Versienummer uit version.php (root) voor APP_VERSION. Ontbreekt het bestand (bv. een subapp los gedeployed), dan
     * 'onbekend': liever geen versie dan een verouderde vaste waarde die er echt uitziet.
     */
    function geeveVersie(string $pad): string
    {
        return is_file($pad) ? (string) require $pad : 'onbekend';
    }
}
