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
