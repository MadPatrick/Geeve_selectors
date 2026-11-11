<?php

declare(strict_types=1);

require_once __DIR__ . '/../../shared/helpers.php';

// Beveiligde tegel (Config > Tegels): zonder ontgrendelen terug naar het overzicht, waar de code wordt gevraagd.
require_once __DIR__ . '/../../shared/tiles.php';
require_once __DIR__ . '/../../shared/paths.php';
tileRequirePage('order-naar-xml', geeveAppUrl('reports'));

require_once __DIR__ . '/../../shared/csrf.php';
$csrf = csrfToken();

// Eén gedeeld versienummer voor hoofdscherm + alle subapps (version.php op
// rootniveau) - valt terug op deze waarde als dat bestand ontbreekt.
define('APP_VERSION', is_file(__DIR__ . '/../../version.php') ? (string) require __DIR__ . '/../../version.php' : '0.2.1');
?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Order naar XML | Geeve Hydraulics</title>
    <?php
    $headCss = ['shared/tabel.css', 'shared/rapport.css'];   // vanaf de portal-root, in laadvolgorde
    require __DIR__ . '/../../shared/head.php';
    ?>
</head>
<body>
<main class="page-shell page-shell--wide">
    <?php
    $headerTitle = 'Order naar XML';
    $headerImagesPath = geeveUrl('images/');
    $headerHomeHref = geeveAppUrl('reports');
    $headerTopline = ''; // het versienummer staat onderaan de pagina, achter de copyright-regel
    require __DIR__ . '/../../shared/header.php';
    ?>

    <section class="panel" id="oxApp" data-endpoint="api/data.php" data-xml="api/xml.php" data-csrf="<?= h($csrf) ?>">
        <h2>Order naar XML</h2>
        <p class="ka-empty">Vul het ordernummer van een verkooporder uit Exact in en klik op Gegevens ophalen. Controleer de orderkop en de regels en klik daarna op Export XML: het bestand kun je naar de klant sturen.</p>
        <div class="ka-actions">
            <label class="msp-field">
                <span>Ordernummer</span>
                <input type="text" id="oxNummer" class="ka-input" inputmode="numeric" maxlength="18" autocomplete="off" autofocus>
            </label>
            <button type="button" id="oxOphalen" class="ka-button">Gegevens ophalen</button>
            <button type="button" id="oxExport" class="ka-button ka-button--secondary" disabled>Export XML</button>
            <span id="oxStatus" class="msp-status"></span>
        </div>
        <div id="oxMessage" class="ka-message ka-message--error" hidden></div>
    </section>

    <section class="panel" id="oxResult" hidden>
        <div class="msp-summary" id="oxKop"></div>
        <div class="ka-table-wrap msp-scroll"><table class="ka-table" id="oxTable"></table></div>
    </section>

    <p class="page-footer">(c) 2026 PdB - Geeve Hydraulics - Versie <?= h(APP_VERSION) ?></p>
</main>
<script src="<?= h(geeveAssetHref('reports/ordernaarxml/assets/ordernaarxml.js')) ?>"></script>
</body>
</html>
