<?php

declare(strict_types=1);

// Shared by index.php, download.php and upload.php so the three agree on
// where each CSV lives and which fallback locations are accepted.

$csvFileCandidates = [
    'staal' => [
        __DIR__ . '/../data/artikelnummers_staal.csv',
        __DIR__ . '/../artikelnummers_staal.csv',
    ],
    'rvs' => [
        __DIR__ . '/../data/artikelnummers_rvs.csv',
        __DIR__ . '/../artikelnummers_rvs.csv',
    ],
];

$accessoryCsvCandidates = [
    __DIR__ . '/../data/artikelnummers_accessoires.csv',
    __DIR__ . '/../artikelnummers_accessoires.csv',
];

$rvsOmvlechtingCsvCandidates = [
    __DIR__ . '/../data/artikelnummers_rvs_omvlechting.csv',
    __DIR__ . '/../artikelnummers_rvs_omvlechting.csv',
];

function findFirstReadableFile(array $candidates): ?string
{
    foreach ($candidates as $candidate) {
        if (is_file($candidate) && is_readable($candidate)) {
            return $candidate;
        }
    }

    return null;
}
