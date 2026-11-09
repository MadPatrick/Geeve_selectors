<?php

declare(strict_types=1);

// Beveiligde tegel (Config > Tegels): zonder ontgrendelen geen gegevens.
require_once __DIR__ . '/../../../shared/tiles.php';
tileApiGuard('stickers-order');

/**
 * Artikelstickers per Verkooporder: de regels van één verkooporder, rechtstreeks uit Exact (database 005: orkrg + orsrg + Items,
 * voorrd/evloc voor de locatie, cicmpy en ItemAccounts voor de debiteur en "uw artikelnummer"). Zelfde kolommen en dezelfde
 * koppelingen als SP_Artikelstickers_voor_op_zakjes, maar zonder de tijdelijke tabel temp_Artikel_Stikkers_order: die wordt
 * elke 5 minuten opnieuw opgebouwd en bevat alleen openstaande orders (afgehandld = 0), dus een afgehandelde of zeer nieuwe order gaf
 * "Geen regels gevonden".
 * Bewuste afwijking van de procedure: geen filter op afgehandld. Welke regels nog te leveren zijn, bepaalt de schakelaar Compleet/Backorders.
 * Overig gelijk: orkrg.ord_soort = 'V', regels met ar_soort <> 'P' (geen tekstregels), debnr gevuld.
 */

require_once __DIR__ . '/../../../reports/inc.php';

const STICKER_COLUMNS = [
    'looplocatie', 'bonnr', 'locatie', 'debnr', 'debnm', 'uw_ref', 'artikelnummer', 'artikelnaam', 'besteld', 'geleverd',
    'ord_rgl', 'uw_artikelnummer', 'ia_CountryOfOrigin', 'soort', 'artgrpFin',
];

/** SELECT voor de orderregels; $where is een vaste voorwaarde op het ordernummer (nooit gebruikersinvoer, alleen ? als parameter). */
function stickerOrderSql(string $where): string
{
    return 'SET NOCOUNT ON; SELECT ' .
        'el.TextField1 AS looplocatie, okop.ordernr AS bonnr, el.maglok AS locatie, okop.debnr AS debnr, c.cmp_name AS debnm, ' .
        'okop.docnumber AS uw_ref, org.artcode AS artikelnummer, org.oms45 AS artikelnaam, org.esr_aantal AS besteld, ' .
        'org.aant_gelev AS geleverd, org.regel AS ord_rgl, ia.ItemCodeAccount AS uw_artikelnummer, ia.CountryOfOrigin AS ia_CountryOfOrigin, ' .
        'i.Class_04 AS soort, ' .
        'CASE WHEN i.Assortment >= 0 AND i.Assortment < 10000 THEN RIGHT(\'0000\' + CONVERT(varchar(4), i.Assortment), 4) ELSE \'\' END AS artgrpFin ' .
        'FROM orkrg AS okop WITH (NOLOCK) ' .
        'JOIN orsrg AS org WITH (NOLOCK) ON okop.ordernr = org.ordernr ' .
        'LEFT JOIN items AS i WITH (NOLOCK) ON org.artcode = i.ItemCode ' .
        'LEFT JOIN voorrd AS vrd WITH (NOLOCK) ON i.ItemCode = vrd.artcode AND vrd.magcode = \'1\' ' .
        'LEFT JOIN evloc AS el WITH (NOLOCK) ON vrd.maglok = el.maglok ' .
        'LEFT JOIN cicmpy AS c WITH (NOLOCK) ON okop.debnr = c.debnr ' .
        'LEFT JOIN ItemAccounts AS ia WITH (NOLOCK) ON c.cmp_wwn = ia.AccountCode AND org.artcode = ia.ItemCode ' .
        "WHERE {$where} AND okop.ord_soort = 'V' AND org.ar_soort <> 'P' AND okop.debnr IS NOT NULL";
}

reportApi(function (string $nummer): array {
    $pdo = reportConnection('stickers-order');

    // Eerst de snelle zoekopdracht op de kolom zelf (index). Een enkel ordernummer heeft spaties vooraan: vindt de snelle niets,
    // dan volgt dezelfde zoekopdracht met LTRIM/RTRIM, zodat het resultaat gelijk blijft (zie templates/barcode/api/zoek.php).
    $stmt = $pdo->prepare(stickerOrderSql('okop.ordernr = ?'));
    $stmt->execute([$nummer]);
    $data = $stmt->fetchAll();
    if ($data === []) {
        $stmt = $pdo->prepare(stickerOrderSql('LTRIM(RTRIM(okop.ordernr)) = ?'));
        $stmt->execute([$nummer]);
        $data = $stmt->fetchAll();
    }

    $rows = [];
    foreach ($data as $row) {
        $out = [];
        foreach (STICKER_COLUMNS as $c) {
            $v = $row[$c] ?? null;
            $out[$c] = $v instanceof DateTimeInterface ? $v->format('Y-m-d') : ($v === null ? '' : trim((string) $v));
        }
        $rows[] = $out;
    }

    if ($rows === []) {
        return ['rows' => [], 'hint' => stickerOrderHint($pdo, $nummer)];
    }

    // Ordervolgorde: op regelnummer (numeriek, ook als de kolom tekst is).
    usort($rows, fn ($a, $b) => strnatcmp($a['ord_rgl'], $b['ord_rgl']));

    return ['rows' => $rows];
});

/** Bij een lege uitkomst: bestaat de order wel in Exact, en van welke soort? (alleen lezen) */
function stickerOrderHint(PDO $pdo, string $nummer): string
{
    try {
        $st = $pdo->prepare('SET NOCOUNT ON; SELECT TOP 1 ord_soort, afgehandld FROM orkrg WITH (NOLOCK) WHERE LTRIM(RTRIM(ordernr)) = ?');
        $st->execute([$nummer]);
        $r = $st->fetch();
        if ($r === false) {
            return 'order niet gevonden in Exact.';
        }
        $soort = trim((string) $r['ord_soort']);
        return $soort === 'V'
            ? 'order bestaat, maar heeft geen artikelregels (alleen tekstregels of geen debiteur).'
            : 'dit is geen verkooporder (soort ' . $soort . ').';
    } catch (Throwable $e) {
        return 'order niet te controleren.';
    }
}
