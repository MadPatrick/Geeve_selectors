<?php

declare(strict_types=1);

// Beveiligde tegel (Config > Tegels): zonder ontgrendelen geen gegevens.
require_once __DIR__ . '/../../../shared/tiles.php';
tileApiGuard('stickers-artikel');

/**
 * Stickers op Artikelnummer: artikelen uit [Exactrapportage].[dbo].[1002_Artikellabels] waarvan het artikelnummer de zoekterm bevat
 * (dezelfde query als de oude rapportbouwer, met % aan beide kanten), eerst wat met de zoekterm begint. Zelfde SQL Server en
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
    $esc = strtr($zoek, ['[' => '[[]', '%' => '[%]', '_' => '[_]']);

    $stmt = reportConnection()->prepare(
        'SET NOCOUNT ON; SELECT TOP ' . (ARTIKEL_MAX_ROWS + 1) . ' [Omschrijving], [artikel] FROM [Exactrapportage].[dbo].[1002_Artikellabels]'
        . ' WHERE [artikel] LIKE :filter_artikelnr'
        // Eerst het exacte artikelnummer, dan wat ermee begint, daarna waar het ergens anders in voorkomt; binnen elke groep op artikel.
        . ' ORDER BY CASE WHEN [artikel] = :exact THEN 0 WHEN [artikel] LIKE :begin THEN 1 ELSE 2 END, [artikel]'
    );
    $stmt->execute([':filter_artikelnr' => '%' . $esc . '%', ':exact' => $zoek, ':begin' => $esc . '%']);

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
