<?php

declare(strict_types=1);

// Beveiligde tegel (Config > Tegels): zonder ontgrendelen geen gegevens.
require_once __DIR__ . '/../../../shared/tiles.php';
tileApiGuard('stickers-productie');

/**
 * Productiestickers per verkooporder: de productieorderregels van één verkooporder uit de stored procedure
 * SP_productie_label (data-database, standaard 005_Data_Geeve) @ordernr (dezelfde als de oude rapportbouwer), in de volgorde van de procedure.
 * Zelfde SQL Server en inloggegevens (via Config) als /stauff en de rapporten.
 */

require_once __DIR__ . '/../../../reports/inc.php';

/**
 * Veld => mogelijke kolomnamen uit de procedure (vergeleken zonder hoofdletters, spaties en underscores), zodat zowel
 * "ARTIKEL OMSCHRIJVING" als "PO_ARTIKEL_OMSCHRIJVING" werkt.
 */
const PRODUCTIE_COLUMNS = [
    'ordernr' => ['ordernr', 'poordernr'],
    'project' => ['moederproject', 'pomoederproject'],
    'debiteur' => ['orddebtorname', 'podebtorname', 'debtorname'],
    'locatie' => ['locatie', 'powarehouselocation', 'warehouselocation'],
    'looplocatie' => ['looplocatie', 'polooplocatie'],
    'artikel' => ['artikel', 'poartikel'],
    'omschrijving' => ['artikelomschrijving', 'poartikelomschrijving'],
    'aantal' => ['aantal', 'poprodtot', 'prodtot'],
];

reportApi(function (string $nummer): array {
    // Snelle route rechtstreeks uit Exact (ca. 0,05 s); geeft die niets of een fout, dan de procedure zoals voorheen (2,5 tot 3 s).
    $exact = productieRegelsUitExact($nummer);
    if ($exact !== []) {
        return ['rows' => $exact];
    }

    $data = reportCallProcedure(geeveDbNaamSql('data') . '.[dbo].[SP_productie_label]', '@ordernr', $nummer, null, 'stickers-productie');

    $key = fn (string $name): string => strtolower(str_replace([' ', '_'], '', $name));
    $index = [];
    foreach ($data['columns'] as $i => $name) {
        $index[$key($name)] = $i;
    }
    $pick = [];
    foreach (PRODUCTIE_COLUMNS as $field => $names) {
        foreach ($names as $name) {
            if (isset($index[$name])) {
                $pick[$field] = $index[$name];
                break;
            }
        }
        if (!isset($pick[$field]) && $field !== 'project') {
            throw new RuntimeException('SP_productie_label geeft geen kolom voor "' . $field . '" terug (wel: ' . implode(', ', $data['columns']) . ').');
        }
    }

    $rows = [];
    foreach ($data['rows'] as $row) {
        $out = [];
        foreach (array_keys(PRODUCTIE_COLUMNS) as $field) {
            $v = isset($pick[$field]) ? $row[$pick[$field]] : null;
            $out[$field] = $v === null ? '' : trim((string) $v);
        }
        $rows[] = $out;
    }

    return ['rows' => productieAantallenAlsPicklijst($rows, $nummer)];
});

/**
 * Het aantal op de sticker is gelijk aan de picklijst van Slangkaarten printen: materiaal per stuk van de productieorder x het
 * aantal van de verkooporder. De procedure telt bij slangregels zonder productieorder op 3 stuks 1 slang in plaats van het
 * bestelde aantal (order 36023662: 77296-36-12 gaf 4, de picklijst 12).
 * Rechtstreeks uit Exact (database 005), zonder de slangkaarten: moederproject = PRProject met ParentProject = ordernummer, de
 * productieorders zijn het moederproject zelf en de subprojecten (.nnn). In gbkmut zijn transtype 'B' de geplande productieregels
 * (transsubtype 'A' = eindproduct, 'B' = materiaal; elke regel heeft +x en een tegenboeking -x, dus alleen aantal > 0).
 * Factor = som orsrg.esr_aantal van de orderregels met het eindproduct / som van de aantallen van de productieorders met dat
 * eindproduct. Alleen artikelen die één keer in de uitkomst staan en hier voorkomen worden vervangen; de rest (en alles, als de
 * query mislukt) blijft zoals de procedure het geeft. Getest op 40 recente orders: 38 gelijk aan de picklijst.
 *
 * @param list<array<string, string>> $rows
 * @return list<array<string, string>>
 */
function productieAantallenAlsPicklijst(array $rows, string $nummer): array
{
    $sql = 'SET NOCOUNT ON; WITH moeder AS (' .
        'SELECT RTRIM(ProjectNr) AS pr FROM PRProject WITH (NOLOCK) WHERE ParentProject = ?' .
        '), mut AS (' .
        'SELECT g.project, LTRIM(RTRIM(g.artcode)) AS artcode, g.transsubtype, g.aantal FROM moeder m ' .
        "JOIN gbkmut g WITH (NOLOCK) ON g.project = m.pr OR g.project LIKE m.pr + '.%' " .
        "WHERE g.transtype = 'B' AND g.aantal > 0 AND g.artcode IS NOT NULL" .
        '), eind AS (' .
        "SELECT project, artcode AS ItemCode, SUM(aantal) AS pq FROM mut WHERE transsubtype = 'A' GROUP BY project, artcode" .
        '), pqtot AS (' .
        'SELECT ItemCode, SUM(pq) AS pqtot FROM eind GROUP BY ItemCode' .
        '), ord AS (' .
        'SELECT LTRIM(RTRIM(o.artcode)) AS artcode, SUM(o.esr_aantal) AS besteld FROM orsrg o WITH (NOLOCK) ' .
        'WHERE o.ordernr = ? GROUP BY LTRIM(RTRIM(o.artcode))' .
        ') SELECT mt.artcode, SUM(mt.aantal * ord.besteld / NULLIF(pqtot.pqtot, 0)) AS totaal FROM mut mt ' .
        'JOIN eind e ON e.project = mt.project JOIN pqtot ON pqtot.ItemCode = e.ItemCode JOIN ord ON ord.artcode = e.ItemCode ' .
        "WHERE mt.transsubtype = 'B' AND mt.artcode <> e.ItemCode GROUP BY mt.artcode";

    try {
        $pdo = reportConnection('stickers-productie');
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nummer, $nummer]);
        $totalen = [];
        foreach ($stmt->fetchAll() as $r) {
            $totalen[strtoupper(trim((string) $r['artcode']))] = (float) $r['totaal'];
        }
        if ($totalen === []) {
            return $rows;
        }

        $voorkomen = [];
        foreach ($rows as $row) {
            $k = strtoupper(trim($row['artikel']));
            $voorkomen[$k] = ($voorkomen[$k] ?? 0) + 1;
        }
        foreach ($rows as $i => $row) {
            $k = strtoupper(trim($row['artikel']));
            if (($voorkomen[$k] ?? 0) === 1 && isset($totalen[$k]) && $totalen[$k] > 0) {
                $rows[$i]['aantal'] = rtrim(rtrim(number_format($totalen[$k], 4, '.', ''), '0'), '.');
            }
        }
    } catch (Throwable $e) {
        // Niet te lezen: de aantallen uit de procedure blijven staan.
    }

    return $rows;
}

/**
 * De regels van SP_productie_label rechtstreeks uit Exact (database 005), zonder de procedure: het materiaal per stuk van de
 * productieorder x het aantal van de verkooporder (zie productieAantallenAlsPicklijst()), met omschrijving, locatie en looplocatie
 * zoals Artikelen per Verkooporder, op volgorde looplocatie, locatie, artikel. Getest tegen de procedure op 41 recente orders:
 * 37 gelijk; verschillen: de gewenste aantallen (x bestelde aantal), een afronding op 2 decimalen in de procedure en locatie in
 * hoofdletters. Orders zonder moederproject geven geen regels (de procedure ook niet).
 *
 * @return list<array<string, string>> leeg bij geen regels of een fout (dan volgt de procedure)
 */
function productieRegelsUitExact(string $nummer): array
{
    $sql = 'SET NOCOUNT ON; WITH moeder AS (' .
        'SELECT RTRIM(ProjectNr) AS pr FROM PRProject WITH (NOLOCK) WHERE ParentProject = ?' .
        '), mut AS (' .
        'SELECT g.project, LTRIM(RTRIM(g.artcode)) AS artcode, g.transsubtype, g.aantal FROM moeder m ' .
        "JOIN gbkmut g WITH (NOLOCK) ON g.project = m.pr OR g.project LIKE m.pr + '.%' " .
        "WHERE g.transtype = 'B' AND g.aantal > 0 AND g.artcode IS NOT NULL" .
        '), eind AS (' .
        "SELECT project, artcode AS ItemCode, SUM(aantal) AS pq FROM mut WHERE transsubtype = 'A' GROUP BY project, artcode" .
        '), pqtot AS (' .
        'SELECT ItemCode, SUM(pq) AS pqtot FROM eind GROUP BY ItemCode' .
        '), ord AS (' .
        'SELECT LTRIM(RTRIM(o.artcode)) AS artcode, SUM(o.esr_aantal) AS besteld FROM orsrg o WITH (NOLOCK) ' .
        'WHERE o.ordernr = ? GROUP BY LTRIM(RTRIM(o.artcode))' .
        '), tot AS (' .
        'SELECT mt.artcode, SUM(mt.aantal * ord.besteld / NULLIF(pqtot.pqtot, 0)) AS aantal FROM mut mt ' .
        'JOIN eind e ON e.project = mt.project JOIN pqtot ON pqtot.ItemCode = e.ItemCode JOIN ord ON ord.artcode = e.ItemCode ' .
        "WHERE mt.transsubtype = 'B' AND mt.artcode <> e.ItemCode GROUP BY mt.artcode" .
        ') SELECT (SELECT TOP 1 pr FROM moeder) AS project, d.cmp_name AS debiteur, RTRIM(el.maglok) AS locatie, ' .
        'RTRIM(el.TextField1) AS looplocatie, t.artcode AS artikel, i.Description AS omschrijving, t.aantal AS aantal ' .
        'FROM tot t LEFT JOIN Items i WITH (NOLOCK) ON i.ItemCode = t.artcode ' .
        "LEFT JOIN voorrd v WITH (NOLOCK) ON v.artcode = t.artcode AND v.magcode = '1' " .
        'LEFT JOIN evloc el WITH (NOLOCK) ON el.maglok = v.maglok ' .
        'LEFT JOIN orkrg k WITH (NOLOCK) ON k.ordernr = ? LEFT JOIN cicmpy d WITH (NOLOCK) ON d.debnr = k.debnr ' .
        'ORDER BY el.TextField1, el.maglok, t.artcode';

    try {
        $stmt = reportConnection('stickers-productie')->prepare($sql);
        $stmt->execute([$nummer, $nummer, $nummer]);
        $rows = [];
        foreach ($stmt->fetchAll() as $r) {
            $aantal = (float) $r['aantal'];
            $rows[] = [
                'ordernr' => $nummer,
                'project' => trim((string) ($r['project'] ?? '')),
                'debiteur' => trim((string) ($r['debiteur'] ?? '')),
                'locatie' => trim((string) ($r['locatie'] ?? '')),
                'looplocatie' => trim((string) ($r['looplocatie'] ?? '')),
                'artikel' => trim((string) ($r['artikel'] ?? '')),
                'omschrijving' => trim((string) ($r['omschrijving'] ?? '')),
                'aantal' => rtrim(rtrim(number_format($aantal, 4, '.', ''), '0'), '.'),
            ];
        }
        return $rows;
    } catch (Throwable $e) {
        return [];
    }
}
