<?php

declare(strict_types=1);

// Beveiligde tegel (Config > Tegels): zonder ontgrendelen terug naar het Stickers-scherm, waar de code wordt gevraagd.
require_once __DIR__ . '/../../shared/tiles.php';
tileRequirePage('stickers-artikel', '../index.php');

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
    <title>Stickers op Artikelnummer | Geeve Hydraulics</title>
    <link rel="icon" href="../../favicon.ico?v=<?= h(assetVersion('../../favicon.ico')) ?>" type="image/x-icon">
    <link rel="stylesheet" href="../../shared/style.css?v=<?= h(assetVersion('../../shared/style.css')) ?>">
    <!-- Knoppen, tabel, invoerveld en meldingen zijn dezelfde als in de rapporten. -->
    <link rel="stylesheet" href="../../klantartikel/assets/style.css?v=<?= h(assetVersion('../../klantartikel/assets/style.css')) ?>">
    <link rel="stylesheet" href="../../reports/msp/assets/style.css?v=<?= h(assetVersion('../../reports/msp/assets/style.css')) ?>">
    <link rel="stylesheet" href="../assets/artikel-stickers.css?v=<?= h(assetVersion('../assets/artikel-stickers.css')) ?>">
</head>
<body>
<main class="page-shell page-shell--wide">
    <?php
    $headerTitle = 'Stickers op Artikelnummer';
    $headerImagesPath = '../../images/';
    $headerHomeHref = '../index.php';
    $headerTopline = ''; // het versienummer staat onderaan de pagina, achter de copyright-regel
    require __DIR__ . '/../../shared/header.php';
    ?>

    <section class="panel sa-top">
        <div class="sa-form">
            <h2>Stickers op Artikelnummer</h2>
            <p class="ka-empty">Zoek op (een deel van) een artikelnummer. Klik een regel aan voor het voorbeeld; vink regels aan om er meer tegelijk te printen (zonder vinkjes wordt de aangeklikte regel geprint).</p>
            <div class="ka-actions" id="saActions" data-endpoint="api/data.php" data-csrf="<?= h($csrf) ?>">
                <label class="msp-field">
                    <span>Zoek op artikelnr</span>
                    <input type="text" id="saSearch" class="ka-input" maxlength="60" autocomplete="off" autofocus>
                </label>
                <span id="saStatus" class="msp-status"></span>
            </div>
            <div id="saMessage" class="ka-message ka-message--error" hidden></div>

            <div id="saResult" hidden>
                <div class="ka-table-wrap sa-table-wrap">
                    <table class="ka-table" id="saTable">
                        <thead>
                        <tr>
                            <th><input type="checkbox" id="saAll" aria-label="Alle regels"></th>
                            <th>Artikel</th>
                            <th>Omschrijving</th>
                        </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Voorbeeld van de aangeklikte regel, met daaronder aantal en de printknoppen. -->
        <div class="sa-side" id="saSide" hidden>
            <div class="sa-preview" id="saSheet"></div>
            <div class="sa-print">
                <label class="sa-copies"><input type="number" id="saCopies" class="ka-input" min="1" max="99" value="1"> x afdrukken</label>
                <div class="sa-buttons">
                    <button type="button" class="ka-button sa-btn sa-btn--geel" data-roll="geel">Print Geel</button>
                    <button type="button" class="ka-button sa-btn sa-btn--zilver" data-roll="zilver">Print Zilver</button>
                    <button type="button" class="ka-button sa-btn sa-btn--geeve" data-roll="geeve">Print Geeve</button>
                </div>
                <span id="saPrintInfo" class="msp-status"></span>
            </div>
        </div>
    </section>

    <p class="page-footer">(c) 2026 PdB - Geeve Hydraulics - Versie <?= h(APP_VERSION) ?></p>
</main>
<!-- Alleen voor het printen: hier komen de stickers (elk aantal keer) vlak voor window.print(). -->
<div id="saPrintSheet" class="sa-printsheet" aria-hidden="true"></div>
<script src="../assets/vendor/JsBarcode.code128.min.js"></script>
<script src="../assets/artikel-stickers.js?v=<?= h(assetVersion('../assets/artikel-stickers.js')) ?>"></script>
</body>
</html>
