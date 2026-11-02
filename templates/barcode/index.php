<?php

declare(strict_types=1);

require_once __DIR__ . '/../../shared/helpers.php';

// Tegel-toegang (Config > Tegels): is de tegel beveiligd, dan terug naar het overzicht, waar de code wordt gevraagd.
require_once __DIR__ . '/../../shared/tiles.php';
require_once __DIR__ . '/../../shared/paths.php';
tileRequirePage('barcode', geeveAppUrl('templates'));

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
    <title>Barcode inkoopontvangst | Geeve Hydraulics</title>
    <?php
    // Knoppen, invoervelden en meldingen komen uit Klant Artikelnummers.
    $headCss = ['klantartikel/assets/style.css', 'templates/barcode/assets/style.css'];
    require __DIR__ . '/../../shared/head.php';
    ?>
</head>
<body>
<main class="page-shell">
    <?php
    $headerTitle = 'Barcode inkoopontvangst';
    $headerImagesPath = geeveUrl('images/');
    $headerHomeHref = geeveAppUrl('templates'); // tegel staat onder Templates
    $headerTopline = ''; // het versienummer staat onderaan de pagina, achter de copyright-regel
    require __DIR__ . '/../../shared/header.php';
    ?>

    <section class="panel" id="bcApp" data-endpoint="api/zoek.php" data-csrf="<?= h($csrf) ?>">
        <h2 id="bcLeverancier" class="bc-leverancier">Leverancier: -</h2>
        <p class="ka-empty">Scan of typ het inkoopordernummer en het artikelnummer. Het open aantal (besteld min al ontvangen) komt uit Exact. Het ontvangst boeken is nog niet aangesloten: de knop is uit tot dat is afgesproken.</p>
        <form id="bcForm" class="bc-form" autocomplete="off">
            <label class="bc-veld">Inkooporder
                <input type="text" id="bcOrder" class="ka-input" inputmode="numeric" maxlength="18" autofocus>
            </label>
            <label class="bc-veld">Artikelnummer
                <input type="text" id="bcArtikel" class="ka-input" maxlength="40">
            </label>
            <button type="submit" id="bcZoek" class="ka-button">Opzoeken</button>
        </form>
        <div id="bcMessage" class="ka-message ka-message--error" hidden></div>
        <div id="bcResultaat" hidden>
            <p class="bc-regel"><strong id="bcOmschrijving"></strong></p>
            <table class="ka-table bc-tabel">
                <tbody>
                    <tr><th>Besteld</th><td id="bcBesteld"></td></tr>
                    <tr><th>Al ontvangen</th><td id="bcOntvangen"></td></tr>
                    <tr><th>Open</th><td id="bcOpen"></td></tr>
                </tbody>
            </table>
            <div class="bc-form">
                <label class="bc-veld">Aantal ontvangen
                    <input type="text" id="bcAantal" class="ka-input" inputmode="decimal" maxlength="12">
                </label>
                <button type="button" id="bcBoek" class="ka-button" disabled>Ontvangst boeken</button>
            </div>
        </div>
    </section>

    <p class="page-footer">(c) 2026 PdB - Geeve Hydraulics - Versie <?= h(APP_VERSION) ?></p>
</main>
<script src="<?= h(geeveAssetHref('templates/barcode/assets/barcode.js')) ?>"></script>
</body>
</html>
