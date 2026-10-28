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
    // Nettoprijs = het veld "Netto afspraak" van het slangkaartscherm = kolom PrijsBerekend (Patrick 2026-10-27, voorbeeld 03-3212-0300 = 14,50).
    // De tabel heeft ruim 217.000 kaarten; filteren op de server voorkomt dat de browser vastloopt.
    $where = ['PrijsBerekend IS NOT NULL', 'PrijsBerekend > 0'];
    $params = [];
    $like = static fn (string $w): string => '%' . str_replace(['\\', '%', '_', '['], ['\\\\', '\\%', '\\_', '\\['], $w) . '%';
    // Klant: losse woorden in willekeurige volgorde, hoofdletters en leestekens (. , - ') tellen niet mee; elk woord moet in de
    // klantnaam of in het klantnummer (Debiteur) voorkomen. De vergelijking is zo ruim als de collation van de database.
    $naamZonder = "REPLACE(REPLACE(REPLACE(REPLACE(DebiteurNaam, '.', ''), ',', ''), '-', ''), '''', '')";
    foreach (preg_split('/\s+/', trim((string) ($in['klant'] ?? ''))) ?: [] as $woord) {
        $zonder = str_replace(['.', ',', '-', "'"], '', $woord);
        if ($zonder === '') {
            continue;
        }
        $where[] = "(" . $naamZonder . " LIKE ? ESCAPE '\\' OR LTRIM(RTRIM(Debiteur)) LIKE ? ESCAPE '\\')";
        $params[] = $like($zonder);
        $params[] = $like($zonder);
    }
    // Gekozen klant uit de keuzelijst: exact op klantnummer (dus 100 vindt niet ook 1000 of 1100).
    $debnr = trim((string) ($in['debnr'] ?? ''));
    if ($debnr !== '') {
        $where[] = 'LTRIM(RTRIM(Debiteur)) = ?';
        $params[] = $debnr;
    }
    $nummer = trim((string) ($in['nummer'] ?? ''));
    if ($nummer !== '') {
        $where[] = "Nummer LIKE ? ESCAPE '\\'";
        $params[] = $like($nummer);
    }
    $stmt = $pdo->prepare(
        'SET NOCOUNT ON; SELECT LTRIM(RTRIM(Nummer)) AS Nummer, LTRIM(RTRIM(DebiteurNaam)) AS DebiteurNaam, LTRIM(RTRIM(Debiteur)) AS Debiteur, PrijsBerekend, Datum ' .
        'FROM [Slangkaarten].[dbo].[Slangkaarten] WITH (NOLOCK) WHERE ' . implode(' AND ', $where) . ' ORDER BY Nummer'
    );
    $stmt->execute($params);
    $rows = [];
    while ($r = $stmt->fetch(PDO::FETCH_NUM)) {
        $rows[] = [
            reportNormalizeValue($r[0], false),
            reportNormalizeValue($r[1], false),
            reportNormalizeValue($r[2], false),
            reportNormalizeValue($r[3], true),
            reportNormalizeValue($r[4], false, true),
        ];
    }
    return [
        'columns' => ['Slangassemblage', 'Klantnaam', 'Klantnr', 'Nettoprijs', 'Aanmaakdatum'],
        'numeric' => [false, false, false, true, false],
        'dates' => [false, false, false, false, true],
        'rows' => $rows,
        'tijden' => ['ophalen' => (int) round((microtime(true) - $t) * 1000)],
    ];
}, false);
