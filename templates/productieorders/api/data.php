<?php

declare(strict_types=1);

// Tegel-toegang (Config > Tegels): zonder ontgrendelen geen gegevens.
require_once __DIR__ . '/../../../shared/tiles.php';
tileApiGuard('productieorders');

/**
 * Overzicht gereed te melden productieorders: de stored procedure
 * SP_SQLQuery_ProductieordersGereed_te_melden (data-database, standaard 005_Data_Geeve) (zonder parameters), dezelfde als in de Excel van Carl
 * ("Overzicht gereed te melden productieorders.xlsx"). Kolommen en volgorde als in die Excel; het app-account heeft EXECUTE
 * nodig op de procedure. Zelfde SQL Server en inloggegevens (via Config) als de andere rapporten.
 */

require_once __DIR__ . '/../../../reports/inc.php';

const PRODUCTIEORDERS_KOLOMMEN = [
    'ProjectNr' => 'ProjectNr',
    'projectnm' => 'projectnm',
    'Status' => 'Status',
    'uit te geven' => 'uit te geven',
    'uitgegeven' => 'uitgegeven',
    'te Ontvangen' => 'te Ontvangen',
    'ontvangen' => 'ontvangen',
];

/**
 * Moederproject per productieproject: PRProject.ParentProject (in SP_productie_label koppelt de verkooporder zo aan zijn
 * productieprojecten: orkrg.ordernr = PRProject.ParentProject). Aparte query, zodat de procedure van Carl ongewijzigd blijft.
 *
 * @param list<string> $projecten
 * @return array<string,string> projectnr (getrimd) => moederproject (getrimd)
 */
function productieordersMoeders(array $projecten): array
{
    $pdo = reportConnection('productieorders');
    $out = [];
    foreach (array_chunk(array_values(array_unique($projecten)), 500) as $deel) {
        $stmt = $pdo->prepare(
            'SET NOCOUNT ON; SELECT LTRIM(RTRIM(ProjectNr)), LTRIM(RTRIM(ParentProject)) FROM PRProject WITH (NOLOCK) ' .
            'WHERE LTRIM(RTRIM(ProjectNr)) IN (' . implode(',', array_fill(0, count($deel), '?')) . ')'
        );
        $stmt->execute($deel);
        while ($r = $stmt->fetch(PDO::FETCH_NUM)) {
            $out[(string) $r[0]] = (string) $r[1];
        }
    }
    return $out;
}

/**
 * Dezelfde lijst rechtstreeks uit Exact (database 005), zonder de procedure van Carl (8 tot 21 s; deze query 0,1 s). De logica is
 * afgeleid uit de procedure (de definitie is voor het app-account niet leesbaar) en op 162 rijen rij voor rij gelijk bevonden:
 * in gbkmut, alleen voorraadrekening 3000 (met voorloopspaties), per project: uit te geven = -som aantal transtype B/subtype B,
 * uitgegeven = -som N/B, te Ontvangen = som B/A, ontvangen = som N/A; projectnaam = gbkmut.oms25 (transtype B). Filter: project
 * PR... met Status A, uit te geven > 0 en ontvangen >= te Ontvangen. Niet getest: een project met te Ontvangen = 0 en
 * ontvangen = 0, maar uit te geven > 0 komt wel in de lijst; de procedure kan dat anders doen.
 *
 * @return array{columns: list<string>, numeric: list<bool>, dates: list<bool>, rows: list<list<mixed>>}
 */
function productieordersUitExact(): array
{
    $sql = 'SET NOCOUNT ON; SELECT RTRIM(p.ProjectNr) AS ProjectNr, x.projectnm, p.Status, ' .
        'x.ug AS [uit te geven], x.ugd AS [uitgegeven], x.te AS [te Ontvangen], x.ont AS [ontvangen], RTRIM(p.ParentProject) AS Moeder ' .
        'FROM PRProject p WITH (NOLOCK) CROSS APPLY (SELECT ' .
        "SUM(CASE WHEN g.transtype='B' AND g.transsubtype='B' THEN -g.aantal ELSE 0 END) AS ug, " .
        "SUM(CASE WHEN g.transtype='N' AND g.transsubtype='B' THEN -g.aantal ELSE 0 END) AS ugd, " .
        "SUM(CASE WHEN g.transtype='B' AND g.transsubtype='A' THEN g.aantal ELSE 0 END) AS te, " .
        "SUM(CASE WHEN g.transtype='N' AND g.transsubtype='A' THEN g.aantal ELSE 0 END) AS ont, " .
        "MAX(CASE WHEN g.transtype='B' THEN g.oms25 END) AS projectnm FROM gbkmut g WITH (NOLOCK) " .
        "WHERE g.project = p.ProjectNr AND g.reknr = '     3000' AND g.transtype IN ('B','N') AND g.transsubtype IN ('A','B')) x " .
        "WHERE p.Status = 'A' AND p.ProjectNr LIKE 'PR%' AND x.ug > 0 AND x.ont >= x.te ORDER BY p.ProjectNr";

    $stmt = reportConnection('productieorders')->query($sql);
    $getal = static fn (mixed $v): float => round((float) $v, 4);
    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_NUM) as $r) {
        $rows[] = [
            trim((string) $r[7]), trim((string) $r[0]), trim((string) $r[1]), trim((string) $r[2]),
            $getal($r[3]), $getal($r[4]), $getal($r[5]), $getal($r[6]),
        ];
    }

    return [
        'columns' => ['Moederproject', 'ProjectNr', 'projectnm', 'Status', 'uit te geven', 'uitgegeven', 'te Ontvangen', 'ontvangen'],
        'numeric' => [false, false, false, false, true, true, true, true],
        'dates' => array_fill(0, 8, false),
        'rows' => $rows,
    ];
}

reportApi(function (array $in): array {
    // Sessie niet vergrendeld houden tijdens het ophalen: anders hangen andere pagina's van dezelfde gebruiker erop.
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    $t = microtime(true);
    // Snelle route rechtstreeks uit Exact; mislukt die, dan de procedure van Carl zoals voorheen.
    try {
        $data = productieordersUitExact();
        $data['waarschuwing'] = '';
        $data['tijden'] = ['ophalen' => (int) round((microtime(true) - $t) * 1000)];
        return $data;
    } catch (Throwable $e) {
        // Verder met de procedure.
    }
    $data = reportCallProcedure(geeveDbNaamSql('data') . '.[dbo].[SP_SQLQuery_ProductieordersGereed_te_melden]', '', null, PRODUCTIEORDERS_KOLOMMEN, 'productieorders');

    // Kolom Moederproject als eerste kolom. Lukt het opzoeken niet, dan blijft de lijst werken met een lege kolom en een melding.
    $moeders = [];
    $waarschuwing = '';
    try {
        $moeders = productieordersMoeders(array_map(static fn ($r): string => trim((string) $r[0]), $data['rows']));
    } catch (Throwable $e) {
        $waarschuwing = 'Moederproject kon niet worden opgezocht: ' . geeveFoutTekst($e);
    }
    foreach ($data['rows'] as $i => $r) {
        array_splice($data['rows'][$i], 0, 0, [$moeders[trim((string) $r[0])] ?? '']);
    }
    array_splice($data['columns'], 0, 0, ['Moederproject']);
    array_splice($data['numeric'], 0, 0, [false]);
    array_splice($data['dates'], 0, 0, [false]);
    $data['waarschuwing'] = $waarschuwing;
    $data['tijden'] = ['ophalen' => (int) round((microtime(true) - $t) * 1000)];
    return $data;
}, false);
