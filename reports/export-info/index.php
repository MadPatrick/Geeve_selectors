<?php

declare(strict_types=1);

require_once __DIR__ . '/../../shared/helpers.php';

// Beveiligde tegel (Config > Tegels): zonder ontgrendelen terug naar het overzicht, waar de code wordt gevraagd.
require_once __DIR__ . '/../../shared/tiles.php';
require_once __DIR__ . '/../../shared/paths.php';
tileRequirePage('export-info', geeveAppUrl('templates'));

require_once __DIR__ . '/../../shared/csrf.php';

// Eén gedeeld versienummer voor hoofdscherm + alle subapps (version.php op
// rootniveau) - valt terug op deze waarde als dat bestand ontbreekt.
define('APP_VERSION', is_file(__DIR__ . '/../../version.php') ? (string) require __DIR__ . '/../../version.php' : '0.2.1');

$csrf = csrfToken();
?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Export info | Geeve Hydraulics</title>
    <!-- Knoppen, tabel en meldingen zijn dezelfde als in Klant Artikelnummers. -->
    <?php
    $headCss = ['assets/style.css', 'klantartikel/assets/style.css', 'reports/msp/assets/style.css', 'reports/export-info/assets/style.css'];   // vanaf de portal-root, in laadvolgorde
    require __DIR__ . '/../../shared/head.php';
    ?>
</head>
<body>
<main class="page-shell page-shell--wide">
    <?php
    $headerTitle = 'Export info';
    $headerImagesPath = geeveUrl('images/');
    $headerHomeHref = geeveAppUrl('templates'); // tegel staat onder Templates
    $headerTopline = ''; // het versienummer staat onderaan de pagina, achter de copyright-regel
    require __DIR__ . '/../../shared/header.php';
    ?>

    <section class="panel" id="eiApp" data-endpoint="api/data.php" data-csrf="<?= h($csrf) ?>">
        <h2>Export info</h2>
        <p class="ka-empty">Haalt de orderregels voor de exportdocumenten uit Exact op (dezelfde gegevens als het Excel-bestand "Exact Export info Order"). 
            <br>Vul het ordernummer in, haal de gegevens op, vul collo, gewicht en afmetingen aan en exporteer de gekozen weergave naar Excel.</p>
        <div class="ka-actions">
            <label class="msp-field">
                <span>Ordernummer</span>
                <input type="text" id="eiNumber" class="ka-input" inputmode="numeric" maxlength="18" autocomplete="off" autofocus>
            </label>
            <button type="button" id="eiFetch" class="ka-button">Gegevens ophalen</button>
            <span id="eiStatus" class="msp-status"></span>
        </div>
        <div id="eiMessage" class="ka-message ka-message--error" hidden></div>
    </section>

    <section class="panel" id="eiResult" hidden>
        <div class="ei-bar">
            <div class="rp-toggle" id="eiView" role="group" aria-label="Weergave">
                <button type="button" data-view="orders" class="is-active">Orders</button>
                <button type="button" data-view="deme">Deme</button>
                <button type="button" data-view="nov">NOV</button>
            </div>
            <button type="button" id="eiExport" class="ka-button">Export naar Excel</button>
        </div>
        <div class="msp-summary" id="eiSummary"></div>
        <div class="ka-table-wrap msp-scroll"><table class="ka-table" id="eiTable"></table></div>
    </section>

    <p class="page-footer">(c) 2026 PdB - Geeve Hydraulics - Versie <?= h(APP_VERSION) ?></p>
</main>
<script src="<?= h(geeveUrl('reports/assets/vendor/xlsx-style.min.js')) ?>"></script>
<script src="assets/export-info.js?v=<?= h(assetVersion('assets/export-info.js')) ?>"></script>
</body>
</html>
