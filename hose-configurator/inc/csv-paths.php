<?php

declare(strict_types=1);

// This app keeps its own copy of the hose article list (copied from
// /hoses/data), separate from the /hoses selector's data - edits made here
// (e.g. via a future upload feature) don't affect /hoses, and vice versa.

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

function findFirstReadableFile(array $candidates): ?string
{
    foreach ($candidates as $candidate) {
        if (is_file($candidate) && is_readable($candidate)) {
            return $candidate;
        }
    }

    return null;
}
