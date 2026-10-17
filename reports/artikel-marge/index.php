<?php

declare(strict_types=1);

// Beveiligde tegel (Config > Tegels): zonder ontgrendelen terug naar het overzicht, waar de code wordt gevraagd.
require_once __DIR__ . '/../../shared/tiles.php';
tileRequirePage('artikel-marge', '../index.php');

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
    <title>Artikelmarges | Geeve Hydraulics</title>
    <link rel="icon" href="../../favicon.ico?v=<?= h(assetVersion('../../favicon.ico')) ?>" type="image/x-icon">
    <link rel="stylesheet" href="../../shared/style.css?v=<?= h(assetVersion('../../shared/style.css')) ?>">
    <link rel="stylesheet" href="../../assets/style.css?v=<?= h(assetVersion('../../assets/style.css')) ?>">
    <!-- Knoppen, tabel en meldingen zijn dezelfde als in Klant Artikelnummers. -->
    <link rel="stylesheet" href="../../klantartikel/assets/style.css?v=<?= h(assetVersion('../../klantartikel/assets/style.css')) ?>">
    <link rel="stylesheet" href="../msp/assets/style.css?v=<?= h(assetVersion('../msp/assets/style.css')) ?>">
</head>
<body>
<main class="page-shell page-shell--wide">
    <?php
    $headerTitle = 'Artikelmarges';
    $headerImagesPath = '../../images/';
    $headerHomeHref = '../index.php';
    $headerTopline = ''; // het versienummer staat onderaan de pagina, achter de copyright-regel
    require __DIR__ . '/../../shared/header.php';
    ?>

    <section class="panel">
        <h2>Artikelmarges</h2>
        <p class="ka-empty">Per artikel de standaard verkoopprijs, de standaard inkoopprijs, de toeslag en de marge, rechtstreeks uit Exact (dezelfde gegevens als de Excel "Artikel met verkoopprijs, inkoopprijs en marge"). Beide velden zijn optioneel: leeg = alle artikelen. Op het scherm staan de eerste 500 regels, de download bevat alles.</p>
        <div class="ka-actions" id="rpActions" data-endpoint="api/data.php" data-csrf="<?= h($csrf) ?>" data-label="" data-sheet="Artikelmarges" data-file="Artikelmarges">
            <label class="msp-field">
                <span>Artikelgroep</span>
                <input type="text" class="ka-input" data-field="groep" data-optional="1" maxlength="20" autocomplete="off" autofocus>
            </label>
            <label class="msp-field">
                <span>Artikelnummer of omschrijving bevat</span>
                <input type="text" class="ka-input" data-field="zoek" data-optional="1" maxlength="60" autocomplete="off">
            </label>
            <button type="button" id="rpFetch" class="ka-button">Gegevens ophalen</button>
            <button type="button" id="rpDownload" class="ka-button ka-button--secondary" disabled>Download Excel</button>
            <span id="rpStatus" class="msp-status"></span>
        </div>
        <div id="rpMessage" class="ka-message ka-message--error" hidden></div>
    </section>

    <section class="panel" id="rpResult" hidden>
        <div class="msp-summary" id="rpSummary"></div>
        <div class="ka-table-wrap msp-scroll"><table class="ka-table rp-head-wrap" id="rpTable"></table></div>
    </section>

    <p class="page-footer">(c) 2026 PdB - Geeve Hydraulics - Versie <?= h(APP_VERSION) ?></p>
</main>
<script src="../assets/vendor/xlsx-style.min.js"></script>
<script src="../assets/sp-report.js?v=<?= h(assetVersion('../assets/sp-report.js')) ?>"></script>
</body>
</html>
