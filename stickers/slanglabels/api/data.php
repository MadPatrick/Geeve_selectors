<?php

declare(strict_types=1);

// Beveiligde tegel (Config > Tegels): zonder ontgrendelen geen gegevens.
require_once __DIR__ . '/../../../shared/tiles.php';
tileApiGuard('stickers-slanglabels');

/**
 * Slanglabels: per slang van één order het slangnummer, uw referentie, lengte, datum, klantnaam en aantal.
 * Zelfde SQL Server en inloggegevens (via Config) als /stauff en de rapporten.
 *
 * Bron: eerst de slangkaart-app; staat de order daar niet in, dan view [1655 VW_Alle_OrdersGH] (dezelfde query als de oude rapportbouwer), net als de 1002-labelviews in database
 * Exactrapportage. ordernummer kan een char-kolom met opvulspaties zijn: daarom LTRIM/RTRIM.
 */

require_once __DIR__ . '/../../../reports/inc.php';
require_once __DIR__ . '/../../../shared/slangkaartapp.php';

function slanglabelSql(): string
{
    return 'SELECT [ordernummer], [artnr_slangnummer], [UwReferentie_slangnummer2], [datum], [Klantnaam], [Aantal],'
        . ' [Lengte_Slangnummer3], [Artnr_KL] FROM ' . geeveDbNaamSql('rapportage') . '.[dbo].[1655 VW_Alle_OrdersGH]'
        . ' WHERE LTRIM(RTRIM([ordernummer])) = ? ORDER BY [ordernummer], [regel]';
}

/** Kolomnaam in de bron (zonder hoofdlettergevoeligheid, ook als die met een voorvoegsel begint) => veld in de app. */
const SLANGLABEL_COLUMNS = [
    'ordernummer' => 'ordernummer',
    'artnr_slangnummer' => 'slangnummer',
    'uwreferentie' => 'referentie',
    'datum' => 'datum',
    'klantnaam' => 'klantnaam',
    'aantal' => 'aantal',
    'lengte' => 'lengte',
    'artnr_kl' => 'artnr_kl',
];

/**
 * Notitie van de slangkaart per slangnummer (kolom Notitie van "2500 Slangkaarten bij order", dezelfde bron als de
 * slangkaart in Slangkaarten printen). Lukt de query niet, dan blijft de notitie leeg en werken de labels zoals voorheen.
 *
 * @return array<string, string> HOOFDLETTERS slangnummer => notitie
 */
function slanglabelNotities(string $nummer): array
{
    $uit = [];
    try {
        $stmt = reportConnection('stickers-slanglabels')->prepare('SET NOCOUNT ON; SELECT [GHnr], [Notitie] FROM '
            . geeveDbNaamSql('slangkaarten') . '.[dbo].[2500 Slangkaarten bij order] WHERE LTRIM(RTRIM([ordernr])) = ?');
        $stmt->execute([$nummer]);
        foreach ($stmt->fetchAll() as $r) {
            $uit[strtoupper(trim((string) ($r['GHnr'] ?? '')))] = slanglabelNotitieTekst($r['Notitie'] ?? '');
        }
    } catch (Throwable $e) {
        return [];
    }

    return $uit;
}

/** Notitie op één regel (regeleinden en dubbele spaties worden een spatie). */
function slanglabelNotitieTekst($v): string
{
    return trim((string) preg_replace('/\s+/', ' ', (string) ($v ?? '')));
}

reportApi(function (string $nummer): array {
    $uitApp = [];
    foreach (slangkaartAppRegels(['ordernr' => $nummer]) as $r) {
        $datum = trim((string) ($r['orddat'] ?? ''));
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $datum, $m) === 1) {
            $datum = $m[3] . '-' . $m[2] . '-' . $m[1];
        }
        $aantal = $r['Aantal'] ?? '';
        $uitApp[] = [
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

    // Orders die in de slangkaart-app staan: alleen de app (GH-nummers). De oude view 1655 zet voor klant 546 "SN" en voor
    // klant 666 "VR" voor het nummer en zou dezelfde slang dubbel op het label brengen (order 36023869: 30 + 30).
    if ($uitApp !== []) {
        return ['rows' => $uitApp];
    }

    // Orders van vóór de app staan alleen in de oude database (bijv. order 36020475): daarvoor blijft de view de bron.
    $stmt = reportConnection('stickers-slanglabels')->prepare('SET NOCOUNT ON; ' . slanglabelSql());
    $stmt->execute([$nummer]);

    $notities = slanglabelNotities($nummer);
    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $out = array_fill_keys(array_values(SLANGLABEL_COLUMNS), '');
        $out['notitie'] = '';
        foreach ($row as $col => $v) {
            $key = strtolower((string) $col);
            foreach (SLANGLABEL_COLUMNS as $prefix => $field) {
                if (str_starts_with($key, $prefix) && $out[$field] === '') {
                    if ($v instanceof DateTimeInterface) {
                        $v = $v->format('d-m-Y');
                    } elseif ($field === 'datum' && is_string($v) && preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $v, $m) === 1) {
                        $v = $m[3] . '-' . $m[2] . '-' . $m[1];
                    } elseif ($field === 'aantal' && is_numeric($v)) {
                        $v = (string) (int) round((float) $v);
                    }
                    $out[$field] = $v === null ? '' : trim((string) $v);
                    break;
                }
            }
        }
        // Is in Exact het klantartikelnummer ingevuld, dan staat dat op het label in plaats van de referentie.
        if ($out['artnr_kl'] !== '') {
            $out['referentie'] = $out['artnr_kl'];
        }
        $out['notitie'] = $notities[strtoupper($out['slangnummer'])] ?? '';
        $rows[] = $out;
    }

    return ['rows' => $rows];
});
