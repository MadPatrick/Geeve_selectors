<?php

declare(strict_types=1);

const APP_VERSION = '0.0.17';

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

function readVariant(array $row, string $type, int $number): array
{
    if ($type === 'combo') {
        $prefix = '2delig_' . $number;

        return [
            'number'      => $number,
            'huls'        => getColumn($row, $prefix . ' - Huls'),
            'pilaar'      => getColumn($row, $prefix . ' - Pilaar'),
            'persmaat'    => getColumn($row, $prefix . ' - Persmaat (mm)'),
            'schilIntern' => getColumn($row, $prefix . ' - Schilmaat intern (mm)'),
            'schilExtern' => getColumn($row, $prefix . ' - Schilmaat extern (mm)'),
        ];
    }

    $prefix = '1delig_' . $number;

    return [
        'number'        => $number,
        'koppeling'     => getColumn($row, $prefix),
        'persmaat'      => getColumn($row, $prefix . ' - Persmaat (mm)'),
        'insteekdiepte' => getColumn($row, $prefix . ' - Insteekdiepte (mm)'),
        'schilIntern'   => getColumn($row, $prefix . ' - Schilmaat intern (mm)'),
        'schilExtern'   => getColumn($row, $prefix . ' - Schilmaat extern (mm)'),
    ];
}

function hasCombo(array $variant): bool
{
    return $variant['huls'] !== '' || $variant['pilaar'] !== '';
}

function hasCoupling(array $variant): bool
{
    return $variant['koppeling'] !== '';
}

function articleKey(string $articleNumber): string
{
    return '@' . strtolower(trim($articleNumber));
}


function loadAccessoryRows(?string $csvFile, array &$errors): array
{
    if ($csvFile === null) {
        $errors[] = 'Bestand artikelnummers_accessoires.csv is niet gevonden. Accessoires worden niet getoond.';
        return [];
    }

    $handle = fopen($csvFile, 'r');
    if ($handle === false) {
        $errors[] = 'CSV-bestand artikelnummers_accessoires.csv kan niet worden geopend.';
        return [];
    }

    $headers = fgetcsv($handle, 0, ';');
    if ($headers === false) {
        fclose($handle);
        $errors[] = 'CSV-bestand artikelnummers_accessoires.csv bevat geen geldige kopregel.';
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

        $articleNumber = getColumn($row, 'artnr');
        if ($articleNumber === '') {
            continue;
        }

        $rows[articleKey($articleNumber)] = [
            'outside'       => getColumn($row, 'Buitenmaat slang (mm)'),
            'polyGuard'     => getColumn($row, 'PolyGuard'),
            'parKoil'       => getColumn($row, 'ParKoil'),
            'springGuard'   => getColumn($row, 'Spring Guard'),
            'firesleeve'    => getColumn($row, 'Firesleeve'),
            'spiralGuard'   => getColumn($row, 'SpiralGuard'),
            'texsleeve'     => getColumn($row, 'Texsleeve'),
            'hulsTexStaal'  => getColumn($row, 'Huls tex staal'),
            'hulsTexRvs'    => getColumn($row, 'Huls tex RVS'),
        ];
    }

    fclose($handle);
    return $rows;
}

function loadMaterialRows(?string $csvFile, string $label, array &$errors): array
{
    $expectedName = $label === 'Staal' ? 'artikelnummers_staal.csv' : 'artikelnummers_rvs.csv';
    if ($csvFile === null) {
        $errors[] = "Bestand {$expectedName} voor {$label} is niet gevonden.";
        return [];
    }

    $basename = basename($csvFile);

    if (!is_readable($csvFile)) {
        $errors[] = "Bestand {$basename} voor {$label} kan niet worden gelezen.";
        return [];
    }

    $handle = fopen($csvFile, 'r');
    if ($handle === false) {
        $errors[] = "CSV-bestand {$basename} voor {$label} kan niet worden geopend.";
        return [];
    }

    $headers = fgetcsv($handle, 0, ';');
    if ($headers === false) {
        fclose($handle);
        $errors[] = "CSV-bestand {$basename} voor {$label} bevat geen geldige kopregel.";
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

        $articleNumber = getColumn($row, 'artnr');
        if ($articleNumber === '') {
            continue;
        }

        $combos = [];
        $couplings = [];

        for ($number = 1; $number <= 2; $number++) {
            $variant = readVariant($row, 'combo', $number);
            if (hasCombo($variant)) {
                $combos[] = $variant;
            }
        }

        for ($number = 1; $number <= 3; $number++) {
            $variant = readVariant($row, 'coupling', $number);
            if (hasCoupling($variant)) {
                $couplings[] = $variant;
            }
        }

        
        $rows[] = [
            'artnr'      => $articleNumber,
            'artnm'      => getColumn($row, 'artnm'),
            'vendor'     => getColumn($row, 'Leverancier'),
            'supplier'   => getColumn($row, 'Artikelnr leverancier'),
            'werkdruk'   => getColumn($row, 'Werkdruk (bar)'),
            'combo'      => $combos,
            'couplings'  => $couplings,
        ];
    }

    fclose($handle);
    return $rows;
}

$csvFiles = [
    'staal' => findFirstReadableFile($csvFileCandidates['staal']),
    'rvs'   => findFirstReadableFile($csvFileCandidates['rvs']),
];

$materialRows = [
    'staal' => loadMaterialRows($csvFiles['staal'], 'Staal', $loadErrors),
    'rvs'   => loadMaterialRows($csvFiles['rvs'], 'RVS', $loadErrors),
];

$accessoryCsvFile = findFirstReadableFile($accessoryCsvCandidates);
$accessoryRows = loadAccessoryRows($accessoryCsvFile, $loadErrors);

$merged = [];
$order = [];

foreach (['staal', 'rvs'] as $material) {
    foreach ($materialRows[$material] as $row) {
        $key = articleKey($row['artnr']);

        if (!isset($merged[$key])) {
            $merged[$key] = [
                'artnr'          => $row['artnr'],
                'artnm'          => $row['artnm'],
                'vendor'         => $row['vendor'],
                'supplier'       => $row['supplier'],
                'werkdruk'       => $row['werkdruk'],
                'comboStaal'     => [],
                'koppelingStaal' => [],
                'comboRvs'       => [],
                'koppelingRvs'   => [],
                'accessories'    => $accessoryRows[$key] ?? [],
            ];
            $order[] = $key;
        } else {
            foreach (['artnm', 'vendor', 'supplier', 'werkdruk'] as $field) {
                if ($merged[$key][$field] === '' && $row[$field] !== '') {
                    $merged[$key][$field] = $row[$field];
                }
            }
        }

        if ($material === 'staal') {
            $merged[$key]['comboStaal'] = $row['combo'];
            $merged[$key]['koppelingStaal'] = $row['couplings'];
        } else {
            $merged[$key]['comboRvs'] = $row['combo'];
            $merged[$key]['koppelingRvs'] = $row['couplings'];
        }


        if (isset($accessoryRows[$key])) {
            $merged[$key]['accessories'] = $accessoryRows[$key];
        }
    }
}

$articles = [];
foreach ($order as $key) {
    $articles[] = $merged[$key];
}

usort($articles, static fn(array $a, array $b): int => strnatcasecmp($a['artnr'], $b['artnr']));

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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
                <h1>Hose and fitting Selector</h1>
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
                <h2>Slangtype zoeken</h2>
            </div>
            <div class="section-actions">
                <div class="status-pill <?= h($dataState) ?>"><?= h($dataLabel) ?></div>
                <div class="pdf-dropdown">
                    <button type="button" id="pdfButton" class="pdf-button" aria-haspopup="true" aria-expanded="false">Download catalogus (PDF)</button>
                    <div class="pdf-menu" id="pdfMenu" hidden>
                        <button type="button" class="pdf-menu-item" data-scope="all">Complete catalogus</button>
                        <button type="button" class="pdf-menu-item" data-scope="accessoires">Accessoires</button>
                        <button type="button" class="pdf-menu-item" data-scope="staal">Staal</button>
                        <button type="button" class="pdf-menu-item" data-scope="rvs">RVS</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="search-row">
            <label class="field search-field" for="search">
                <span>Artikel zoeken</span>
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
        </div>
        <small>Zoek op artikelnummer, omschrijving, leverancier of leveranciersartikelnummer, of laat "Artikel zoeken" leeg en combineer de maat (de laatste cijfers van het artikelnummer) met de werkdruk (bar).</small>
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
<script src="assets/selector.js?v=<?= h(APP_VERSION) ?>"></script>
</body>
</html>
