<?php

declare(strict_types=1);

// Beveiligde tegel (Config > Tegels): zonder ontgrendelen geen gegevens.
require_once __DIR__ . '/../../../shared/tiles.php';
tileApiGuard('stickers-slanglabels');

/**
 * Slanglabels: per slang van één order het slangnummer, uw referentie, lengte, datum, klantnaam en aantal.
 * Zelfde SQL Server en inloggegevens (via Config) als /stauff en de rapporten.
 *
 * Bron: de slangkaart-app plus view [1655 VW_Alle_OrdersGH] (dezelfde query als de oude rapportbouwer), net als de 1002-labelviews in database
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

/** Sleutel om dezelfde slang uit de view en uit de app te herkennen: het nummer zonder de prefix GH, SN of VR. */
function slanglabelSleutel(string $nummer): string
{
    return strtoupper(trim(preg_replace('/^\s*(GH|SN|VR)\s+/i', '', $nummer) ?? $nummer));
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
 * @return array<string, string> slanglabelSleutel() van het slangnummer => notitie
 */
function slanglabelNotities(string $nummer): array
{
    $uit = [];
    try {
        $stmt = reportConnection('stickers-slanglabels')->prepare('SET NOCOUNT ON; SELECT [GHnr], [Notitie] FROM '
            . geeveDbNaamSql('slangkaarten') . '.[dbo].[2500 Slangkaarten bij order] WHERE LTRIM(RTRIM([ordernr])) = ?');
        $stmt->execute([$nummer]);
        foreach ($stmt->fetchAll() as $r) {
            // Sleutel zonder GH/SN/VR-voorvoegsel: de view zet voor sommige klanten SN of VR voor het nummer, GHnr heeft GH.
            $sleutel = slanglabelSleutel((string) ($r['GHnr'] ?? ''));
            if ($sleutel !== '') {
                $uit[$sleutel] = slanglabelNotitieTekst($r['Notitie'] ?? '');
            }
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

    $stmt = reportConnection('stickers-slanglabels')->prepare('SET NOCOUNT ON; ' . slanglabelSql());
    $stmt->execute([$nummer]);

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
        $rows[] = $out;
    }

    // Een order kan half in de app en half in de oude database staan: alles tonen. Staat een slang in beide, dan alleen
    // die uit de app. De view zet voor klant 546 "SN" en voor klant 666 "VR" voor het nummer, de app heeft "GH": met
    // slanglabelSleutel() gelden die als dezelfde slang (order 36023869: anders 30 + 30).
    if ($uitApp !== []) {
        $inApp = [];
        foreach ($uitApp as $r) {
            $sleutel = slanglabelSleutel($r['slangnummer']);
            if ($sleutel !== '') {
                $inApp[$sleutel] = true;   // een slang zonder nummer mag geen andere lege-nummer-rijen wegfilteren
            }
        }
        $rows = array_values(array_filter($rows, static fn(array $r): bool => !isset($inApp[slanglabelSleutel((string) $r['slangnummer'])])));
    }

    // De notitie van de oude slangkaarten (extra, trage query) alleen ophalen voor slangen die niet uit de app komen:
    // de app geeft de notitie zelf mee.
    if ($rows !== []) {
        $notities = slanglabelNotities($nummer);
        foreach ($rows as $i => $r) {
            $rows[$i]['notitie'] = $notities[slanglabelSleutel((string) $r['slangnummer'])] ?? '';
        }
    }
    $rows = array_merge($uitApp, $rows);

    return ['rows' => $rows];
});
