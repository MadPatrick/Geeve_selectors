<?php

declare(strict_types=1);

require_once __DIR__ . '/inc/csv-paths.php';

$datasets = [
    'staal' => [
        'candidates' => $csvFileCandidates['staal'],
        'filename'   => 'artikelnummers_staal.csv',
    ],
    'rvs' => [
        'candidates' => $csvFileCandidates['rvs'],
        'filename'   => 'artikelnummers_rvs.csv',
    ],
    'accessoires' => [
        'candidates' => $accessoryCsvCandidates,
        'filename'   => 'artikelnummers_accessoires.csv',
    ],
];

$dataset = $_GET['dataset'] ?? '';

if (!isset($datasets[$dataset])) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Onbekend databestand.';
    exit;
}

$file = findFirstReadableFile($datasets[$dataset]['candidates']);
if ($file === null) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Bestand niet gevonden.';
    exit;
}

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $datasets[$dataset]['filename'] . '"');
header('Content-Length: ' . (string) filesize($file));
header('Cache-Control: no-store');
readfile($file);
