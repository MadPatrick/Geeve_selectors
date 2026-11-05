<?php

declare(strict_types=1);

// Beveiligde tegel (Config > Tegels): zonder ontgrendelen geen gegevens.
require_once __DIR__ . '/../../../shared/tiles.php';
tileApiGuard('offerte-import', 'inkooporder-import');

/**
 * Controleert de velden van een offerte- of inkooporder-import tegen Exact: verkoper/inkoper (humres.res_id), debiteur- of
 * crediteurnummer (cicmpy) en artikelcodes (Items; bij inkoop met minimale bestelhoeveelheid `minOrder` van de leverancier). POST met csrf, verkoper, debiteur (het nummer van de debiteur of crediteur),
 * soort (debiteur of crediteur), items[] en (offerte) suppliers[] met leveranciersnummers; antwoord:
 * {"ok":true,"verkoper":{"found":bool,"name":"..."},"partij":{...},"debiteur":{...},"items":{"CODE":{"found":true,"description":"..."}},
 *  "suppliers":{"LEVNR":{"item":"CODE of leeg","matches":["CODE",...]}},"customers":{"KLANTARTIKEL":{"item":"CODE of leeg","matches":[...]}}}
 * (customers_list = artikelnummers van de klant, alleen bij een offerte met een gevonden debiteur)
 * Alleen lezen; niets wordt in Exact gewijzigd.
 */

require_once __DIR__ . '/../../../reports/inc.php';
require_once __DIR__ . '/../../../shared/csrf.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !csrfValid($_POST['csrf'] ?? null)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Ongeldig verzoek (sessie verlopen). Laad de pagina opnieuw.']);
    exit;
}
// Sessie vrijgeven: de controle kan lang duren en een vastgehouden sessielock laat alle andere pagina's van dezelfde gebruiker wachten.
session_write_close();

$flags = JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE;
try {
    @set_time_limit(180); // veel regels: de standaard 30 s is te kort
    $t0 = microtime(true);
    $pdo = reportConnection('offerte-import');
    $out = ['ok' => true];
    $ms = ['verbinding' => (int) round((microtime(true) - $t0) * 1000)];

    // Verkoper: medewerker (humres.res_id).
    $seller = trim((string) ($_POST['verkoper'] ?? ''));
    $out['verkoper'] = ['found' => false, 'name' => ''];
    if ($seller !== '' && preg_match('/^\d{1,9}$/', $seller) === 1) {
        $stmt = $pdo->prepare('SET NOCOUNT ON; SELECT TOP 1 RTRIM(fullname) AS naam FROM humres WHERE res_id = ?');
        $stmt->execute([(int) $seller]);
        $row = $stmt->fetch();
        if ($row !== false) {
            $out['verkoper'] = ['found' => true, 'name' => trim((string) $row['naam'])];
        }
    }

    // Debiteur of crediteur (leverancier): nummer of code in cicmpy.
    $creditor = (string) ($_POST['soort'] ?? 'debiteur') === 'crediteur';
    $party = trim((string) ($_POST['debiteur'] ?? ''));
    $out['partij'] = ['found' => false, 'name' => ''];
    $partyKeys = [];
    if ($party !== '' && strlen($party) <= 20) {
        $sql = $creditor
            ? 'SET NOCOUNT ON; SELECT TOP 1 RTRIM(cmp_name) AS naam, LTRIM(RTRIM(crdnr)) AS nr, LTRIM(RTRIM(crdcode)) AS code FROM cicmpy WHERE LTRIM(RTRIM(crdnr)) = ? OR LTRIM(RTRIM(crdcode)) = ?'
            : 'SET NOCOUNT ON; SELECT TOP 1 RTRIM(cmp_name) AS naam, LTRIM(RTRIM(debnr)) AS nr, LTRIM(RTRIM(debcode)) AS code FROM cicmpy WHERE LTRIM(RTRIM(debnr)) = ? OR LTRIM(RTRIM(debcode)) = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$party, $party]);
        $row = $stmt->fetch();
        if ($row !== false) {
            $out['partij'] = ['found' => true, 'name' => trim((string) $row['naam'])];
            $partyKeys = array_values(array_unique(array_filter([trim((string) $row['nr']), trim((string) $row['code'])], static fn ($v) => $v !== '')));
        }
    }
    $out['debiteur'] = $out['partij']; // oude naam, voor de zekerheid

    // Leveranciersnummers (artikelcode van de leverancier, ItemAccounts.ItemCodeAccount bij een crediteur) -> ons artikelnummer.
    // Eén artikel gevonden: dat artikel; geen of meerdere verschillende artikelen: geen artikel (bij meerdere de kandidaten).
    $suppliers = [];
    $supplierInput = (array) ($_POST['suppliers'] ?? []);
    if (isset($_POST['suppliers_list']) && is_string($_POST['suppliers_list'])) {
        $supplierInput = array_merge($supplierInput, explode("\n", $_POST['suppliers_list']));
    }
    foreach ($supplierInput as $code) {
        $code = trim((string) $code);
        if ($code !== '' && strlen($code) <= 60) {
            $suppliers[strtoupper($code)] = $code;
        }
    }
    if (count($suppliers) > 2000) {
        throw new RuntimeException('Te veel leveranciersnummers in één controle (maximaal 2000).');
    }
    $supplierHits = [];
    $t1 = microtime(true);
    foreach (array_chunk(array_values($suppliers), 2000) as $chunk) {
        $stmt = $pdo->prepare(
            'SET NOCOUNT ON; SELECT DISTINCT LTRIM(RTRIM(ItemCodeAccount)) AS s, RTRIM(ItemCode) AS c FROM ItemAccounts'
            . " WHERE LTRIM(RTRIM(ISNULL(crdnr, ''))) <> '' AND ItemCodeAccount IN (" . implode(',', array_fill(0, count($chunk), 'CAST(? AS varchar(60))')) . ')'
        );
        $stmt->execute($chunk);
        foreach ($stmt->fetchAll() as $row) {
            $item = trim((string) $row['c']);
            if ($item !== '') {
                $supplierHits[strtoupper(trim((string) $row['s']))][strtoupper($item)] = $item;
            }
        }
    }
    $ms['leveranciersnummers'] = (int) round((microtime(true) - $t1) * 1000);
    $supplierOut = [];
    foreach (array_keys($suppliers) as $key) {
        $hits = array_values($supplierHits[$key] ?? []);
        sort($hits);
        $supplierOut[$key] = ['item' => count($hits) === 1 ? $hits[0] : '', 'matches' => $hits];
    }
    $out['suppliers'] = (object) $supplierOut;

    // Artikelklant (artikelcode van de klant, ItemAccounts.ItemCodeAccount bij een debiteur) -> ons artikelnummer. Alleen bij een
    // offerte en alleen voor de ingevulde, gevonden debiteur (een klantartikel hoort bij één klant). Zelfde antwoordvorm als bij
    // leveranciersnummers: één artikel gevonden = dat artikel; geen of meerdere verschillende = geen artikel (bij meerdere de kandidaten).
    $customers = [];
    $customerInput = [];
    if (isset($_POST['customers_list']) && is_string($_POST['customers_list'])) {
        $customerInput = explode("\n", $_POST['customers_list']);
    }
    foreach ($customerInput as $code) {
        $code = trim((string) $code);
        if ($code !== '' && strlen($code) <= 60) {
            $customers[strtoupper($code)] = $code;
        }
    }
    if (count($customers) > 2000) {
        throw new RuntimeException('Te veel artikelnummers van de klant in één controle (maximaal 2000).');
    }
    $customerHits = [];
    $t1b = microtime(true);
    if (!$creditor && $partyKeys !== [] && $customers !== []) {
        $keyMarks = implode(',', array_fill(0, count($partyKeys), '?'));
        foreach (array_chunk(array_values($customers), 1000) as $chunk) {
            $stmt = $pdo->prepare(
                'SET NOCOUNT ON; SELECT DISTINCT LTRIM(RTRIM(ia.ItemCodeAccount)) AS s, RTRIM(ia.ItemCode) AS c FROM ItemAccounts ia'
                . ' JOIN cicmpy c ON ia.AccountCode = c.cmp_wwn'
                . ' WHERE (LTRIM(RTRIM(c.debnr)) IN (' . $keyMarks . ') OR LTRIM(RTRIM(c.debcode)) IN (' . $keyMarks . '))'
                . ' AND ia.ItemCodeAccount IN (' . implode(',', array_fill(0, count($chunk), 'CAST(? AS varchar(60))')) . ')'
            );
            $stmt->execute(array_merge($partyKeys, $partyKeys, $chunk));
            foreach ($stmt->fetchAll() as $row) {
                $item = trim((string) $row['c']);
                if ($item !== '') {
                    $customerHits[strtoupper(trim((string) $row['s']))][strtoupper($item)] = $item;
                }
            }
        }
    }
    $ms['artikelklant'] = (int) round((microtime(true) - $t1b) * 1000);
    $customerOut = [];
    foreach (array_keys($customers) as $key) {
        $hits = array_values($customerHits[$key] ?? []);
        sort($hits);
        $customerOut[$key] = ['item' => count($hits) === 1 ? $hits[0] : '', 'matches' => $hits];
    }
    $out['customers'] = (object) $customerOut;

    // Artikelen: bestaan ze in Exact, met omschrijving (ook de artikelen die bij een leveranciersnummer of artikelklant zijn gevonden).
    $codes = [];
    foreach ((array) ($_POST['items'] ?? []) as $code) {
        $code = trim((string) $code);
        if ($code !== '' && strlen($code) <= 30) {
            $codes[strtoupper($code)] = $code;
        }
    }
    foreach (array_merge($supplierOut, $customerOut) as $hit) {
        if ($hit['item'] !== '' && strlen($hit['item']) <= 30) {
            $codes[strtoupper($hit['item'])] = $hit['item'];
        }
    }
    if (count($codes) > 2000) {
        throw new RuntimeException('Te veel artikelen in één controle (maximaal 2000).');
    }
    $items = [];
    $t2 = microtime(true);
    foreach (array_chunk(array_values($codes), 500) as $chunk) {
        $stmt = $pdo->prepare('SET NOCOUNT ON; SELECT RTRIM(ItemCode) AS c, RTRIM(Description) AS d FROM Items WHERE ItemCode IN (' . implode(',', array_fill(0, count($chunk), 'CAST(? AS varchar(30))')) . ')');
        $stmt->execute($chunk);
        foreach ($stmt->fetchAll() as $row) {
            $items[strtoupper(trim((string) $row['c']))] = ['found' => true, 'description' => trim((string) $row['d'])];
        }
    }
    // Inkoop: minimale bestelhoeveelheid van de leverancier (ItemAccounts.PurchaseOrderSize) per artikel.
    if ($creditor && $partyKeys !== [] && $items !== []) {
        $found = array_keys($items);
        foreach (array_chunk($found, 400) as $chunk) {
            $itemMarks = implode(',', array_fill(0, count($chunk), 'CAST(? AS varchar(30))'));
            $keyMarks = implode(',', array_fill(0, count($partyKeys), '?'));
            $stmt = $pdo->prepare(
                'SET NOCOUNT ON; SELECT RTRIM(ItemCode) AS c, MAX(PurchaseOrderSize) AS m FROM ItemAccounts WHERE ItemCode IN (' . $itemMarks . ')'
                . ' AND (LTRIM(RTRIM(crdnr)) IN (' . $keyMarks . ') OR LTRIM(RTRIM(AccountCode)) IN (' . $keyMarks . ')) GROUP BY RTRIM(ItemCode)'
            );
            $stmt->execute(array_merge($chunk, $partyKeys, $partyKeys));
            foreach ($stmt->fetchAll() as $row) {
                $key = strtoupper(trim((string) $row['c']));
                if (isset($items[$key]) && (float) $row['m'] > 0) {
                    $items[$key]['minOrder'] = (float) $row['m'];
                }
            }
        }
    }
    $ms['artikelen'] = (int) round((microtime(true) - $t2) * 1000);
    $out['ms'] = $ms; // diagnose: milliseconden per onderdeel (zie browserconsole)
    $out['items'] = (object) $items;

    echo json_encode($out, $flags);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => geeveFoutTekst($e)], $flags);
}
