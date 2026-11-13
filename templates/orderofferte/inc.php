<?php

declare(strict_types=1);

/**
 * Order of Offerte naar Excel/XML: gedeelde logica voor api/data.php (scherm + Excel) en api/xml.php (XML-bestand).
 * Voert dbo.SP_orders_naar_Excel (data-database, standaard 005_Data_Geeve) uit voor één order-/offertenummer en toont dezelfde kolommen (en namen) als de Excel-sjabloon.
 */

require_once __DIR__ . '/../../reports/inc.php';

const ORDER_OFFERTE_COLUMNS = [
    'rgls_regel' => 'Line',
    'rgls_esr_aantal' => 'Amount',
    'rgls_artcode' => 'Partnumber',
    'rgls_oms45' => 'Description',
    'rgls_prijs83' => 'Price',
    'rgls_korting' => 'Discount',
    'rgls_prijs_n' => 'Price nett',
    'rgls_aant_gelev' => 'Delivered',
    'rgls_aant_fakt' => 'Invoiced',
    'rgls_statistnr' => 'HS code',
    'rgls_landoorspr' => 'Country of Origin',
    'rgls_bdr_ev_ed_val' => 'Total Nett',
    'rgls_bdr_vat_val' => 'VAT',
    'rgls_bdr_val' => 'Total incl. VAT',
    'rgls_gewicht_bi' => 'Weight',
];

/**
 * Vult lege velden van de orderregel aan met de artikelgegevens in database 005: statistisch nummer en nettogewicht
 * (Items) en land van herkomst (vrij veld UserField_03, label "Land van herkomst"). Bij jullie staan deze vaak alleen op het artikel en niet op de orderregel.
 *
 * @param array{columns: list<string>, numeric: list<bool>, dates: list<bool>, rows: list<list<string|int|float|null>>} $data
 * @return array{columns: list<string>, numeric: list<bool>, dates: list<bool>, rows: list<list<string|int|float|null>>}
 */
function fillFromItems(array $data): array
{
    $col = array_flip($data['columns']);
    foreach (['Partnumber', 'HS code', 'Country of Origin', 'Weight'] as $need) {
        if (!isset($col[$need])) {
            return $data;
        }
    }

    $codes = [];
    foreach ($data['rows'] as $row) {
        $code = trim((string) $row[$col['Partnumber']]);
        if ($code !== '') {
            $codes[$code] = true;
        }
    }
    if ($codes === []) {
        return $data;
    }

    $pdo = reportConnection('order-offerte');
    $hasWeight = (int) $pdo->query("SELECT COUNT(*) FROM sys.columns WHERE object_id = OBJECT_ID('Items') AND name = 'NetWeight'")->fetchColumn() > 0;
    $weightSql = $hasWeight ? 'NetWeight' : 'NULL';
    $items = [];
    foreach (array_chunk(array_keys($codes), 500) as $chunk) {
        $in = implode(',', array_fill(0, count($chunk), '?'));
        $stmt = $pdo->prepare("SET NOCOUNT ON; SELECT RTRIM(ItemCode) AS c, RTRIM(StatisticalNumber) AS s, RTRIM(UserField_03) AS l, {$weightSql} AS w FROM Items WHERE ItemCode IN ({$in})");
        $stmt->execute($chunk);
        foreach ($stmt->fetchAll() as $r) {
            $items[(string) $r['c']] = $r;
        }
    }

    foreach ($data['rows'] as &$row) {
        $code = trim((string) $row[$col['Partnumber']]);
        $item = $items[$code] ?? null;
        if ($item !== null && trim((string) $row[$col['HS code']]) === '' && trim((string) $item['s']) !== '') {
            $row[$col['HS code']] = trim((string) $item['s']);
        }
        if ($item !== null && (float) $row[$col['Weight']] == 0.0 && is_numeric($item['w']) && (float) $item['w'] > 0) {
            $row[$col['Weight']] = $item['w'] + 0;
        }
        if ($item !== null && trim((string) $item['l']) !== '') {
            $row[$col['Country of Origin']] = trim((string) $item['l']);
        }
    }
    unset($row);

    return $data;
}

/** @return array<string, mixed> gegevens voor scherm/Excel/XML van één order of offerte */
function orderOfferteOphalen(string $nummer): array
{
    $raw = reportCallProcedure(geeveDbNaamSql('data') . '.[dbo].[SP_orders_naar_Excel]', '@ordernr', $nummer, null, 'order-offerte');

    // Titelregels voor de Excel: "Offerte 198572" / "Order 35020024" en de naam van de klant.
    $soort = strtoupper((string) reportFirstValue($raw, 'kop_ord_soort'));
    $label = match (true) {
        $soort === '' => 'Order/Offerte',
        str_starts_with($soort, 'Q'), str_starts_with($soort, 'OFF') => 'Offerte',
        default => 'Order',
    };
    $titles = [$label . ' ' . $nummer];
    $klant = reportFirstValue($raw, 'kop_ord_debtor_name');
    if ($klant !== null) {
        $titles[] = $klant;
    }

    $data = fillFromItems(reportSelect($raw, ORDER_OFFERTE_COLUMNS));
    $data['titles'] = $titles;
    $data['soort'] = $soort;
    $data['label'] = $label; // Order of Offerte (voor de XML)
    $data['klant'] = $klant ?? '';
    $data['currency'] = ['Price', 'Price nett', 'Total Nett', 'Total incl. VAT']; // bedragen in euro tonen
    $data['percent'] = ['Discount'];
    $data['text'] = ['Partnumber']; // artikelnummer: celopmaak Tekst in Excel
    // Excel-formules (per rij): {Kolomnaam} wordt de cel van die kolom; percentagekolommen staan in Excel als breuk.
    $data['formulas'] = [
        'Price nett' => '{Price}*(1-{Discount})',
        'Total Nett' => '{Price nett}*{Amount}',
        'Total incl. VAT' => '{Total Nett}*(1+{VAT}/100)',
    ];
    return $data;
}

/** XML-element voor elke kolom van de Excel (zelfde volgorde en inhoud); namen zonder spaties. */
const ORDER_OFFERTE_XML_TAGS = [
    'Line' => 'Line',
    'Amount' => 'Amount',
    'Partnumber' => 'Partnumber',
    'Description' => 'Description',
    'Price' => 'Price',
    'Discount' => 'Discount',
    'Price nett' => 'PriceNett',
    'Delivered' => 'Delivered',
    'Invoiced' => 'Invoiced',
    'HS code' => 'HSCode',
    'Country of Origin' => 'CountryOfOrigin',
    'Total Nett' => 'TotalNett',
    'VAT' => 'VAT',
    'Total incl. VAT' => 'TotalInclVAT',
    'Weight' => 'Weight',
];

/**
 * XML-tekst (UTF-8) van dezelfde gegevens als op het scherm/in de Excel (orderOfferteOphalen); DOMDocument zorgt voor de escaping.
 *
 * @param array<string, mixed> $data resultaat van orderOfferteOphalen()
 */
function orderOfferteXmlBouwen(string $nummer, array $data): string
{
    $doc = new DOMDocument('1.0', 'UTF-8');
    $doc->formatOutput = true;
    $root = $doc->createElement((string) ($data['label'] ?? 'Order') === 'Offerte' ? 'Offerte' : 'Order');
    $doc->appendChild($root);

    $voegToe = static function (DOMElement $ouder, string $naam, $waarde) use ($doc): void {
        $tekst = is_float($waarde) || is_int($waarde) ? (string) (round((float) $waarde, 4) + 0) : (string) $waarde;
        // Stuurtekens die in XML 1.0 niet mogen, eruit halen.
        $schoon = (string) preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', trim($tekst));
        $el = $doc->createElement($naam);
        $el->appendChild($doc->createTextNode($schoon));
        $ouder->appendChild($el);
    };

    $kop = $doc->createElement('Header');
    $root->appendChild($kop);
    $voegToe($kop, 'Number', $nummer);
    $voegToe($kop, 'Type', (string) ($data['label'] ?? ''));
    $voegToe($kop, 'Customer', (string) ($data['klant'] ?? ''));

    $regels = $doc->createElement('Lines');
    $root->appendChild($regels);
    foreach ($data['rows'] as $row) {
        $regel = $doc->createElement('Line');
        $regels->appendChild($regel);
        foreach ($data['columns'] as $i => $kolom) {
            $tag = ORDER_OFFERTE_XML_TAGS[$kolom] ?? null;
            if ($tag !== null && $tag !== 'Line') {
                $voegToe($regel, $tag, $row[$i] ?? '');
            } elseif ($tag === 'Line') {
                $regel->setAttribute('nr', trim((string) ($row[$i] ?? '')));
            }
        }
    }
    return (string) $doc->saveXML();
}
