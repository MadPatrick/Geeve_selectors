<?php

declare(strict_types=1);

// Beveiligde tegel (Config > Tegels): zonder ontgrendelen geen gegevens.
require_once __DIR__ . '/../../../shared/tiles.php';
tileApiGuard('order-offerte');

require_once __DIR__ . '/../inc.php';

reportApi(static fn (string $nummer): array => orderOfferteOphalen($nummer));
