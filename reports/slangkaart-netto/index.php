<?php

declare(strict_types=1);

require_once __DIR__ . '/../../shared/helpers.php';

// Tegel-toegang (Config > Tegels): is de tegel beveiligd, dan terug naar het overzicht, waar de code wordt gevraagd.
require_once __DIR__ . '/../../shared/tiles.php';
require_once __DIR__ . '/../../shared/paths.php';
tileRequirePage('slangkaart-netto', geeveAppUrl('reports'));

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
    <title>Slangkaart Nettoprijzen | Geeve Hydraulics</title>
    <?php
    // Knoppen, tabel en meldingen komen uit Klant Artikelnummers; msp-pill uit het MSP-rapport; filterrij uit Orderhistorie.
    $headCss = ['klantartikel/assets/style.css', 'reports/msp/assets/style.css', 'templates/orderhistorie/assets/style.css', 'reports/slangkaart-netto/assets/style.css'];
    require __DIR__ . '/../../shared/head.php';
    ?>
</head>
<body>
<main class="page-shell page-shell--wide">
    <?php
    $headerTitle = 'Slangkaart Nettoprijzen';
    $headerImagesPath = geeveUrl('images/');
    $headerHomeHref = geeveAppUrl('reports'); // tegel staat onder Reports
    $headerTopline = ''; // het versienummer staat onderaan de pagina, achter de copyright-regel
    require __DIR__ . '/../../shared/header.php';
    ?>

    <section class="panel" id="snApp" data-endpoint="api/data.php" data-klanten="api/klanten.php" data-csrf="<?= h($csrf) ?>">
        <h2>Slangassemblages met nettoprijs</h2>
        <p class="ka-empty">Slangkaarten met een netto afspraak, met slangassemblagenummer, klantnaam en nettoprijs (alleen lezen, rechtstreeks uit de Slangkaarten-database). De nettoprijs is het veld "Netto afspraak" van de slangkaart. De lijst wordt pas opgehaald als je op Vernieuwen klikt; op het scherm staan maximaal 1.000 regels, de Excel bevat alles. Klik op een kolomkop om te sorteren, gebruik de zoekvelden onder de kop om te filteren.</p>
        <div class="ka-actions">
            <div class="oh-klant">
                <input type="text" id="snKlant" class="ka-input" placeholder="Klantnaam of klantnr…" aria-label="Klantnaam of klantnummer" title="Typ een klantnummer of (delen van) de naam, kies uit de lijst of druk op Enter voor de bovenste" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="snKlantHits">
                <ul class="oh-hits" id="snKlantHits" role="listbox" hidden></ul>
            </div>
            <input type="text" id="snNummer" class="ka-input" placeholder="Slangnummer…" aria-label="Slangnummer" autocomplete="off">
            <button type="button" id="snVernieuw" class="ka-button">Vernieuwen</button>
            <button type="button" id="snDownload" class="ka-button ka-button--secondary" disabled>Download Excel</button>
        </div>
        <div id="snMessage" class="ka-message ka-message--error" hidden></div>
    </section>

    <section class="panel" id="snResult" hidden>
        <div class="msp-summary" id="snSummary"></div>
        <div class="ka-table-wrap msp-scroll"><table class="ka-table oh-table" id="snTable"></table></div>
    </section>

    <p class="page-footer">(c) 2026 PdB - Geeve Hydraulics - Versie <?= h(APP_VERSION) ?></p>
</main>
<script src="<?= h(geeveUrl('reports/assets/vendor/xlsx-style.min.js')) ?>"></script>
<script src="<?= h(geeveAssetHref('reports/slangkaart-netto/assets/slangkaart-netto.js')) ?>"></script>
</body>
</html>
