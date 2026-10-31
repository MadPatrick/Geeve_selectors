<?php

declare(strict_types=1);

require_once __DIR__ . '/../../shared/helpers.php';

// Beveiligde tegel (Config > Tegels): zonder ontgrendelen terug naar het Stickers-scherm, waar de code wordt gevraagd.
require_once __DIR__ . '/../../shared/tiles.php';
require_once __DIR__ . '/../../shared/paths.php';
require_once __DIR__ . '/../../shared/printen.php';
tileRequirePage('stickers-order', geeveAppUrl('stickers'));

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
    <title>Artikelen per Verkooporder | Geeve Hydraulics</title>
    <!-- Knoppen, tabel, invoerveld en meldingen zijn dezelfde als in de rapporten. -->
    <?php
    $headCss = ['klantartikel/assets/style.css', 'reports/msp/assets/style.css', 'stickers/assets/art-sticker.css', 'stickers/assets/order-stickers.css'];   // vanaf de portal-root, in laadvolgorde
    require __DIR__ . '/../../shared/head.php';
    ?>
</head>
<body>
<main class="page-shell page-shell--wide">
    <?php
    $headerTitle = 'Artikelen per Verkooporder';
    $headerImagesPath = geeveUrl('images/');
    $headerHomeHref = geeveAppUrl('stickers');
    $headerTopline = ''; // het versienummer staat onderaan de pagina, achter de copyright-regel
    require __DIR__ . '/../../shared/header.php';
    ?>

    <section class="panel so-top">
        <div class="so-form">
        <h2>Artikelen per Verkooporder</h2>
        <p class="ka-empty">Vul een ordernummer in: je krijgt per orderregel een sticker (102 × 54 mm), in de volgorde van de order of van de looplocatie, of als artikelsticker (70 × 25 mm, ALTEC 72000496: omschrijving, barcode en artikelnummer). Vink regels uit die je niet wilt printen. Met &ldquo;Backorders&rdquo; (standaard) krijg je alleen de regels die nog niet (helemaal) geleverd zijn; met &ldquo;Compleet&rdquo; alle regels van de order.</p>
        <div class="ka-actions" id="soActions" data-endpoint="api/data.php" data-csrf="<?= h($csrf) ?>">
            <label class="msp-field">
                <span>Ordernummer</span>
                <input type="text" id="soNumber" class="ka-input" inputmode="numeric" maxlength="18" autocomplete="off" autofocus>
            </label>
            <button type="button" id="soFetch" class="ka-button">Gegevens ophalen</button>
            <div class="so-keuzes">
                <div class="rp-toggle" id="soSort" role="group" aria-label="Volgorde">
                    <button type="button" data-sort="regel">Orderregel</button>
                    <button type="button" data-sort="loop" class="is-active">Looplocatie</button>
                    <button type="button" data-sort="artikel">Artikelsticker</button>
                </div>
                <div class="rp-toggle" id="soFilter" role="group" aria-label="Regels">
                    <button type="button" data-filter="compleet">Compleet</button>
                    <button type="button" data-filter="backorders" class="is-active">Backorders</button>
                </div>
            </div>
            <span id="soStatus" class="msp-status"></span>
        </div>
        <div id="soMessage" class="ka-message ka-message--error" hidden></div>
        </div>

        <!-- Voorbeeld: rechts in dit blok, één sticker tegelijk. -->
        <div class="so-side" id="soPreviewPanel" hidden>
            <div class="so-preview" id="soSheet"></div>
            <!-- Vorige/volgende sticker en "Sticker x van y": verborgen (het script gebruikt ze nog). -->
            <div hidden>
            <div class="so-nav" id="soNav">
                <button type="button" id="soPrev" class="ka-button ka-button--secondary" aria-label="Vorige sticker">&lt;</button>
                <span id="soPreviewCount" class="msp-status"></span>
                <button type="button" id="soNext" class="ka-button ka-button--secondary" aria-label="Volgende sticker">&gt;</button>
            </div>
            </div>
            <div class="so-print-rechts">
            <?= geevePrintAfdrukInstellingenHtml('stickers-order') ?>
            <div class="print-group so-printrow">
                <?= geevePrinterSelectHtml('stickers-order', $csrf) ?>
                <button type="button" id="soPrint" class="print-btn so-print" disabled><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9V2h12v7"></path><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg><span>Print</span></button>
            </div>
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
<script src="<?= h(geeveUrl('stickers/assets/vendor/JsBarcode.code128.min.js')) ?>"></script>
<script src="<?= h(geeveUrl('stickers/assets/art-sticker.js')) ?>?v=<?= h(assetVersion('../assets/art-sticker.js')) ?>"></script>
<script src="<?= h(geeveUrl('stickers/assets/order-stickers.js')) ?>?v=<?= h(assetVersion('../assets/order-stickers.js')) ?>"></script>
<script src="<?= h(geeveUrl('stickers/assets/direct-print.js')) ?>?v=<?= h(assetVersion('../assets/direct-print.js')) ?>"></script>
</body>
</html>
