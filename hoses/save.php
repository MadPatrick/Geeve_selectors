<?php

declare(strict_types=1);

// Endpoint voor de "wijzigen"-modus in index.php: slaat de bewerkte
// koppeling-/accessoirevelden van precies één artikel op in de bron-CSV's.
// Zelfde toegangsmodel als de rest van de app (IP-restrictie via .htaccess,
// geen aparte login) en hetzelfde back-up-patroon als upload.php (kopie
// naar data/backups/ vóór elke vervanging).

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/inc/csv-paths.php';
require_once __DIR__ . '/inc/data-loader.php';

function respond(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(['ok' => false, 'error' => 'Alleen POST toegestaan.'], 405);
}

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (!str_contains($contentType, 'application/json')) {
    respond(['ok' => false, 'error' => 'Content-Type moet application/json zijn.'], 400);
}

$raw = file_get_contents('php://input');
$payload = json_decode((string) $raw, true);
if (!is_array($payload)) {
    respond(['ok' => false, 'error' => 'Ongeldige of ontbrekende JSON-body.'], 400);
}

$artnr = isset($payload['artnr']) ? trim((string) $payload['artnr']) : '';
if ($artnr === '') {
    respond(['ok' => false, 'error' => 'Artikelnummer ontbreekt.'], 400);
}

$context = is_array($payload['context'] ?? null) ? $payload['context'] : [];
$contextArtnm = cleanValue($context['artnm'] ?? '');
$contextVendor = cleanValue($context['vendor'] ?? '');
$contextSupplier = cleanValue($context['supplier'] ?? '');
$contextWerkdruk = cleanValue($context['werkdruk'] ?? '');

// Veldsleutels (zie comboColumns()/couplingColumns()/accessoryColumnMap())
// die een getal moeten bevatten. Zelfde definitie als NUMERIC_FIELDS in
// assets/selector.js - hier nogmaals gecontroleerd, want de client-check is
// alleen voor directe feedback en mag nooit de enige verdediging zijn op
// een endpoint dat naar schijf schrijft.
const NUMERIC_FIELD_KEYS = ['pilaar', 'persmaat', 'schilIntern', 'schilExtern', 'insteekdiepte', 'outside'];

// Waarden mogen niet met een formuleteken beginnen (voorkomt CSV/formule-
// injectie mocht het bestand later in Excel/Sheets geopend worden), en
// numerieke velden moeten daadwerkelijk een getal zijn (komma of punt als
// decimaalteken) - leeg blijft altijd toegestaan (veld leegmaken).
function sanitizeFieldValue($value, string $fieldKey = ''): string
{
    $text = cleanValue($value);
    if ($text === '') {
        return '';
    }
    if (in_array($text[0], ['=', '+', '@'], true)) {
        throw new InvalidArgumentException("Een waarde mag niet beginnen met '{$text[0]}': \"{$text}\".");
    }
    if (in_array($fieldKey, NUMERIC_FIELD_KEYS, true) && preg_match('/^\d+([.,]\d+)?$/', $text) !== 1) {
        throw new InvalidArgumentException("Ongeldig getal voor '{$fieldKey}': \"{$text}\" (verwacht bijvoorbeeld 54 of 54,3).");
    }
    return $text;
}

// PHP's eigen fputcsv() plaatst (anders dan Python's csv-module met
// QUOTE_MINIMAL, waarmee deze CSV's altijd geschreven zijn) ook aanhalings-
// tekens om elk veld met een spatie erin - dat zou bij elke opslag de hele
// tot dan toe ongemoeide rest van het bestand op onopvallende wijze anders
// laten quoten dan het origineel. Deze twee functies schrijven regels op
// exact dezelfde manier als Python's csv.excel-dialect (LFDialect/
// CRLFDialect elders in dit project): quote alleen als het veld de
// scheidingsteken, aanhalingsteken, CR of LF bevat, aanhalingstekens erin
// verdubbelen.
function encodeCsvField(string $value): string
{
    if (preg_match('/[,"\r\n]/', $value) === 1) {
        return '"' . str_replace('"', '""', $value) . '"';
    }
    return $value;
}

function writeCsvLine(array $fields, string $eol): string
{
    return implode(',', array_map('encodeCsvField', $fields)) . $eol;
}

function buildMaterialColumnUpdates(array $materialPayload): array
{
    $updates = [];

    $combo = is_array($materialPayload['combo'] ?? null) ? $materialPayload['combo'] : [];
    for ($number = 1; $number <= 2; $number++) {
        $fields = $combo[(string) $number] ?? $combo[$number] ?? null;
        if (!is_array($fields)) {
            continue;
        }
        foreach (comboColumns($number) as $fieldKey => $columnName) {
            if (array_key_exists($fieldKey, $fields)) {
                $updates[$columnName] = sanitizeFieldValue($fields[$fieldKey], $fieldKey);
            }
        }
    }

    $couplings = is_array($materialPayload['couplings'] ?? null) ? $materialPayload['couplings'] : [];
    for ($number = 1; $number <= 3; $number++) {
        $fields = $couplings[(string) $number] ?? $couplings[$number] ?? null;
        if (!is_array($fields)) {
            continue;
        }
        foreach (couplingColumns($number) as $fieldKey => $columnName) {
            if (array_key_exists($fieldKey, $fields)) {
                $updates[$columnName] = sanitizeFieldValue($fields[$fieldKey], $fieldKey);
            }
        }
    }

    return $updates;
}

function buildAccessoryColumnUpdates(array $accessoryPayload): array
{
    $updates = [];
    foreach (accessoryColumnMap() as $fieldKey => $columnName) {
        if (array_key_exists($fieldKey, $accessoryPayload)) {
            $updates[$columnName] = sanitizeFieldValue($accessoryPayload[$fieldKey], $fieldKey);
        }
    }
    return $updates;
}

// Leest, past aan (of maakt aan als de rij nog niet bestaat) en schrijft één
// rij terug. Gebruikt een los .lock-bestand (niet het CSV-bestand zelf) om
// gelijktijdige opslag-verzoeken serieel te laten verlopen zonder het race-
// probleem dat ontstaat wanneer je flock() combineert met atomic rename op
// hetzelfde pad (een tweede, wachtende aanvraag zou anders na het vrijgeven
// van de lock nog de oude inode inlezen).
function updateCsvRow(string $path, string $eol, string $artnr, array $columnUpdates, array $contextColumns): void
{
    $dir = dirname($path);
    if (!is_writable($dir)) {
        error_log('save.php: map niet schrijfbaar: ' . $dir . ' (rechten: ' . decoct(fileperms($dir) & 0777) . ')');
        throw new RuntimeException(
            'De map "' . basename($dir) . '/" is niet schrijfbaar voor de webserver, dus wijzigingen kunnen niet '
            . 'worden opgeslagen. Vraag de hostingbeheerder om schrijfrechten te geven aan de webserver-gebruiker '
            . '(bijv. chmod 775 data/, of chown naar de juiste gebruiker) - zelfde vereiste als voor de '
            . 'upload-knop op de data-pagina.'
        );
    }

    $lockPath = $path . '.lock';
    $lockHandle = fopen($lockPath, 'c');
    if ($lockHandle === false) {
        $lastError = error_get_last();
        error_log('save.php: kan lock-bestand niet aanmaken: ' . $lockPath . ': ' . ($lastError['message'] ?? 'onbekende fout'));
        throw new RuntimeException(
            'Kan lock-bestand niet aanmaken voor ' . basename($path) . '. Controleer de schrijfrechten op de map '
            . '"' . basename($dir) . '/" voor de webserver-gebruiker.'
        );
    }

    if (!flock($lockHandle, LOCK_EX)) {
        fclose($lockHandle);
        throw new RuntimeException('Kan geen lock krijgen op ' . basename($path) . '.');
    }

    try {
        if (!is_file($path)) {
            throw new RuntimeException(basename($path) . ' bestaat niet.');
        }

        $in = fopen($path, 'r');
        if ($in === false) {
            throw new RuntimeException('Kan ' . basename($path) . ' niet lezen.');
        }

        $headerRaw = fgetcsv($in, 0, ',');
        if ($headerRaw === false) {
            fclose($in);
            throw new RuntimeException(basename($path) . ' bevat geen geldige kopregel.');
        }
        // BOM zit als literaire bytes vooraan de eerste kolomnaam; die halen
        // we er hier uit en schrijven we bij het wegschrijven weer apart
        // terug (zelfde aanpak als Python's encoding='utf-8-sig' elders in
        // dit project).
        $headerRaw[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headerRaw[0]) ?? $headerRaw[0];
        $headers = array_map('cleanValue', $headerRaw);
        $normalizedHeaders = array_map('normalizeHeader', $headers);

        $artnrIndex = array_search('artnr', $normalizedHeaders, true);
        if ($artnrIndex === false) {
            fclose($in);
            throw new RuntimeException(basename($path) . ' heeft geen artnr-kolom.');
        }

        $rows = [];
        $foundIndex = null;
        while (($data = fgetcsv($in, 0, ',')) !== false) {
            if (count($data) !== count($headers)) {
                continue;
            }
            if ($foundIndex === null && normalizeHeader((string) $data[$artnrIndex]) === normalizeHeader($artnr)) {
                $foundIndex = count($rows);
            }
            $rows[] = $data;
        }
        fclose($in);

        $hasRealUpdate = false;
        foreach ($columnUpdates as $value) {
            if ($value !== '') {
                $hasRealUpdate = true;
                break;
            }
        }

        if ($foundIndex === null) {
            if (!$hasRealUpdate) {
                // Niets in te vullen en de rij bestaat nog niet -> geen lege
                // rij aanmaken.
                return;
            }

            $newRow = array_fill(0, count($headers), '');
            $newRow[$artnrIndex] = $artnr;
            foreach ($contextColumns as $columnName => $value) {
                if ($value === '') {
                    continue;
                }
                $idx = array_search(normalizeHeader($columnName), $normalizedHeaders, true);
                if ($idx !== false) {
                    $newRow[$idx] = $value;
                }
            }
            $rows[] = $newRow;
            $foundIndex = count($rows) - 1;
        }

        foreach ($columnUpdates as $columnName => $value) {
            $idx = array_search(normalizeHeader($columnName), $normalizedHeaders, true);
            if ($idx === false) {
                throw new RuntimeException("Onbekende kolom '{$columnName}' in " . basename($path) . '.');
            }
            $rows[$foundIndex][$idx] = $value;
        }

        $backupDir = dirname($path) . '/backups';
        if (!is_dir($backupDir)) {
            @mkdir($backupDir, 0775, true);
        }
        if (is_dir($backupDir) && is_writable($backupDir)) {
            $backupName = pathinfo($path, PATHINFO_FILENAME) . '-' . date('Ymd-His') . '.csv';
            @copy($path, $backupDir . '/' . $backupName);
        }

        $tmpPath = $path . '.tmp-' . bin2hex(random_bytes(4));
        $out = fopen($tmpPath, 'w');
        if ($out === false) {
            throw new RuntimeException('Kan tijdelijk bestand niet aanmaken voor ' . basename($path) . '.');
        }
        fwrite($out, "\xEF\xBB\xBF");
        fwrite($out, writeCsvLine($headers, $eol));
        foreach ($rows as $row) {
            fwrite($out, writeCsvLine($row, $eol));
        }
        fclose($out);

        if (!rename($tmpPath, $path)) {
            @unlink($tmpPath);
            throw new RuntimeException('Kan ' . basename($path) . ' niet bijwerken (rename mislukt).');
        }
    } finally {
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
    }
}

$savedParts = [];

try {
    if (isset($payload['staal']) && is_array($payload['staal'])) {
        $path = findFirstReadableFile($csvFileCandidates['staal']);
        if ($path === null) {
            throw new RuntimeException('artikelnummers_staal.csv niet gevonden.');
        }
        updateCsvRow($path, "\n", $artnr, buildMaterialColumnUpdates($payload['staal']), [
            'artnm'                 => $contextArtnm,
            'Leverancier'           => $contextVendor,
            'Artikelnr leverancier' => $contextSupplier,
            'Werkdruk (bar)'        => $contextWerkdruk,
        ]);
        $savedParts[] = 'staal';
    }

    if (isset($payload['rvs']) && is_array($payload['rvs'])) {
        $path = findFirstReadableFile($csvFileCandidates['rvs']);
        if ($path === null) {
            throw new RuntimeException('artikelnummers_rvs.csv niet gevonden.');
        }
        updateCsvRow($path, "\n", $artnr, buildMaterialColumnUpdates($payload['rvs']), [
            'artnm'                 => $contextArtnm,
            'Leverancier'           => $contextVendor,
            'Artikelnr leverancier' => $contextSupplier,
            'Werkdruk (bar)'        => $contextWerkdruk,
        ]);
        $savedParts[] = 'rvs';
    }

    if (isset($payload['accessoires']) && is_array($payload['accessoires'])) {
        $path = findFirstReadableFile($accessoryCsvCandidates);
        if ($path === null) {
            throw new RuntimeException('artikelnummers_accessoires.csv niet gevonden.');
        }
        updateCsvRow($path, "\r\n", $artnr, buildAccessoryColumnUpdates($payload['accessoires']), [
            'artnm'                 => $contextArtnm,
            'Leverancier'           => $contextVendor,
            'Artikelnr leverancier' => $contextSupplier,
        ]);
        $savedParts[] = 'accessoires';
    }
} catch (Throwable $e) {
    respond(['ok' => false, 'error' => $e->getMessage(), 'savedParts' => $savedParts], 400);
}

$freshErrors = [];
$freshCsvFiles = [
    'staal' => findFirstReadableFile($csvFileCandidates['staal']),
    'rvs'   => findFirstReadableFile($csvFileCandidates['rvs']),
];
$freshAccessoryCsvFile = findFirstReadableFile($accessoryCsvCandidates);
$freshArticles = loadMergedArticles($freshCsvFiles, $freshAccessoryCsvFile, $freshErrors);

$updatedArticle = null;
foreach ($freshArticles as $article) {
    if (articleKey($article['artnr']) === articleKey($artnr)) {
        $updatedArticle = $article;
        break;
    }
}

if ($updatedArticle === null) {
    respond(['ok' => false, 'error' => 'Opslaan gelukt, maar artikel kon niet opnieuw worden ingelezen.', 'savedParts' => $savedParts], 500);
}

respond(['ok' => true, 'article' => $updatedArticle]);
