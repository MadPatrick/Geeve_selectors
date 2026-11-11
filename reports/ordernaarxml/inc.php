<?php

declare(strict_types=1);

/**
 * Order naar XML: leest één verkooporder (orkrg + orsrg + Items + cicmpy, database 005; alleen lezen) en maakt er een XML-bestand van.
 * Zelfde bron en selectie als Artikelen per Verkooporder (stickers/order): orkrg.ord_soort = 'V', geen tekstregels (ar_soort <> 'P').
 * Prijs = netto orderprijs (orsrg.prijs_n), eenheid = Items.eenh_verk. Let op: kolomnamen zijn afgeleid van bestaande tegels en nog niet
 * tegen de echte database getoetst voor deze tegel (prijs_n en eenh_verk staan al in Inkoop per Artikelgroep).
 */
require_once __DIR__ . '/../inc.php';

/**
 * @return array{kop: array<string,string>, regels: list<array<string,string>>}|null null = order niet gevonden (of geen verkooporder)
 */
function orderNaarXmlOphalen(string $nummer): ?array
{
    $pdo = reportConnection('order-naar-xml');

    $kop = null;
    // Eerst op de kolom zelf (index); een ordernummer kan spaties vooraan hebben, dan volgt dezelfde zoekopdracht met LTRIM/RTRIM.
    foreach (['k.ordernr = ?', 'LTRIM(RTRIM(k.ordernr)) = ?'] as $where) {
        $stmt = $pdo->prepare(
            'SET NOCOUNT ON; SELECT TOP 1 k.ordernr, k.orddat, k.debnr, c.cmp_name, k.refer, k.docnumber ' .
            'FROM orkrg k WITH (NOLOCK) LEFT JOIN cicmpy c WITH (NOLOCK) ON c.debnr = k.debnr ' .
            "WHERE {$where} AND k.ord_soort = 'V'"
        );
        $stmt->execute([$nummer]);
        $kop = $stmt->fetch(PDO::FETCH_NUM);
        if ($kop !== false) {
            break;
        }
    }
    if ($kop === false || $kop === null) {
        return null;
    }
    $ordernr = (string) $kop[0]; // exact zoals in Exact opgeslagen (met eventuele opvulspaties), voor de regelquery
    $datum = $kop[1] instanceof DateTimeInterface ? $kop[1]->format('Y-m-d') : (preg_match('/^(\d{4}-\d{2}-\d{2})/', (string) $kop[1], $m) === 1 ? $m[1] : trim((string) $kop[1]));

    $stmt = $pdo->prepare(
        'SET NOCOUNT ON; SELECT s.regel, s.artcode, ISNULL(NULLIF(LTRIM(RTRIM(i.Description)), \'\'), s.oms45), s.esr_aantal, s.prijs_n, i.eenh_verk ' .
        'FROM orsrg s WITH (NOLOCK) LEFT JOIN Items i WITH (NOLOCK) ON i.ItemCode = s.artcode ' .
        "WHERE s.ordernr = ? AND s.ar_soort <> 'P'"
    );
    $stmt->execute([$ordernr]);
    $regels = [];
    while ($r = $stmt->fetch(PDO::FETCH_NUM)) {
        $regels[] = [
            'regel' => trim((string) $r[0]),
            'artikelcode' => trim((string) $r[1]),
            'omschrijving' => trim((string) $r[2]),
            'aantal' => is_numeric($r[3]) ? (string) (round((float) $r[3], 4) + 0) : '',
            'prijs' => is_numeric($r[4]) ? number_format((float) $r[4], 2, '.', '') : '',
            'eenheid' => trim((string) $r[5]),
        ];
    }
    usort($regels, static fn (array $a, array $b): int => strnatcmp($a['regel'], $b['regel']));

    return [
        'kop' => [
            'ordernummer' => trim($ordernr),
            'orderdatum' => $datum,
            'klantnummer' => trim((string) $kop[2]),
            'klantnaam' => trim((string) $kop[3]),
            'referentie' => trim((string) $kop[4]),
            'uw_referentie' => trim((string) $kop[5]),
        ],
        'regels' => $regels,
    ];
}

/** XML-tekst (UTF-8) voor één order; DOMDocument zorgt voor de escaping van speciale tekens. */
function orderNaarXmlBouwen(array $order): string
{
    $doc = new DOMDocument('1.0', 'UTF-8');
    $doc->formatOutput = true;
    $root = $doc->createElement('Order');
    $doc->appendChild($root);

    $voegToe = static function (DOMElement $ouder, string $naam, string $tekst) use ($doc): DOMElement {
        // Stuurtekens die in XML 1.0 niet mogen, eruit halen.
        $schoon = (string) preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $tekst);
        $el = $doc->createElement($naam);
        $el->appendChild($doc->createTextNode($schoon));
        $ouder->appendChild($el);
        return $el;
    };

    $kop = $doc->createElement('Orderkop');
    $root->appendChild($kop);
    $voegToe($kop, 'Ordernummer', $order['kop']['ordernummer']);
    $voegToe($kop, 'Orderdatum', $order['kop']['orderdatum']);
    $voegToe($kop, 'Klantnummer', $order['kop']['klantnummer']);
    $voegToe($kop, 'Klantnaam', $order['kop']['klantnaam']);
    $voegToe($kop, 'Referentie', $order['kop']['referentie']);
    $voegToe($kop, 'UwReferentie', $order['kop']['uw_referentie']);

    $regels = $doc->createElement('Orderregels');
    $root->appendChild($regels);
    foreach ($order['regels'] as $nr => $r) {
        $regel = $doc->createElement('Orderregel');
        $regel->setAttribute('nr', (string) ($nr + 1));
        $regels->appendChild($regel);
        $voegToe($regel, 'Artikelcode', $r['artikelcode']);
        $voegToe($regel, 'Omschrijving', $r['omschrijving']);
        $voegToe($regel, 'Aantal', $r['aantal']);
        $voegToe($regel, 'Prijs', $r['prijs']);
        $voegToe($regel, 'Eenheid', $r['eenheid']);
    }
    return (string) $doc->saveXML();
}
