<?php

declare(strict_types=1);

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
    <title>MSP report | Geeve Hydraulics</title>
    <link rel="icon" href="../../favicon.ico?v=<?= h(assetVersion('../../favicon.ico')) ?>" type="image/x-icon">
    <link rel="stylesheet" href="../../shared/style.css?v=<?= h(assetVersion('../../shared/style.css')) ?>">
    <link rel="stylesheet" href="../../assets/style.css?v=<?= h(assetVersion('../../assets/style.css')) ?>">
    <!-- Knoppen, tabel en meldingen zijn dezelfde als in Klant Artikelnummers. -->
    <link rel="stylesheet" href="../../klantartikel/assets/style.css?v=<?= h(assetVersion('../../klantartikel/assets/style.css')) ?>">
    <link rel="stylesheet" href="assets/style.css?v=<?= h(assetVersion('assets/style.css')) ?>">
</head>
<body>
<main class="page-shell page-shell--wide">
    <?php
    $headerTitle = 'MSP report';
    $headerImagesPath = '../../images/';
    $headerHomeHref = '../index.php';
    $headerTopline = '<span class="version-inline">Versie ' . h(APP_VERSION) . '</span>';
    require __DIR__ . '/../../shared/header.php';
    ?>

    <section class="panel">
        <h2>MSP claim - factuurregels</h2>
        <p class="ka-empty">Haalt de MSP-factuurregels op uit Exact (dezelfde gegevens als het Excel-bestand "MSP CLAIM") en toont ze hieronder. Daarna kun je ze downloaden als Excel-bestand.</p>
        <div class="ka-actions" id="mspActions" data-endpoint="api/data.php" data-csrf="<?= h($csrf) ?>">
            <button type="button" id="mspFetch" class="ka-button">Gegevens ophalen</button>
            <button type="button" id="mspDownload" class="ka-button ka-button--secondary" disabled>Download Excel</button>
            <span id="mspStatus" class="msp-status"></span>
        </div>
        <div id="mspMessage" class="ka-message ka-message--error" hidden></div>
    </section>

    <section class="panel" id="mspResult" hidden>
        <div class="msp-summary" id="mspSummary"></div>
        <div class="ka-table-wrap msp-scroll"><table class="ka-table" id="mspTable"></table></div>
    </section>

    <p class="page-footer">Geeve Hydraulics</p>
</main>
<script src="../../klantartikel/assets/vendor/xlsx.core.min.js"></script>
<script src="assets/msp.js?v=<?= h(assetVersion('assets/msp.js')) ?>"></script>
</body>
</html>
