<?php

declare(strict_types=1);

const APP_VERSION = '0.1.0';

require_once __DIR__ . '/inc/csv-paths.php';

$loadErrors = [];

function cleanValue($value): string
{
    if ($value === null) {
        return '';
    }

    $value = str_replace(["\xC2\xA0", "_x000D_"], ' ', (string) $value);
    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

    return trim($value);
}

function normalizeHeader($value): string
{
    $value = preg_replace('/^\xEF\xBB\xBF/', '', (string) $value) ?? (string) $value;
    return strtolower(cleanValue($value));
}

function getColumn(array $row, string $columnName): string
{
    $target = normalizeHeader($columnName);

    foreach ($row as $column => $value) {
        if (normalizeHeader($column) === $target) {
            return cleanValue($value);
        }
    }

    return '';
}

function loadAdapterRows(?string $csvFile, array &$errors): array
{
    if ($csvFile === null) {
        $errors[] = 'Bestand artikelnummers_adapters.csv is niet gevonden.';
        return [];
    }

    $handle = fopen($csvFile, 'r');
    if ($handle === false) {
        $errors[] = 'CSV-bestand artikelnummers_adapters.csv kan niet worden geopend.';
        return [];
    }

    $headers = fgetcsv($handle, 0, ';');
    if ($headers === false) {
        fclose($handle);
        $errors[] = 'CSV-bestand artikelnummers_adapters.csv bevat geen geldige kopregel.';
        return [];
    }

    $headers = array_map('cleanValue', $headers);
    $rows = [];

    while (($data = fgetcsv($handle, 0, ';')) !== false) {
        if (count($data) !== count($headers)) {
            continue;
        }

        $row = array_combine($headers, $data);
        if ($row === false) {
            continue;
        }

        $artnr = getColumn($row, 'artnr');
        if ($artnr === '') {
            continue;
        }

        $rows[] = [
            'artnr'          => $artnr,
            'crossRef'       => getColumn($row, 'cross_ref'),
            'familieCode'    => getColumn($row, 'familie_code'),
            'familieNaam'    => getColumn($row, 'familie_naam'),
            'omschrijving'   => getColumn($row, 'omschrijving'),
            'hoek'           => getColumn($row, 'hoek'),
            'draadsoort1'    => getColumn($row, 'draadsoort_1'),
            'draadmaat1'     => getColumn($row, 'draadmaat_1'),
            'connectieType1' => getColumn($row, 'connectie_type_1'),
            'draadsoort2'    => getColumn($row, 'draadsoort_2'),
            'draadmaat2'     => getColumn($row, 'draadmaat_2'),
            'connectieType2' => getColumn($row, 'connectie_type_2'),
            'tubeOdMm'       => getColumn($row, 'tube_od_mm'),
            'tubeOdInch'     => getColumn($row, 'tube_od_inch'),
        ];
    }

    fclose($handle);
    return $rows;
}

$csvFile = findFirstReadableFile($csvFileCandidates['adapters']);
$articles = loadAdapterRows($csvFile, $loadErrors);

usort($articles, static fn(array $a, array $b): int => strnatcasecmp($a['artnr'], $b['artnr']));

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$articleCount = count($articles);
$dataState = $loadErrors === [] ? 'ready' : 'error';
$dataLabel = $loadErrors === [] ? $articleCount . ' adapters geladen' : 'Controleer databestand';
?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Adapters Selector | Geeve Hydraulics</title>
    <link rel="stylesheet" href="assets/style.css?v=<?= h(APP_VERSION) ?>">
</head>
<body>
<main class="page-shell">
    <header class="page-header">
        <div class="brand-panel">
            <div class="brand-copy">
                <div class="brand-logo-row">
                    <img src="images/geeve.jpg" alt="Geeve Hydraulics - know how in hydraulics" class="brand-logo-img">
                    <img src="images/rubix.jpg" alt="Powered by Rubix" class="brand-rubix-img">
                </div>
            </div>
            <div class="header-content">
                <div class="header-topline">
                    <span class="version-inline">Versie <?= h(APP_VERSION) ?></span>
                    <a href="data.php" class="header-icon-button" title="Data downloaden / uploaden" aria-label="Data downloaden / uploaden">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <ellipse cx="12" cy="5" rx="8" ry="3"></ellipse>
                            <path d="M4 5v6c0 1.66 3.58 3 8 3s8-1.34 8-3V5"></path>
                            <path d="M4 11v6c0 1.66 3.58 3 8 3s8-1.34 8-3v-6"></path>
                        </svg>
                    </a>
                </div>
                <h1>Adapters Selector</h1>
            </div>
        </div>
    </header>

    <?php foreach ($loadErrors as $loadError): ?>
        <section class="warning-box"><?= h($loadError) ?></section>
    <?php endforeach; ?>

    <section class="panel search-panel">
        <div class="section-heading">
            <div>
                <span class="step">Selectie</span>
                <h2>Adapter zoeken</h2>
            </div>
            <div class="section-actions">
                <div class="status-pill <?= h($dataState) ?>"><?= h($dataLabel) ?></div>
                <button type="button" id="resetButton" class="pdf-button">Filters wissen</button>
            </div>
        </div>

        <div class="filter-grid">
            <div class="filter-port">
                <div class="filter-port-heading">Aansluiting 1</div>
                <label class="field" for="draadsoort1">
                    <span>Draadsoort</span>
                    <select id="draadsoort1"></select>
                </label>
                <label class="field" for="draadmaat1">
                    <span>Draadmaat</span>
                    <select id="draadmaat1"></select>
                </label>
                <label class="field" for="connectie1">
                    <span>Connectie type</span>
                    <select id="connectie1"></select>
                </label>
            </div>

            <div class="filter-port">
                <div class="filter-port-heading">Aansluiting 2</div>
                <label class="field" for="draadsoort2">
                    <span>Draadsoort</span>
                    <select id="draadsoort2"></select>
                </label>
                <label class="field" for="draadmaat2">
                    <span>Draadmaat</span>
                    <select id="draadmaat2"></select>
                </label>
                <label class="field" for="connectie2">
                    <span>Connectie type</span>
                    <select id="connectie2"></select>
                </label>
            </div>

            <div class="filter-port filter-port-hoek">
                <div class="filter-port-heading">Vorm</div>
                <label class="field" for="hoek">
                    <span>Hoek</span>
                    <select id="hoek"></select>
                </label>
            </div>
        </div>
        <small>Aansluiting 1 en 2 zijn verwisselbaar &mdash; de volgorde waarin je ze invult maakt niet uit. Laat een veld op "Alle" staan om niet op dat kenmerk te filteren.</small>
    </section>

    <section id="result" class="panel result-panel" aria-live="polite">
        <div class="section-heading">
            <div>
                <span class="step">Resultaat</span>
                <h2>Passende adapters</h2>
            </div>
            <div id="resultCount" class="result-count"></div>
        </div>

        <div id="resultTableWrap" class="result-table-wrap">
            <table class="result-table">
                <thead>
                    <tr>
                        <th>Artikelcode</th>
                        <th>Kruisverwijzing</th>
                        <th>Familie</th>
                        <th>Hoek</th>
                        <th>Aansluiting 1</th>
                        <th>Aansluiting 2</th>
                    </tr>
                </thead>
                <tbody id="resultTableBody"></tbody>
            </table>
        </div>

        <div id="resultMoreNote" class="result-more-note" hidden></div>

        <div id="emptyResult" class="empty-result" hidden>
            Geen adapters gevonden voor deze combinatie van kenmerken.
        </div>
    </section>
</main>

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
<script src="assets/selector.js?v=<?= h(APP_VERSION) ?>"></script>
</body>
</html>
