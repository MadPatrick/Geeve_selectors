<?php

declare(strict_types=1);

/**
 * Hulp voor de gedeelde voortgangsbalk (shared/progress.js, GeeveProgress.download) bij een download via een
 * formulier-POST. Roep geeveProgressDownloadDone() aan vlak vóór de header('Content-Disposition: attachment ...')
 * van de export: het zet een cookie met de code die de pagina meestuurde, waarop de balk "klaar" meldt.
 * Zonder dat veld (bijv. een aanroep zonder JavaScript) doet de functie niets.
 */
if (!function_exists('geeveProgressDownloadDone')) {
    function geeveProgressDownloadDone(): void
    {
        $token = (string) ($_POST['geeve_dl'] ?? '');
        if ($token !== '' && preg_match('/^\d{1,30}$/', $token) === 1 && !headers_sent()) {
            setcookie('geeve_dl', $token, 0, '/');
        }
    }
}
