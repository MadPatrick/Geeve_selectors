<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

$csvFile = dirname(__DIR__) . '/data/stauff_selector.csv';
if (!is_file($csvFile)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'CSV-bestand niet gevonden.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$handle = fopen($csvFile, 'rb');
if ($handle === false) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'CSV-bestand kan niet worden geopend.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$header = fgetcsv($handle, 0, ';');
if ($header === false) {
    fclose($handle);
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'CSV-bestand heeft geen kopregel.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Remove UTF-8 BOM from first header if present.
$header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string)$header[0]);
$rows = [];
while (($data = fgetcsv($handle, 0, ';')) !== false) {
    if (count($data) === 1 && trim((string)$data[0]) === '') {
        continue;
    }
    $data = array_pad($data, count($header), '');
    $rows[] = array_combine($header, array_slice($data, 0, count($header)));
}
fclose($handle);

echo json_encode([
    'ok' => true,
    'count' => count($rows),
    'rows' => $rows,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
