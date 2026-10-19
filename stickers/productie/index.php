<?php

declare(strict_types=1);

// Beveiligde tegel (Config > Tegels): zonder ontgrendelen terug naar het Stickers-scherm, waar de code wordt gevraagd.
require_once __DIR__ . '/../../shared/tiles.php';
require_once __DIR__ . '/../../shared/paths.php';
require_once __DIR__ . '/../../shared/printen.php';
tileRequirePage('stickers-productie', geeveAppUrl('stickers'));

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
    <title>Artikelen per Productieorder | Geeve Hydraulics</title>
    <link rel="icon" href="<?= h(geeveUrl('favicon.ico')) ?>?v=<?= h(assetVersion('../../favicon.ico')) ?>" type="image/x-icon">
    <link rel="stylesheet" href="<?= h(geeveUrl('shared/style.css')) ?>?v=<?= h(assetVersion('../../shared/style.css')) ?>">
    <!-- Knoppen, tabel, invoerveld en meldingen zijn dezelfde als in de rapporten. -->
    <link rel="stylesheet" href="<?= h(geeveUrl('klantartikel/assets/style.css')) ?>?v=<?= h(assetVersion('../../klantartikel/assets/style.css')) ?>">
    <link rel="stylesheet" href="<?= h(geeveUrl('reports/msp/assets/style.css')) ?>?v=<?= h(assetVersion('../../reports/msp/assets/style.css')) ?>">
    <link rel="stylesheet" href="<?= h(geeveUrl('stickers/assets/productie-stickers.css')) ?>?v=<?= h(assetVersion('../assets/productie-stickers.css')) ?>">
</head>
<body>
<main class="page-shell page-shell--wide">
    <?php
    $headerTitle = 'Artikelen per Productieorder';
    $headerImagesPath = geeveUrl('images/');
    $headerHomeHref = geeveAppUrl('stickers');
    $headerTopline = ''; // het versienummer staat onderaan de pagina, achter de copyright-regel
    require __DIR__ . '/../../shared/header.php';
    ?>

    <section class="panel ps-top">
        <div class="ps-form">
            <h2>Artikelen per Productieorder</h2>
            <p class="ka-empty">Vul een verkoopordernummer in: je krijgt per productieorderregel een sticker (102 × 54 mm). Vink regels uit die je niet wilt printen.</p>
            <div class="ka-actions" id="psActions" data-endpoint="api/data.php" data-csrf="<?= h($csrf) ?>">
                <label class="msp-field">
                    <span>Ordernummer</span>
                    <input type="text" id="psNumber" class="ka-input" inputmode="numeric" maxlength="18" autocomplete="off" autofocus>
                </label>
                <button type="button" id="psFetch" class="ka-button">Gegevens ophalen</button>
                <span id="psStatus" class="msp-status"></span>
            </div>
            <!-- Aantal en printknop onder het ordernummer. -->
            <div class="ps-print">
                <label class="ps-copies"><input type="number" id="psCopies" class="ka-input" min="1" max="99" value="1"> x afdrukken</label>
                <?= geevePrinterSelectHtml('stickers-productie', $csrf) ?>
                <button type="button" id="psPrint" class="print-btn" disabled><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9V2h12v7"></path><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg><span>Print</span></button>
                <span id="psPrintInfo" class="msp-status"></span>
            </div>
            <div id="psMessage" class="ka-message ka-message--error" hidden></div>
        </div>

        <!-- Voorbeeld: één sticker tegelijk, met daaronder < >. -->
        <div class="ps-side" id="psSide" hidden>
            <div class="ps-preview" id="psSheet"></div>
            <div class="ps-nav">
                <button type="button" id="psPrev" class="ka-button ka-button--secondary" aria-label="Vorige sticker">&lt;</button>
                <span id="psPreviewCount" class="msp-status"></span>
                <button type="button" id="psNext" class="ka-button ka-button--secondary" aria-label="Volgende sticker">&gt;</button>
            </div>
        </div>
    </section>

    <section class="panel" id="psResult" hidden>
        <div class="msp-summary" id="psSummary"></div>
        <div class="ka-table-wrap">
            <table class="ka-table" id="psTable">
                <thead>
                <tr>
                    <th><input type="checkbox" id="psAll" checked aria-label="Alle regels"></th>
                    <th>Moederproject</th>
                    <th>Locatie</th>
                    <th>Looplocatie</th>
                    <th>Artikel</th>
                    <th>Omschrijving</th>
                    <th>Aantal</th>
                </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </section>

    <p class="page-footer">(c) 2026 PdB - Geeve Hydraulics - Versie <?= h(APP_VERSION) ?></p>
</main>
<!-- Alleen voor het printen: hier komen de stickers (elk aantal keer) vlak voor window.print(). -->
<div id="psPrintSheet" class="ps-printsheet" aria-hidden="true"></div>
<script src="<?= h(geeveUrl('stickers/assets/vendor/JsBarcode.code128.min.js')) ?>"></script>
<script src="<?= h(geeveUrl('stickers/assets/art-sticker.js')) ?>?v=<?= h(assetVersion('../assets/art-sticker.js')) ?>"></script>
<script src="<?= h(geeveUrl('stickers/assets/productie-stickers.js')) ?>?v=<?= h(assetVersion('../assets/productie-stickers.js')) ?>"></script>
<script src="<?= h(geeveUrl('stickers/assets/direct-print.js')) ?>?v=<?= h(assetVersion('../assets/direct-print.js')) ?>"></script>
</body>
</html>
