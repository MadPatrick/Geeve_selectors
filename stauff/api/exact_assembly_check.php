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
 * LET OP - NOG TE VERIFIËREN: de samenstellingscode is een door deze app
 * zelf opgebouwde weergave-string (zie updateAssemblyCode() in
 * assets/selector.js), géén bevestigde Exact-conventie voor hoe Stauff
 * een vooraf samengesteld kit-artikel zijn ItemCode geeft. Zolang dat niet
 * bevestigd is, proberen we een paar aannemelijke varianten (met/zonder
 * spaties, met/zonder koppeltekens) tegen GRV_SalesItems.ItemCode, net
 * zoals exact_prices.php dat voor de prijskolom doet - welke variant (als
 * die er is) een match gaf staat in de response ("matchedVariant"), zodat
 * dat met de klant bevestigd/aangepast kan worden. Bewust GEEN filter op
 * [Item Group] = 67: een compleet kit-artikel hoort mogelijk in een
 * andere artikelgroep dan de losse beugel-onderdelen.
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

// Kandidaat-varianten van de samenstellingscode, van "precies zoals
// getoond" tot "alle spaties/koppeltekens weg" - zie docblock hierboven.
$variants = array_values(array_unique(array_filter([
    $code,
    str_replace(' ', '', $code),
    str_replace(['-', ' '], '', $code),
])));

try {
    $pdo = getPdoConnection();
} catch (Throwable $exception) {
    echo json_encode(['ok' => true, 'found' => false, 'item' => null], JSON_UNESCAPED_UNICODE);
    exit;
}

$placeholders = [];
$params = [];
foreach ($variants as $index => $variant) {
    $placeholders[] = ":v{$index}";
    $params["v{$index}"] = $variant;
}
$inClause = implode(', ', $placeholders);

try {
    $stmt = $pdo->prepare(
        "SELECT ItemCode, [Item Description] FROM GRV_SalesItems WHERE ItemCode IN ({$inClause})"
    );
    $stmt->execute($params);
    $row = $stmt->fetch();
} catch (Throwable $exception) {
    echo json_encode(['ok' => true, 'found' => false, 'item' => null], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($row === false) {
    echo json_encode(['ok' => true, 'found' => false, 'item' => null], JSON_UNESCAPED_UNICODE);
    exit;
}

$matchedItemCode = (string) $row['ItemCode'];
$matchedVariant = null;
foreach ($variants as $index => $variant) {
    if (strcasecmp($variant, $matchedItemCode) === 0) {
        $matchedVariant = $index === 0 ? 'exact' : ($index === 1 ? 'zonder-spaties' : 'zonder-koppeltekens-en-spaties');
        break;
    }
}

echo json_encode([
    'ok' => true,
    'found' => true,
    'item' => [
        'ItemCode' => $matchedItemCode,
        'Description' => (string) ($row['Item Description'] ?? ''),
    ],
    'matchedVariant' => $matchedVariant,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
