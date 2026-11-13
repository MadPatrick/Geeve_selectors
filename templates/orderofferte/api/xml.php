<?php

declare(strict_types=1);

// Beveiligde tegel (Config > Tegels): zonder ontgrendelen geen gegevens.
require_once __DIR__ . '/../../../shared/tiles.php';
tileApiGuard('order-offerte');

/** Download van het XML-bestand (formulier-POST met ordernummer en CSRF-token). Zelfde bron als de Excel. Alleen lezen. */
require_once __DIR__ . '/../inc.php';
require_once __DIR__ . '/../../../shared/csrf.php';
require_once __DIR__ . '/../../../shared/progress.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !csrfValid($_POST['csrf'] ?? null)) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Ongeldig verzoek (sessie verlopen). Laad de pagina opnieuw.';
    exit;
}
csrfReleaseSession();
@set_time_limit(300);

try {
    $nummer = reportOrderNumber($_POST['nummer'] ?? '');
    $data = orderOfferteOphalen($nummer);
    if ($data['rows'] === []) {
        throw new RuntimeException('Geen regels gevonden voor ' . $nummer . '.');
    }
    $xml = orderOfferteXmlBouwen($data);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo geeveFoutTekst($e);
    exit;
}

geeveProgressDownloadDone();   // laat de voortgangsbalk weten dat het bestand begint
header('Content-Type: application/xml; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $data['kop']['soort'] . '_' . preg_replace('/\D/', '', $nummer) . '.xml"');
header('Cache-Control: no-store');
header('Content-Length: ' . strlen($xml));
echo $xml;
