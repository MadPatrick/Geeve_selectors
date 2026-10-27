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
    <title>Naamstickers | Geeve Hydraulics</title>
    <!-- Knoppen, invoervelden en meldingen zijn dezelfde als in de rapporten. -->
    <?php
    $headCss = ['klantartikel/assets/style.css', 'reports/msp/assets/style.css', 'stickers/assets/ptouch.css'];   // vanaf de portal-root, in laadvolgorde
    require __DIR__ . '/../../shared/head.php';
    ?>
</head>
<body>
<main class="page-shell page-shell--wide">
    <?php
    $headerTitle = 'Naamstickers';
    $headerImagesPath = geeveUrl('images/');
    $headerHomeHref = geeveAppUrl('stickers');
    $headerTopline = ''; // het versienummer staat onderaan de pagina, achter de copyright-regel
    require __DIR__ . '/../../shared/header.php';
    ?>

    <section class="panel pt-top">
        <h2>Naamstickers</h2>
        <p class="ka-empty">Typ de tekst rechtstreeks op de sticker (102 × 51 mm). Het lettertype is Verdana Bold, de tekst is horizontaal en verticaal uit te lijnen (standaard midden). De letter is zo groot dat er minimaal twee regels op de sticker passen en wordt kleiner naarmate je meer regels typt (er blijft steeds één lege regel onder de tekst). Enter geeft een nieuwe regel.</p>

        <div class="pt-edit">
        <div class="pt-preview">
            <div class="pt-sticker" id="ptSticker"><div class="pt-text" id="ptText" contenteditable="plaintext-only" spellcheck="false" role="textbox" aria-multiline="true" aria-label="Tekst op de sticker" data-placeholder="Typ hier je tekst" autofocus></div></div>
        </div>
        <div class="pt-align">
            <span class="pt-align-label">Uitlijnen horizontaal</span>
            <div class="rp-toggle" id="ptAlign" role="group" aria-label="Uitlijnen">
                <button type="button" data-align="left">Links</button>
                <button type="button" data-align="center" class="is-active">Midden</button>
                <button type="button" data-align="right">Rechts</button>
            </div>
            <span class="pt-align-label">Uitlijnen verticaal</span>
            <div class="rp-toggle" id="ptValign" role="group" aria-label="Uitlijnen verticaal">
                <button type="button" data-valign="flex-start">Boven</button>
                <button type="button" data-valign="center" class="is-active">Midden</button>
                <button type="button" data-valign="flex-end">Onder</button>
            </div>
        </div>
        </div>

        <div class="pt-print">
            <button type="button" id="ptClear" class="ka-button ka-button--secondary">Leegmaken</button>
            <?= geevePrintAfdrukInstellingenHtml('stickers-ptouch') ?>
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
