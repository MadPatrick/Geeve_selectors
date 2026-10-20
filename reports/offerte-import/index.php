<?php

declare(strict_types=1);

// Beveiligde tegel (Config > Tegels): zonder ontgrendelen terug naar het overzicht, waar de code wordt gevraagd.
require_once __DIR__ . '/../../shared/tiles.php';
require_once __DIR__ . '/../../shared/paths.php';
tileRequirePage('offerte-import', geeveAppUrl('templates'));

require_once __DIR__ . '/../../shared/csrf.php';

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
    <title>Offerte Import | Geeve Hydraulics</title>
    <link rel="icon" href="<?= h(geeveUrl('favicon.ico')) ?>?v=<?= h(assetVersion('../../favicon.ico')) ?>" type="image/x-icon">
    <link rel="stylesheet" href="<?= h(geeveUrl('shared/style.css')) ?>?v=<?= h(assetVersion('../../shared/style.css')) ?>">
    <link rel="stylesheet" href="<?= h(geeveUrl('assets/style.css')) ?>?v=<?= h(assetVersion('../../assets/style.css')) ?>">
    <!-- Knoppen, tabel, meldingen en de sleepzone zijn dezelfde als in Klant Artikelnummers. -->
    <link rel="stylesheet" href="<?= h(geeveUrl('klantartikel/assets/style.css')) ?>?v=<?= h(assetVersion('../../klantartikel/assets/style.css')) ?>">
    <link rel="stylesheet" href="<?= h(geeveUrl('reports/msp/assets/style.css')) ?>?v=<?= h(assetVersion('../msp/assets/style.css')) ?>">
    <link rel="stylesheet" href="assets/style.css?v=<?= h(assetVersion('assets/style.css')) ?>">
</head>
<body>
<main class="page-shell page-shell--wide">
    <?php
    $headerTitle = 'Offerte Import';
    $headerImagesPath = geeveUrl('images/');
    $headerHomeHref = geeveAppUrl('templates'); // tegel staat onder Templates
    $headerTopline = ''; // het versienummer staat onderaan de pagina, achter de copyright-regel
    require __DIR__ . '/../../shared/header.php';
    ?>

    <section class="panel" id="oiApp" data-validate="api/validate.php" data-kind="offerte" data-csrf="<?= h($csrf) ?>">
        <h2>Offerte importeren via Excel</h2>
        <p class="ka-empty">Download de lege template (Regelnr, Aantal, Artikelcode, Leveranciersnummer), vul hem in en importeer hem hier (kies het bestand of sleep het naar het vak). 
            <br>Verkoper, debiteurnummer, omschrijving en datum vul je hieronder in. 
            <br>Weet je alleen het artikelnummer van de leverancier, laat dan de artikelcode leeg en vul het leveranciersnummer in: ons artikelnummer wordt in Exact opgezocht (zonder match blijft de artikelcode leeg). 
            <br>De velden en artikelen worden gecontroleerd met Exact: de artikelomschrijving komt uit Exact, en artikelen die niet bestaan staan rood. 
            <br>
            <br>Is alles in orde, dan maak je het XML-bestand voor de import in Exact (<strong>Systeem\Importeren XML\Logistieke import</strong>).</p>
        <div class="ka-actions">
            <button type="button" id="oiTemplate" class="ka-button ka-button--secondary">Download lege template</button>
            <button type="button" id="oiExport" class="ka-button" disabled>Exporteer XML</button>
        </div>
        <div id="oiDrop" class="ka-drop" tabindex="0" role="button">
            <strong>Excel importeren</strong> - sleep een ingevulde template hierheen of <u>kies een bestand</u>
            <input type="file" id="oiFile" accept=".xlsx,.xls,.xlsm,.xltm" hidden>
        </div>
        <div id="oiMessage" class="ka-message ka-message--error" hidden></div>
        <div id="oiInfo" class="ka-message ka-message--ok" hidden></div>
        <div id="oiProblems" class="ka-message oi-problems" hidden></div>
    </section>

    <section class="panel">
        <h2>Offertegegevens</h2>
        <div class="oi-fields">
            <label class="msp-field">
                <span>Verkoper</span>
                <input type="text" id="oiSeller" class="ka-input" maxlength="60" autocomplete="off" placeholder="Nummer of naam">
                <small id="oiSellerCheck" class="oi-check"></small>
            </label>
            <label class="msp-field">
                <span>Debiteurnummer</span>
                <input type="text" id="oiDebtor" class="ka-input" maxlength="60" autocomplete="off" placeholder="Nummer of naam">
                <small id="oiDebtorCheck" class="oi-check"></small>
            </label>
            <label class="msp-field oi-wide">
                <span>Omschrijving</span>
                <input type="text" id="oiDescription" class="ka-input" maxlength="100" autocomplete="off">
                <small id="oiDescriptionCheck" class="oi-check"></small>
            </label>
            <label class="msp-field">
                <span>Datum</span>
                <input type="date" id="oiDate" class="ka-input">
                <small id="oiDateCheck" class="oi-check"></small>
            </label>
        </div>
    </section>

    <section class="panel" id="oiResult">
        <div class="oi-summary-bar">
            <div class="msp-summary" id="oiSummary"></div>
            <button type="button" id="oiAdd" class="ka-button ka-button--secondary">Regel toevoegen</button>
        </div>
        <div class="ka-table-wrap msp-scroll">
            <table class="ka-table" id="oiTable">
                <thead>
                    <tr>
                        <th>Regelnr</th><th>Aantal</th><th>Leveranciersnummer</th><th>Artikelcode</th><th>Artikelregelomschrijving</th>
                        <th>Artikel in Exact</th><th></th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </section>

    <p class="page-footer">(c) 2026 PdB - Geeve Hydraulics - Versie <?= h(APP_VERSION) ?></p>
</main>
<script src="<?= h(geeveUrl('reports/assets/vendor/xlsx-style.min.js')) ?>"></script>
<script src="assets/offerte.js?v=<?= h(assetVersion('assets/offerte.js')) ?>"></script>
</body>
</html>
