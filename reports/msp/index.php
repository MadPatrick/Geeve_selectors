<?php

declare(strict_types=1);

require_once __DIR__ . '/../../shared/helpers.php';

// Beveiligde tegel (Config > Tegels): zonder ontgrendelen terug naar het overzicht, waar de code wordt gevraagd.
require_once __DIR__ . '/../../shared/tiles.php';
require_once __DIR__ . '/../../shared/paths.php';
tileRequirePage('msp', geeveAppUrl('reports'));

require_once __DIR__ . '/../../shared/csrf.php';

// Eén gedeeld versienummer voor hoofdscherm + alle subapps (version.php op
// rootniveau) - valt terug op deze waarde als dat bestand ontbreekt.
define('APP_VERSION', is_file(__DIR__ . '/../../version.php') ? (string) require __DIR__ . '/../../version.php' : '0.2.1');

$csrf = csrfToken();

// Standaard de vorige maand; te kiezen: de laatste 4 jaar tot en met de huidige maand.
$months = ['januari', 'februari', 'maart', 'april', 'mei', 'juni', 'juli', 'augustus', 'september', 'oktober', 'november', 'december'];
$previous = new DateTimeImmutable('first day of last month');
$defaultMonth = (int) $previous->format('n');
$defaultYear = (int) $previous->format('Y');
$thisYear = (int) date('Y');
?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>MSP report | Geeve Hydraulics</title>
    <!-- Knoppen, tabel en meldingen zijn dezelfde als in Klant Artikelnummers. -->
    <?php
    $headCss = ['assets/style.css', 'klantartikel/assets/style.css', 'reports/msp/assets/style.css'];   // vanaf de portal-root, in laadvolgorde
    require __DIR__ . '/../../shared/head.php';
    ?>
</head>
<body>
<main class="page-shell page-shell--wide">
    <?php
    $headerTitle = 'MSP report';
    $headerImagesPath = geeveUrl('images/');
    $headerHomeHref = geeveAppUrl('reports');
    $headerTopline = ''; // het versienummer staat onderaan de pagina, achter de copyright-regel
    require __DIR__ . '/../../shared/header.php';
    ?>

    <section class="panel">
        <h2>MSP claim - factuurregels</h2>
        <p class="ka-empty">Parker MSP claim report: de factuurregels van de gekozen maand uit Exact (dezelfde gegevens als het Excel-bestand "MSP CLAIM"). Kies de maand, haal de gegevens op en download ze daarna als Excel-bestand.</p>
        <div class="ka-actions" id="mspActions" data-endpoint="api/data.php" data-csrf="<?= h($csrf) ?>">
            <label class="msp-field">
                <span>Maand</span>
                <select id="mspMonth" class="ka-input">
                    <?php foreach ($months as $i => $name): ?>
                        <option value="<?= $i + 1 ?>"<?= $i + 1 === $defaultMonth ? ' selected' : '' ?>><?= h($name) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="msp-field">
                <span>Jaar</span>
                <select id="mspYear" class="ka-input">
                    <?php for ($y = $thisYear; $y >= $thisYear - 3; $y--): ?>
                        <option value="<?= $y ?>"<?= $y === $defaultYear ? ' selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </label>
            <button type="button" id="mspFetch" class="ka-button">Gegevens ophalen</button>
            <button type="button" id="mspDownload" class="ka-button ka-button--secondary" disabled>Download Excel</button>
            <span id="mspStatus" class="msp-status"></span>
        </div>
        <div id="mspMessage" class="ka-message ka-message--error" hidden></div>
    </section>

    <section class="panel" id="mspResult" hidden>
        <div class="msp-summary" id="mspSummary"></div>
        <div class="ka-table-wrap msp-scroll"><table class="ka-table" id="mspTable"></table></div>
    </section>

    <p class="page-footer">(c) 2026 PdB - Geeve Hydraulics - Versie <?= h(APP_VERSION) ?></p>
</main>
<script src="<?= h(geeveUrl('klantartikel/assets/vendor/xlsx.core.min.js')) ?>"></script>
<script src="assets/msp.js?v=<?= h(assetVersion('assets/msp.js')) ?>"></script>
</body>
</html>
