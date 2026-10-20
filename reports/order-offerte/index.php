<?php

declare(strict_types=1);

// Beveiligde tegel (Config > Tegels): zonder ontgrendelen terug naar het overzicht, waar de code wordt gevraagd.
require_once __DIR__ . '/../../shared/tiles.php';
require_once __DIR__ . '/../../shared/paths.php';
tileRequirePage('order-offerte', geeveAppUrl('templates'));

require_once __DIR__ . '/../../shared/csrf.php';

// Eén gedeeld versienummer voor hoofdscherm + alle subapps (version.php op
// rootniveau) - valt terug op deze waarde als dat bestand ontbreekt.
define('APP_VERSION', is_file(__DIR__ . '/../../version.php') ? (string) require __DIR__ . '/../../version.php' : '0.2.1');

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function assetVersion(string $relativePath): string
{
    $full = __DIR__ . '/' . $relativePath;
    $mtime = @filemtime($full);
    return $mtime !== false ? (string) $mtime : APP_VERSION;
}

$csrf = csrfToken();
?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Order of Offerte naar Excel | Geeve Hydraulics</title>
    <link rel="icon" href="<?= h(geeveUrl('favicon.ico')) ?>?v=<?= h(assetVersion('../../favicon.ico')) ?>" type="image/x-icon">
    <link rel="stylesheet" href="<?= h(geeveUrl('shared/style.css')) ?>?v=<?= h(assetVersion('../../shared/style.css')) ?>">
    <link rel="stylesheet" href="<?= h(geeveUrl('assets/style.css')) ?>?v=<?= h(assetVersion('../../assets/style.css')) ?>">
    <!-- Knoppen, tabel en meldingen zijn dezelfde als in Klant Artikelnummers. -->
    <link rel="stylesheet" href="<?= h(geeveUrl('klantartikel/assets/style.css')) ?>?v=<?= h(assetVersion('../../klantartikel/assets/style.css')) ?>">
    <link rel="stylesheet" href="<?= h(geeveUrl('reports/msp/assets/style.css')) ?>?v=<?= h(assetVersion('../msp/assets/style.css')) ?>">
</head>
<body>
<main class="page-shell page-shell--wide">
    <?php
    $headerTitle = 'Order of Offerte naar Excel';
    $headerImagesPath = geeveUrl('images/');
    $headerHomeHref = geeveAppUrl('templates'); // tegel staat onder Templates
    $headerTopline = ''; // het versienummer staat onderaan de pagina, achter de copyright-regel
    require __DIR__ . '/../../shared/header.php';
    ?>

    <section class="panel">
        <h2>Order of Offerte naar Excel</h2>
        <p class="ka-empty">Haalt de regels van een order of offerte uit Exact op (dezelfde gegevens als het Excel-bestand "Exact Order of Offerte naar Excel"). 
            <br>Vul het offerte- of ordernummer in, haal de gegevens op en download ze daarna als Excel-bestand.</p>
        <div class="ka-actions" id="rpActions" data-endpoint="api/data.php" data-csrf="<?= h($csrf) ?>" data-label="Offerte/ordernummer" data-sheet="orders offertes" data-file="Order_Offerte">
            <label class="msp-field">
                <span>Offerte/ordernummer</span>
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
<script src="<?= h(geeveUrl('reports/assets/vendor/xlsx-style.min.js')) ?>"></script>
<script src="<?= h(geeveUrl('reports/assets/sp-report.js')) ?>?v=<?= h(assetVersion('../assets/sp-report.js')) ?>"></script>
</body>
</html>
