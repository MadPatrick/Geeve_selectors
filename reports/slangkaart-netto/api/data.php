<?php

declare(strict_types=1);

// Tegel-toegang (Config > Tegels): zonder ontgrendelen geen gegevens.
require_once __DIR__ . '/../../../shared/tiles.php';
tileApiGuard('slangkaart-netto');

/**
 * Overzicht slangassemblages met nettoprijs: uit de eigen database [Slangkaarten] (tabel Slangkaarten, de kop van elke
 * slangkaart): Nummer (= Exact-artikelnummer van de assemblage), DebiteurNaam en PrijsNetto. Alleen lezen; het app-account
 * heeft db_datareader nodig op de database Slangkaarten (zie slangkaart-web/docs/LEGACY-SLANGKAARTEN-DB-2026-09-18.md).
 */

require_once __DIR__ . '/../../inc.php';

reportApi(function (array $in): array {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    $t = microtime(true);
    $pdo = reportConnection();
    $stmt = $pdo->prepare(
        'SET NOCOUNT ON; SELECT LTRIM(RTRIM(Nummer)) AS Nummer, LTRIM(RTRIM(DebiteurNaam)) AS DebiteurNaam, PrijsNetto ' .
        'FROM [Slangkaarten].[dbo].[Slangkaarten] WITH (NOLOCK) WHERE PrijsNetto IS NOT NULL AND PrijsNetto > 0 ORDER BY Nummer'
    );
    $stmt->execute();
    $rows = [];
    while ($r = $stmt->fetch(PDO::FETCH_NUM)) {
        $rows[] = [
            reportNormalizeValue($r[0], false),
            reportNormalizeValue($r[1], false),
            reportNormalizeValue($r[2], true),
        ];
    }
    return [
        'columns' => ['Slangassemblage', 'Klantnaam', 'Nettoprijs'],
        'numeric' => [false, false, true],
        'dates' => [false, false, false],
        'rows' => $rows,
        'tijden' => ['ophalen' => (int) round((microtime(true) - $t) * 1000)],
    ];
}, false);
