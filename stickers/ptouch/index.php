<?php

declare(strict_types=1);

require_once __DIR__ . '/../../shared/helpers.php';

// Beveiligde tegel (Config > Tegels): zonder ontgrendelen terug naar het Stickers-scherm, waar de code wordt gevraagd.
require_once __DIR__ . '/../../shared/tiles.php';
require_once __DIR__ . '/../../shared/paths.php';
require_once __DIR__ . '/../../shared/csrf.php';
require_once __DIR__ . '/../../shared/printen.php';
require_once __DIR__ . '/../../shared/stickers_kaart.php';
tileRequirePage('stickers-ptouch', geeveAppUrl('stickers'));

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
    <title>Naamstickers | Geeve Hydraulics</title>
    <!-- Knoppen, invoervelden en meldingen zijn dezelfde als in de rapporten. -->
    <?php
    $headCss = ['shared/tabel.css', 'shared/rapport.css', 'shared/stickers.css', 'stickers/assets/ptouch.css'];   // vanaf de portal-root, in laadvolgorde
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

    <section class="panel sk-top">
        <div class="sk-invoer">
            <?= stickerTitelHtml('Naamstickers', '102 × 51 mm / 70 × 25 mm', 'Typ de tekst rechtstreeks op de sticker; de letter schaalt mee. Enter geeft een nieuwe regel.', 'Het lettertype is Verdana Bold, de tekst is horizontaal en verticaal uit te lijnen (standaard midden). De letter is zo groot dat er minimaal twee regels op de sticker passen en wordt kleiner naarmate je meer regels typt (er blijft steeds één lege regel onder de tekst).') ?>
            <div class="pt-align">
                <span class="pt-align-label">Formaat</span>
                <div class="rp-toggle" id="ptMaat" role="group" aria-label="Formaat sticker">
                    <button type="button" data-maat="groot" class="is-active">Sticker Groot (102 × 51 mm)</button>
                    <button type="button" data-maat="klein">Sticker Klein (70 × 25 mm)</button>
                </div>
            </div>
        </div>
        <?= stickerKaartHtml([
            'tileId' => 'stickers-ptouch', 'csrf' => $csrf,
            'preview' => '<div class="pt-preview"><div class="pt-sticker" id="ptSticker"><div class="pt-text" id="ptText" contenteditable="plaintext-only" spellcheck="false" role="textbox" aria-multiline="true" aria-label="Tekst op de sticker" data-placeholder="Typ hier je tekst" autofocus></div></div></div>',
            'actiesMidden' => '<div class="rp-toggle rp-toggle--icons" id="ptAlign" role="group" aria-label="Uitlijnen horizontaal"><button type="button" data-align="left" title="Links (uitlijnen horizontaal)" aria-label="Links, uitlijnen horizontaal"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 6h16M4 12h10M4 18h13"/></svg></button><button type="button" data-align="center" class="is-active" title="Midden (uitlijnen horizontaal)" aria-label="Midden, uitlijnen horizontaal"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 6h16M7 12h10M5.5 18h13"/></svg></button><button type="button" data-align="right" title="Rechts (uitlijnen horizontaal)" aria-label="Rechts, uitlijnen horizontaal"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 6h16M10 12h10M7 18h13"/></svg></button></div><div class="rp-toggle rp-toggle--icons" id="ptValign" role="group" aria-label="Uitlijnen verticaal"><button type="button" data-valign="flex-start" title="Boven (uitlijnen verticaal)" aria-label="Boven, uitlijnen verticaal"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M8 7h8"/></svg></button><button type="button" data-valign="center" class="is-active" title="Midden (uitlijnen verticaal)" aria-label="Midden, uitlijnen verticaal"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M8 12h8"/></svg></button><button type="button" data-valign="flex-end" title="Onder (uitlijnen verticaal)" aria-label="Onder, uitlijnen verticaal"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M8 17h8"/></svg></button></div>',
            'copiesId' => 'ptAantal', 'clearId' => 'ptClear', 'printId' => 'ptPrint', 'printDisabled' => true,
        ]) ?>
    </section>
    <p class="page-footer">(c) 2026 PdB - Geeve Hydraulics - Versie <?= h(APP_VERSION) ?></p>
</main>
<!-- Alleen voor het printen: hier komen de stickers (aantal keer) vlak voor window.print(). -->
<div id="ptPrintSheet" class="pt-printsheet" aria-hidden="true"></div>
<script src="<?= h(geeveUrl('stickers/assets/ptouch.js')) ?>?v=<?= h(assetVersion('../assets/ptouch.js')) ?>"></script>
<script src="<?= h(geeveUrl('shared/direct-print.js')) ?>?v=<?= h(assetVersion('../../shared/direct-print.js')) ?>"></script>
</body>
</html>
