<?php

declare(strict_types=1);

// Beveiligde tegel (Config > Tegels): zonder ontgrendelen geen gegevens.
require_once __DIR__ . '/../../../shared/tiles.php';
tileApiGuard('stickers-slanglabels');

/**
 * Slanglabels: per slang van één order het slangnummer, uw referentie, lengte, datum, klantnaam en aantal.
 * Zelfde SQL Server en inloggegevens (via Config) als /stauff en de rapporten.
 *
 * Bron: view [1655 VW_Alle_OrdersGH] (dezelfde query als de oude rapportbouwer), net als de 1002-labelviews in database
 * Exactrapportage. ordernummer kan een char-kolom met opvulspaties zijn: daarom LTRIM/RTRIM.
 */

require_once __DIR__ . '/../../../reports/inc.php';

const SLANGLABEL_SQL = 'SELECT [ordernummer], [artnr_slangnummer], [UwReferentie_slangnummer2], [datum], [Klantnaam], [Aantal],'
    . ' [Lengte_Slangnummer3], [Artnr_KL] FROM [Exactrapportage].[dbo].[1655 VW_Alle_OrdersGH]'
    . ' WHERE LTRIM(RTRIM([ordernummer])) = ? ORDER BY [ordernummer], [regel]';

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

reportApi(function (string $nummer): array {
    $stmt = reportConnection()->prepare('SET NOCOUNT ON; ' . SLANGLABEL_SQL);
    $stmt->execute([$nummer]);

    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $out = array_fill_keys(array_values(SLANGLABEL_COLUMNS), '');
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
        $rows[] = $out;
    }

    return ['rows' => $rows];
});
