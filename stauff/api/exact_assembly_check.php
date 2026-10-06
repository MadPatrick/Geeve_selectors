<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

require_once dirname(__DIR__) . '/inc/db.php';

/**
 * Controleert of de samenstelling die de gebruiker net heeft opgebouwd
 * (de samenstellingscode, bijv. "SP-215 PP-SIG-DP-AS-W3") al als compleet,
 * kant-en-klaar artikel in Exact bestaat - i.p.v. apart bij elke losse
 * locatie (1-6) te moeten bestellen.
 *
 * Exacte match, geen varianten: de code moet LETTERLIJK (incl. spaties,
 * koppeltekens en de volgorde van de onderdelen zoals updateAssemblyCode()
 * die opbouwt, locatie 1 t/m 6) overeenkomen met GRV_SalesItems.ItemCode.
 * Bewust GEEN filter op [Item Group] = 67: een compleet kit-artikel hoort
 * mogelijk in een andere artikelgroep dan de losse beugel-onderdelen.
 *
 * Geeft bij een connectiefout of 0 matches gewoon found=false terug
 * (geen foutmelding op de pagina) - de rest van de configurator blijft
 * normaal werken.
 */
$code = trim((string) ($_GET['code'] ?? ''));
if ($code === '') {
    echo json_encode(['ok' => true, 'found' => false, 'item' => null], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo = getPdoConnection();
} catch (Throwable $exception) {
    echo json_encode(['ok' => true, 'found' => false, 'item' => null], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $stmt = $pdo->prepare(
        'SELECT ItemCode, [Item Description] FROM GRV_SalesItems WHERE ItemCode = :code'
    );
    $stmt->execute(['code' => $code]);
    $row = $stmt->fetch();
} catch (Throwable $exception) {
    echo json_encode(['ok' => true, 'found' => false, 'item' => null], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($row === false) {
    echo json_encode(['ok' => true, 'found' => false, 'item' => null], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'ok' => true,
    'found' => true,
    'item' => [
        'ItemCode' => (string) $row['ItemCode'],
        'Description' => (string) ($row['Item Description'] ?? ''),
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
