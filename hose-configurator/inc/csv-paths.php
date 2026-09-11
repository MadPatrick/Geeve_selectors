<?php

declare(strict_types=1);

// This app keeps its own, trimmed copy of the hose article list (Staal
// only - RVS and the 1-delig/Pilaar coupling columns are dropped), separate
// from the /hoses selector's data - edits made here don't affect /hoses,
// and vice versa.

$csvFileCandidates = [
    'artikelnummers' => [
        __DIR__ . '/../data/artikelnummers.csv',
        __DIR__ . '/../artikelnummers.csv',
    ],
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
