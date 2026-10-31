<?php

declare(strict_types=1);

// Tegel-toegang (Config > Tegels): zonder ontgrendelen geen gegevens.
require_once __DIR__ . '/../../../shared/tiles.php';
tileApiGuard('barcode');

/**
 * Barcode inkoopontvangst, opzoeken (alleen lezen): leverancier van de inkooporder en per artikel besteld, ontvangen en open aantal.
 * Bron: orkrg (kop) + orsrg (regels) in database 005, zoals Inkoop per Artikelgroep: inkooporder = orkrg.ord_soort 'B',
 * besteld = orsrg.esr_aantal, ontvangen = orsrg.aant_gelev (open = verschil, zoals de open verkooporders in Stauff).
 * Er wordt niets geboekt; zie docs/BARCODE-ONTVANGST.md.
 */

require_once __DIR__ . '/../../../reports/inc.php';

/** orkrg.ord_soort van een inkooporder. */
const BARCODE_INKOOP_SOORT = 'B';

/** Eerste gevulde tekstwaarde van de genoemde kolommen (die kolomnamen zijn niet allemaal op de server bevestigd). */
function barcodeEerste(array $row, array $names): string
{
    foreach ($names as $name) {
        $v = trim((string) ($row[$name] ?? ''));
        if ($v !== '') {
            return $v;
        }
    }
    return '';
}

reportApi(function (array $in): array {
    $order = reportOrderNumber($in['order'] ?? '');
    $artikel = trim((string) ($in['artikel'] ?? ''));
    if ($artikel === '' || strlen($artikel) > 40) {
        throw new RuntimeException('Vul een artikelnummer in (maximaal 40 tekens).');
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    $pdo = reportConnection();

    $stmt = $pdo->prepare('SET NOCOUNT ON; SELECT TOP 1 * FROM orkrg WITH (NOLOCK) WHERE LTRIM(RTRIM(ordernr)) = ? AND ord_soort = ?');
    $stmt->execute([$order, BARCODE_INKOOP_SOORT]);
    $kop = $stmt->fetch();
    if ($kop === false) {
        throw new RuntimeException('Inkooporder ' . $order . ' niet gevonden.');
    }

    $leverancier = barcodeEerste($kop, ['ord_debtor_name', 'ord_creditor_name']);
    if ($leverancier === '') {
        $nr = barcodeEerste($kop, ['crdnr', 'debnr']);
        if ($nr !== '') {
            $stmt = $pdo->prepare('SET NOCOUNT ON; SELECT TOP 1 RTRIM(cmp_name) AS naam FROM cicmpy WITH (NOLOCK) WHERE LTRIM(RTRIM(debnr)) = ? OR LTRIM(RTRIM(crdnr)) = ?');
            $stmt->execute([$nr, $nr]);
            $c = $stmt->fetch();
            $leverancier = $c === false ? '' : trim((string) $c['naam']);
        }
    }

    $stmt = $pdo->prepare(
        'SET NOCOUNT ON; SELECT RTRIM(r.artcode) AS artcode, RTRIM(i.Description) AS omschrijving, ' .
        'SUM(ISNULL(r.esr_aantal, 0)) AS besteld, SUM(ISNULL(r.aant_gelev, 0)) AS ontvangen ' .
        'FROM orsrg r WITH (NOLOCK) LEFT JOIN Items i WITH (NOLOCK) ON i.ItemCode = r.artcode ' .
        'WHERE LTRIM(RTRIM(r.ordernr)) = ? AND UPPER(LTRIM(RTRIM(r.artcode))) = UPPER(?) ' .
        'GROUP BY RTRIM(r.artcode), RTRIM(i.Description)'
    );
    $stmt->execute([$order, $artikel]);
    $regel = $stmt->fetch();
    if ($regel === false) {
        // Leverancier wel meegeven, zodat de kop klopt ook als het artikel niet op deze order staat.
        echo json_encode(['ok' => false, 'leverancier' => $leverancier, 'error' => 'Artikel ' . $artikel . ' staat niet op inkooporder ' . $order . '.'], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    $besteld = (float) $regel['besteld'];
    $ontvangen = (float) $regel['ontvangen'];
    return [
        'leverancier' => $leverancier,
        'artikel' => trim((string) $regel['artcode']),
        'omschrijving' => trim((string) $regel['omschrijving']),
        'besteld' => $besteld,
        'ontvangen' => $ontvangen,
        'open' => max(0.0, $besteld - $ontvangen),
    ];
}, false);
