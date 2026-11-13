<?php

declare(strict_types=1);

require_once __DIR__ . '/../../shared/helpers.php';

// Beveiligde tegel (Config > Tegels): zonder ontgrendelen terug naar het overzicht, waar de code wordt gevraagd.
require_once __DIR__ . '/../../shared/tiles.php';
require_once __DIR__ . '/../../shared/paths.php';
tileRequirePage('inkooporder', geeveAppUrl('templates'));

require_once __DIR__ . '/../../shared/csrf.php';

// Eén gedeeld versienummer voor hoofdscherm + alle subapps (version.php op
// rootniveau); geeveVersie() geeft 'onbekend' als dat bestand ontbreekt.
define('APP_VERSION', geeveVersie(__DIR__ . '/../../version.php'));

$csrf = csrfToken();
?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Export Inkooporder naar Excel | Geeve Hydraulics</title>
    <!-- Knoppen, tabel en meldingen zijn dezelfde als in Klant Artikelnummers. -->
    <?php
    $headCss = ['templates/inkooporder/assets/style.css', 'shared/tabel.css', 'shared/rapport.css'];   // vanaf de portal-root, in laadvolgorde
    require __DIR__ . '/../../shared/head.php';
    ?>
</head>
<body>
<main class="page-shell page-shell--wide">
    <?php
    $headerTitle = 'Export Inkooporder naar Excel';
    $headerImagesPath = geeveUrl('images/');
    $headerHomeHref = geeveAppUrl('templates'); // tegel staat onder Templates
    $headerTopline = ''; // het versienummer staat onderaan de pagina, achter de copyright-regel
    require __DIR__ . '/../../shared/header.php';
    ?>

    <section class="panel">
        <h2>Export Inkooporder naar Excel</h2>
        <p class="ka-empty">Haalt de inkooporder met regels uit Exact op (dezelfde gegevens als het Excel-bestand "Exact Inkooporder naar Excel"). Vul het ordernummer in, haal de gegevens op en download ze daarna als Excel-bestand.</p>
        <div class="ka-actions" id="rpActions" data-endpoint="api/data.php" data-csrf="<?= h($csrf) ?>" data-label="Ordernummer" data-sheet="Inkooporder" data-file="Inkooporder">
            <label class="msp-field">
                <span>Ordernummer</span>
                <input type="text" id="rpNumber" class="ka-input" inputmode="numeric" maxlength="18" autocomplete="off" autofocus>
            </label>
            <button type="button" id="rpFetch" class="ka-button">Gegevens ophalen</button>
            <button type="button" id="rpDownload" class="ka-button ka-button--secondary" disabled>Download Excel</button>
            <span id="rpStatus" class="msp-status"></span>
        </div>
        <div id="rpMessage" class="ka-message ka-message--error" hidden></div>
    </section>

    <section class="panel" id="rpResult" hidden>
        <div class="msp-summary" id="rpSummary"></div>
        <div class="ka-table-wrap msp-scroll"><table class="ka-table" id="rpTable"></table></div>
    </section>

    <p class="page-footer">(c) 2026 PdB - Geeve Hydraulics - Versie <?= h(APP_VERSION) ?></p>
</main>
<script src="<?= h(geeveUrl('shared/vendor/xlsx-style.min.js')) ?>"></script>
<script src="<?= h(geeveUrl('shared/sp-report.js')) ?>?v=<?= h(assetVersion('../../shared/sp-report.js')) ?>"></script>
</body>
</html>
