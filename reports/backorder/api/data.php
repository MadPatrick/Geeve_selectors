<?php

declare(strict_types=1);

// Beveiligde tegel (Config > Tegels): zonder ontgrendelen geen gegevens.
require_once __DIR__ . '/../../../shared/tiles.php';
tileApiGuard('backorder');

/**
 * Backorder: openstaande verkooporderregels van een klant (besteld, nog niet (volledig) geleverd). Alleen lezen.
 * Zelfde bron en definitie van "open" als Negatieve voorraad: orsrg (esr_aantal > aant_gelev) bij orkrg.ord_soort = 'V', plus Items voor
 * de omschrijving. Tekstregels (artikel 30, "binnen xx dagen") worden niet getoond, zoals in de Orderhistorie.
 * Let op: de kolomnamen orkrg.debnr/refer/docnumber/orddat en orsrg.afldat zijn afgeleid van orhkrg/orhsrg en nog niet tegen de echte database getoetst.
 */

require_once __DIR__ . '/../../inc.php';

const BACKORDER_MAX = 20000;

reportApi(function (array $in): array {
    $debnr = trim((string) ($in['debnr'] ?? ''));
    if ($debnr === '' || strlen($debnr) > 20) {
        throw new RuntimeException('Kies eerst een klant uit de lijst.');
    }

    // Sessie niet vergrendeld houden tijdens een lange query.
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    $pdo = reportConnection('backorder');

    $stmt = $pdo->prepare(
        'SET NOCOUNT ON; SELECT TOP ' . (BACKORDER_MAX + 1) . ' k.ordernr, k.orddat, LTRIM(RTRIM(k.refer)), LTRIM(RTRIM(k.docnumber)), ' .
        'LTRIM(RTRIM(s.artcode)), LTRIM(RTRIM(i.Description)), s.esr_aantal, s.aant_gelev, s.esr_aantal - s.aant_gelev, s.afldat ' .
        'FROM orsrg s WITH (NOLOCK) INNER JOIN orkrg k WITH (NOLOCK) ON k.ordernr = s.ordernr ' .
        'LEFT JOIN Items i WITH (NOLOCK) ON i.ItemCode = s.artcode ' .
        "WHERE k.ord_soort = 'V' AND LTRIM(RTRIM(k.debnr)) = ? AND s.esr_aantal > s.aant_gelev " .
        "AND LTRIM(RTRIM(s.artcode)) <> '30' AND ISNULL(i.Description, '') NOT LIKE 'binnen%dagen%' " .
        'ORDER BY k.ordernr, s.artcode'
    );
    $stmt->setAttribute(PDO::SQLSRV_ATTR_QUERY_TIMEOUT, 120);
    $stmt->execute([$debnr]);

    $datum = static function (mixed $v): ?string {
        if ($v === null || $v === '') {
            return null;
        }
        if ($v instanceof DateTimeInterface) {
            return $v->format('Y-m-d');
        }
        return preg_match('/^(\d{4}-\d{2}-\d{2})/', (string) $v, $m) === 1 ? $m[1] : trim((string) $v);
    };
    $getal = static fn (mixed $v): int|float => is_numeric($v) ? round((float) $v, 4) + 0 : 0;

    $rows = [];
    $orders = [];
    while ($r = $stmt->fetch(PDO::FETCH_NUM)) {
        $orders[trim((string) $r[0])] = true;
        $rows[] = [
            trim((string) $r[0]), $datum($r[1]), trim((string) $r[2]), trim((string) $r[3]), trim((string) $r[4]), trim((string) $r[5]),
            $getal($r[6]), $getal($r[7]), $getal($r[8]), $datum($r[9]),
        ];
    }
    $capped = count($rows) > BACKORDER_MAX;
    if ($capped) {
        array_pop($rows);
    }

    $kop = [['label' => 'Klant', 'value' => trim((string) ($in['klant'] ?? '')) ?: $debnr], ['label' => 'Orders', 'value' => (string) count($orders)]];
    if ($capped) {
        $kop[] = ['label' => 'Let op', 'value' => 'alleen de eerste ' . BACKORDER_MAX . ' regels'];
    }

    return [
        'columns' => ['Ordernr', 'Orderdatum', 'Referentie', 'Uw referentie', 'Artikelnr', 'Omschrijving', 'Besteld', 'Geleverd', 'Openstaand', 'Afleverdatum'],
        'numeric' => [false, false, false, false, false, false, true, true, true, false],
        'dates' => [false, true, false, false, false, false, false, false, false, true],
        'klantNaam' => trim((string) ($in['klant'] ?? '')),
        'kop' => $kop,
        'rows' => $rows,
    ];
}, false);
