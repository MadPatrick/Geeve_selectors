<?php

declare(strict_types=1);

require_once __DIR__ . '/../../shared/helpers.php';

// Beveiligde tegel (Config > Tegels): zonder ontgrendelen terug naar het overzicht, waar de code wordt gevraagd.
require_once __DIR__ . '/../../shared/tiles.php';
require_once __DIR__ . '/../../shared/paths.php';
tileRequirePage('brutoprijzen', geeveAppUrl('reports'));

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
    <title>Brutoprijzen per artikelgroep | Geeve Hydraulics</title>
    <!-- Knoppen, tabel en meldingen zijn dezelfde als in Artikelmarges. -->
    <?php
    $headCss = ['assets/style.css', 'shared/tabel.css', 'shared/rapport.css'];   // vanaf de portal-root, in laadvolgorde
    require __DIR__ . '/../../shared/head.php';
    ?>
</head>
<body>
<main class="page-shell page-shell--wide">
    <?php
    $headerTitle = 'Brutoprijzen per artikelgroep';
    $headerImagesPath = geeveUrl('images/');
    $headerHomeHref = geeveAppUrl('reports');
    $headerTopline = ''; // het versienummer staat onderaan de pagina, achter de copyright-regel
    require __DIR__ . '/../../shared/header.php';
    ?>

    <section class="panel">
        <h2>Brutoprijzen per artikelgroep</h2>
        <p class="ka-empty">Vul een artikelgroepnummer in: per artikel van die groep staan ons artikelnummer, het leveranciersnummer (artikelcode bij de hoofdleverancier), de leverancier (crediteurnummer, bijv. 100011 voor Parker) en de bruto verkoopprijs (standaard verkoopprijs, voor korting) in de lijst; filteren op status kan ook. "Export naar Excel" maakt hiervan een Excel-bestand met deze kolommen.</p>
        <div class="ka-actions" id="rpActions" data-endpoint="api/data.php" data-csrf="<?= h($csrf) ?>" data-label="Artikelgroep" data-sheet="Brutoprijzen" data-file="Brutoprijzen">
            <label class="msp-field">
                <span>Artikelgroep</span>
                <input type="text" class="ka-input" data-field="groep" data-name="een artikelgroep" maxlength="20" autocomplete="off" autofocus>
            </label>
            <label class="msp-field">
                <span>Status</span>
                <select class="ka-input" data-field="status" data-optional="1">
                    <option value="">Alle</option>
                    <option value="A">Actief</option>
                    <option value="E">Vervallen</option>
                    <option value="N">Non-actief</option>
                </select>
            </label>
            <button type="button" id="rpFetch" class="ka-button">Gegevens ophalen</button>
            <button type="button" id="rpDownload" class="ka-button ka-button--secondary" disabled>Export naar Excel</button>
            <span id="rpStatus" class="msp-status"></span>
        </div>
        <div id="rpMessage" class="ka-message ka-message--error" hidden></div>
    </section>

    <section class="panel" id="rpResult" hidden>
        <div class="msp-summary" id="rpSummary"></div>
        <div class="ka-table-wrap msp-scroll"><table class="ka-table rp-head-wrap" id="rpTable"></table></div>
    </section>

    <p class="page-footer">(c) 2026 PdB - Geeve Hydraulics - Versie <?= h(APP_VERSION) ?></p>
</main>
<script src="<?= h(geeveUrl('shared/vendor/xlsx-style.min.js')) ?>"></script>
<script src="<?= h(geeveUrl('shared/sp-report.js')) ?>?v=<?= h(assetVersion('../../shared/sp-report.js')) ?>"></script>
</body>
</html>
