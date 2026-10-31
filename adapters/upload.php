<?php

declare(strict_types=1);

// Zelfde tegelvergrendeling als de selector (Config > Tegels): zonder ontgrendelen terug naar het hoofdscherm.
require_once __DIR__ . '/../shared/tiles.php';
tileRequirePage('adapters', '../index.php');

require_once __DIR__ . '/inc/csv-paths.php';

$datasets = [
    'adapters' => [
        'candidates' => $csvFileCandidates['adapters'],
        'columns'    => [15, 16],   // 15 = oud formaat (alles hoge druk), 16 = met kolom drukklasse
        'label'      => 'Adapters',
    ],
];

function redirectWithMessage(string $status, string $message): void
{
    $query = http_build_query(['upload' => $status, 'msg' => $message]);
    header('Location: data.php?' . $query);
    exit;
}

// The dataset is not chosen explicitly - it is derived from the uploaded
// file's own name, so the file needs to keep containing "adapter" (as in
// the downloaded artikelnummers_adapters.csv filename).
function detectDatasetFromFilename(string $filename): ?string
{
    $name = strtolower($filename);

    if (str_contains($name, 'adapter')) {
        return 'adapters';
    }

    return null;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: data.php');
    exit;
}

require_once __DIR__ . '/../shared/csrf.php';
if (!csrfValid($_POST['csrf'] ?? null)) {
    redirectWithMessage('error', 'Ongeldig verzoek (sessie verlopen). Laad de pagina opnieuw en probeer het nog eens.');
}

if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
    redirectWithMessage('error', 'Upload mislukt. Kies een geldig CSV-bestand.');
}

$tmpPath = $_FILES['csv_file']['tmp_name'];
$originalName = (string) $_FILES['csv_file']['name'];

if (!is_uploaded_file($tmpPath)) {
    redirectWithMessage('error', 'Ongeldige upload.');
}

if (strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) !== 'csv') {
    redirectWithMessage('error', 'Alleen .csv-bestanden zijn toegestaan.');
}

$dataset = detectDatasetFromFilename($originalName);
if ($dataset === null) {
    redirectWithMessage('error', sprintf(
        'Kan het databestand niet herkennen aan de bestandsnaam "%s". Zorg dat de naam "adapter" bevat (bijv. artikelnummers_adapters.csv).',
        $originalName
    ));
}

$handle = fopen($tmpPath, 'r');
if ($handle === false) {
    redirectWithMessage('error', 'Geüpload bestand kan niet worden gelezen.');
}
$firstLine = (string) fgets($handle);
fclose($handle);
// De app leest komma-gescheiden bestanden; een met Excel opgeslagen bestand is vaak puntkomma-gescheiden.
$delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
$headerLine = str_getcsv($firstLine, $delimiter, '"', '');

if ($headerLine === false) {
    redirectWithMessage('error', 'Geüpload bestand bevat geen geldige kopregel.');
}

$expectedColumns = $datasets[$dataset]['columns'];
if (!in_array(count($headerLine), $expectedColumns, true)) {
    redirectWithMessage('error', sprintf(
        'Kopregel heeft %d kolom(men), verwacht %s voor %s. Bestand is niet opgeslagen.',
        count($headerLine),
        implode(' of ', $expectedColumns),
        $datasets[$dataset]['label']
    ));
}

// Write to the file the app is currently reading from (central data/ or an
// old location), or to the central location if there is none yet - see
// geeveDataWriteTarget() in shared/paths.php.
$targetFile = geeveDataWriteTarget($datasets[$dataset]['candidates']);
$targetDir = dirname($targetFile);

if (!is_dir($targetDir) && !@mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
    error_log('CSV-upload: kon map niet aanmaken: ' . $targetDir);
    redirectWithMessage('error', 'Doelmap "data/" bestaat niet en kan niet worden aangemaakt door de webserver.');
}

if (!is_writable($targetDir)) {
    error_log('CSV-upload: map niet schrijfbaar: ' . $targetDir . ' (eigenaar/rechten: ' . decoct(fileperms($targetDir) & 0777) . ')');
    redirectWithMessage('error', 'De map "data/" is niet schrijfbaar voor de webserver. Vraag de hostingbeheerder om schrijfrechten te geven aan de webserver-gebruiker (bijv. chmod 775 data/, of chown naar de juiste gebruiker).');
}

if (is_file($targetFile) && !is_writable($targetFile)) {
    error_log('CSV-upload: bestand niet schrijfbaar: ' . $targetFile);
    redirectWithMessage('error', 'Het bestaande bestand is niet schrijfbaar voor de webserver. Controleer de bestandsrechten op de server.');
}

$backupDir = geeveBackupDir($targetFile);
if (!is_dir($backupDir)) {
    @mkdir($backupDir, 0775, true);
}

if (is_file($targetFile) && is_dir($backupDir) && is_writable($backupDir)) {
    $backupName = pathinfo($targetFile, PATHINFO_FILENAME) . '-' . date('Ymd-His') . '.csv';
    @copy($targetFile, $backupDir . '/' . $backupName);
}

if (!@move_uploaded_file($tmpPath, $targetFile)) {
    $lastError = error_get_last();
    error_log('CSV-upload mislukt voor dataset "' . $dataset . '" naar ' . $targetFile . ': ' . ($lastError['message'] ?? 'onbekende fout'));
    redirectWithMessage('error', 'Bestand kon niet worden opgeslagen op de server. Waarschijnlijk ontbreken schrijfrechten voor de webserver. Controleer de PHP-foutlog op de server voor de exacte reden.');
}

redirectWithMessage('ok', $datasets[$dataset]['label'] . '-bestand is bijgewerkt.');
