<?php

declare(strict_types=1);

// Beveiligde tegel (Config > Tegels): zonder ontgrendelen terug naar het Stickers-scherm, waar de code wordt gevraagd.
require_once __DIR__ . '/../../shared/tiles.php';
require_once __DIR__ . '/../../shared/paths.php';
tileRequirePage('stickers-artikel-groot', geeveAppUrl('stickers'));


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

?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Accu's | Geeve Hydraulics</title>
    <link rel="icon" href="<?= h(geeveUrl('favicon.ico')) ?>?v=<?= h(assetVersion('../../favicon.ico')) ?>" type="image/x-icon">
    <link rel="stylesheet" href="<?= h(geeveUrl('shared/style.css')) ?>?v=<?= h(assetVersion('../../shared/style.css')) ?>">
    <!-- Knoppen, invoervelden en meldingen zijn dezelfde als in de rapporten. -->
    <link rel="stylesheet" href="<?= h(geeveUrl('klantartikel/assets/style.css')) ?>?v=<?= h(assetVersion('../../klantartikel/assets/style.css')) ?>">
    <link rel="stylesheet" href="<?= h(geeveUrl('reports/msp/assets/style.css')) ?>?v=<?= h(assetVersion('../../reports/msp/assets/style.css')) ?>">
    <link rel="stylesheet" href="<?= h(geeveUrl('stickers/assets/accu.css')) ?>?v=<?= h(assetVersion('../assets/accu.css')) ?>">
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

    <section class="panel ac-top">
        <div class="ac-form">
            <h2>Accu's</h2>
            <p class="ka-empty">Vul de velden in; het voorbeeld rechts loopt mee. Met Leegmaken begin je opnieuw (bedrijfsnaam en datum van vandaag blijven staan).</p>
            <div class="ac-fields" id="acFields">
                <label class="msp-field ac-wide"><span>Bedrijfsnaam</span><input type="text" class="ka-input" data-field="bedrijf" maxlength="40" value="GEEVE HYDRAULICS BV"></label>
                <label class="msp-field"><span>Voorvuldruk</span><input type="text" class="ka-input" data-field="druk" maxlength="12" autofocus></label>
                <label class="msp-field"><span>Datum</span><input type="date" class="ka-input" data-field="datum"></label>
                <label class="msp-field ac-wide"><span>Vrij veld</span><input type="text" class="ka-input" data-field="vrij" maxlength="40"></label>
            </div>
        </div>

        <!-- Voorbeeld van de sticker met daaronder aantal, leegmaken en printen. -->
        <div class="ac-side">
            <div class="ac-preview" id="acSheet"></div>
            <div class="ac-print">
                <label class="ac-copies"><input type="number" id="acCopies" class="ka-input" min="1" max="99" value="1"> x afdrukken</label>
                <button type="button" id="acClear" class="ka-button ka-button--secondary">Leegmaken</button>
                <button type="button" id="acPrint" class="print-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9V2h12v7"></path><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg><span>Print</span></button>
            </div>
        </div>
    </section>

    <p class="page-footer">(c) 2026 PdB - Geeve Hydraulics - Versie <?= h(APP_VERSION) ?></p>
</main>
<!-- Alleen voor het printen: hier komen de stickers (aantal keer) vlak voor window.print(). -->
<div id="acPrintSheet" class="ac-printsheet" aria-hidden="true"></div>
<script src="<?= h(geeveUrl('stickers/assets/accu.js')) ?>?v=<?= h(assetVersion('../assets/accu.js')) ?>"></script>
</body>
</html>
