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

// Standaard de huidige maand tot vandaag.
$defaultTo = date('Y-m-d');
$defaultFrom = date('Y-m-d', strtotime('first day of this month'));
?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Productiviteit per medewerker | Geeve Hydraulics</title>
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
    $headerTitle = 'Productiviteit per medewerker';
    $headerImagesPath = '../../images/';
    $headerHomeHref = '../index.php';
    $headerTopline = '<span class="version-inline">Versie ' . h(APP_VERSION) . '</span>';
    require __DIR__ . '/../../shared/header.php';
    ?>

    <section class="panel">
        <h2>Productiviteit per medewerker</h2>
        <p class="ka-empty">Toont per medewerker het aantal orders of orderregels. <strong>Productie</strong> telt de logistiek afgehandelde (geleverde) verkooporders, <strong>Verkoop</strong> telt de verwerkte verkooporders op de verwerkingsdag (alleen lopende orders): rechtstreeks ingevoerde orders bij degene die ze invoert; orders die uit een offerte zijn verwerkt staan onder "Onbekend", omdat Exact niet vastlegt wie dat heeft gedaan. <strong>Offertes</strong> telt de offertes op de dag dat ze zijn ingevoerd, bij degene die ze invoert. Het <strong>Overzicht</strong> toont de werkdagen van deze week, deze week, de weken van deze maand en de hele maand; bij <strong>Eigen periode</strong> kies je zelf de datums (maximaal 62 dagen, een kolom per dag). Productieorders (werkorders) zijn nog niet opgenomen.</p>
        <div class="ka-actions" id="rpActions" data-endpoint="api/data.php" data-csrf="<?= h($csrf) ?>" data-label="" data-sheet="Productiviteit" data-file="Productiviteit">
            <input type="hidden" id="rpWeergave" data-field="weergave" value="overzicht">
            <div class="rp-toggle" id="rpView" role="group" aria-label="Weergave">
                <button type="button" data-view="overzicht" class="is-active">Overzicht</button>
                <button type="button" data-view="periode">Eigen periode</button>
            </div>
            <label class="msp-field rp-dates" hidden>
                <span>Datum van</span>
                <input type="date" class="ka-input" data-field="van" data-name="de datum van" value="<?= h($defaultFrom) ?>">
            </label>
            <label class="msp-field rp-dates" hidden>
                <span>Datum tot</span>
                <input type="date" class="ka-input" data-field="tot" data-name="de datum tot" value="<?= h($defaultTo) ?>">
            </label>
            <input type="hidden" id="rpModus" data-field="modus" value="productie">
            <div class="rp-toggle" role="group" aria-label="Soort">
                <button type="button" data-mode="productie" class="is-active">Productie</button>
                <button type="button" data-mode="verkoop">Verkoop</button>
                <button type="button" data-mode="offerte">Offertes</button>
            </div>
            <button type="button" id="rpFetch" class="ka-button">Gegevens ophalen</button>
            <button type="button" id="rpDownload" class="ka-button ka-button--secondary" disabled>Download Excel</button>
            <span id="rpStatus" class="msp-status"></span>
        </div>
        <div id="rpMessage" class="ka-message ka-message--error" hidden></div>
    </section>

    <section class="panel" id="rpResult" hidden>
        <div class="rp-bar">
            <div class="rp-toggle" id="rpMeasure" role="group" aria-label="Telling" hidden>
                <button type="button" data-measure="Orders" class="is-active">Orders</button>
                <button type="button" data-measure="Orderregels">Orderregels</button>
            </div>
        </div>
        <div class="msp-summary" id="rpSummary"></div>
        <div class="ka-table-wrap msp-scroll"><table class="ka-table" id="rpTable"></table></div>
    </section>

    <section class="panel" id="rpDetail" hidden>
        <div class="rp-detail-head">
            <h2 id="rpDetailTitle"></h2>
            <button type="button" id="rpDetailClose" class="ka-button ka-button--secondary">Sluiten</button>
        </div>
        <div id="rpDetailMessage" class="ka-message ka-message--error" hidden></div>
        <div class="ka-table-wrap msp-scroll"><table class="ka-table" id="rpDetailTable"></table></div>
    </section>

    <p class="page-footer">Geeve Hydraulics</p>
</main>
<script src="../assets/vendor/xlsx-style.min.js"></script>
<script src="../assets/sp-report.js?v=<?= h(assetVersion('../assets/sp-report.js')) ?>"></script>
</body>
</html>
