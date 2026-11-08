<?php

declare(strict_types=1);

// Tegel-toegang (Config > Tegels): zonder ontgrendelen geen gegevens.
require_once __DIR__ . '/../../../shared/tiles.php';
tileApiGuard('backorder');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

require_once __DIR__ . '/../../../reports/inc.php';

// Zoeklogica staat gedeeld in reports/inc.php (reportKlantenZoeken), ook voor de andere tegel met een klantkeuze.
reportKlantenZoeken('backorder');
