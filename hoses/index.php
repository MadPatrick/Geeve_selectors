<?php

declare(strict_types=1);

const APP_VERSION = '0.2.0';

require_once __DIR__ . '/inc/csv-paths.php';
require_once __DIR__ . '/inc/data-loader.php';

$loadErrors = [];

$csvFiles = [
    'staal' => findFirstReadableFile($csvFileCandidates['staal']),
    'rvs'   => findFirstReadableFile($csvFileCandidates['rvs']),
];

$accessoryCsvFile = findFirstReadableFile($accessoryCsvCandidates);

$articles = loadMergedArticles($csvFiles, $accessoryCsvFile, $loadErrors);

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Cache-busting op basis van de laatste wijzigingsdatum van het bestand
// zelf, zodat elke aanpassing aan style.css/selector.js automatisch een
// nieuwe URL krijgt - geen handmatige versie-ophoging meer nodig.
function assetVersion(string $relativePath): string
{
    $full = __DIR__ . '/' . $relativePath;
    $mtime = @filemtime($full);
    return $mtime !== false ? (string) $mtime : APP_VERSION;
}

$articleCount = count($articles);
$dataState = $loadErrors === [] ? 'ready' : 'error';
$dataLabel = $loadErrors === [] ? $articleCount . ' artikelen geladen' : 'Controleer databestanden';
?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Hose and fitting Selector | Geeve Hydraulics</title>
    <link rel="icon" href="../favicon.ico?v=<?= h(assetVersion('../favicon.ico')) ?>" type="image/x-icon">
    <link rel="stylesheet" href="assets/style.css?v=<?= h(assetVersion('assets/style.css')) ?>">
</head>
<body>
<main class="page-shell">
    <header class="page-header">
        <div class="brand-panel">
            <div class="brand-copy">
                <div class="brand-logo-row">
                    <img src="../images/geeve.jpg" alt="Geeve Hydraulics - know how in hydraulics" class="brand-logo-img">
                    <img src="../images/rubix.jpg" alt="Powered by Rubix" class="brand-rubix-img">
                </div>
            </div>
            <a href="../index.php" class="header-home-button" title="Terug naar hoofdmenu" aria-label="Terug naar hoofdmenu">
                <svg viewBox="0 0 24 24" width="36" height="36" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M3 11.5 12 4l9 7.5"></path>
                    <path d="M5.5 9.5V20a1 1 0 0 0 1 1H10v-5a2 2 0 1 1 4 0v5h3.5a1 1 0 0 0 1-1V9.5"></path>
                </svg>
            </a>
            <div class="header-content">
                <div class="header-topline">
                    <div class="topbar-status-pill <?= h($dataState) ?>"><?= h($dataLabel) ?></div>
                    <div class="pdf-dropdown">
                        <button type="button" id="pdfButton" class="topbar-pdf-button" aria-haspopup="true" aria-expanded="false">Perslijst (PDF)</button>
                        <div class="pdf-menu" id="pdfMenu" hidden>
                            <button type="button" class="pdf-menu-item" data-scope="all">Complete catalogus</button>
                            <button type="button" class="pdf-menu-item" data-scope="accessoires">Accessoires</button>
                            <button type="button" class="pdf-menu-item" data-scope="staal">Staal</button>
                            <button type="button" class="pdf-menu-item" data-scope="rvs">RVS</button>
                        </div>
                    </div>
                    <button type="button" id="editToggleButton" class="header-icon-button" title="Gegevens wijzigen" aria-label="Gegevens wijzigen" disabled>
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M12 20h9"></path>
                            <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"></path>
                        </svg>
                    </button>
                    <button type="button" id="saveEditButton" class="header-icon-button header-icon-button-save" title="Wijzigingen opslaan" aria-label="Wijzigingen opslaan" hidden>
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M20 6 9 17l-5-5"></path>
                        </svg>
                    </button>
                    <button type="button" id="cancelEditButton" class="header-icon-button header-icon-button-cancel" title="Wijzigingen annuleren" aria-label="Wijzigingen annuleren" hidden>
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M18 6 6 18"></path>
                            <path d="M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <h1>Hose and fitting Selector</h1>
            </div>
        </div>
    </header>

    <?php foreach ($loadErrors as $loadError): ?>
        <section class="warning-box"><?= h($loadError) ?></section>
    <?php endforeach; ?>

    <section class="panel search-panel">
        <div class="search-row">
            <label class="field search-field" for="search">
                <span>Slangtype zoeken</span>
                <div class="autocomplete">
                    <input
                        id="search"
                        type="text"
                        placeholder="Typ bijvoorbeeld 0462, GLOBALCORE of 462TC-08"
                        autocomplete="off"
                        autofocus
                        aria-autocomplete="list"
                        aria-controls="suggestions"
                    >
                    <div id="suggestions" class="suggestions" role="listbox" hidden></div>
                </div>
            </label>
            <label class="field search-field-maat" for="searchMaat">
                <span>Maat</span>
                <input
                    id="searchMaat"
                    type="text"
                    inputmode="numeric"
                    placeholder="bv. 12"
                    autocomplete="off"
                >
            </label>
            <label class="field search-field-werkdruk" for="searchWerkdruk">
                <span>Werkdruk</span>
                <input
                    id="searchWerkdruk"
                    type="text"
                    inputmode="numeric"
                    placeholder="bv. 225"
                    autocomplete="off"
                    list="werkdrukOptions"
                >
                <datalist id="werkdrukOptions"></datalist>
            </label>
            <button type="button" id="clearFilterButton" class="clear-filter-button">Wis filter</button>
        </div>
        <small>Zoek op artikelnummer, omschrijving, leverancier of leveranciersartikelnummer, of laat "Slangtype zoeken" leeg en combineer de maat (de laatste cijfers van het artikelnummer) met de werkdruk (bar).</small>
    </section>

    <section id="result" class="panel result-panel" aria-live="polite" hidden>
        <div class="section-heading">
            <div>
                <span class="step">Resultaat</span>
                <h2>Passende koppelingen</h2>
            </div>
        </div>

        <div class="article-facts">
            <div><span>Artikelnummer</span><strong id="resultArtnr">—</strong></div>
            <div class="article-description"><span>Omschrijving</span><strong id="resultArtnm">—</strong></div>
            <div><span>Leverancier</span><strong id="resultVendor">—</strong></div>
            <div><span>Art.nr. leverancier</span><strong id="resultSupplier">—</strong></div>
            <div><span>Werkdruk</span><strong id="resultWerkdruk">—</strong></div>
        </div>

        <div id="editStatus" class="edit-status" hidden></div>


        <div id="onePieceSection" class="result-section one-piece-section" hidden>
            <div class="subsection-heading">
                <div>
                    <span class="step">1-delig</span>
                    <h3>1-delige koppelingen</h3>
                </div>
                <div id="onePieceCount" class="result-count"></div>
            </div>
            <div id="onePieceGrid" class="material-grid"></div>
        </div>


        <div id="twoPieceSection" class="result-section" hidden>
            <div class="subsection-heading">
                <div>
                    <span class="step">2-delig</span>
                    <h3>2-delige koppelingen</h3>
                </div>
            </div>
            <div id="twoPieceGrid" class="material-grid"></div>
        </div>

        <div id="accessorySection" class="accessory-section" hidden>
            <div class="accessory-heading">
                <span class="step">Accessoires</span>
                <h3>Passende accessoires</h3>
            </div>
            <div id="accessoryGrid" class="accessory-facts"></div>
        </div>

        <div id="emptyResult" class="empty-result" hidden>
            Voor dit slangtype zijn nog geen passende koppelingen in de lijsten opgenomen.
        </div>
    </section>
</main>

<div id="printSheet" class="print-sheet" aria-hidden="true"></div>

<script>
window.APP_VERSION = <?= json_encode(APP_VERSION, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
window.ARTICLES = <?= json_encode(
    $articles,
    JSON_UNESCAPED_UNICODE |
    JSON_UNESCAPED_SLASHES |
    JSON_HEX_TAG |
    JSON_HEX_AMP |
    JSON_HEX_APOS |
    JSON_HEX_QUOT
) ?>;
</script>
<script src="assets/selector.js?v=<?= h(assetVersion('assets/selector.js')) ?>"></script>
</body>
</html>
