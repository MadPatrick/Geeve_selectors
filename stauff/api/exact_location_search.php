<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

require_once dirname(__DIR__) . '/inc/db.php';

/**
 * Live zoekfunctie op artikelnummer voor 1 samenstellingslocatie (1, 2, 3,
 * 4 of 5) - de standaard, altijd actieve bron voor kandidaat-artikelen in
 * assets/selector.js (rebuildComponents()/populateExtraArticleSelect()).
 * Er wordt geen CSV meer gebruikt; het tandwiel/zoekfilter per locatie is
 * alleen nog een optionele handmatige override van de standaard-
 * voorvoegsels (PREFIXES_BY_POSITION).
 *
 * $prefixes ("SP;SPAL;SPV", ; -gescheiden) - artikelen moeten met 1 van
 * deze voorvoegsels BEGINNEN (LIKE 'PREFIX%'), niet fuzzy/contains: de
 * standaardwaarde komt uit PREFIXES_BY_POSITION in assets/selector.js, met
 * het tandwiel/zoekfilter als optionele handmatige override. Speciale
 * waarde "__DIGIT__" (gebruikt voor locatie 2, Beugel-als-extra-regel):
 * artikelen moeten met een CIJFER beginnen (LIKE '[0-9]%'), net als de
 * hoofd-beugelzoekopdracht in exact_search.php - Beugel-artikelen hebben
 * geen letter-voorvoegsel.
 *
 * $material (optioneel, altijd de hele materiaalFAMILIE als ;-lijst, bijv.
 * "W1;W2;W3" voor Staal of "W4;W5;W55" voor RVS - er is geen apart vooraf
 * gekozen exacte W-code meer) - artikelen moeten 1 van deze materiaalcodes
 * ook BEVATTEN (LIKE '%MATERIAAL%'). De Staal/RVS-switch is de enige
 * materiaal-filter; de specifieke W-code volgt uit welk artikel de
 * gebruiker per locatie kiest (ook voor locatie 6 zelf, dat is nu een
 * gewone pulldown met alleen de familie-codes).
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
$materialParam = trim((string) ($_GET['material'] ?? ''));
$group = trim((string) ($_GET['group'] ?? ''));

if ($prefixesParam === '') {
    echo json_encode(['ok' => true, 'count' => 0, 'rows' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$digitFirst = $prefixesParam === '__DIGIT__';
$prefixes = $digitFirst ? [] : array_values(array_unique(array_filter(
    array_map('trim', explode(';', $prefixesParam)),
    static fn(string $prefix): bool => $prefix !== ''
)));
if (!$digitFirst && $prefixes === []) {
    echo json_encode(['ok' => true, 'count' => 0, 'rows' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$materials = array_values(array_unique(array_filter(
    array_map('trim', explode(';', $materialParam)),
    static fn(string $material): bool => $material !== ''
)));

try {
    $pdo = getPdoConnection();
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}

$params = ['groep' => STAUFF_ITEM_GROUP];

if ($digitFirst) {
    // Beugel-artikelen (locatie 2) hebben geen letter-voorvoegsel - zelfde
    // patroon als de hoofd-beugelzoekopdracht in exact_search.php.
    $codeCondition = "ItemCode LIKE '[0-9]%'";
} else {
    $prefixConditions = [];
    foreach ($prefixes as $index => $prefix) {
        // LIKE-jokertekens (%, _) in een door de gebruiker ingevoerd voorvoegsel
        // moeten als letterlijke tekens behandeld worden, anders kan een
        // toevallige % of _ de query laten afwijken van "begint met".
        $prefixConditions[] = "ItemCode LIKE :prefix{$index}";
        $params["prefix{$index}"] = escapeLikeLiteral($prefix) . '%';
    }
    $codeCondition = '(' . implode(' OR ', $prefixConditions) . ')';
}

$sql = 'SELECT TOP 50 ItemCode, [Item Description] FROM GRV_SalesItems ' .
    'WHERE [Item Group] = :groep AND ' . $codeCondition;

if ($materials !== []) {
    $materialConditions = [];
    foreach ($materials as $index => $materialValue) {
        $materialConditions[] = "ItemCode LIKE :material{$index}";
        $params["material{$index}"] = '%' . escapeLikeLiteral($materialValue) . '%';
    }
    $sql .= ' AND (' . implode(' OR ', $materialConditions) . ')';
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
