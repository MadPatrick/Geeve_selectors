<?php

declare(strict_types=1);

// Tegel-toegang (Config > Tegels): zonder ontgrendelen geen gegevens.
require_once __DIR__ . '/../../../shared/tiles.php';
tileApiGuard('productieorders');

/**
 * Overzicht gereed te melden productieorders: de stored procedure
 * [005_Data_Geeve].[dbo].[SP_SQLQuery_ProductieordersGereed_te_melden] (zonder parameters), dezelfde als in de Excel van Carl
 * ("Overzicht gereed te melden productieorders.xlsx"). Kolommen en volgorde als in die Excel; het app-account heeft EXECUTE
 * nodig op de procedure. Zelfde SQL Server en inloggegevens (via Config) als de andere rapporten.
 */

require_once __DIR__ . '/../../inc.php';

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
    $pdo = reportConnection();
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

reportApi(function (array $in): array {
    // Sessie niet vergrendeld houden tijdens het ophalen: anders hangen andere pagina's van dezelfde gebruiker erop.
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    $t = microtime(true);
    $data = reportCallProcedure('[005_Data_Geeve].[dbo].[SP_SQLQuery_ProductieordersGereed_te_melden]', '', null, PRODUCTIEORDERS_KOLOMMEN);

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
