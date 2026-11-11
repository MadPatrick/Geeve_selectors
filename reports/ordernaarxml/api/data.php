<?php

declare(strict_types=1);

// Beveiligde tegel (Config > Tegels): zonder ontgrendelen geen gegevens.
require_once __DIR__ . '/../../../shared/tiles.php';
tileApiGuard('order-naar-xml');

/** Voorbeeld op het scherm: orderkop en orderregels van één verkooporder. Alleen lezen. */
require_once __DIR__ . '/../inc.php';

reportApi(function (string $nummer): array {
    $order = orderNaarXmlOphalen($nummer);
    if ($order === null) {
        throw new RuntimeException('Verkooporder ' . $nummer . ' niet gevonden in Exact.');
    }
    return $order;
});
