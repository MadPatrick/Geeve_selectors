<?php

declare(strict_types=1);

/** Inkooporder naar Excel: voert dbo.SP_Alle_Orders_met_regelsplus_inkoop (database Exactrapportage) uit voor één ordernummer. */

require_once __DIR__ . '/../../inc.php';

reportApi(fn (string $nummer) => reportCallProcedure('[Exactrapportage].[dbo].[SP_Alle_Orders_met_regelsplus_inkoop]', '@ordernummer', $nummer));
