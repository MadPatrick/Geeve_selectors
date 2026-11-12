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
