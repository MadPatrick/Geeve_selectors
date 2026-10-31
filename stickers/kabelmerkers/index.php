<?php

declare(strict_types=1);

require_once __DIR__ . '/../../shared/helpers.php';

// Beveiligde tegel (Config > Tegels): zonder ontgrendelen terug naar het Stickers-scherm, waar de code wordt gevraagd.
require_once __DIR__ . '/../../shared/tiles.php';
require_once __DIR__ . '/../../shared/paths.php';
require_once __DIR__ . '/../../shared/csrf.php';
require_once __DIR__ . '/../../shared/printen.php';
require_once __DIR__ . '/../../shared/stickers_kaart.php';
tileRequirePage('stickers-kabelmerkers', geeveAppUrl('stickers'));

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
    <title>Kabelmerkers Geel | Geeve Hydraulics</title>
    <!-- Knoppen, invoervelden en meldingen zijn dezelfde als in de rapporten. -->
    <?php
    $headCss = ['klantartikel/assets/style.css', 'reports/msp/assets/style.css', 'shared/stickers.css', 'stickers/assets/kabelmerkers.css'];   // vanaf de portal-root, in laadvolgorde
    require __DIR__ . '/../../shared/head.php';
    ?>
</head>
<body>
<main class="page-shell page-shell--wide">
    <?php
    $headerTitle = 'Kabelmerkers Geel';
    $headerImagesPath = geeveUrl('images/');
    $headerHomeHref = geeveAppUrl('stickers');
    $headerTopline = ''; // het versienummer staat onderaan de pagina, achter de copyright-regel
    require __DIR__ . '/../../shared/header.php';
    ?>

    <section class="panel sk-top">
        <div class="sk-invoer">
            <?= stickerTitelHtml('Kabelmerkers Geel', '70 × 15 mm', 'Vul de velden in (maximaal 20 tekens per regel); het voorbeeld loopt mee.', 'Label ALTEC 72001207 (TTP ADC, 70 × 15 mm, A235), lichtgeel. Maximaal 20 tekens per regel, de schuine strepen niet meegeteld; de datum telt als 8 tekens. Regel 1 bestaat uit A1/A2/A3, regel 2 uit Datum/B2/B3 (lege velden worden overgeslagen, de delen staan gescheiden door een schuine streep). Print: 55 mm tussen de transportgaten. Het voorbeeld toont de sticker zoals hij er op het label uitziet, de tekst staat op de bovenste; de andere twee zijn lege voorbeelden (te vullen met een Excel-import).') ?>
            <div class="km-edit">
                <div class="km-regel">
                    <h3>Regel 1</h3>
                    <label for="kmA1">Regel A1</label>
                    <input type="text" id="kmA1" class="ka-input" maxlength="20" spellcheck="false" autofocus>
                    <label for="kmA2">Regel A2</label>
                    <input type="text" id="kmA2" class="ka-input" maxlength="20" spellcheck="false">
                    <label for="kmA3">Regel A3</label>
                    <input type="text" id="kmA3" class="ka-input" maxlength="20" spellcheck="false">
                </div>
                <div class="km-regel">
                    <h3>Regel 2</h3>
                    <label for="kmDatum">Datum</label>
                    <input type="date" id="kmDatum" class="ka-input" required>
                    <label for="kmB2">Regel B2</label>
                    <input type="text" id="kmB2" class="ka-input" maxlength="20" spellcheck="false">
                    <label for="kmB3">Regel B3</label>
                    <input type="text" id="kmB3" class="ka-input" maxlength="20" spellcheck="false">
                </div>
            </div>
            <div id="kmMelding" class="ka-message ka-message--error km-melding" role="alert" hidden></div>
        </div>
        <?= stickerKaartHtml([
            'tileId' => 'stickers-kabelmerkers', 'csrf' => $csrf,
            'preview' => '<div class="km-preview" id="kmPreview" aria-label="Voorbeeld van de sticker">'
                . '<div class="km-sticker"><div class="km-tekst"></div></div>'
                . '<div class="km-sticker"><div class="km-tekst"></div></div>'
                . '<div class="km-sticker"><div class="km-tekst"></div></div></div>',
            'copiesId' => 'kmAantal', 'clearId' => 'kmClear', 'printId' => 'kmPrint', 'printDisabled' => true,
        ]) ?>
    </section>
    <section class="panel">
        <h2>Excel importeren</h2>
        <div class="ka-actions km-import">
            <button type="button" id="kmTemplate" class="ka-button ka-button--secondary">Download lege template</button>
            <div id="kmDrop" class="ka-drop" tabindex="0" role="button">
                <strong>Excel importeren</strong> - sleep een ingevulde template hierheen of <u>kies een bestand</u> (kolommen Tekst A1, A2, A3, B1, B2, B3)
                <input type="file" id="kmFile" accept=".xlsx,.xls,.xlsm,.xltm" hidden>
            </div>
        </div>
        <div id="kmImportMelding" class="ka-message" hidden></div>
        <div id="kmImportTabel" hidden></div>
    </section>

    <p class="page-footer">(c) 2026 PdB - Geeve Hydraulics - Versie <?= h(APP_VERSION) ?></p>
</main>
<!-- Alleen voor het printen: hier komen de stickers (aantal keer) vlak voor window.print(). -->
<div id="kmPrintSheet" class="km-printsheet" aria-hidden="true"></div>
<script src="<?= h(geeveUrl('klantartikel/assets/vendor/xlsx.core.min.js')) ?>?v=<?= h(assetVersion('../../klantartikel/assets/vendor/xlsx.core.min.js')) ?>"></script>
<script src="<?= h(geeveUrl('reports/assets/vendor/xlsx-lezen.js')) ?>?v=<?= h(assetVersion('../../reports/assets/vendor/xlsx-lezen.js')) ?>"></script>
<script src="<?= h(geeveUrl('reports/assets/vendor/xlsx-style.min.js')) ?>"></script>
<script src="<?= h(geeveUrl('stickers/assets/kabelmerkers.js')) ?>?v=<?= h(assetVersion('../assets/kabelmerkers.js')) ?>"></script>
<script src="<?= h(geeveUrl('stickers/assets/direct-print.js')) ?>?v=<?= h(assetVersion('../assets/direct-print.js')) ?>"></script>
</body>
</html>
