<?php

declare(strict_types=1);

// Beveiligde tegel (Config > Tegels): zonder ontgrendelen terug naar het Stickers-scherm, waar de code wordt gevraagd.
require_once __DIR__ . '/../../shared/tiles.php';
tileRequirePage('stickers-slanglabels', '../index.php');

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
    <title>Slanglabels | Geeve Hydraulics</title>
    <link rel="icon" href="../../favicon.ico?v=<?= h(assetVersion('../../favicon.ico')) ?>" type="image/x-icon">
    <link rel="stylesheet" href="../../shared/style.css?v=<?= h(assetVersion('../../shared/style.css')) ?>">
    <!-- Knoppen, tabel, invoerveld en meldingen zijn dezelfde als in de rapporten. -->
    <link rel="stylesheet" href="../../klantartikel/assets/style.css?v=<?= h(assetVersion('../../klantartikel/assets/style.css')) ?>">
    <link rel="stylesheet" href="../../reports/msp/assets/style.css?v=<?= h(assetVersion('../../reports/msp/assets/style.css')) ?>">
    <link rel="stylesheet" href="../assets/slanglabels.css?v=<?= h(assetVersion('../assets/slanglabels.css')) ?>">
</head>
<body>
<main class="page-shell page-shell--wide">
    <?php
    $headerTitle = 'Slanglabels';
    $headerImagesPath = '../../images/';
    $headerHomeHref = '../index.php';
    $headerTopline = ''; // het versienummer staat onderaan de pagina, achter de copyright-regel
    require __DIR__ . '/../../shared/header.php';
    ?>

    <section class="panel sl-top">
        <div class="sl-form">
            <h2>Slanglabels</h2>
            <p class="ka-empty">Vul een ordernummer in en haal de slangen op. Klik een regel aan om de velden te vullen; je kunt ze daarna nog aanpassen. Vink regels aan om er meer tegelijk te printen (elk met het eigen aantal); zonder vinkjes wordt de aangeklikte regel geprint.</p>
            <div class="ka-actions" id="slActions" data-endpoint="api/data.php" data-csrf="<?= h($csrf) ?>">
                <label class="msp-field">
                    <span>Ordernummer</span>
                    <input type="text" id="slNumber" class="ka-input" inputmode="numeric" maxlength="18" autocomplete="off" autofocus>
                </label>
                <button type="button" id="slFetch" class="ka-button">Gegevens ophalen</button>
                <span id="slStatus" class="msp-status"></span>
            </div>
            <div id="slMessage" class="ka-message ka-message--error" hidden></div>

            <div class="sl-fields" id="slFields">
                <label class="msp-field"><span>Slangnummer</span><input type="text" class="ka-input" data-field="slangnummer" maxlength="40"></label>
                <label class="msp-field"><span>Uw referentie</span><input type="text" class="ka-input" data-field="referentie" maxlength="60"></label>
                <label class="msp-field"><span>Lengte</span><input type="text" class="ka-input" data-field="lengte" maxlength="20"></label>
                <label class="msp-field"><span>Datum</span><input type="text" class="ka-input" data-field="datum" maxlength="20"></label>
                <label class="msp-field sl-wide"><span>Klantnaam</span><input type="text" class="ka-input" data-field="klantnaam" maxlength="80"></label>
                <label class="msp-field"><span>Aantal</span><input type="number" class="ka-input" data-field="aantal" min="1" max="99"></label>
            </div>
        </div>

        <!-- Voorbeeld van het label met daaronder de printknop. -->
        <div class="sl-side">
            <div class="sl-preview" id="slSheet"></div>
            <div class="sl-print">
                <span id="slPrintInfo" class="msp-status"></span>
                <button type="button" id="slPrint" class="print-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9V2h12v7"></path><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg><span>Print</span></button>
            </div>
        </div>
    </section>

    <section class="panel" id="slResult" hidden>
        <div class="ka-table-wrap">
            <table class="ka-table" id="slTable">
                <thead>
                <tr>
                    <th><input type="checkbox" id="slAll" aria-label="Alle regels"></th>
                    <th>Slangnummer</th>
                    <th>Uw referentie</th>
                    <th>Datum</th>
                    <th>Klantnaam</th>
                    <th>Aantal</th>
                    <th>Lengte</th>
                    <th>Artnr KL</th>
                </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </section>

    <p class="page-footer">(c) 2026 PdB - Geeve Hydraulics - Versie <?= h(APP_VERSION) ?></p>
</main>
<!-- Alleen voor het printen: hier komen de labels (elk aantal keer) vlak voor window.print(). -->
<div id="slPrintSheet" class="sl-printsheet" aria-hidden="true"></div>
<script src="../assets/slanglabels.js?v=<?= h(assetVersion('../assets/slanglabels.js')) ?>"></script>
</body>
</html>
