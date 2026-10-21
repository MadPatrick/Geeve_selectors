<?php

declare(strict_types=1);

// Beveiligde tegel (Config > Tegels): zonder ontgrendelen terug naar het Stickers-scherm, waar de code wordt gevraagd.
require_once __DIR__ . '/../../shared/tiles.php';
require_once __DIR__ . '/../../shared/paths.php';
require_once __DIR__ . '/../../shared/csrf.php';
require_once __DIR__ . '/../../shared/printen.php';
tileRequirePage('stickers-ptouch', geeveAppUrl('stickers'));

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
    <title>P-Touch | Geeve Hydraulics</title>
    <!-- Knoppen, invoervelden en meldingen zijn dezelfde als in de rapporten. -->
    <?php
    $headCss = ['klantartikel/assets/style.css', 'reports/msp/assets/style.css', 'stickers/assets/ptouch.css'];   // vanaf de portal-root, in laadvolgorde
    require __DIR__ . '/../../shared/head.php';
    ?>
</head>
<body>
<main class="page-shell page-shell--wide">
    <?php
    $headerTitle = 'P-Touch';
    $headerImagesPath = geeveUrl('images/');
    $headerHomeHref = geeveAppUrl('stickers');
    $headerTopline = ''; // het versienummer staat onderaan de pagina, achter de copyright-regel
    require __DIR__ . '/../../shared/header.php';
    ?>

    <section class="panel pt-top">
        <h2>P-Touch</h2>
        <p class="ka-empty">Typ de tekst rechtstreeks op de sticker (102 × 51 mm). Het lettertype is Arial Black 40 px; wordt de tekst te veel, dan wordt de letter vanzelf kleiner zodat alles op de sticker past. Enter geeft een nieuwe regel.</p>

        <div class="pt-preview">
            <div class="pt-sticker" id="ptSticker"><div class="pt-text" id="ptText" contenteditable="plaintext-only" spellcheck="false" role="textbox" aria-multiline="true" aria-label="Tekst op de sticker" data-placeholder="Typ hier je tekst" autofocus></div></div>
        </div>

        <div class="pt-print">
            <button type="button" id="ptClear" class="ka-button ka-button--secondary">Leegmaken</button>
            <div class="print-group">
                <?= geevePrinterSelectHtml('stickers-ptouch', $csrf) ?>
                <button type="button" id="ptPrint" class="print-btn" disabled><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9V2h12v7"></path><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg><span>Print</span></button>
            </div>
        </div>
    </section>

    <p class="page-footer">(c) 2026 PdB - Geeve Hydraulics - Versie <?= h(APP_VERSION) ?></p>
</main>
<!-- Alleen voor het printen: hier komen de stickers (aantal keer) vlak voor window.print(). -->
<div id="ptPrintSheet" class="pt-printsheet" aria-hidden="true"></div>
<script src="<?= h(geeveUrl('stickers/assets/ptouch.js')) ?>?v=<?= h(assetVersion('../assets/ptouch.js')) ?>"></script>
<script src="<?= h(geeveUrl('stickers/assets/direct-print.js')) ?>?v=<?= h(assetVersion('../assets/direct-print.js')) ?>"></script>
</body>
</html>
