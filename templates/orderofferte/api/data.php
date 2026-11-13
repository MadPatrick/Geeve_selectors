<?php

declare(strict_types=1);

// Beveiligde tegel (Config > Tegels): zonder ontgrendelen geen gegevens.
require_once __DIR__ . '/../../../shared/tiles.php';
tileApiGuard('order-offerte');

/**
 * Order of Offerte naar Excel: voert dbo.SP_orders_naar_Excel (data-database, standaard 005_Data_Geeve) uit voor één order-/offertenummer
 * en toont dezelfde kolommen (en namen) als de Excel-sjabloon.
 */

require_once __DIR__ . '/../inc.php';

reportApi(function (string $nummer): array {
    $data = orderOfferteOphalen($nummer);
    unset($data['kop']); // alleen voor de XML ($data['kopkolommen'] blijft staan: welke kop_-kolommen de procedure geeft)
    return $data;
});
