<?php

declare(strict_types=1);

// Tegel-toegang (Config > Tegels): is de tegel beveiligd, dan terug naar het overzicht, waar de code wordt gevraagd.
require_once __DIR__ . '/../../shared/tiles.php';
require_once __DIR__ . '/../../shared/paths.php';
tileRequirePage('orderhistorie', geeveAppUrl('templates'));

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
    <title>Orderhistorie | Geeve Hydraulics</title>
    <?php
    // Knoppen, tabel en meldingen zijn dezelfde als in Klant Artikelnummers; msp-field/msp-pill komen uit het MSP-rapport.
    $headCss = ['klantartikel/assets/style.css', 'reports/msp/assets/style.css', 'reports/orderhistorie/assets/style.css'];
    require __DIR__ . '/../../shared/head.php';
    ?>
</head>
<body>
<main class="page-shell page-shell--wide">
    <?php
    $headerTitle = 'Orderhistorie';
    $headerImagesPath = geeveUrl('images/');
    $headerHomeHref = geeveAppUrl('templates'); // tegel staat onder Templates
    $headerTopline = ''; // het versienummer staat onderaan de pagina, achter de copyright-regel
    require __DIR__ . '/../../shared/header.php';
    ?>

    <section class="panel" id="ohApp" data-endpoint="api/data.php" data-klanten="api/klanten.php" data-csrf="<?= h($csrf) ?>">
        <h2>Orderhistorie</h2>
        <p class="ka-empty">Alle geleverde orderregels sinds de eerste order, per klant of over alle klanten. Kies een klant en/of vul een periode, artikelnummer of omschrijving in en klik op Zoeken. Boven de lijst staan zoekvelden per kolom om het resultaat verder te filteren (bijv. omschrijving "bulkhead" met jaar "2019").</p>
        <div class="ka-actions oh-filters">
            <label class="msp-field oh-klant">
                <span>Klant</span>
                <input type="text" class="ka-input" id="ohKlant" placeholder="Debiteurnummer of naam (leeg = alle klanten)" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="ohKlantHits" autofocus>
                <ul class="oh-hits" id="ohKlantHits" role="listbox" hidden></ul>
            </label>
            <label class="msp-field"><span>Afgeleverd vanaf</span><input type="date" class="ka-input" id="ohVan"></label>
            <label class="msp-field"><span>Tot en met</span><input type="date" class="ka-input" id="ohTot"></label>
            <label class="msp-field"><span>Artikelnummer bevat</span><input type="text" class="ka-input" id="ohArtnr" maxlength="80" autocomplete="off"></label>
            <label class="msp-field"><span>Omschrijving bevat</span><input type="text" class="ka-input" id="ohOmsc" maxlength="80" autocomplete="off"></label>
            <button type="button" id="ohZoek" class="ka-button">Zoeken</button>
            <button type="button" id="ohDownload" class="ka-button ka-button--secondary" disabled>Download Excel</button>
            <span id="ohStatus" class="msp-status"></span>
        </div>
        <div id="ohMessage" class="ka-message ka-message--error" hidden></div>
    </section>

    <section class="panel" id="ohResult" hidden>
        <div class="msp-summary" id="ohSummary"></div>
        <div class="ka-table-wrap msp-scroll"><table class="ka-table oh-table" id="ohTable"></table></div>
    </section>

    <p class="page-footer">(c) 2026 PdB - Geeve Hydraulics - Versie <?= h(APP_VERSION) ?></p>
</main>
<script src="<?= h(geeveUrl('reports/assets/vendor/xlsx-style.min.js')) ?>"></script>
<script src="<?= h(geeveAssetHref('reports/orderhistorie/assets/orderhistorie.js')) ?>"></script>
</body>
</html>
