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
    <title>Artikelstickers per Verkooporder | Geeve Hydraulics</title>
    <link rel="icon" href="../../favicon.ico?v=<?= h(assetVersion('../../favicon.ico')) ?>" type="image/x-icon">
    <link rel="stylesheet" href="../../shared/style.css?v=<?= h(assetVersion('../../shared/style.css')) ?>">
    <!-- Knoppen, tabel, invoerveld en meldingen zijn dezelfde als in de rapporten. -->
    <link rel="stylesheet" href="../../klantartikel/assets/style.css?v=<?= h(assetVersion('../../klantartikel/assets/style.css')) ?>">
    <link rel="stylesheet" href="../../reports/msp/assets/style.css?v=<?= h(assetVersion('../../reports/msp/assets/style.css')) ?>">
    <link rel="stylesheet" href="../assets/art-sticker.css?v=<?= h(assetVersion('../assets/art-sticker.css')) ?>">
    <link rel="stylesheet" href="../assets/order-stickers.css?v=<?= h(assetVersion('../assets/order-stickers.css')) ?>">
</head>
<body>
<main class="page-shell page-shell--wide">
    <?php
    $headerTitle = 'Artikelstickers per Verkooporder';
    $headerImagesPath = '../../images/';
    $headerHomeHref = '../index.php';
    $headerTopline = ''; // het versienummer staat onderaan de pagina, achter de copyright-regel
    require __DIR__ . '/../../shared/header.php';
    ?>

    <section class="panel so-top">
        <div class="so-form">
        <h2>Artikelstickers per Verkooporder</h2>
        <p class="ka-empty">Vul een ordernummer in: je krijgt per orderregel een sticker (102 × 54 mm), in de volgorde van de order of van de looplocatie, of als artikelsticker (omschrijving, barcode en artikelnummer). Vink regels uit die je niet wilt printen.</p>
        <div class="ka-actions" id="soActions" data-endpoint="api/data.php" data-csrf="<?= h($csrf) ?>">
            <label class="msp-field">
                <span>Ordernummer</span>
                <input type="text" id="soNumber" class="ka-input" inputmode="numeric" maxlength="18" autocomplete="off" autofocus>
            </label>
            <div class="rp-toggle" id="soSort" role="group" aria-label="Volgorde">
                <button type="button" data-sort="regel" class="is-active">Orderregel</button>
                <button type="button" data-sort="loop">Looplocatie</button>
                <button type="button" data-sort="artikel">Artikelsticker</button>
            </div>
            <button type="button" id="soFetch" class="ka-button">Gegevens ophalen</button>
            <span id="soStatus" class="msp-status"></span>
            <button type="button" id="soPrint" class="print-btn so-print" disabled><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9V2h12v7"></path><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg><span>Print</span></button>
        </div>
        <div id="soMessage" class="ka-message ka-message--error" hidden></div>
        </div>

        <!-- Voorbeeld: rechts in dit blok, één sticker tegelijk. -->
        <div class="so-side" id="soPreviewPanel" hidden>
            <div class="so-preview" id="soSheet"></div>
            <div class="so-nav" id="soNav">
                <button type="button" id="soPrev" class="ka-button ka-button--secondary" aria-label="Vorige sticker">&lt;</button>
                <span id="soPreviewCount" class="msp-status"></span>
                <button type="button" id="soNext" class="ka-button ka-button--secondary" aria-label="Volgende sticker">&gt;</button>
            </div>
        </div>
    </section>

    <section class="panel" id="soResult" hidden>
        <div class="msp-summary" id="soSummary"></div>
        <div class="ka-table-wrap">
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
        <div class="ka-pager" id="soPager" hidden>
            <button type="button" class="ka-button ka-button--secondary" id="soPagePrev">&larr; Vorige</button>
            <span id="soPageInfo"></span>
            <button type="button" class="ka-button ka-button--secondary" id="soPageNext">Volgende &rarr;</button>
        </div>
    </section>

    <p class="page-footer">(c) 2026 PdB - Geeve Hydraulics - Versie <?= h(APP_VERSION) ?></p>
</main>
<script src="../assets/vendor/JsBarcode.code128.min.js"></script>
<script src="../assets/art-sticker.js?v=<?= h(assetVersion('../assets/art-sticker.js')) ?>"></script>
<script src="../assets/order-stickers.js?v=<?= h(assetVersion('../assets/order-stickers.js')) ?>"></script>
</body>
</html>
