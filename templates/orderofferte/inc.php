<?php

declare(strict_types=1);

/**
 * Gedeelde onderdelen van Export Order/Offerte naar Excel/XML: de gegevens ophalen (api/data.php voor het scherm en de Excel,
 * api/xml.php voor het XML-bestand) en het XML-bestand bouwen. Beide knoppen gebruiken dus dezelfde bron.
 * Order of Offerte: voert dbo.SP_orders_naar_Excel (data-database, standaard 005_Data_Geeve) uit voor één order-/offertenummer
 * en toont dezelfde kolommen (en namen) als de Excel-sjabloon.
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


/**
 * Eerste gevulde waarde van een van de kolommen uit de procedure (de kolomnaam van de orderkop is niet overal gelijk).
 *
 * @param list<string> $kandidaten
 */
function orderOfferteKopWaarde(array $raw, array $kandidaten): string
{
    foreach ($kandidaten as $kolom) {
        $v = reportFirstValue($raw, $kolom);
        if ($v !== null) {
            return $v;
        }
    }
    return '';
}

/**
 * Haalt de order/offerte op en geeft de tabelgegevens voor het scherm/Excel terug, plus 'kop' (orderkop voor de XML).
 *
 * @return array<string,mixed>
 */
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
    $data['currency'] = ['Price', 'Price nett', 'Total Nett', 'Total incl. VAT']; // bedragen in euro tonen
    $data['percent'] = ['Discount'];
    $data['text'] = ['Partnumber']; // artikelnummer: celopmaak Tekst in Excel
    // Excel-formules (per rij): {Kolomnaam} wordt de cel van die kolom; percentagekolommen staan in Excel als breuk.
    $data['formulas'] = [
        'Price nett' => '{Price}*(1-{Discount})',
        'Total Nett' => '{Price nett}*{Amount}',
        'Total incl. VAT' => '{Total Nett}*(1+{VAT}/100)',
    ];
    // Orderkop voor de XML. Alleen kolommen die de procedure echt teruggeeft komen erin; de rest blijft leeg.
    $data['kop'] = [
        'soort' => $label,
        'ordernummer' => orderOfferteKopWaarde($raw, ['kop_ordernr']) ?: $nummer,
        'datum' => orderOfferteKopWaarde($raw, ['kop_orddat', 'kop_ord_datum', 'kop_ord_date', 'kop_datum']),
        'klantnummer' => orderOfferteKopWaarde($raw, ['kop_debnr', 'kop_ord_debtor', 'kop_ord_debtor_nr', 'kop_ord_debtor_code']),
        'klantnaam' => (string) $klant,
        'referentie' => orderOfferteKopWaarde($raw, ['kop_refer', 'kop_ord_refer']),
        'uw_referentie' => orderOfferteKopWaarde($raw, ['kop_docnumber']),
    ];
    return $data;
}

/** Waarde uit een rij op kolomnaam; getallen netjes, leeg als de kolom ontbreekt. */
function orderOfferteXmlGetal(mixed $v, int $decimalen): string
{
    return is_numeric($v) ? number_format((float) $v, $decimalen, '.', '') : '';
}

/** XML-tekst (UTF-8) voor een order of offerte; DOMDocument zorgt voor de escaping van speciale tekens. */
function orderOfferteXmlBouwen(array $data): string
{
    $doc = new DOMDocument('1.0', 'UTF-8');
    $doc->formatOutput = true;
    $root = $doc->createElement('Order');
    $doc->appendChild($root);

    $voegToe = static function (DOMElement $ouder, string $naam, string $tekst) use ($doc): void {
        // Stuurtekens die in XML 1.0 niet mogen, eruit halen.
        $schoon = (string) preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $tekst);
        $el = $doc->createElement($naam);
        $el->appendChild($doc->createTextNode($schoon));
        $ouder->appendChild($el);
    };

    $k = $data['kop'];
    $kop = $doc->createElement('Orderkop');
    $root->appendChild($kop);
    $voegToe($kop, 'Soort', $k['soort']);
    $voegToe($kop, 'Ordernummer', $k['ordernummer']);
    $voegToe($kop, 'Orderdatum', preg_match('/^(\d{4}-\d{2}-\d{2})/', $k['datum'], $m) === 1 ? $m[1] : $k['datum']);
    $voegToe($kop, 'Klantnummer', $k['klantnummer']);
    $voegToe($kop, 'Klantnaam', $k['klantnaam']);
    $voegToe($kop, 'Referentie', $k['referentie']);
    $voegToe($kop, 'UwReferentie', $k['uw_referentie']);

    $col = array_flip($data['columns']);
    $waarde = static fn (array $row, string $naam): mixed => isset($col[$naam]) ? $row[$col[$naam]] : null;
    $regels = $doc->createElement('Orderregels');
    $root->appendChild($regels);
    foreach ($data['rows'] as $row) {
        $regel = $doc->createElement('Orderregel');
        $regel->setAttribute('nr', trim((string) $waarde($row, 'Line')));
        $regels->appendChild($regel);
        $voegToe($regel, 'Artikelcode', trim((string) $waarde($row, 'Partnumber')));
        $voegToe($regel, 'Omschrijving', trim((string) $waarde($row, 'Description')));
        $voegToe($regel, 'Aantal', is_numeric($waarde($row, 'Amount')) ? (string) (round((float) $waarde($row, 'Amount'), 4) + 0) : '');
        $voegToe($regel, 'Prijs', orderOfferteXmlGetal($waarde($row, 'Price'), 2));
        // Korting staat in de procedure als breuk (0,05 = 5 %); in de XML als percentage.
        $korting = $waarde($row, 'Discount');
        $voegToe($regel, 'KortingPercentage', is_numeric($korting) ? number_format((float) $korting * 100, 2, '.', '') : '');
        $voegToe($regel, 'NettoPrijs', orderOfferteXmlGetal($waarde($row, 'Price nett'), 2));
        $voegToe($regel, 'TotaalNetto', orderOfferteXmlGetal($waarde($row, 'Total Nett'), 2));
        $voegToe($regel, 'Geleverd', orderOfferteXmlGetal($waarde($row, 'Delivered'), 4));
        $voegToe($regel, 'Gefactureerd', orderOfferteXmlGetal($waarde($row, 'Invoiced'), 4));
        $voegToe($regel, 'HSCode', trim((string) $waarde($row, 'HS code')));
        $voegToe($regel, 'LandVanHerkomst', trim((string) $waarde($row, 'Country of Origin')));
        $voegToe($regel, 'Gewicht', orderOfferteXmlGetal($waarde($row, 'Weight'), 3));
    }
    return (string) $doc->saveXML();
}
