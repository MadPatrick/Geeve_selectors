<?php

declare(strict_types=1);

// Beveiligde tegel (Config > Tegels): zonder ontgrendelen geen gegevens.
require_once __DIR__ . '/../../../shared/tiles.php';
tileApiGuard('stickers-order');

/**
 * Artikelstickers per Verkooporder: de regels van één order uit [005_Data_Geeve].[dbo].[temp_Artikel_Stikkers_order]
 * (dezelfde query als de NiceLabel-template), gesorteerd op orderregel. Zelfde SQL Server en inloggegevens (via Config)
 * als /stauff en de rapporten.
 */

require_once __DIR__ . '/../../../reports/inc.php';

const STICKER_COLUMNS = [
    'looplocatie', 'bonnr', 'locatie', 'debnr', 'debnm', 'uw_ref', 'artikelnummer', 'artikelnaam', 'besteld', 'geleverd',
    'ord_rgl', 'uw_artikelnummer', 'ia_CountryOfOrigin', 'soort', 'artgrpFin',
];

reportApi(function (string $nummer): array {
    $columns = implode(', ', array_map(fn ($c) => '[' . $c . ']', STICKER_COLUMNS));
    // bonnr kan een char-kolom met opvulspaties zijn: daarom LTRIM/RTRIM.
    $stmt = reportConnection()->prepare(
        "SET NOCOUNT ON; SELECT {$columns} FROM [005_Data_Geeve].[dbo].[temp_Artikel_Stikkers_order] WHERE LTRIM(RTRIM([bonnr])) = ?"
    );
    $stmt->execute([$nummer]);

    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $out = [];
        foreach (STICKER_COLUMNS as $c) {
            $v = $row[$c] ?? null;
            $out[$c] = $v instanceof DateTimeInterface ? $v->format('Y-m-d') : ($v === null ? '' : trim((string) $v));
        }
        $rows[] = $out;
    }

    // Ordervolgorde: op regelnummer (numeriek, ook als de kolom tekst is).
    usort($rows, fn ($a, $b) => strnatcmp($a['ord_rgl'], $b['ord_rgl']));

    return ['rows' => $rows];
});
