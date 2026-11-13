<?php

declare(strict_types=1);

// Beveiligde tegel (Config > Tegels): zonder ontgrendelen geen gegevens.
require_once __DIR__ . '/../../../shared/tiles.php';
tileApiGuard('order-offerte');

/** Download van het XML-bestand van een order of offerte (formulier-POST met nummer en CSRF-token). Alleen lezen. */
require_once __DIR__ . '/../inc.php';
require_once __DIR__ . '/../../../shared/csrf.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !csrfValid($_POST['csrf'] ?? null)) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Ongeldig verzoek (sessie verlopen). Laad de pagina opnieuw.';
    exit;
}
csrfReleaseSession();

try {
    $nummer = reportOrderNumber($_POST['nummer'] ?? '');
    $data = orderOfferteOphalen($nummer);
    if ($data['rows'] === []) {
        throw new RuntimeException('Geen regels gevonden voor ' . $nummer . '.');
    }
    $xml = orderOfferteXmlBouwen($nummer, $data);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo geeveFoutTekst($e);
    exit;
}

header('Content-Type: application/xml; charset=utf-8');
header('Content-Disposition: attachment; filename="' . (($data['label'] ?? 'Order') === 'Offerte' ? 'offerte' : 'order') . '-' . $nummer . '.xml"');
header('Cache-Control: no-store');
header('Content-Length: ' . strlen($xml));
echo $xml;
