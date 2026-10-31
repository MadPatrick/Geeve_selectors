<?php

declare(strict_types=1);

require_once __DIR__ . '/../../shared/helpers.php';

// Beveiligde tegel (Config > Tegels): zonder ontgrendelen terug naar het Stickers-scherm, waar de code wordt gevraagd.
require_once __DIR__ . '/../../shared/tiles.php';
require_once __DIR__ . '/../../shared/paths.php';
require_once __DIR__ . '/../../shared/printen.php';
require_once __DIR__ . '/../../shared/stickers_kaart.php';
tileRequirePage('stickers-slanglabels', geeveAppUrl('stickers'));

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
    <title>Slanglabels | Geeve Hydraulics</title>
    <!-- Knoppen, tabel, invoerveld en meldingen zijn dezelfde als in de rapporten. -->
    <?php
    $headCss = ['klantartikel/assets/style.css', 'reports/msp/assets/style.css', 'shared/stickers.css', 'stickers/assets/slanglabels.css'];   // vanaf de portal-root, in laadvolgorde
    require __DIR__ . '/../../shared/head.php';
    ?>
</head>
<body>
<main class="page-shell page-shell--wide">
    <?php
    $headerTitle = 'Slanglabels';
    $headerImagesPath = geeveUrl('images/');
    $headerHomeHref = geeveAppUrl('stickers');
    $headerTopline = ''; // het versienummer staat onderaan de pagina, achter de copyright-regel
    require __DIR__ . '/../../shared/header.php';
    ?>

    <section class="panel sk-top">
        <div class="sk-invoer">
            <?= stickerTitelHtml('Slanglabels', '25 × 95 mm', 'Vul een ordernummer in en haal de slangen op. Klik een regel aan om de velden te vullen; je kunt ze daarna nog aanpassen.', 'Alle regels staan aangevinkt en worden geprint (elk met het eigen aantal); vink uit wat je niet wilt. Zonder vinkjes wordt de aangeklikte regel geprint. Met het vinkje bij Datum kies je met of zonder datum op het label. Printvlak 25 × 25 mm bovenaan, de rest is plakrand.') ?>
            <div class="ka-actions" id="slActions" data-endpoint="api/data.php" data-csrf="<?= h($csrf) ?>">
                <label class="msp-field">
                    <span>Ordernummer</span>
                    <input type="text" id="slNumber" class="ka-input" inputmode="numeric" maxlength="18" autocomplete="off" autofocus>
                </label>
                <button type="button" id="slFetch" class="ka-button">Gegevens ophalen</button>
                <span id="slStatus" class="msp-status"></span>
            </div>
            <div id="slMessage" class="ka-message ka-message--error" hidden></div>

            <div class="sl-fields" id="slFields">
                <label class="msp-field"><span>Slangnummer</span><input type="text" class="ka-input" data-field="slangnummer" maxlength="40"></label>
                <label class="msp-field"><span>Uw referentie</span><input type="text" class="ka-input" data-field="referentie" maxlength="60"></label>
                <label class="msp-field"><span>Lengte</span><input type="text" class="ka-input" data-field="lengte" maxlength="20"></label>
                <label class="msp-field"><span>Datum</span><input type="text" class="ka-input" data-field="datum" maxlength="20"></label>
                <label class="msp-field sl-klant"><span>Klantnaam</span><input type="text" class="ka-input" data-field="klantnaam" maxlength="80"></label>
                <div class="sl-aantalrij">
                <label class="msp-field sl-aantal"><span>Aantal</span><input type="number" class="ka-input" data-field="aantal" min="1" max="99"></label>
                <div class="msp-field sl-printen"><span>Printen Datum</span><label class="sl-check"><input type="checkbox" id="slWithDate" checked> Datum op label</label></div>
                </div>
            </div>
        </div>
        <?= stickerKaartHtml([
            'tileId' => 'stickers-slanglabels', 'csrf' => $csrf,
            'preview' => '<div class="sl-preview" id="slSheet"></div>',
            'extra' => '<span id="slPrintInfo" class="msp-status"></span>',
            'printId' => 'slPrint',
        ]) ?>
    </section>
    <section class="panel" id="slResult" hidden>
        <div class="ka-table-wrap">
            <table class="ka-table" id="slTable">
                <thead>
                <tr>
                    <th><input type="checkbox" id="slAll" aria-label="Alle regels"></th>
                    <th>Slangnummer</th>
                    <th>Uw referentie</th>
                    <th>Datum</th>
                    <th>Klantnaam</th>
                    <th>Aantal</th>
                    <th>Lengte</th>
                    <th>Artnr KL</th>
                </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </section>

    <p class="page-footer">(c) 2026 PdB - Geeve Hydraulics - Versie <?= h(APP_VERSION) ?></p>
</main>
<!-- Alleen voor het printen: hier komen de labels (elk aantal keer) vlak voor window.print(). -->
<div id="slPrintSheet" class="sl-printsheet" aria-hidden="true"></div>
<script src="<?= h(geeveUrl('stickers/assets/slanglabels.js')) ?>?v=<?= h(assetVersion('../assets/slanglabels.js')) ?>"></script>
<script src="<?= h(geeveUrl('stickers/assets/direct-print.js')) ?>?v=<?= h(assetVersion('../assets/direct-print.js')) ?>"></script>
</body>
</html>
