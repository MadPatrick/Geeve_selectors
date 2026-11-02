<?php

declare(strict_types=1);

require_once __DIR__ . '/../../shared/helpers.php';

// Beveiligde tegel (Config > Tegels): zonder ontgrendelen terug naar het Stickers-scherm, waar de code wordt gevraagd.
require_once __DIR__ . '/../../shared/tiles.php';
require_once __DIR__ . '/../../shared/paths.php';
require_once __DIR__ . '/../../shared/printen.php';
require_once __DIR__ . '/../../shared/stickers_kaart.php';
tileRequirePage('stickers-artikel', geeveAppUrl('stickers'));

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
    <title>Artikelnummer | Geeve Hydraulics</title>
    <!-- Knoppen, tabel, invoerveld en meldingen zijn dezelfde als in de rapporten. -->
    <?php
    $headCss = ['klantartikel/assets/style.css', 'reports/msp/assets/style.css', 'shared/stickers.css', 'stickers/assets/art-sticker.css', 'stickers/assets/artikel-stickers.css'];   // vanaf de portal-root, in laadvolgorde
    require __DIR__ . '/../../shared/head.php';
    ?>
</head>
<body>
<main class="page-shell page-shell--wide">
    <?php
    $headerTitle = 'Artikelnummer';
    $headerImagesPath = geeveUrl('images/');
    $headerHomeHref = geeveAppUrl('stickers');
    $headerTopline = ''; // het versienummer staat onderaan de pagina, achter de copyright-regel
    require __DIR__ . '/../../shared/header.php';
    ?>

    <section class="panel sk-top">
        <div class="sk-invoer">
            <?= stickerTitelHtml('Artikelnummer', '70 × 25 mm', 'Zoek op (een deel van) een artikelnummer en klik een regel aan voor het voorbeeld.', 'Label ALTEC 72000496. Vink regels aan om er meer tegelijk te printen (zonder vinkjes wordt de aangeklikte regel geprint).') ?>
            <div class="ka-actions" id="saActions" data-endpoint="api/data.php" data-csrf="<?= h($csrf) ?>">
                <label class="msp-field">
                    <span>Zoek op artikelnr</span>
                    <input type="text" id="saSearch" class="ka-input" maxlength="60" autocomplete="off" autofocus>
                </label>
                <span id="saStatus" class="msp-status"></span>
            </div>
            <div id="saMessage" class="ka-message ka-message--error" hidden></div>
        </div>
        <?= stickerKaartHtml([
            'id' => 'saSide', 'tileId' => 'stickers-artikel', 'csrf' => $csrf,
            'preview' => '<div class="sa-preview" id="saSheet"></div>',
            'copiesId' => 'saCopies', 'extra' => '<span id="saPrintInfo" class="msp-status"></span>',
            'printId' => 'saPrint', 'printClass' => 'sa-btn', 'printAttrs' => ' data-roll="geeve"', 'printDisabled' => true,
        ]) ?>
    </section>
    <section class="panel" id="saResult" hidden>
        <div class="ka-table-wrap sa-table-wrap">
            <table class="ka-table" id="saTable">
                <thead>
                <tr>
                    <th><input type="checkbox" id="saAll" aria-label="Alle regels"></th>
                    <th>Artikel</th>
                    <th>Omschrijving</th>
                </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </section>

    <p class="page-footer">(c) 2026 PdB - Geeve Hydraulics - Versie <?= h(APP_VERSION) ?></p>
</main>
<!-- Alleen voor het printen: hier komen de stickers (elk aantal keer) vlak voor window.print(). -->
<div id="saPrintSheet" class="sa-printsheet" aria-hidden="true"></div>
<script src="<?= h(geeveUrl('stickers/assets/vendor/JsBarcode.code128.min.js')) ?>"></script>
<script src="<?= h(geeveUrl('stickers/assets/art-sticker.js')) ?>?v=<?= h(assetVersion('../assets/art-sticker.js')) ?>"></script>
<script src="<?= h(geeveUrl('stickers/assets/artikel-stickers.js')) ?>?v=<?= h(assetVersion('../assets/artikel-stickers.js')) ?>"></script>
<script src="<?= h(geeveUrl('stickers/assets/direct-print.js')) ?>?v=<?= h(assetVersion('../assets/direct-print.js')) ?>"></script>
</body>
</html>
