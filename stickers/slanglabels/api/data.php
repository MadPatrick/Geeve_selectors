<?php

declare(strict_types=1);

// Beveiligde tegel (Config > Tegels): zonder ontgrendelen geen gegevens.
require_once __DIR__ . '/../../../shared/tiles.php';
tileApiGuard('stickers-slanglabels');

/**
 * Slanglabels: per slang van één order het slangnummer, uw referentie, lengte, datum, klantnaam, aantal en notitie.
 *
 * Bron: alleen de slangkaart-app (shared/slangkaartapp.php). De oude views [1655 VW_Alle_OrdersGH] en
 * [1655 VW_Alle_OrdersGH2] worden niet meer gebruikt (Patrick 2026-11-13): ze zetten voor klant 546 "SN" en voor
 * klant 666 "VR" voor het nummer, waardoor slangen naast de GH-nummers uit de app dubbel op het label kwamen
 * (order 36023869: 30 + 30). Het slangnummer is nu overal het GH-nummer.
 */

require_once __DIR__ . '/../../../reports/inc.php';
require_once __DIR__ . '/../../../shared/slangkaartapp.php';

/** Notitie op één regel (regeleinden en dubbele spaties worden een spatie). */
function slanglabelNotitieTekst($v): string
{
    return trim((string) preg_replace('/\s+/', ' ', (string) ($v ?? '')));
}

reportApi(function (string $nummer): array {
    $rows = [];
    foreach (slangkaartAppRegels(['ordernr' => $nummer]) as $r) {
        $datum = trim((string) ($r['orddat'] ?? ''));
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $datum, $m) === 1) {
            $datum = $m[3] . '-' . $m[2] . '-' . $m[1];
        }
        $aantal = $r['Aantal'] ?? '';
        $rows[] = [
            'ordernummer' => trim((string) ($r['ordernr'] ?? '')),
            'slangnummer' => trim((string) ($r['GHnr'] ?? '')),
            'referentie' => trim((string) ($r['Uw_referentie'] ?? '')),
            'datum' => $datum,
            'klantnaam' => trim((string) ($r['nm'] ?? '')),
            'aantal' => is_numeric($aantal) ? (string) (int) round((float) $aantal) : '',
            // De app geeft de lengte in meters (2,65): omrekenen naar millimeters.
            'lengte' => is_numeric($r['Lengte'] ?? null) ? (string) (int) round((float) $r['Lengte'] * 1000) : trim((string) ($r['Lengte'] ?? '')),
            'artnr_kl' => '',
            'notitie' => slanglabelNotitieTekst($r['Notitie'] ?? ''),
        ];
    }

    return ['rows' => $rows];
});
