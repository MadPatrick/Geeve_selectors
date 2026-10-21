<?php

declare(strict_types=1);

// The hose list comes from the central hose data of the Slangen fitting
// Selector (data/hoses/artikelnummers_staal.csv, see shared/paths.php); this
// app reads its columns by name, which are a subset of that file's. Its own
// trimmed copy (data/configurator/artikelnummers.csv) is only the fallback. slangkoppelingen.csv is this app's own file.

require_once __DIR__ . '/../../shared/paths.php';

$csvFileCandidates = [
    'artikelnummers' => array_merge(
        [geeveDataDir() . '/hoses/artikelnummers_staal.csv'],
        geeveDataCandidates('configurator', 'artikelnummers.csv')
    ),
    'slangkoppelingen' => geeveDataCandidates('configurator', 'slangkoppelingen.csv'),
];
