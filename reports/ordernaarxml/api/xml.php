<?php

declare(strict_types=1);

// Beveiligde tegel (Config > Tegels): zonder ontgrendelen geen gegevens.
require_once __DIR__ . '/../../../shared/tiles.php';
tileApiGuard('order-naar-xml');

/** Download van het XML-bestand (formulier-POST met ordernummer en CSRF-token). Alleen lezen. */
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
    $order = orderNaarXmlOphalen($nummer);
    if ($order === null) {
        throw new RuntimeException('Verkooporder ' . $nummer . ' niet gevonden in Exact.');
    }
    $xml = orderNaarXmlBouwen($order);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo geeveFoutTekst($e);
    exit;
}

header('Content-Type: application/xml; charset=utf-8');
header('Content-Disposition: attachment; filename="order-' . preg_replace('/\D/', '', $nummer) . '.xml"');
header('Cache-Control: no-store');
header('Content-Length: ' . strlen($xml));
echo $xml;
