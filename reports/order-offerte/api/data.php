<?php

declare(strict_types=1);

/**
 * Order of Offerte naar Excel: voert dbo.SP_orders_naar_Excel (database 005_Data_Geeve) uit voor één order-/offertenummer
 * en toont dezelfde kolommen (en namen) als de Excel-sjabloon.
 */

require_once __DIR__ . '/../../inc.php';

const ORDER_OFFERTE_COLUMNS = [
    'rgls_regel' => 'Line',
    'rgls_esr_aantal' => 'Amount',
    'rgls_artcode' => 'Partnumber',
    'rgls_oms45' => 'Description',
    'rgls_prijs83' => 'Price',
    'rgls_korting' => 'Discount',
    'rgls_prijs_n' => 'Price nett',
    'rgls_aant_gelev' => 'Delivered',
    'rgls_aant_fakt' => 'Invoiced',
    'rgls_statistnr' => 'rgls_statistnr',
    'rgls_taric' => 'rgls_taric',
    'rgls_landoorspr' => 'Country of Origin',
    'rgls_IntrastatEnabled' => 'rgls_IntrastatEnabled',
    'rgls_bdr_ev_ed_val' => 'Total Netto VAT',
    'rgls_bdr_vat_val' => 'rgls_bdr_vat_val',
    'rgls_bdr_val' => 'Total Nett',
    'rgls_gewicht_bi' => 'Weight',
];

reportApi(fn (string $nummer) => reportCallProcedure('[005_Data_Geeve].[dbo].[SP_orders_naar_Excel]', '@ordernr', $nummer, ORDER_OFFERTE_COLUMNS));
