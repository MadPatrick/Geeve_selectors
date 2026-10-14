<?php

declare(strict_types=1);

// Beveiligde tegel (Config > Tegels): zonder ontgrendelen terug naar het Stickers-scherm, waar de code wordt gevraagd.
require_once __DIR__ . '/../../shared/tiles.php';
tileRequirePage('stickers-order', '../index.php');

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
    <title>Artikelstickers per order | Geeve Hydraulics</title>
    <link rel="icon" href="../../favicon.ico?v=<?= h(assetVersion('../../favicon.ico')) ?>" type="image/x-icon">
    <link rel="stylesheet" href="../../shared/style.css?v=<?= h(assetVersion('../../shared/style.css')) ?>">
    <!-- Knoppen, tabel, invoerveld en meldingen zijn dezelfde als in de rapporten. -->
    <link rel="stylesheet" href="../../klantartikel/assets/style.css?v=<?= h(assetVersion('../../klantartikel/assets/style.css')) ?>">
    <link rel="stylesheet" href="../../reports/msp/assets/style.css?v=<?= h(assetVersion('../../reports/msp/assets/style.css')) ?>">
    <link rel="stylesheet" href="../assets/order-stickers.css?v=<?= h(assetVersion('../assets/order-stickers.css')) ?>">
</head>
<body>
<main class="page-shell page-shell--wide">
    <?php
    $headerTitle = 'Artikelstickers per order';
    $headerImagesPath = '../../images/';
    $headerHomeHref = '../index.php';
    $headerTopline = ''; // het versienummer staat onderaan de pagina, achter de copyright-regel
    require __DIR__ . '/../../shared/header.php';
    ?>

    <section class="panel">
        <h2>Artikelstickers per order</h2>
        <p class="ka-empty">Vul een ordernummer in: je krijgt per orderregel een sticker (102 × 54 mm), in de volgorde van de order. Vink regels uit die je niet wilt printen.</p>
        <div class="ka-actions" id="soActions" data-endpoint="api/data.php" data-csrf="<?= h($csrf) ?>">
            <label class="msp-field">
                <span>Ordernummer</span>
                <input type="text" id="soNumber" class="ka-input" inputmode="numeric" maxlength="18" autocomplete="off" autofocus>
            </label>
            <button type="button" id="soFetch" class="ka-button">Gegevens ophalen</button>
            <button type="button" id="soPrint" class="ka-button ka-button--secondary" disabled>Print stickers</button>
            <span id="soStatus" class="msp-status"></span>
        </div>
        <div id="soMessage" class="ka-message ka-message--error" hidden></div>
    </section>

    <section class="panel" id="soResult" hidden>
        <div class="msp-summary" id="soSummary"></div>
        <div class="ka-table-wrap msp-scroll">
            <table class="ka-table" id="soTable">
                <thead>
                <tr>
                    <th><input type="checkbox" id="soAll" checked aria-label="Alle regels"></th>
                    <th>Rgl</th>
                    <th>Artikelnr</th>
                    <th>Omschrijving</th>
                    <th>Besteld</th>
                    <th>Geleverd</th>
                    <th>Locatie</th>
                    <th>Looplocatie</th>
                    <th>Uw artikelnr</th>
                    <th>Country of Origin</th>
                </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </section>

    <section class="panel" id="soPreviewPanel" hidden>
        <h2>Voorbeeld <small id="soPreviewCount"></small></h2>
        <div class="so-preview" id="soSheet"></div>
    </section>

    <p class="page-footer">(c) 2026 PdB - Geeve Hydraulics - Versie <?= h(APP_VERSION) ?></p>
</main>
<script src="../assets/vendor/JsBarcode.code128.min.js"></script>
<script src="../assets/order-stickers.js?v=<?= h(assetVersion('../assets/order-stickers.js')) ?>"></script>
</body>
</html>
