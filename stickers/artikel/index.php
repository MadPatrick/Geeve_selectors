<?php

declare(strict_types=1);

// Beveiligde tegel (Config > Tegels): zonder ontgrendelen terug naar het Stickers-scherm, waar de code wordt gevraagd.
require_once __DIR__ . '/../../shared/tiles.php';
require_once __DIR__ . '/../../shared/paths.php';
require_once __DIR__ . '/../../shared/printen.php';
tileRequirePage('stickers-artikel', geeveAppUrl('stickers'));

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
    <title>Artikelnummer | Geeve Hydraulics</title>
    <!-- Knoppen, tabel, invoerveld en meldingen zijn dezelfde als in de rapporten. -->
    <?php
    $headCss = ['klantartikel/assets/style.css', 'reports/msp/assets/style.css', 'stickers/assets/art-sticker.css', 'stickers/assets/artikel-stickers.css'];   // vanaf de portal-root, in laadvolgorde
    require __DIR__ . '/../../shared/head.php';
    ?>
</head>
<body>
<main class="page-shell page-shell--wide">
    <?php
    $headerTitle = 'Artikelnummer';
    $headerImagesPath = geeveUrl('images/');
    $headerHomeHref = geeveAppUrl('stickers');
    $headerTopline = ''; // het versienummer staat onderaan de pagina, achter de copyright-regel
    require __DIR__ . '/../../shared/header.php';
    ?>

    <section class="panel sa-top">
        <div class="sa-form">
            <h2>Artikelnummer</h2>
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

        <!-- Voorbeeld van de aangeklikte regel. -->
        <div class="sa-side" id="saSide" hidden>
            <div class="sa-preview" id="saSheet"></div>
            <!-- Aantal, printerkeuze en Print onder het voorbeeld. -->
            <div class="sa-print">
                <span id="saPrintInfo" class="msp-status"></span>
                <label class="sa-copies"><input type="number" id="saCopies" class="ka-input" min="1" max="99" value="1"> x afdrukken</label>
                <div class="print-group">
                    <?= geevePrinterSelectHtml('stickers-artikel', $csrf) ?>
                    <button type="button" class="print-btn sa-btn" data-roll="geeve"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9V2h12v7"></path><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg><span>Print</span></button>
                </div>
            </div>
        </div>
    </section>

    <p class="page-footer">(c) 2026 PdB - Geeve Hydraulics - Versie <?= h(APP_VERSION) ?></p>
</main>
<!-- Alleen voor het printen: hier komen de stickers (elk aantal keer) vlak voor window.print(). -->
<div id="saPrintSheet" class="sa-printsheet" aria-hidden="true"></div>
<script src="<?= h(geeveUrl('stickers/assets/vendor/JsBarcode.code128.min.js')) ?>"></script>
<script src="<?= h(geeveUrl('stickers/assets/art-sticker.js')) ?>?v=<?= h(assetVersion('../assets/art-sticker.js')) ?>"></script>
<script src="<?= h(geeveUrl('stickers/assets/artikel-stickers.js')) ?>?v=<?= h(assetVersion('../assets/artikel-stickers.js')) ?>"></script>
<script src="<?= h(geeveUrl('stickers/assets/direct-print.js')) ?>?v=<?= h(assetVersion('../assets/direct-print.js')) ?>"></script>
</body>
</html>
