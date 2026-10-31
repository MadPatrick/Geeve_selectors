<?php

declare(strict_types=1);

// Beveiligde tegel (Config > Tegels): zonder ontgrendelen geen gegevens.
require_once __DIR__ . '/../../../shared/tiles.php';
tileApiGuard('artikel-import');

/**
 * Artikelgegevens Import: controleert artikelcodes, leveranciers (crediteuren) en artikelgroepen tegen Exact en geeft de
 * huidige waarden uit Exact terug, zodat het dashboard ze naast de nieuwe waarden kan tonen.
 * POST met csrf, items[], suppliers[] en groups[]; antwoord:
 * {"ok":true,
 *  "items":{"CODE":{"found":true,"description":"...","status":"A","weight":"1.5","price":"12.3","group":"10",
 *                   "accounts":[{"supplier":"100011","itemCode":"...","price":"..","size":".."}]}},
 *  "suppliers":{"CODE":{"found":true,"name":"...","nr":"100011","code":"..."}},
 *  "groups":{"CODE":{"found":true,"description":"..."}},
 *  "warnings":["..."]}
 * Kolommen die niet in elke Exact-installatie hetzelfde heten (gewicht, status, inkoopprijs) worden uit sys.columns bepaald;
 * lukt een deel niet, dan komt er een waarschuwing en blijft de rest werken. Alleen lezen; niets wordt in Exact gewijzigd.
 */

require_once __DIR__ . '/../../inc.php';
require_once __DIR__ . '/../../../shared/csrf.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !csrfValid($_POST['csrf'] ?? null)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Ongeldig verzoek (sessie verlopen). Laad de pagina opnieuw.']);
    exit;
}
csrfReleaseSession(); // sessie vrijgeven: trage verwerking mag andere tegels niet blokkeren

/** Niet-lege, unieke waarden uit een POST-lijst (sleutel = hoofdletters). @return array<string,string> */
function artikelImportList(string $name, int $maxLength): array
{
    $out = [];
    foreach ((array) ($_POST[$name] ?? []) as $value) {
        $value = trim((string) $value);
        if ($value !== '' && strlen($value) <= $maxLength) {
            $out[strtoupper($value)] = $value;
        }
    }
    if (count($out) > 2000) {
        throw new RuntimeException('Te veel waarden in één controle (maximaal 2000).');
    }
    return $out;
}

/** Eerste bestaande kolom uit de kandidaten (hoofdletterongevoelig), of null. */
function artikelImportColumn(PDO $pdo, string $table, array $candidates): ?string
{
    static $cache = [];
    if (!isset($cache[$table])) {
        $stmt = $pdo->prepare('SELECT name FROM sys.columns WHERE object_id = OBJECT_ID(?)');
        $stmt->execute([$table]);
        $cache[$table] = array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }
    foreach ($candidates as $candidate) {
        foreach ($cache[$table] as $column) {
            if (strcasecmp($column, $candidate) === 0) {
                return $column;
            }
        }
    }
    return null;
}

/** Getal uit SQL als tekst met punt, zonder overbodige nullen ('' als leeg). */
function artikelImportNumber(mixed $value): string
{
    if ($value === null || !is_numeric($value)) {
        return '';
    }
    $text = rtrim(rtrim(number_format((float) $value, 6, '.', ''), '0'), '.');
    return $text === '-0' ? '0' : $text;
}

$flags = JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE;
try {
    $pdo = reportConnection();
    $out = ['ok' => true, 'items' => [], 'suppliers' => [], 'groups' => [], 'warnings' => []];

    // ---- Artikelen: bestaan ze, met omschrijving, status, gewicht en artikelgroep ----
    $codes = artikelImportList('items', 30);
    $items = [];
    if ($codes !== []) {
        $condition = artikelImportColumn($pdo, 'Items', ['Condition']);
        $weight = artikelImportColumn($pdo, 'Items', ['NetWeight', 'WeightNet', 'Weight']);
        $select = 'RTRIM(ItemCode) AS c, RTRIM(Description) AS d, LTRIM(RTRIM(Assortment)) AS g'
            . ($condition !== null ? ', RTRIM([' . $condition . ']) AS s' : ", '' AS s")
            . ($weight !== null ? ', [' . $weight . '] AS w' : ', NULL AS w');
        foreach (array_chunk(array_values($codes), 500) as $chunk) {
            $stmt = $pdo->prepare('SET NOCOUNT ON; SELECT ' . $select . ' FROM Items WHERE ItemCode IN (' . implode(',', array_fill(0, count($chunk), '?')) . ')');
            $stmt->execute($chunk);
            foreach ($stmt->fetchAll() as $row) {
                $items[strtoupper(trim((string) $row['c']))] = [
                    'found' => true,
                    'code' => trim((string) $row['c']),
                    'description' => trim((string) $row['d']),
                    'status' => trim((string) $row['s']),
                    'weight' => artikelImportNumber($row['w']),
                    'price' => '',
                    'group' => trim((string) $row['g']),
                    'accounts' => [],
                ];
            }
        }
    }

    if ($items !== []) {
        $found = array_values(array_map(static fn ($i) => $i['code'], $items));

        // Verkoopprijs: de standaard verkoopprijs (prijslijst "salesprice" in staffl, zoals de MSP-query), de nieuwste geldige.
        try {
            $validFrom = artikelImportColumn($pdo, 'staffl', ['validfrom']);
            foreach (array_chunk($found, 500) as $chunk) {
                $stmt = $pdo->prepare(
                    "SET NOCOUNT ON; SELECT RTRIM(artcode) AS c, prijs83 AS p FROM staffl WHERE prijslijst = 'salesprice' AND artcode IN ("
                    . implode(',', array_fill(0, count($chunk), '?')) . ')'
                    . ($validFrom !== null ? ' AND (validfrom IS NULL OR validfrom <= GETDATE()) ORDER BY validfrom' : '')
                );
                $stmt->execute($chunk);
                foreach ($stmt->fetchAll() as $row) {
                    $key = strtoupper(trim((string) $row['c']));
                    if (isset($items[$key])) {
                        $items[$key]['price'] = artikelImportNumber($row['p']); // gesorteerd op validfrom: de laatste wint
                    }
                }
            }
        } catch (Throwable $e) {
            $out['warnings'][] = 'Verkoopprijs uit Exact kon niet worden opgehaald: ' . geeveFoutTekst($e);
        }

        // Leveranciergegevens per artikel (ItemAccounts, gekoppeld op crdnr).
        try {
            $supplierItem = artikelImportColumn($pdo, 'ItemAccounts', ['ItemCodeAccount']);
            $purchasePrice = artikelImportColumn($pdo, 'ItemAccounts', ['PurchasePrice', 'PurchPrice', 'Price']);
            $orderSize = artikelImportColumn($pdo, 'ItemAccounts', ['PurchaseOrderSize', 'OrderSize']);
            $main = artikelImportColumn($pdo, 'ItemAccounts', ['MainAccount', 'IsMainAccount', 'Main']);
            $select = 'RTRIM(ia.ItemCode) AS c, LTRIM(RTRIM(ia.crdnr)) AS nr, RTRIM(cm.cmp_name) AS naam'
                . ($supplierItem !== null ? ', RTRIM(ia.[' . $supplierItem . ']) AS ic' : ", '' AS ic")
                . ($purchasePrice !== null ? ', ia.[' . $purchasePrice . '] AS pp' : ', NULL AS pp')
                . ($orderSize !== null ? ', ia.[' . $orderSize . '] AS os' : ', NULL AS os')
                . ($main !== null ? ', CAST(ia.[' . $main . '] AS int) AS m' : ', 0 AS m');
            foreach (array_chunk($found, 500) as $chunk) {
                $stmt = $pdo->prepare(
                    'SET NOCOUNT ON; SELECT ' . $select . ' FROM ItemAccounts ia'
                    . ' LEFT JOIN cicmpy cm ON LTRIM(RTRIM(cm.crdnr)) = LTRIM(RTRIM(ia.crdnr))'
                    . " WHERE LTRIM(RTRIM(ISNULL(ia.crdnr, ''))) <> '' AND ia.ItemCode IN (" . implode(',', array_fill(0, count($chunk), '?')) . ')'
                );
                $stmt->execute($chunk);
                foreach ($stmt->fetchAll() as $row) {
                    $key = strtoupper(trim((string) $row['c']));
                    if (!isset($items[$key])) {
                        continue;
                    }
                    $items[$key]['accounts'][] = [
                        'supplier' => trim((string) $row['nr']),
                        'name' => trim((string) $row['naam']),
                        'itemCode' => trim((string) $row['ic']),
                        'price' => artikelImportNumber($row['pp']),
                        'size' => artikelImportNumber($row['os']),
                        'main' => (int) $row['m'] === 1,
                    ];
                }
            }
        } catch (Throwable $e) {
            $out['warnings'][] = 'Leveranciergegevens uit Exact konden niet worden opgehaald: ' . geeveFoutTekst($e);
        }
    }
    foreach ($items as $key => $item) {
        unset($items[$key]['code']);
    }
    $out['items'] = (object) $items;

    // ---- Leveranciers (crediteuren): crdnr of crdcode in cicmpy ----
    $suppliers = [];
    foreach (artikelImportList('suppliers', 20) as $key => $code) {
        $stmt = $pdo->prepare(
            'SET NOCOUNT ON; SELECT TOP 1 RTRIM(cmp_name) AS naam, LTRIM(RTRIM(crdnr)) AS nr, LTRIM(RTRIM(crdcode)) AS code FROM cicmpy'
            . ' WHERE LTRIM(RTRIM(crdnr)) = ? OR LTRIM(RTRIM(crdcode)) = ?'
        );
        $stmt->execute([$code, $code]);
        $row = $stmt->fetch();
        $suppliers[$key] = $row === false
            ? ['found' => false, 'name' => '', 'nr' => '', 'code' => '']
            : ['found' => true, 'name' => trim((string) $row['naam']), 'nr' => trim((string) $row['nr']), 'code' => trim((string) $row['code'])];
    }
    $out['suppliers'] = (object) $suppliers;

    // ---- Artikelgroepen (ItemAssortment) ----
    $groups = [];
    foreach (artikelImportList('groups', 20) as $key => $code) {
        $stmt = $pdo->prepare('SET NOCOUNT ON; SELECT TOP 1 ISNULL(RTRIM(Description_0), \'\') AS d FROM ItemAssortment WHERE LTRIM(RTRIM(Assortment)) = ?');
        $stmt->execute([$code]);
        $row = $stmt->fetch();
        $groups[$key] = $row === false ? ['found' => false, 'description' => ''] : ['found' => true, 'description' => trim((string) $row['d'])];
    }
    $out['groups'] = (object) $groups;

    echo json_encode($out, $flags);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => geeveFoutTekst($e)], $flags);
}
