<?php

declare(strict_types=1);

require_once __DIR__ . '/../../shared/helpers.php';

// Beveiligde tegel (Config > Tegels): zonder ontgrendelen terug naar het Stickers-scherm, waar de code wordt gevraagd.
require_once __DIR__ . '/../../shared/tiles.php';
require_once __DIR__ . '/../../shared/paths.php';
require_once __DIR__ . '/../../shared/csrf.php';
require_once __DIR__ . '/../../shared/printen.php';
require_once __DIR__ . '/../../shared/stickers_kaart.php';
tileRequirePage('stickers-artikel-groot', geeveAppUrl('stickers'));


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
    <title>Accu's | Geeve Hydraulics</title>
    <!-- Knoppen, invoervelden en meldingen zijn dezelfde als in de rapporten. -->
    <?php
    $headCss = ['klantartikel/assets/style.css', 'reports/msp/assets/style.css', 'shared/stickers.css', 'stickers/assets/accu.css'];   // vanaf de portal-root, in laadvolgorde
    require __DIR__ . '/../../shared/head.php';
    ?>
</head>
<body>
<main class="page-shell page-shell--wide">
    <?php
    $headerTitle = "Accu's";
    $headerImagesPath = geeveUrl('images/');
    $headerHomeHref = geeveAppUrl('stickers');
    $headerTopline = ''; // het versienummer staat onderaan de pagina, achter de copyright-regel
    require __DIR__ . '/../../shared/header.php';
    ?>

    <section class="panel sk-top">
        <div class="sk-invoer">
            <?= stickerTitelHtml("Accu's", '70 × 32 mm', 'Vul de velden in; het voorbeeld loopt mee.', 'Met Leegmaken begin je opnieuw (bedrijfsnaam en datum van vandaag blijven staan).') ?>
            <div class="ac-fields" id="acFields">
                <label class="msp-field ac-wide"><span>Bedrijfsnaam</span><input type="text" class="ka-input" data-field="bedrijf" maxlength="40" value="GEEVE HYDRAULICS BV"></label>
                <label class="msp-field"><span>Voorvuldruk</span><input type="text" class="ka-input" data-field="druk" maxlength="12" autofocus></label>
                <label class="msp-field"><span>Datum</span><input type="date" class="ka-input" data-field="datum"></label>
                <label class="msp-field ac-wide"><span>Vrij veld</span><input type="text" class="ka-input" data-field="vrij" maxlength="40"></label>
            </div>
        </div>
        <?= stickerKaartHtml([
            'tileId' => 'stickers-artikel-groot', 'csrf' => $csrf,
            'preview' => '<div class="ac-preview" id="acSheet"></div>',
            'copiesId' => 'acCopies', 'clearId' => 'acClear', 'printId' => 'acPrint',
        ]) ?>
    </section>

    <p class="page-footer">(c) 2026 PdB - Geeve Hydraulics - Versie <?= h(APP_VERSION) ?></p>
</main>
<!-- Alleen voor het printen: hier komen de stickers (aantal keer) vlak voor window.print(). -->
<div id="acPrintSheet" class="ac-printsheet" aria-hidden="true"></div>
<script src="<?= h(geeveUrl('stickers/assets/accu.js')) ?>?v=<?= h(assetVersion('../assets/accu.js')) ?>"></script>
<script src="<?= h(geeveUrl('stickers/assets/direct-print.js')) ?>?v=<?= h(assetVersion('../assets/direct-print.js')) ?>"></script>
</body>
</html>
