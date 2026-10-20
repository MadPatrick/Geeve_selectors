<?php

declare(strict_types=1);

// Shared by index.php, download.php, upload.php and save.php so they agree on
// where each CSV lives. Central location first (data/hoses/, see
// shared/paths.php).

require_once __DIR__ . '/../../shared/paths.php';

$csvFileCandidates = [
    'staal' => geeveDataCandidates('hoses', 'artikelnummers_staal.csv'),
    'rvs' => geeveDataCandidates('hoses', 'artikelnummers_rvs.csv'),
];

$accessoryCsvCandidates = geeveDataCandidates('hoses', 'artikelnummers_accessoires.csv');
