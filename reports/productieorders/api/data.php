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

reportApi(function (array $in): array {
    // Sessie niet vergrendeld houden tijdens het ophalen: anders hangen andere pagina's van dezelfde gebruiker erop.
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    $t = microtime(true);
    $data = reportCallProcedure('[005_Data_Geeve].[dbo].[SP_SQLQuery_ProductieordersGereed_te_melden]', '', null, PRODUCTIEORDERS_KOLOMMEN);
    $data['tijden'] = ['ophalen' => (int) round((microtime(true) - $t) * 1000)];
    return $data;
}, false);
