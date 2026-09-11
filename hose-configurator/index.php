<?php

declare(strict_types=1);

const APP_VERSION = '0.3.0';

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

// Zelfde conventie als in /hoses: de "maat" van een slang is de laatste
// cijferreeks in het eigen artikelnummer (bijv. "04" in "0201-04"). Dat is
// de dash-maat waarop de slang en een koppeling-artikelcode uit
// slangkoppelingen.csv straks tegen elkaar gematcht worden.
function hoseMaat(string $artnr): ?int
{
    if (!preg_match_all('/\d+/', $artnr, $matches)) {
        return null;
    }

    return (int) end($matches[0]);
}

// Een koppeling-artikelcode in slangkoppelingen.csv is opgebouwd als
// <familie>-<draadmaatcode>-<slangmaatcode>[variant-achtervoegsel], bijv.
// "10213-04-04VL". Het laatste dash-deel begint met de slangmaat (dezelfde
// dash-schaal als hierboven) en kan daarna een lettercode dragen
// (MS/NIS/OR/SWIVEL/VL/ZK/...) voor een productvariant - alleen de leidende
// cijfers tellen mee voor het matchen met een slang.
function couplingHoseMaat(string $itemCode): ?int
{
    $parts = explode('-', $itemCode);
    $last = end($parts);

    if (!preg_match('/^(\d+)/', $last, $matches)) {
        return null;
    }

    return (int) $matches[1];
}

// De bouwvorm van de koppeling komt uit de eigen "type"-kolom in
// slangkoppelingen.csv (buiten/flange/standpijp/wartel). Banjo-koppelingen
// staan daar bewust leeg in - die herken je al aan draadsoort "banjo".
// Alleen als een rij ooit zonder "type" én zonder banjo-draadsoort
// voorkomt, valt dit terug op de omschrijvingstekst als vangnet.
function couplingType(string $draadsoort, string $omschrijving, string $typeColumn): string
{
    if ($draadsoort === 'banjo') {
        return 'banjo';
    }
    if ($typeColumn !== '') {
        return $typeColumn;
    }

    if (stripos($omschrijving, 'banjo') !== false) {
        return 'banjo';
    }
    if (stripos($omschrijving, 'flange') !== false) {
        return 'flange';
    }
    if (stripos($omschrijving, 'standpipe') !== false) {
        return 'standpijp';
    }
    if (stripos($omschrijving, 'swivel') !== false) {
        return 'wartel';
    }

    return 'buiten';
}

function loadHoseRows(?string $csvFile, array &$errors): array
{
    if ($csvFile === null) {
        $errors[] = 'Bestand artikelnummers.csv is niet gevonden.';
        return [];
    }

    $handle = fopen($csvFile, 'r');
    if ($handle === false) {
        $errors[] = 'CSV-bestand artikelnummers.csv kan niet worden geopend.';
        return [];
    }

    $headers = fgetcsv($handle, 0, ';');
    if ($headers === false) {
        fclose($handle);
        $errors[] = 'CSV-bestand artikelnummers.csv bevat geen geldige kopregel.';
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

        // Een slang heeft twee uiteinden - 2delig_1 hoort bij koppeling 1,
        // 2delig_2 bij koppeling 2. Uitdrukkelijk los houden (i.p.v. één
        // samengevoegde lijst) zodat de juiste huls bij de juiste kant getoond
        // wordt in plaats van bij elkaar geveegd.
        $huls = [];
        foreach ([1, 2] as $number) {
            $hulsCode = getColumn($row, '2delig_' . $number . ' - Huls');
            $huls[$number] = $hulsCode !== '' ? [
                'code'     => $hulsCode,
                'persmaat' => getColumn($row, '2delig_' . $number . ' - Persmaat (mm)'),
            ] : null;
        }

        $rows[] = [
            'artnr'    => $articleNumber,
            'artnm'    => getColumn($row, 'artnm'),
            'werkdruk' => getColumn($row, 'Werkdruk (bar)'),
            'maat'     => hoseMaat($articleNumber),
            'huls1'    => $huls[1],
            'huls2'    => $huls[2],
        ];
    }

    fclose($handle);
    return $rows;
}

function loadCouplingRows(?string $csvFile, array &$errors): array
{
    if ($csvFile === null) {
        $errors[] = 'Bestand slangkoppelingen.csv is niet gevonden.';
        return [];
    }

    $handle = fopen($csvFile, 'r');
    if ($handle === false) {
        $errors[] = 'CSV-bestand slangkoppelingen.csv kan niet worden geopend.';
        return [];
    }

    $headers = fgetcsv($handle, 0, ';');
    if ($headers === false) {
        fclose($handle);
        $errors[] = 'CSV-bestand slangkoppelingen.csv bevat geen geldige kopregel.';
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

        $artikelnummer = getColumn($row, 'artikelnummer');
        $draadsoort = getColumn($row, 'draadsoort');
        $maat = getColumn($row, 'maat');
        if ($artikelnummer === '' || $draadsoort === '' || $maat === '') {
            continue;
        }

        $omschrijving = getColumn($row, '[Items.Description]');

        $rows[] = [
            'artikelnummer' => $artikelnummer,
            'omschrijving'  => $omschrijving,
            'draadsoort'    => $draadsoort,
            'maat'          => $maat,
            'stand'         => getColumn($row, 'stand'),
            'type'          => couplingType($draadsoort, $omschrijving, getColumn($row, 'type')),
            'hoseMaat'      => couplingHoseMaat($artikelnummer),
        ];
    }

    fclose($handle);
    return $rows;
}

$hoses = loadHoseRows(findFirstReadableFile($csvFileCandidates['artikelnummers']), $loadErrors);
$couplings = loadCouplingRows(findFirstReadableFile($csvFileCandidates['slangkoppelingen']), $loadErrors);

usort($hoses, static fn(array $a, array $b): int => strnatcasecmp($a['artnr'], $b['artnr']));

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// De "stand"-kolom in slangkoppelingen.csv is de hoek van de koppeling: 0
// (recht), 45 of 90 graden.
function standButtonsHtml(): string
{
    $buttons = [
        ['0', 'Recht', '<line x1="4" y1="12" x2="20" y2="12"></line>'],
        ['45', '45°', '<path d="M5 19 13 11 20 11"></path>'],
        ['90', 'Haaks', '<path d="M6 4v8a6 6 0 0 0 6 6h6"></path>'],
    ];

    // "Recht" staat standaard aan (zelfde default als selector.js), zodat de
    // eerste render (vóór het JS-init-script draait) al klopt.
    $html = '';
    foreach ($buttons as [$value, $label, $path]) {
        $isDefault = $value === '0';
        $class = $isDefault ? 'stand-icon active' : 'stand-icon';
        $html .= '<button type="button" class="' . $class . '" data-stand="' . h($value) . '" aria-pressed="' . ($isDefault ? 'true' : 'false') . '" title="' . h($label) . '">'
            . '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>'
            . '<span>' . h($label) . '</span>'
            . '</button>';
    }

    return $html;
}

// Bouwvorm van de koppeling (zie couplingType()).
function typeButtonsHtml(): string
{
    $buttons = [
        ['wartel', 'Wartel', '<circle cx="12" cy="12" r="3"></circle><path d="M12 3a9 9 0 1 1 -6.36 2.64"></path><path d="M3 3v5h5"></path>'],
        ['buiten', 'Buiten', '<line x1="12" y1="4" x2="12" y2="20"></line><line x1="8" y1="8" x2="16" y2="8"></line><line x1="8" y1="12" x2="16" y2="12"></line><line x1="8" y1="16" x2="16" y2="16"></line>'],
        ['standpijp', 'Standpijp', '<path d="M12 3v13"></path><path d="M7 20h10"></path><path d="M9 16l-2 4"></path><path d="M15 16l2 4"></path>'],
        ['banjo', 'Banjo', '<circle cx="12" cy="12" r="7"></circle><line x1="12" y1="2" x2="12" y2="22"></line>'],
        ['flange', 'Flens', '<circle cx="12" cy="12" r="8"></circle><circle cx="12" cy="12" r="2.5"></circle><circle cx="12" cy="5" r="1" fill="currentColor"></circle><circle cx="19" cy="12" r="1" fill="currentColor"></circle><circle cx="12" cy="19" r="1" fill="currentColor"></circle><circle cx="5" cy="12" r="1" fill="currentColor"></circle>'],
    ];

    $html = '';
    foreach ($buttons as [$value, $label, $path]) {
        $html .= '<button type="button" class="stand-icon" data-type="' . h($value) . '" aria-pressed="false" title="' . h($label) . '">'
            . '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>'
            . '<span>' . h($label) . '</span>'
            . '</button>';
    }

    return $html;
}

$hoseCount = count($hoses);
$couplingCount = count($couplings);
$dataState = $loadErrors === [] ? 'ready' : 'error';
$dataLabel = $loadErrors === []
    ? $hoseCount . ' slangtypes, ' . $couplingCount . ' koppelingen geladen'
    : 'Controleer databestanden';
?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Slang configurator | Geeve Hydraulics</title>
    <link rel="stylesheet" href="assets/style.css?v=<?= h(APP_VERSION) ?>">
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
                    <span class="version-inline">Versie <?= h(APP_VERSION) ?></span>
                </div>
                <h1>Slang configurator</h1>
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
                <h2>Stel je slang samen</h2>
            </div>
            <div class="section-actions">
                <div class="status-pill <?= h($dataState) ?>"><?= h($dataLabel) ?></div>
                <button type="button" id="resetButton" class="pdf-button">Filters wissen</button>
            </div>
        </div>

        <div class="config-grid">
            <div class="field gc-1 gr-1">
                <span>Type 1</span>
                <div id="type1" class="stand-icon-group" role="group" aria-label="Type koppeling 1">
                    <?= typeButtonsHtml() ?>
                </div>
            </div>
            <label class="field gc-1 gr-2" for="draadsoort1">
                <span>Draadsoort 1</span>
                <select id="draadsoort1"></select>
            </label>
            <label class="field gc-1 gr-3" for="koppeling1">
                <span>Koppeling 1 (maat)</span>
                <select id="koppeling1" disabled></select>
            </label>
            <div class="field gc-1 gr-4">
                <span>Stand 1</span>
                <div id="stand1" class="stand-icon-group" role="group" aria-label="Stand koppeling 1">
                    <?= standButtonsHtml() ?>
                </div>
            </div>

            <label class="field gc-2 gr-2" for="lengte">
                <span>Lengte slang</span>
                <div class="lengte-input">
                    <input id="lengte" type="number" inputmode="numeric" min="1" step="1" value="1000">
                    <span class="lengte-unit">mm</span>
                </div>
            </label>
            <label class="field gc-2 gr-3" for="slangtype">
                <span>Type slang</span>
                <select id="slangtype">
                    <option value="">Kies een artikelnummer&hellip;</option>
                </select>
            </label>

            <div class="field gc-3 gr-1">
                <span>Type 2</span>
                <div id="type2" class="stand-icon-group" role="group" aria-label="Type koppeling 2">
                    <?= typeButtonsHtml() ?>
                </div>
            </div>
            <label class="field gc-3 gr-2" for="draadsoort2">
                <span>Draadsoort 2</span>
                <select id="draadsoort2"></select>
            </label>
            <label class="field gc-3 gr-3" for="koppeling2">
                <span>Koppeling 2 (maat)</span>
                <select id="koppeling2" disabled></select>
            </label>
            <div class="field gc-3 gr-4">
                <span>Stand 2</span>
                <div id="stand2" class="stand-icon-group" role="group" aria-label="Stand koppeling 2">
                    <?= standButtonsHtml() ?>
                    </div>
                </div>
            </div>
        </div>

        <label class="checkbox-field">
            <input type="checkbox" id="textsleeve">
            <span>Met textsleeve</span>
        </label>
        <small>Kies eerst een type (wartel/buiten/standpijp/banjo/flens); draadsoort en koppeling 1/2 tonen dan
            de bijpassende opties. Het slangtype-overzicht toont vervolgens alleen nog de slangen waarvan de eigen
            maat bij de gekozen koppeling(en) past.</small>
    </section>

    <section class="panel visual-panel">
        <div id="hoseVisual" class="hose-visual" aria-hidden="true"></div>
        <dl id="configSummary" class="config-summary"></dl>
    </section>
</main>

<script>
window.APP_VERSION = <?= json_encode(APP_VERSION, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
window.HOSES = <?= json_encode(
    $hoses,
    JSON_UNESCAPED_UNICODE |
    JSON_UNESCAPED_SLASHES |
    JSON_HEX_TAG |
    JSON_HEX_AMP |
    JSON_HEX_APOS |
    JSON_HEX_QUOT
) ?>;
window.COUPLINGS = <?= json_encode(
    $couplings,
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
