<?php

declare(strict_types=1);

// Beveiligde tegel (Config > Tegels): zonder ontgrendelen geen gegevens.
require_once __DIR__ . '/../../../shared/tiles.php';
tileApiGuard('stickers-productie');

/**
 * Productiestickers per verkooporder: de productieorderregels van één verkooporder uit de stored procedure
 * SP_productie_label (data-database, standaard 005_Data_Geeve) @ordernr (dezelfde als de oude rapportbouwer), in de volgorde van de procedure.
 * Zelfde SQL Server en inloggegevens (via Config) als /stauff en de rapporten.
 */

require_once __DIR__ . '/../../../reports/inc.php';
require_once __DIR__ . '/../../../slkprinten/inc/db.php';
require_once __DIR__ . '/../../../slkprinten/inc/queries.php';
require_once __DIR__ . '/../../../slkprinten/inc/picklijst.php';

/**
 * Veld => mogelijke kolomnamen uit de procedure (vergeleken zonder hoofdletters, spaties en underscores), zodat zowel
 * "ARTIKEL OMSCHRIJVING" als "PO_ARTIKEL_OMSCHRIJVING" werkt.
 */
const PRODUCTIE_COLUMNS = [
    'ordernr' => ['ordernr', 'poordernr'],
    'project' => ['moederproject', 'pomoederproject'],
    'debiteur' => ['orddebtorname', 'podebtorname', 'debtorname'],
    'locatie' => ['locatie', 'powarehouselocation', 'warehouselocation'],
    'looplocatie' => ['looplocatie', 'polooplocatie'],
    'artikel' => ['artikel', 'poartikel'],
    'omschrijving' => ['artikelomschrijving', 'poartikelomschrijving'],
    'aantal' => ['aantal', 'poprodtot', 'prodtot'],
];

reportApi(function (string $nummer): array {
    $data = reportCallProcedure(geeveDbNaamSql('data') . '.[dbo].[SP_productie_label]', '@ordernr', $nummer, null, 'stickers-productie');

    $key = fn (string $name): string => strtolower(str_replace([' ', '_'], '', $name));
    $index = [];
    foreach ($data['columns'] as $i => $name) {
        $index[$key($name)] = $i;
    }
    $pick = [];
    foreach (PRODUCTIE_COLUMNS as $field => $names) {
        foreach ($names as $name) {
            if (isset($index[$name])) {
                $pick[$field] = $index[$name];
                break;
            }
        }
        if (!isset($pick[$field]) && $field !== 'project') {
            throw new RuntimeException('SP_productie_label geeft geen kolom voor "' . $field . '" terug (wel: ' . implode(', ', $data['columns']) . ').');
        }
    }

    $rows = [];
    foreach ($data['rows'] as $row) {
        $out = [];
        foreach (array_keys(PRODUCTIE_COLUMNS) as $field) {
            $v = isset($pick[$field]) ? $row[$pick[$field]] : null;
            $out[$field] = $v === null ? '' : trim((string) $v);
        }
        $rows[] = $out;
    }

    return ['rows' => productieAantallenAlsPicklijst($rows, $nummer)];
});

/**
 * Het aantal op de sticker is gelijk aan de picklijst van Slangkaarten printen (slkprinten, picklijstAantallen()). De procedure
 * telt bij slangregels zonder productieorder 1 slang in plaats van het bestelde aantal (order 36023662: 77296-36-12 gaf 4, de
 * picklijst 12). Alleen artikelen die één keer in de uitkomst staan en ook op de picklijst voorkomen worden vervangen; de rest
 * (en alles, als de slangkaarten niet te lezen zijn) blijft zoals de procedure het geeft.
 *
 * @param list<array<string, string>> $rows
 * @return list<array<string, string>>
 */
function productieAantallenAlsPicklijst(array $rows, string $nummer): array
{
    try {
        $pdo = getPdoConnection();
        $keys = [];
        foreach (findHoseLinesByOrder($pdo, $nummer) as $line) {
            $keys[] = pick($line, HOSE_KEY_COLUMNS);
        }
        if ($keys === []) {
            return $rows;
        }
        $totalen = [];
        foreach (picklijstAantallen(findHoseCardsByKeys($pdo, $keys, $nummer)) as $artikel => $aantal) {
            $totalen[strtoupper(trim((string) $artikel))] = $aantal;
        }

        $voorkomen = [];
        foreach ($rows as $row) {
            $k = strtoupper(trim($row['artikel']));
            $voorkomen[$k] = ($voorkomen[$k] ?? 0) + 1;
        }
        foreach ($rows as $i => $row) {
            $k = strtoupper(trim($row['artikel']));
            if (($voorkomen[$k] ?? 0) === 1 && isset($totalen[$k]) && $totalen[$k] > 0) {
                $rows[$i]['aantal'] = rtrim(rtrim(number_format($totalen[$k], 4, '.', ''), '0'), '.');
            }
        }
    } catch (Throwable $e) {
        // Slangkaarten niet bereikbaar: de aantallen uit de procedure blijven staan.
    }

    return $rows;
}
