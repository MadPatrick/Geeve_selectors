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

/** LIKE-patroon '%waarde%' met ESCAPE '\'. */
function slangkaartNettoLike(string $waarde): string
{
    return '%' . str_replace(['\\', '%', '_', '['], ['\\\\', '\\%', '\\_', '\\['], $waarde) . '%';
}

/**
 * Tolerante klantzoekactie (typefouten): elk zoekwoord moet in een woord van de klantnaam voorkomen, met hooguit 1
 * (zoekwoord t/m 6 tekens) of 2 (langer) afwijkende tekens; t/m 3 tekens moet het exact zijn. Geeft klantnummers
 * (Debiteur) terug; geen mb_* nodig.
 *
 * @return list<string>
 */
function slangkaartNettoFuzzyKlanten(PDO $pdo, string $tabel, string $klant): array
{
    $zoek = preg_split('/[^a-z0-9]+/', strtolower($klant), -1, PREG_SPLIT_NO_EMPTY);
    if (!$zoek) {
        return [];
    }
    $res = $pdo->query('SET NOCOUNT ON; SELECT DISTINCT LTRIM(RTRIM(Debiteur)), LTRIM(RTRIM(DebiteurNaam)) FROM ' . $tabel .
        ' WITH (NOLOCK) WHERE PrijsBerekend > 0');
    $ids = [];
    while ($r = $res->fetch(PDO::FETCH_NUM)) {
        $woorden = preg_split('/[^a-z0-9]+/', strtolower((string) $r[1]), -1, PREG_SPLIT_NO_EMPTY);
        if (!$woorden) {
            continue;
        }
        $aaneen = implode('', $woorden);
        foreach ($zoek as $z) {
            if (strpos($aaneen, $z) !== false) {
                continue;
            }
            $max = strlen($z) <= 3 ? 0 : (strlen($z) <= 6 ? 1 : 2);
            $gevonden = false;
            foreach ($woorden as $w) {
                if (
                    strpos($w, $z) !== false
                    || levenshtein($z, substr($w, 0, strlen($z))) <= $max
                    || levenshtein($z, substr($w, 0, strlen($z) + 1)) <= $max
                ) {
                    $gevonden = true;
                    break;
                }
            }
            if (!$gevonden) {
                continue 2;
            }
        }
        $ids[(string) $r[0]] = true;
        if (count($ids) >= 500) {
            break;
        }
    }
    return array_map('strval', array_keys($ids));
}

reportApi(function (array $in): array {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    $t = microtime(true);
    $pdo = reportConnection();
    $tabel = '[Slangkaarten].[dbo].[Slangkaarten]';
    // Nettoprijs = het veld "Netto afspraak" van het slangkaartscherm = kolom PrijsBerekend (Patrick 2026-10-27, voorbeeld 03-3212-0300 = 14,50).
    // De tabel heeft ruim 217.000 kaarten; filteren op de server voorkomt dat de browser vastloopt.
    $where = ['PrijsBerekend IS NOT NULL', 'PrijsBerekend > 0'];
    $params = [];
<<<<<<< Updated upstream
    $like = static fn (string $w): string => '%' . str_replace(['\\', '%', '_', '['], ['\\\\', '\\%', '\\_', '\\['], $w) . '%';
    // Klant: losse woorden in willekeurige volgorde, hoofdletters en leestekens (. , - ') tellen niet mee; elk woord moet in de
    // klantnaam of in het klantnummer (Debiteur) voorkomen. De vergelijking is zo ruim als de collation van de database.
    $naamZonder = "REPLACE(REPLACE(REPLACE(REPLACE(DebiteurNaam, '.', ''), ',', ''), '-', ''), '''', '')";
    foreach (preg_split('/\s+/', trim((string) ($in['klant'] ?? ''))) ?: [] as $woord) {
        $zonder = str_replace(['.', ',', '-', "'"], '', $woord);
        if ($zonder === '') {
            continue;
=======
    $nummer = trim((string) ($in['nummer'] ?? ''));
    if ($nummer !== '') {
        $where[] = "Nummer LIKE ? ESCAPE '\\'";
        $params[] = slangkaartNettoLike($nummer);
    }
    // Klant: woorden in willekeurige volgorde, leestekens en spaties genegeerd; zoekt in naam en klantnummer (Debiteur).
    $klant = trim((string) ($in['klant'] ?? ''));
    $klantWhere = [];
    $klantParams = [];
    if ($klant !== '') {
        $genorm = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(DebiteurNaam, ' ', ''), '.', ''), ',', ''), '-', ''), '&', '')";
        foreach (preg_split('/\s+/', $klant) as $woord) {
            $woord = str_replace(['.', ',', '-', '&'], '', $woord);
            if ($woord === '') {
                continue;
            }
            $klantWhere[] = '(' . $genorm . " LIKE ? ESCAPE '\\' OR LTRIM(RTRIM(Debiteur)) LIKE ? ESCAPE '\\')";
            $klantParams[] = slangkaartNettoLike($woord);
            $klantParams[] = slangkaartNettoLike($woord);
>>>>>>> Stashed changes
        }
        $where[] = "(" . $naamZonder . " LIKE ? ESCAPE '\\' OR LTRIM(RTRIM(Debiteur)) LIKE ? ESCAPE '\\')";
        $params[] = $like($zonder);
        $params[] = $like($zonder);
    }
    $nummer = trim((string) ($in['nummer'] ?? ''));
    if ($nummer !== '') {
        $where[] = "Nummer LIKE ? ESCAPE '\\'";
        $params[] = $like($nummer);
    }
<<<<<<< Updated upstream
    $stmt = $pdo->prepare(
        'SET NOCOUNT ON; SELECT LTRIM(RTRIM(Nummer)) AS Nummer, LTRIM(RTRIM(DebiteurNaam)) AS DebiteurNaam, LTRIM(RTRIM(Debiteur)) AS Debiteur, PrijsBerekend ' .
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
        ];
=======
    $haal = function (array $extraWhere, array $extraParams) use ($pdo, $where, $params, $tabel): array {
        $stmt = $pdo->prepare(
            'SET NOCOUNT ON; SELECT LTRIM(RTRIM(Nummer)) AS Nummer, LTRIM(RTRIM(DebiteurNaam)) AS DebiteurNaam, PrijsBerekend ' .
            'FROM ' . $tabel . ' WITH (NOLOCK) WHERE ' . implode(' AND ', array_merge($where, $extraWhere)) . ' ORDER BY Nummer'
        );
        $stmt->execute(array_merge($params, $extraParams));
        $rows = [];
        while ($r = $stmt->fetch(PDO::FETCH_NUM)) {
            $rows[] = [
                reportNormalizeValue($r[0], false),
                reportNormalizeValue($r[1], false),
                reportNormalizeValue($r[2], true),
            ];
        }
        return $rows;
    };
    $rows = $haal($klantWhere, $klantParams);
    // Geen treffer: tolerant zoeken op klantnaam (typefouten).
    if ($rows === [] && $klantWhere !== []) {
        $ids = slangkaartNettoFuzzyKlanten($pdo, $tabel, $klant);
        if ($ids) {
            $rows = $haal(['LTRIM(RTRIM(Debiteur)) IN (' . implode(',', array_fill(0, count($ids), '?')) . ')'], $ids);
        }
>>>>>>> Stashed changes
    }
    return [
        'columns' => ['Slangassemblage', 'Klantnaam', 'Klantnr', 'Nettoprijs'],
        'numeric' => [false, false, false, true],
        'dates' => [false, false, false, false],
        'rows' => $rows,
        'tijden' => ['ophalen' => (int) round((microtime(true) - $t) * 1000)],
    ];
}, false);
