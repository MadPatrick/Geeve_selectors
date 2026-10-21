<?php

declare(strict_types=1);

// Shared by index.php, download.php and upload.php so they agree on where
// the adapters CSV lives. Central location first (data/adapters/, see
// shared/paths.php).

require_once __DIR__ . '/../../shared/paths.php';

$csvFileCandidates = [
    'adapters' => geeveDataCandidates('adapters', 'artikelnummers_adapters.csv'),
];
