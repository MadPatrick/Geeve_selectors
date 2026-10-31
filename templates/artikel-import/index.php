<?php

declare(strict_types=1);

require_once __DIR__ . '/../../shared/helpers.php';

// Beveiligde tegel (Config > Tegels): zonder ontgrendelen terug naar het overzicht, waar de code wordt gevraagd.
require_once __DIR__ . '/../../shared/tiles.php';
require_once __DIR__ . '/../../shared/paths.php';
tileRequirePage('artikel-import', geeveAppUrl('templates'));

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
    <title>Import Artikelgegevens | Geeve Hydraulics</title>
    <!-- Knoppen, tabel, meldingen en de sleepzone zijn dezelfde als in Klant Artikelnummers en Inkooporder Import. -->
    <?php
    $headCss = ['assets/style.css', 'klantartikel/assets/style.css', 'reports/msp/assets/style.css', 'templates/artikel-import/assets/style.css'];   // vanaf de portal-root, in laadvolgorde
    require __DIR__ . '/../../shared/head.php';
    ?>
</head>
<body>
<main class="page-shell page-shell--wide">
    <?php
    $headerTitle = 'Import Artikelgegevens';
    $headerImagesPath = geeveUrl('images/');
    $headerHomeHref = geeveAppUrl('templates'); // tegel staat onder Templates
    $headerTopline = ''; // het versienummer staat onderaan de pagina, achter de copyright-regel
    require __DIR__ . '/../../shared/header.php';
    ?>

    <section class="panel" id="aiApp" data-validate="api/validate.php" data-csrf="<?= h($csrf) ?>">
        <h2>Artikelgegevens importeren via Excel</h2>
        <p class="ka-empty">Download de lege template, vul hem in en importeer hem hier (kies het bestand of sleep het naar het vak).
            <br>Elk artikel wordt gecontroleerd met Exact: bestaat het, dan staan de huidige waarden uit Exact grijs in de lege velden en zijn gewijzigde velden geel. 
            <br>Een artikel dat nog niet bestaat wordt bij de import aangemaakt, dus dat moet voorkomen worden.
            <br>Alle velden zijn hier aan te passen; een leeg veld laat de waarde in Exact ongewijzigd. 
            <br>Is alles in orde, dan maak je het XML-bestand voor de import in Exact (<strong>Systeem\Importeren XML</strong>).</p>
        <div class="ka-actions">
            <button type="button" id="aiTemplate" class="ka-button ka-button--secondary">Download lege template</button>
            <button type="button" id="aiExport" class="ka-button" disabled>Exporteer XML</button>
            <div id="aiDrop" class="ka-drop" tabindex="0" role="button">
                <strong>Excel importeren</strong> - sleep een ingevulde template hierheen of <u>kies een bestand</u>
                <input type="file" id="aiFile" accept=".xlsx,.xls,.xlsm,.xltm,.xltx" hidden>
            </div>
        </div>
        <div id="aiMessage" class="ka-message ka-message--error" hidden></div>
        <div id="aiInfo" class="ka-message ka-message--ok" hidden></div>
        <div id="aiWarnings" class="ka-message ai-problems" hidden></div>
        <div id="aiProblems" class="ka-message ai-problems" hidden></div>
    </section>

    <section class="panel" id="aiResult">
        <div class="ai-summary-bar">
            <div class="msp-summary" id="aiSummary"></div>
            <button type="button" id="aiAdd" class="ka-button ka-button--secondary">Regel toevoegen</button>
        </div>
        <div class="ka-table-wrap msp-scroll">
            <table class="ka-table" id="aiTable">
                <colgroup><col style="width: 11%"><col style="width: 8%"><col style="width: 17%"><col style="width: 8%"><col style="width: 6%"><col style="width: 7%"><col style="width: 7%"><col style="width: 7%"><col style="width: 11%"><col style="width: 7%"><col style="width: 7%"><col style="width: 4%"></colgroup>
                <thead>
                    <tr>
                        <th>Artikelcode</th><th>In Exact</th><th>Omschrijving</th><th>Status</th><th>Gewicht</th><th>Verkoopprijs</th>
                        <th>Artikelgroep</th><th>Code leverancier</th><th>Artikelcode leverancier</th><th>Inkoopprijs leverancier</th>
                        <th>Bestelgrootte leverancier</th><th></th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </section>

    <p class="page-footer">(c) 2026 PdB - Geeve Hydraulics - Versie <?= h(APP_VERSION) ?></p>
</main>
<script src="<?= h(geeveUrl('klantartikel/assets/vendor/xlsx.core.min.js')) ?>?v=<?= h(assetVersion('../../klantartikel/assets/vendor/xlsx.core.min.js')) ?>"></script>
<script src="<?= h(geeveUrl('reports/assets/vendor/xlsx-lezen.js')) ?>?v=<?= h(assetVersion('../../reports/assets/vendor/xlsx-lezen.js')) ?>"></script>
<script src="<?= h(geeveUrl('reports/assets/vendor/xlsx-style.min.js')) ?>"></script>
<script src="assets/artikel.js?v=<?= h(assetVersion('assets/artikel.js')) ?>"></script>
</body>
</html>
