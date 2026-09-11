<?php

declare(strict_types=1);

// This app reuses the existing hoses article list instead of keeping its own
// copy, so there is a single source of truth - updates to
// /hoses/data/*.csv are picked up here automatically.

$csvFileCandidates = [
    'staal' => [
        __DIR__ . '/../../hoses/data/artikelnummers_staal.csv',
        __DIR__ . '/../../hoses/artikelnummers_staal.csv',
    ],
    'rvs' => [
        __DIR__ . '/../../hoses/data/artikelnummers_rvs.csv',
        __DIR__ . '/../../hoses/artikelnummers_rvs.csv',
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
