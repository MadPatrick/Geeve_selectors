<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

require_once dirname(__DIR__) . '/inc/db.php';

/**
 * Live zoekfunctie op artikelnummer voor 1 samenstellingslocatie (1, 3, 4
 * of 5 - zie de config-cog per locatierij in index.php/selector.js), als
 * alternatief voor de CSV-gedreven candidatesForPosition() in
 * assets/selector.js.
 *
 * $prefixes ("SP;SPAL;SPV", ; -gescheiden) - artikelen moeten met 1 van
 * deze voorvoegsels BEGINNEN (LIKE 'PREFIX%'), niet fuzzy/contains: de
 * gebruiker configureert hiermee zelf welke artikelcode-prefixes bij die
 * locatie horen (vroeger hardcoded in candidatesForPosition()).
 *
 * $material (optioneel, bijv. "W1") - artikelen moeten deze materiaalcode
 * ook BEVATTEN (LIKE '%MATERIAAL%'), overeenkomend met de op dat moment
 * gekozen Materiaalcode (locatie 6).
 *
 * $group (optioneel, bijv. "GR10") - de volledige bouwgroep-tag zoals die
 * ook uit de omschrijving van de GEKOZEN BEUGEL gehaald is (zie
 * extractGroupTag() in assets/selector.js, aangeroepen in
 * selectExactArticle() met de omschrijving die al uit de live
 * Exact-zoekopdracht komt - dus geen CSV-Bouwgroep, alles blijft uit
 * Exact). Artikelen moeten deze tag ook in hun eigen [Item Description]
 * hebben - dezelfde plek waar Exact de bouwgroep van élk artikelgroep-67-
 * artikel toont (niet alleen beugels). Dit is de fijnslag bovenop
 * $prefixes/$material: zonder deze check kan een voorvoegsel ook
 * artikelen uit een andere bouwgroep opleveren.
 *
 * "<group> " of "<group>" (einde van de tekst) - niet zomaar "%<group>%"
 * - anders matcht bouwgroep "GR10" ook per ongeluk artikelen uit
 * bouwgroep "GR100" (zelfde soort ambiguïteit als destijds bij de
 * Stauff-artikelcodes zelf, zie eerdere sessie-geschiedenis).
 */
const STAUFF_ITEM_GROUP = '67';

function escapeLikeLiteral(string $value): string
{
    return str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $value);
}

/** Haalt de eerste "GRx"-bouwgroepaanduiding uit een Exact-omschrijving. */
function extractGroupTag(string $description): string
{
    // \d direct na "GR" is bewust verplicht - anders matcht dit ook gewone
    // woorden die met "gr" beginnen (GROEP, GRIJS, GROOT, ...).
    return preg_match('/\bGR\d[A-Za-z0-9]*\b/', $description, $matches) === 1 ? $matches[0] : '';
}

$prefixesParam = trim((string) ($_GET['prefixes'] ?? ''));
$material = trim((string) ($_GET['material'] ?? ''));
$group = trim((string) ($_GET['group'] ?? ''));

if ($prefixesParam === '') {
    echo json_encode(['ok' => true, 'count' => 0, 'rows' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$prefixes = array_values(array_unique(array_filter(
    array_map('trim', explode(';', $prefixesParam)),
    static fn(string $prefix): bool => $prefix !== ''
)));
if ($prefixes === []) {
    echo json_encode(['ok' => true, 'count' => 0, 'rows' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo = getPdoConnection();
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}

$prefixConditions = [];
$params = ['groep' => STAUFF_ITEM_GROUP];
foreach ($prefixes as $index => $prefix) {
    // LIKE-jokertekens (%, _) in een door de gebruiker ingevoerd voorvoegsel
    // moeten als letterlijke tekens behandeld worden, anders kan een
    // toevallige % of _ de query laten afwijken van "begint met".
    $prefixConditions[] = "ItemCode LIKE :prefix{$index}";
    $params["prefix{$index}"] = escapeLikeLiteral($prefix) . '%';
}

$sql = 'SELECT TOP 50 ItemCode, [Item Description] FROM GRV_SalesItems ' .
    'WHERE [Item Group] = :groep AND (' . implode(' OR ', $prefixConditions) . ')';

if ($material !== '') {
    $sql .= ' AND ItemCode LIKE :material';
    $params['material'] = '%' . escapeLikeLiteral($material) . '%';
}

if ($group !== '') {
    $groupTag = escapeLikeLiteral($group);
    $sql .= ' AND ([Item Description] LIKE :groupMid OR [Item Description] LIKE :groupEnd)';
    $params['groupMid'] = '%' . $groupTag . ' %';
    $params['groupEnd'] = '%' . $groupTag;
}

$sql .= ' ORDER BY ItemCode';

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rawRows = $stmt->fetchAll();
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}

$rows = [];
foreach ($rawRows as $row) {
    $description = (string) ($row['Item Description'] ?? '');
    $rows[] = [
        'ItemCode' => (string) $row['ItemCode'],
        'Group' => extractGroupTag($description),
    ];
}

echo json_encode([
    'ok' => true,
    'count' => count($rows),
    'rows' => $rows,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
