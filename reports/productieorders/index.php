<?php

declare(strict_types=1);

// Tegel-toegang (Config > Tegels): is de tegel beveiligd, dan terug naar het overzicht, waar de code wordt gevraagd.
require_once __DIR__ . '/../../shared/tiles.php';
require_once __DIR__ . '/../../shared/paths.php';
tileRequirePage('productieorders', geeveAppUrl('templates'));

require_once __DIR__ . '/../../shared/csrf.php';

// Eén gedeeld versienummer voor hoofdscherm + alle subapps (version.php op
// rootniveau) - valt terug op deze waarde als dat bestand ontbreekt.
define('APP_VERSION', is_file(__DIR__ . '/../../version.php') ? (string) require __DIR__ . '/../../version.php' : '0.2.1');

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$csrf = csrfToken();
?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Productie Orders | Geeve Hydraulics</title>
    <?php
    // Knoppen, tabel en meldingen komen uit Klant Artikelnummers; msp-pill uit het MSP-rapport; filterrij uit Orderhistorie.
    $headCss = ['klantartikel/assets/style.css', 'reports/msp/assets/style.css', 'reports/orderhistorie/assets/style.css', 'reports/productieorders/assets/style.css'];
    require __DIR__ . '/../../shared/head.php';
    ?>
</head>
<body>
<main class="page-shell page-shell--wide">
    <?php
    $headerTitle = 'Productie Orders';
    $headerImagesPath = geeveUrl('images/');
    $headerHomeHref = geeveAppUrl('templates'); // tegel staat onder Templates
    $headerTopline = ''; // het versienummer staat onderaan de pagina, achter de copyright-regel
    require __DIR__ . '/../../shared/header.php';
    ?>

    <section class="panel" id="poApp" data-endpoint="api/data.php" data-csrf="<?= h($csrf) ?>">
        <h2>Overzicht gereed te melden productieorders</h2>
        <p class="ka-empty">De productieorders die gereed gemeld moeten worden, rechtstreeks uit Exact (zelfde gegevens als de Excel "Overzicht gereed te melden productieorders"). De lijst wordt bij het openen en daarna elk uur ververst. Klik op een kolomkop om te sorteren, gebruik de zoekvelden onder de kop om te filteren.</p>
        <div class="ka-actions">
            <button type="button" id="poVernieuw" class="ka-button">Vernieuwen</button>
            <button type="button" id="poDownload" class="ka-button ka-button--secondary" disabled>Download Excel</button>
            <label class="po-groep-keuze"><input type="checkbox" id="poGroep" checked> Groeperen op moederproject</label>
            <span id="poStatus" class="msp-status"></span>
        </div>
        <div id="poMessage" class="ka-message ka-message--error" hidden></div>
    </section>

    <section class="panel" id="poResult" hidden>
        <div class="msp-summary" id="poSummary"></div>
        <div class="ka-table-wrap msp-scroll"><table class="ka-table oh-table" id="poTable"></table></div>
    </section>

    <p class="page-footer">(c) 2026 PdB - Geeve Hydraulics - Versie <?= h(APP_VERSION) ?></p>
</main>
<script src="<?= h(geeveUrl('reports/assets/vendor/xlsx-style.min.js')) ?>"></script>
<script src="<?= h(geeveAssetHref('reports/productieorders/assets/productieorders.js')) ?>"></script>
</body>
</html>
