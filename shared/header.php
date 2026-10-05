<?php

declare(strict_types=1);

/**
 * Gedeelde header/brand-panel-include - zie portal-README ("Gedeelde
 * layout") voor het volledige patroon. De bijbehorende CSS staat in
 * shared/style.css (moet al geladen zijn vóór deze include gebruikt
 * wordt). Gebruikt de h()-functie die elke app al zelf definieert (voor
 * HTML-escaping) - dit bestand definieert 'm niet opnieuw.
 *
 * Vóór het includen de volgende variabelen zetten:
 *
 *   $headerTitle      (string, verplicht)  - tekst voor <h1>.
 *   $headerImagesPath (string, optioneel)  - pad naar de images/-map,
 *                                            default '../images/' (het
 *                                            hoofdportaal zet 'images/',
 *                                            want dat heeft geen ../).
 *   $headerShowHome   (bool, optioneel)    - toont de "terug naar
 *                                            hoofdmenu"-knop, default
 *                                            true (het hoofdportaal zet
 *                                            false, want dat IS het
 *                                            hoofdmenu).
 *   $headerHomeHref   (string, optioneel)  - link van die knop, default
 *                                            '../index.php'.
 *   $headerTopline    (string, optioneel)  - kale, kant-en-klare HTML
 *                                            voor de .header-topline-rij
 *                                            (eigen icoon-knoppen/versie-
 *                                            tekst/statuspil) - bouw dit
 *                                            zelf met ob_start()/
 *                                            ob_get_clean() vóór het
 *                                            includen; leeg = geen
 *                                            topline-rij. Wordt ongefilterd
 *                                            uitgevoerd (geen h()) - dus
 *                                            zelf escapen wat niet al
 *                                            vaste markup is.
 *
 * Voorbeeld (stauff/index.php):
 *   $headerTitle = 'Stauff Selector';
 *   require __DIR__ . '/../shared/header.php';
 */

$headerImagesPath = $headerImagesPath ?? '../images/';
$headerShowHome = $headerShowHome ?? true;
$headerHomeHref = $headerHomeHref ?? '../index.php';
$headerTopline = $headerTopline ?? '';
?>
<header class="page-header">
    <div class="brand-panel">
        <div class="brand-copy">
            <div class="brand-logo-row">
                <img src="<?= h($headerImagesPath) ?>geeve.jpg" alt="Geeve Hydraulics - know how in hydraulics" class="brand-logo-img">
                <img src="<?= h($headerImagesPath) ?>rubix.jpg" alt="Powered by Rubix" class="brand-rubix-img">
            </div>
        </div>
        <?php if ($headerShowHome): ?>
        <a href="<?= h($headerHomeHref) ?>" class="header-home-button" title="Terug naar hoofdmenu" aria-label="Terug naar hoofdmenu">
            <svg viewBox="0 0 24 24" width="36" height="36" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M3 11.5 12 4l9 7.5"></path>
                <path d="M5.5 9.5V20a1 1 0 0 0 1 1H10v-5a2 2 0 1 1 4 0v5h3.5a1 1 0 0 0 1-1V9.5"></path>
            </svg>
        </a>
        <?php endif; ?>
        <div class="header-content">
            <?php if ($headerTopline !== ''): ?>
                <div class="header-topline"><?= $headerTopline ?></div>
            <?php endif; ?>
            <h1><?= h($headerTitle) ?></h1>
        </div>
    </div>
</header>
