<?php

declare(strict_types=1);

require_once __DIR__ . '/inc/csv-paths.php';

$datasets = [
    'staal' => [
        'candidates' => $csvFileCandidates['staal'],
        'columns'    => 30,
        'label'      => 'Staal',
    ],
    'rvs' => [
        'candidates' => $csvFileCandidates['rvs'],
        'columns'    => 30,
        'label'      => 'RVS',
    ],
    'accessoires' => [
        'candidates' => $accessoryCsvCandidates,
        'columns'    => 13,
        'label'      => 'Accessoires',
    ],
];

function redirectWithMessage(string $status, string $message): void
{
    $query = http_build_query(['upload' => $status, 'msg' => $message]);
    header('Location: data.php?' . $query);
    exit;
}

// The dataset is not chosen explicitly - it is derived from the uploaded
// file's own name, so the file needs to keep containing "staal", "rvs" or
// "accessoires" (as in the downloaded artikelnummers_*.csv filenames).
function detectDatasetFromFilename(string $filename): ?string
{
    $name = strtolower($filename);

    if (str_contains($name, 'accessoire')) {
        return 'accessoires';
    }
    if (str_contains($name, 'staal')) {
        return 'staal';
    }
    if (str_contains($name, 'rvs')) {
        return 'rvs';
    }

    return null;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: data.php');
    exit;
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
        'Kan het databestand niet herkennen aan de bestandsnaam "%s". Zorg dat de naam "staal", "rvs" of "accessoires" bevat (bijv. artikelnummers_staal.csv).',
        $originalName
    ));
}

$handle = fopen($tmpPath, 'r');
if ($handle === false) {
    redirectWithMessage('error', 'Geüpload bestand kan niet worden gelezen.');
}
$headerLine = fgetcsv($handle, 0, ';');
fclose($handle);

if ($headerLine === false) {
    redirectWithMessage('error', 'Geüpload bestand bevat geen geldige kopregel.');
}

$expectedColumns = $datasets[$dataset]['columns'];
if (count($headerLine) !== $expectedColumns) {
    redirectWithMessage('error', sprintf(
        'Kopregel heeft %d kolom(men), verwacht %d voor %s. Bestand is niet opgeslagen.',
        count($headerLine),
        $expectedColumns,
        $datasets[$dataset]['label']
    ));
}

// Always write to the primary location (data/), regardless of which
// candidate the app is currently reading from.
$targetFile = $datasets[$dataset]['candidates'][0];
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

$backupDir = $targetDir . '/backups';
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
