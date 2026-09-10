<?php

declare(strict_types=1);

// Shared by index.php, download.php and upload.php so they agree on where
// the adapters CSV lives and which fallback locations are accepted.

$csvFileCandidates = [
    'adapters' => [
        __DIR__ . '/../data/artikelnummers_adapters.csv',
        __DIR__ . '/../artikelnummers_adapters.csv',
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
