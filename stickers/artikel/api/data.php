<?php

declare(strict_types=1);

// Beveiligde tegel (Config > Tegels): zonder ontgrendelen geen gegevens.
require_once __DIR__ . '/../../../shared/tiles.php';
tileApiGuard('stickers-artikel');

/**
 * Stickers op Artikelnummer: artikelen uit [Exactrapportage].[dbo].[1002_Artikellabels] waarvan het artikelnummer de zoekterm bevat
 * (dezelfde query als de oude rapportbouwer, met % aan beide kanten), gesorteerd op artikel. Zelfde SQL Server en
 * inloggegevens (via Config) als /stauff en de rapporten.
 */

require_once __DIR__ . '/../../../reports/inc.php';

const ARTIKEL_MAX_ROWS = 500;

reportApi(function (array $post): array {
    $zoek = trim((string) ($post['zoek'] ?? ''));
    if ($zoek === '') {
        throw new RuntimeException('Vul (een deel van) een artikelnummer in.');
    }
    if (strlen($zoek) > 60) {
        throw new RuntimeException('Zoekterm is te lang.');
    }
    // [ % _ in de zoekterm letterlijk nemen, niet als LIKE-jokertekens.
    $like = '%' . strtr($zoek, ['[' => '[[]', '%' => '[%]', '_' => '[_]']) . '%';

    $stmt = reportConnection()->prepare(
        'SET NOCOUNT ON; SELECT TOP ' . (ARTIKEL_MAX_ROWS + 1) . ' [Omschrijving], [artikel] FROM [Exactrapportage].[dbo].[1002_Artikellabels]'
        . ' WHERE [artikel] LIKE :filter_artikelnr ORDER BY [artikel]'
    );
    $stmt->execute([':filter_artikelnr' => $like]);

    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $rows[] = [
            'artikel' => trim((string) ($row['artikel'] ?? '')),
            'omschrijving' => trim((string) ($row['Omschrijving'] ?? '')),
        ];
    }
    $more = count($rows) > ARTIKEL_MAX_ROWS;

    return ['rows' => array_slice($rows, 0, ARTIKEL_MAX_ROWS), 'more' => $more];
}, false);
