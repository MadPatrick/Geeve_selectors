<?php

declare(strict_types=1);

require_once __DIR__ . '/../../shared/helpers.php';

// Beveiligde tegel (Config > Tegels): zonder ontgrendelen terug naar het overzicht, waar de code wordt gevraagd.
require_once __DIR__ . '/../../shared/tiles.php';
require_once __DIR__ . '/../../shared/paths.php';
tileRequirePage('backorder', geeveAppUrl('reports'));

require_once __DIR__ . '/../../shared/csrf.php';
$csrf = csrfToken();

// Eén gedeeld versienummer voor hoofdscherm + alle subapps (version.php op
// rootniveau) - valt terug op deze waarde als dat bestand ontbreekt.
define('APP_VERSION', is_file(__DIR__ . '/../../version.php') ? (string) require __DIR__ . '/../../version.php' : '0.2.1');
?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Backorder | Geeve Hydraulics</title>
    <?php
    $headCss = ['shared/tabel.css', 'shared/rapport.css', 'shared/filterlijst.css'];   // vanaf de portal-root, in laadvolgorde
    require __DIR__ . '/../../shared/head.php';
    ?>
</head>
<body>
<main class="page-shell page-shell--wide">
    <?php
    $headerTitle = 'Backorder';
    $headerImagesPath = geeveUrl('images/');
    $headerHomeHref = geeveAppUrl('reports');
    $headerTopline = ''; // het versienummer staat onderaan de pagina, achter de copyright-regel
    require __DIR__ . '/../../shared/header.php';
    ?>

    <section class="panel" id="boApp" data-endpoint="api/data.php" data-klanten="api/klanten.php" data-csrf="<?= h($csrf) ?>">
        <h2>Backorder</h2>
        <p class="ka-empty">Kies een klant (typ een deel van het debiteurnummer of de naam) en klik op Zoeken. Je ziet de verkooporderregels van die klant waarvan nog items openstaan (niet geleverd). Boven de lijst staan zoekvelden per kolom.</p>
        <div class="ka-actions oh-filters">
            <label class="msp-field oh-klant">
                <span>Klant</span>
                <input type="text" class="ka-input" id="boKlant" placeholder="Debiteurnummer of naam" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="boKlantHits" autofocus>
                <ul class="oh-hits" id="boKlantHits" role="listbox" hidden></ul>
            </label>
            <button type="button" id="boZoek" class="ka-button">Zoeken</button>
            <button type="button" id="boDownload" class="ka-button ka-button--secondary" disabled>Download Excel</button>
            <span id="boStatus" class="msp-status"></span>
        </div>
        <div id="boMessage" class="ka-message ka-message--error" hidden></div>
    </section>

    <section class="panel" id="boResult" hidden>
        <div class="msp-summary" id="boSummary"></div>
        <div class="ka-table-wrap msp-scroll"><table class="ka-table oh-table" id="boTable"></table></div>
    </section>

    <p class="page-footer">(c) 2026 PdB - Geeve Hydraulics - Versie <?= h(APP_VERSION) ?></p>
</main>
<script src="<?= h(geeveUrl('shared/vendor/xlsx-style.min.js')) ?>"></script>
<script src="<?= h(geeveAssetHref('reports/backorder/assets/backorder.js')) ?>"></script>
</body>
</html>
