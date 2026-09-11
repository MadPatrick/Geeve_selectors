<?php

declare(strict_types=1);

const APP_VERSION = '0.2.0';

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

// Elke slang heeft tot 2 "2-delige" (Huls/Pilaar) koppelingsslots. Deze
// dataset bevat alleen nog de Huls-kolommen (Pilaar en de 1-delige
// koppelingen zijn uit dit bestand verwijderd) - Huls is al een kant-en-klaar,
// bruikbaar artikelnummer (bijv. "100V4-20"), dezelfde lijst geldt voor beide
// uiteinden (de brondata kent geen kant-specifieke koppeling, alleen welke
// hulzen bij deze slang passen).
function collectCouplingCodes(array $row): array
{
    // Niet $codes[$code] = true / array_keys(): een puur-numerieke code zou
    // als array-key tot een integer worden omgezet, en json_encode zou die
    // dan als JSON-getal i.p.v. string wegschrijven - dat breekt de JS-kant,
    // die overal een string verwacht.
    $codes = [];

    for ($number = 1; $number <= 2; $number++) {
        $huls = getColumn($row, '2delig_' . $number . ' - Huls');
        if ($huls !== '') {
            $codes[] = $huls;
        }
    }

    return array_values(array_unique($codes));
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

        $rows[] = [
            'artnr'     => $articleNumber,
            'artnm'     => getColumn($row, 'artnm'),
            'werkdruk'  => getColumn($row, 'Werkdruk (bar)'),
            'couplings' => collectCouplingCodes($row),
        ];
    }

    fclose($handle);
    return $rows;
}

$csvFile = findFirstReadableFile($csvFileCandidates['artikelnummers']);
$hoses = loadHoseRows($csvFile, $loadErrors);

usort($hoses, static fn(array $a, array $b): int => strnatcasecmp($a['artnr'], $b['artnr']));

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$hoseCount = count($hoses);
$dataState = $loadErrors === [] ? 'ready' : 'error';
$dataLabel = $loadErrors === [] ? $hoseCount . ' slangtypes geladen' : 'Controleer databestand';
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
            <label class="field" for="koppeling1">
                <span>Koppeling 1</span>
                <select id="koppeling1" disabled></select>
            </label>

            <div class="config-middle">
                <label class="field" for="lengte">
                    <span>Lengte slang</span>
                    <div class="lengte-input">
                        <input id="lengte" type="number" inputmode="numeric" min="1" step="1" value="1000">
                        <span class="lengte-unit">mm</span>
                    </div>
                </label>
                <label class="field" for="slangtype">
                    <span>Type slang</span>
                    <select id="slangtype">
                        <option value="">Kies een artikelnummer&hellip;</option>
                        <?php foreach ($hoses as $hose): ?>
                            <option value="<?= h($hose['artnr']) ?>"><?= h($hose['artnr']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>

            <label class="field" for="koppeling2">
                <span>Koppeling 2</span>
                <select id="koppeling2" disabled></select>
            </label>
        </div>

        <label class="checkbox-field">
            <input type="checkbox" id="textsleeve">
            <span>Met textsleeve</span>
        </label>
        <small>Koppeling 1 en 2 tonen de koppelingen die bij het gekozen artikelnummer passen &mdash; kies eerst een slangtype.</small>
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
</script>
<script src="assets/selector.js?v=<?= h(APP_VERSION) ?>"></script>
</body>
</html>
