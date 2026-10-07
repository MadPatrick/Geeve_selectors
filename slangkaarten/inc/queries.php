<?php

declare(strict_types=1);

/**
 * LET OP - NOG TE VERIFIEREN TEGEN HET ECHTE SCHEMA
 * ==================================================
 * We hebben nu een echt voorbeeld van de gedrukte slangkaart gezien, maar
 * nog geen INFORMATION_SCHEMA-dump van de database. Deze file raadt daarom
 * per veld een paar plausibele kolomnamen (Nederlands, zoals de labels op
 * de kaart), en probeert ze in volgorde:
 *   - filterkolommen (WAAR op gezocht wordt): zie $ORDER_NUMBER_COLUMNS en
 *     $HOSE_KEY_COLUMNS hieronder. tryColumnsQuery() probeert elke
 *     kandidaat als SQL WHERE-kolom en gebruikt de eerste die niet op een
 *     "invalid column"-fout stuit - dus 1 keer het echte schema opgeven in
 *     die arrays (bovenaan zetten) is genoeg, geen code-verbouwing nodig.
 *   - weergavekolommen (labels op de kaart): zie pick() in index.php, die
 *     op dezelfde manier meerdere kandidaat-kolomnamen per veld afgaat.
 *
 * Structuur (bevestigd door het voorbeeld):
 *   "2500 Slangkaarten bij order" = 1 rij per SLANG (niet per order). Een
 *   order met "Aantal slangen" = 4 heeft dus 1 rij die aangeeft dat er 4x
 *   dezelfde slang gemaakt moet worden - geen 4 losse rijen.
 *   "93004 hv 3001 Slangonderdelen Zijde A/B" = koppelonderdelen, gekoppeld
 *   aan 1 specifieke slang (via Slangnummer), niet aan de hele order.
 *
 * Update: aanname was dat de "9501 hv 2501 order picklijst slangen"-tabel
 * nodig was voor het regel-overzicht/selectiescherm. INMIDDELS WEERLEGD
 * (zie Update 3) - die tabel bevat helemaal niet de velden die op dat
 * scherm te zien zijn.
 *
 * Update 2 (INGETROKKEN - zie Update 5): ging ervan uit dat alle kolommen
 * van "2500 Slangkaarten bij order" geprefixt waren met "A_" (gebaseerd op
 * een SSMS-boomstructuur die achteraf de VERKEERDE tabel bleek te zijn -
 * een los onderliggend "Slangkaarten"-tabel, niet de bevraagde tabel).
 * Een directe test (`WHERE A_GHnr = ...`) gaf "Invalid column name" en
 * weerlegde dit. Zie Update 5 voor de echte, geverifieerde kolomnamen.
 *
 * Update 5 (definitief, INFORMATION_SCHEMA.COLUMNS op de juiste tabel):
 * "2500 Slangkaarten bij order" heeft GEEN "A_"-prefix op ENIGE kolom. De
 * echte namen zijn kaal: ordernr, rgl, GHnr, GHnm, nm (klantnaam), Aantal,
 * Referentie, Uw_referentie, Omschrijving, SlangType, Lengte,
 * PrijsPerMeter, ExtraArtikel(2)/AantaExtraArtikel(2)/PrijsExtraArtikel(2),
 * Solderen, Draaien, Monteren, ExtraMontage, Notitie, PrijsBruto/Korting/
 * Netto/Berekend, Hoek, bruto/T_bruto/korting/netto/T_netto, syscreated/
 * SCnm (aangemaakt op/door), sysmodified/SMnm (gewijzigd op/door), orddat
 * (orderdatum), afldat (afleverdatum), ord_adres/ord_pc/ord_pl
 * (Ordercrediteur-adres, naam = "nm", code = "debnr"), del_debnm/del_adres/
 * del_pc/del_pl (Afleveradres), or_lev/or_levnm/D_lev/D_levnm (leverancier,
 * niet gebruikt op dit label), debnr (crediteurcode), Datum, PrijsLabelen/
 * Graveren/Testen, Labelen, Graveren, Testen, ord_soort, TestenSpoelen,
 * PinPrikken, ProppenJN/Proppen, PrijsTesten, SnijlengteJN, DNVCertificaat.
 * Ordercrediteur/Afleveradres zijn dus GEEN losse tekstvelden maar worden
 * in index.php (composeAddressBlock()) uit deze losse velden
 * samengesteld; hetzelfde geldt voor Aangemaakt/Laatste gewijzigd
 * (composeDatumNaam()). Solderen/Draaien/Monteren/ExtraMontage bestaan
 * ook echt, maar zijn geen van de gedrukte vinkjes - vermoedelijk losse
 * productiestap-velden, niet gebruikt op dit label.
 *
 * Update 3 (weerlegt Update 1): het echte schema van "9501 hv 2501 order
 * picklijst slangen" is nu ook bekend, en blijkt slechts 10 kolommen te
 * hebben: ordernr, artcode, Type, T_besteld, bruto, korting, netto,
 * GH_Slang, variantonderdeel, overig. GEEN Klant/Referentie/Omschrijving/
 * GHnm/rgl/Uw_referentie/SlangType - de velden die het originele
 * regel-overzicht liet zien. Conclusie: dat overzicht kwam helemaal niet
 * uit deze tabel, maar was gewoon een subset kolommen van "2500
 * Slangkaarten bij order" (dezelfde tabel als de kaart zelf) - dat
 * verklaart ook meteen waarom élk veld daar leeg bleef en de checkboxes
 * uitgeschakeld waren (verkeerde tabel bevraagd). Deze "9501 ..."-tabel
 * wordt daarom niet meer gebruikt door findHoseLinesByOrder()/
 * findHoseLinesByCustomer() - die bevragen nu "2500 Slangkaarten bij
 * order" net als findHoseCardsByKeys(). PICKLIST_HOSES_TABLE blijft
 * hieronder staan als documentatie, niet als actief gebruikte tabel.
 *
 * Update 4: het echte schema van "93004 hv 3001 Slangonderdelen Zijde
 * A/B" is nu ook bekend (INFORMATION_SCHEMA-uitkomst). Beide tabellen
 * hebben dezelfde 12 kolommen: Nummer (de sleutel om aan de slang te
 * koppelen - "GHnr" op de kaart), Zijde AA (tabel A) / Zijde BB (tabel
 * B), Coupling A (tabel A) / Coupling B (tabel B), TR, Soort, Soortnm,
 * uitvoering, Uitvoeringnm, SCHROEFDRAADMAAT, koppeling, Aantal_A/
 * Aantal_B, Prijs_A/Prijs_B. Dus geen "Artikelnummer"/"Qty" zoals eerder
 * gegokt - de kandidaten hieronder zijn aangepast.
 *
 * Update 6 (weerlegt een deel van Update 4): een echte SELECT * op beide
 * tabellen (voor slangnummer I1512471) laat zien dat "Coupling A"/
 * "Coupling B" ALTIJD leeg zijn en "koppeling" ALTIJD "0" bevat (een
 * placeholder, geen echt artikelnummer) - het werkelijke onderdeelnummer
 * (bv. "10217-48-48RVS") staat in "Zijde AA" (tabel Zijde A) resp.
 * "Zijde BB" (tabel Zijde B). ARTIKELNUMMER_CANDIDATES in index.php is
 * hierop aangepast (Zijde AA/Zijde BB staan nu voorop).
 */

const ORDER_NUMBER_COLUMNS = ['ordernr', 'Ordernummer', 'OrderNr', 'Order nr', 'Order'];
const HOSE_KEY_COLUMNS = ['GHnr', 'Slangnummer', 'Nummer', 'SlangNr', 'Slang nr'];
const KLANT_CANDIDATES = ['nm', 'Klant', 'Klantnaam', 'Debiteurnaam', 'Naam debiteur'];

// Niet meer gebruikt sinds Update 3 - zie toelichting hierboven. Blijft
// hier staan als documentatie van wat ooit geprobeerd is.
const PICKLIST_HOSES_TABLE = '9501 hv 2501 order picklijst slangen';

/**
 * Probeert een SELECT * ... WHERE [kolom] = :waarde uit te voeren met de
 * eerste kandidaat-kolomnaam die geen SQL-fout oplevert (bijv. "invalid
 * column name"). Zo hoeft maar 1 plek aangepast te worden zodra het echte
 * schema bekend is, i.p.v. overal in deze file.
 */
function tryColumnsQuery(PDO $pdo, string $table, array $candidateColumns, string $value): array
{
    $lastException = null;

    foreach ($candidateColumns as $column) {
        $sql = "SELECT * FROM [dbo].[{$table}] WHERE [{$column}] = :value";

        try {
            $statement = $pdo->prepare($sql);
            $statement->execute(['value' => $value]);
            return $statement->fetchAll();
        } catch (PDOException $exception) {
            $lastException = $exception;
            continue;
        }
    }

    throw new DatabaseConfigException(
        "Kon tabel \"{$table}\" niet filteren - geen van de verwachte kolomnamen (" .
        implode(', ', $candidateColumns) . ') bestaat in die tabel. Zet de echte ' .
        'kolomnaam vooraan in ORDER_NUMBER_COLUMNS / HOSE_KEY_COLUMNS in inc/queries.php. ' .
        'Laatste SQL-foutmelding: ' . ($lastException?->getMessage() ?? 'onbekend')
    );
}

/** Zelfde als tryColumnsQuery(), maar met een SQL LIKE '%...%' op tekst. */
function tryColumnsLikeQuery(PDO $pdo, string $table, array $candidateColumns, string $searchTerm): array
{
    $lastException = null;

    foreach ($candidateColumns as $column) {
        $sql = "SELECT * FROM [dbo].[{$table}] WHERE [{$column}] LIKE :value";

        try {
            $statement = $pdo->prepare($sql);
            $statement->execute(['value' => '%' . $searchTerm . '%']);
            return $statement->fetchAll();
        } catch (PDOException $exception) {
            $lastException = $exception;
            continue;
        }
    }

    throw new DatabaseConfigException(
        "Kon tabel \"{$table}\" niet filteren - geen van de verwachte kolomnamen (" .
        implode(', ', $candidateColumns) . ') bestaat in die tabel. ' .
        'Laatste SQL-foutmelding: ' . ($lastException?->getMessage() ?? 'onbekend')
    );
}

/**
 * Zelfde als tryColumnsLikeQuery(), maar "fuzzy": koppeltekens, spaties,
 * punten en underscores worden zowel uit de kolomwaarde (in SQL, via
 * REPLACE) als uit de zoekterm (hieronder) verwijderd vóór het
 * vergelijken. Zo vindt zoeken op slangnummer "482953010" ook
 * "48295-30-10", ongeacht hoe de gebruiker de scheidingstekens intypt.
 */
function tryColumnsFuzzyLikeQuery(PDO $pdo, string $table, array $candidateColumns, string $searchTerm): array
{
    $normalizedTerm = preg_replace('/[-\s._]+/', '', $searchTerm) ?? $searchTerm;
    if ($normalizedTerm === '') {
        $normalizedTerm = $searchTerm;
    }

    $lastException = null;

    foreach ($candidateColumns as $column) {
        $normalizedColumn = "REPLACE(REPLACE(REPLACE(REPLACE([{$column}], '-', ''), ' ', ''), '.', ''), '_', '')";
        $sql = "SELECT * FROM [dbo].[{$table}] WHERE {$normalizedColumn} LIKE :value";

        try {
            $statement = $pdo->prepare($sql);
            $statement->execute(['value' => '%' . $normalizedTerm . '%']);
            return $statement->fetchAll();
        } catch (PDOException $exception) {
            $lastException = $exception;
            continue;
        }
    }

    throw new DatabaseConfigException(
        "Kon tabel \"{$table}\" niet filteren - geen van de verwachte kolomnamen (" .
        implode(', ', $candidateColumns) . ') bestaat in die tabel. ' .
        'Laatste SQL-foutmelding: ' . ($lastException?->getMessage() ?? 'onbekend')
    );
}

/**
 * Zelfde als tryColumnsQuery(), maar filtert met SQL IN (...) op een
 * lijst waarden i.p.v. 1 waarde - zodat bijv. de koppelingen van alle
 * regels van een order in 1 query op te halen zijn i.p.v. 1 query per
 * regel (dat laatste kan bij veel regels de pagina zeer traag maken).
 */
function tryColumnsInQuery(PDO $pdo, string $table, array $candidateColumns, array $values): array
{
    if ($values === []) {
        return [];
    }

    $lastException = null;

    foreach ($candidateColumns as $column) {
        $placeholders = [];
        $params = [];
        foreach (array_values($values) as $index => $value) {
            $placeholder = ":v{$index}";
            $placeholders[] = $placeholder;
            $params[$placeholder] = $value;
        }

        $sql = "SELECT * FROM [dbo].[{$table}] WHERE [{$column}] IN (" . implode(', ', $placeholders) . ')';

        try {
            $statement = $pdo->prepare($sql);
            $statement->execute($params);
            return $statement->fetchAll();
        } catch (PDOException $exception) {
            $lastException = $exception;
            continue;
        }
    }

    throw new DatabaseConfigException(
        "Kon tabel \"{$table}\" niet filteren - geen van de verwachte kolomnamen (" .
        implode(', ', $candidateColumns) . ') bestaat in die tabel. ' .
        'Laatste SQL-foutmelding: ' . ($lastException?->getMessage() ?? 'onbekend')
    );
}

/**
 * Zelfde als tryColumnsInQuery(), maar met een extra AND [kolom] = :waarde
 * erbij - gebruikt om de hoofdrijen van findHoseCardsByKeys() ook op
 * ordernummer te filteren (zie toelichting daar: hetzelfde slangnummer
 * kan in meerdere orders voorkomen).
 */
function tryColumnsInQueryWithEquals(
    PDO $pdo,
    string $table,
    array $inCandidateColumns,
    array $values,
    array $equalsCandidateColumns,
    string $equalsValue
): array {
    if ($values === []) {
        return [];
    }

    $lastException = null;

    foreach ($inCandidateColumns as $inColumn) {
        foreach ($equalsCandidateColumns as $equalsColumn) {
            $placeholders = [];
            $params = [];
            foreach (array_values($values) as $index => $value) {
                $placeholder = ":v{$index}";
                $placeholders[] = $placeholder;
                $params[$placeholder] = $value;
            }
            $params['equalsValue'] = $equalsValue;

            $sql = "SELECT * FROM [dbo].[{$table}] WHERE [{$inColumn}] IN (" . implode(', ', $placeholders) . ")" .
                " AND [{$equalsColumn}] = :equalsValue";

            try {
                $statement = $pdo->prepare($sql);
                $statement->execute($params);
                return $statement->fetchAll();
            } catch (PDOException $exception) {
                $lastException = $exception;
                continue;
            }
        }
    }

    throw new DatabaseConfigException(
        "Kon tabel \"{$table}\" niet filteren - geen combinatie van de verwachte " .
        'kolomnamen (' . implode(', ', $inCandidateColumns) . ' / ' .
        implode(', ', $equalsCandidateColumns) . ') bestaat in die tabel. ' .
        'Laatste SQL-foutmelding: ' . ($lastException?->getMessage() ?? 'onbekend')
    );
}

/** Groepeert rijen op hun HOSE_KEY_COLUMNS-sleutel, voor snel opzoeken. */
function groupRowsByHoseKey(array $rows): array
{
    $grouped = [];
    foreach ($rows as $row) {
        $grouped[pick($row, HOSE_KEY_COLUMNS)][] = $row;
    }

    return $grouped;
}

/**
 * Het selecteerbare regel-overzicht van een order: 1 rij per slang. Dit
 * is stap 2 (voor het printen) - dezelfde tabel/rijen als de kaart zelf
 * (zie Update 3 hierboven), alleen toont index.php hier een subset
 * kolommen. De daadwerkelijke slangkaart-gegevens komen apart uit
 * findHoseCardsByKeys() zodra er regels geselecteerd zijn.
 */
function findHoseLinesByOrder(PDO $pdo, string $orderNumber): array
{
    return tryColumnsQuery($pdo, '2500 Slangkaarten bij order', ORDER_NUMBER_COLUMNS, $orderNumber);
}

/**
 * Lichte variant van de klant-zoekopdracht: haalt alleen ordernummer,
 * klantnaam, orderdatum, "Uw referentie" en ordersoort (Order/Offerte)
 * op i.p.v. alle ±65 kolommen van "2500 Slangkaarten bij order" via
 * SELECT * (wat tryColumnsLikeQuery()
 * doet). Bij een klant met veel (historische) slangregels was dat
 * merkbaar de traagste stap in de app - elke matchende rij, met alle
 * kolommen, alleen om er een paar velden uit te gebruiken (zie
 * findOrdersByCustomer()). Gebruikt de bevestigde kolomnamen (Update 5)
 * rechtstreeks; valt terug op de langzamere SELECT * als dat om wat voor
 * reden dan ook niet lukt (bv. een andere omgeving met afwijkende
 * kolomnamen).
 */
function fetchCustomerOrderSummaryRows(PDO $pdo, string $customerName): array
{
    try {
        $statement = $pdo->prepare(
            'SELECT [ordernr], [nm], [orddat], [Uw_referentie], [ord_soort] FROM [dbo].[2500 Slangkaarten bij order] WHERE [nm] LIKE :value'
        );
        $statement->execute(['value' => '%' . $customerName . '%']);
        return $statement->fetchAll();
    } catch (PDOException $exception) {
        return tryColumnsLikeQuery($pdo, '2500 Slangkaarten bij order', KLANT_CANDIDATES, $customerName);
    }
}

/**
 * Zoekt alle orders van een klant, op basis van alle slangregels die bij
 * die klant horen. Gededupliceerd tot 1 rij per order (met het aantal
 * slangregels van die order erbij), orders met een orderdatum van meer
 * dan 2 jaar geleden worden overgeslagen (onbekende/onleesbare datum
 * blijft wel meedoen, om geen geldige orders per ongeluk te verbergen).
 * Gesorteerd op ordernummer aflopend (hoog naar laag). Dit is de
 * tussenstap "kies een order" als er op klantnaam gezocht wordt - na het
 * kiezen gaat het verder als een gewone ordernummer-zoekopdracht
 * (findHoseLinesByOrder()).
 */
function findOrdersByCustomer(PDO $pdo, string $customerName): array
{
    $rows = fetchCustomerOrderSummaryRows($pdo, $customerName);

    $cutoff = (new DateTimeImmutable())->modify('-2 years');

    $orders = [];
    foreach ($rows as $row) {
        $orderNumber = pick($row, ORDER_NUMBER_COLUMNS);
        if ($orderNumber === '') {
            continue;
        }

        $orderDate = parseDutchDateTime(pick($row, ORDERDATUM_CANDIDATES));
        if ($orderDate !== null && $orderDate < $cutoff) {
            continue;
        }

        if (!isset($orders[$orderNumber])) {
            $orders[$orderNumber] = ['row' => $row, 'count' => 0];
        }
        $orders[$orderNumber]['count']++;
    }

    $orderList = array_values($orders);

    usort($orderList, static function (array $a, array $b): int {
        $orderNumberA = pick($a['row'], ORDER_NUMBER_COLUMNS);
        $orderNumberB = pick($b['row'], ORDER_NUMBER_COLUMNS);

        if (is_numeric($orderNumberA) && is_numeric($orderNumberB)) {
            return $orderNumberB <=> $orderNumberA;
        }

        return strcmp($orderNumberB, $orderNumberA);
    });

    return $orderList;
}

/**
 * De laatste $limit orders/offertes, nieuwste eerst - wat de pagina standaard
 * toont zolang er nog niet gezocht is. Zelfde vorm als findOrdersByCustomer()
 * (1 rij per order + aantal slangregels), zodat renderCustomerOrdersForm()
 * ze direct kan tonen.
 *
 * "Nieuwste" komt uit de ORDER in Exact (database 005, tabel orkrg): het
 * moment waarop de order daar is aangemaakt (orkrg.syscreated, een echte
 * datetime) - niet uit de slangkaart-tabel, want daar zegt de aanmaaktijd
 * van een regel niets over de order (een oude offerte met een recent
 * toegevoegde regel kwam tussen de nieuwe orders). Orderdatum (orddat) is
 * ook niet bruikbaar voor de tijd: die is altijd 00:00:00, en alleen op
 * ordernummer sorteren werkt niet (meerdere nummerreeksen).
 *
 * Werkwijze: zie findRecentOrdersViaExact(). Lukt dat niet, dan terugval op
 * de slangkaart-tabel zelf (alleen orderdatum, zonder aanmaaktijd).
 */
function findRecentOrders(PDO $pdo, int $limit = 10): array
{
    $limit = max(1, min(50, $limit));

    try {
        $orders = findRecentOrdersViaExact($pdo, $limit);
        if ($orders !== []) {
            return $orders;
        }
    } catch (Throwable $exception) {
        // Geen Exact-koppeling/.env: terugval hieronder.
    }

    return findRecentOrdersLocal($pdo, $limit);
}

/**
 * Zet de Exact-gegevens (orddat/syscreated uit orkrg) bij de orders uit de
 * slangkaart-tabel en sorteert op het moment waarop de order in Exact is
 * aangemaakt, nieuwste eerst. Orders zonder Exact-gegevens komen achteraan
 * (op ordernummer).
 *
 * @param array<int, array{row: array<string, mixed>, count: int}> $orders
 * @param array<string, array{orddat: string, syscreated: string}> $exactByOrder per ordernummer
 * @return array<int, array{row: array<string, mixed>, count: int}>
 */
function sortRecentOrdersByExact(array $orders, array $exactByOrder, int $limit): array
{
    foreach ($orders as &$order) {
        $number = trim((string) $order['row']['ordernr']);
        if (isset($exactByOrder[$number])) {
            $order['row']['orddat'] = $exactByOrder[$number]['orddat'];
            $order['row']['syscreated'] = $exactByOrder[$number]['syscreated'];
        }
    }
    unset($order);

    usort($orders, static function (array $a, array $b): int {
        $createdA = (string) ($a['row']['syscreated'] ?? '');
        $createdB = (string) ($b['row']['syscreated'] ?? '');
        if ($createdA !== $createdB) {
            return strcmp($createdB, $createdA);
        }

        return strcmp((string) $b['row']['ordernr'], (string) $a['row']['ordernr']);
    });

    return array_slice($orders, 0, $limit);
}

/**
 * Werkwijze (de eerdere variant zocht 100/400 ordernummers uit Exact op in de
 * slangkaart-tabel en deed daar 5 resp. 33 seconden over):
 *   1. Uit de slangkaart-tabel ALLE orders van de laatste 7 dagen (orderdatum)
 *      ophalen (max. 300; te weinig = 30, 365 dagen, anders alles). Niet
 *      alleen "de nieuwste 10 op orderdatum": een order kan een orderdatum
 *      van vandaag hebben terwijl hij op 23 september is aangemaakt - die
 *      nam dan de plek in van een echt nieuwe order van gisteren.
 *   2. Van die orders de aanmaaktijd uit Exact (orkrg, op ordernummer)
 *      erbij halen (1 query, ~100 ms) en daarop sorteren.
 * Tijden per stap staan in $GLOBALS['recentOrdersTimings'] (index.php?debug=1).
 */
function findRecentOrdersViaExact(PDO $slangPdo, int $limit): array
{
    $GLOBALS['recentOrdersTimings'] = [];
    $table = '[dbo].[2500 Slangkaarten bij order]';

    $orders = [];
    foreach ([7, 30, 365, null] as $days) {
        $where = $days === null ? '' : "WHERE [orddat] >= DATEADD(DAY, -{$days}, CAST(GETDATE() AS date)) ";
        $t = microtime(true);
        $rows = $slangPdo->query(
            "SELECT TOP 300 [ordernr], MIN([nm]) AS [nm], MAX([orddat]) AS [orddat], MIN([Uw_referentie]) AS [Uw_referentie], MIN([ord_soort]) AS [ord_soort], COUNT(*) AS [aantal] " .
            "FROM {$table} {$where}GROUP BY [ordernr] ORDER BY MAX([orddat]) DESC"
        )->fetchAll();
        $GLOBALS['recentOrdersTimings']['slangkaarten-tabel ' . ($days === null ? 'alles' : "{$days} dagen")] = (int) round((microtime(true) - $t) * 1000);

        $orders = [];
        foreach ($rows as $row) {
            $count = (int) ($row['aantal'] ?? 0);
            unset($row['aantal']);
            $orders[] = ['row' => $row, 'count' => $count];
        }
        if (count($orders) >= $limit) {
            break;
        }
    }

    if ($orders === []) {
        return [];
    }

    try {
        $numbers = array_values(array_unique(array_map(
            static fn(array $order): string => trim((string) $order['row']['ordernr']),
            $orders
        )));
        $placeholders = implode(', ', array_fill(0, count($numbers), '?'));
        $t = microtime(true);
        $statement = getExactPdoConnection()->prepare(
            "SELECT [ordernr], [orddat], [syscreated] FROM [dbo].[orkrg] WHERE [ordernr] IN ({$placeholders})"
        );
        $statement->execute($numbers);
        $exactByOrder = [];
        foreach ($statement->fetchAll() as $row) {
            $exactByOrder[trim((string) $row['ordernr'])] = ['orddat' => (string) $row['orddat'], 'syscreated' => (string) $row['syscreated']];
        }
        $GLOBALS['recentOrdersTimings']['orkrg (' . count($numbers) . ' orders)'] = (int) round((microtime(true) - $t) * 1000);
    } catch (Throwable $exception) {
        $exactByOrder = [];
    }

    return sortRecentOrdersByExact($orders, $exactByOrder, $limit);
}

/** Terugval: de nieuwste orders uit de slangkaart-tabel zelf, op orderdatum. */
function findRecentOrdersLocal(PDO $pdo, int $limit): array
{
    $table = '[dbo].[2500 Slangkaarten bij order]';
    $columns = 'MIN([nm]) AS [nm], MAX([orddat]) AS [orddat], MIN([Uw_referentie]) AS [Uw_referentie], MIN([ord_soort]) AS [ord_soort], COUNT(*) AS [aantal]';

    $queries = [
        "SELECT TOP {$limit} [ordernr], {$columns} FROM {$table} GROUP BY [ordernr] ORDER BY MAX([orddat]) DESC, TRY_CAST([ordernr] AS BIGINT) DESC, [ordernr] DESC",
        "SELECT TOP {$limit} [ordernr], {$columns} FROM {$table} GROUP BY [ordernr] ORDER BY TRY_CAST([ordernr] AS BIGINT) DESC, [ordernr] DESC",
    ];

    $rows = null;
    foreach ($queries as $sql) {
        try {
            $rows = $pdo->query($sql)->fetchAll();
            break;
        } catch (PDOException $exception) {
            continue;
        }
    }

    $orders = [];
    foreach ($rows ?? [] as $row) {
        $count = (int) ($row['aantal'] ?? 0);
        unset($row['aantal']);
        $orders[] = ['row' => $row, 'count' => $count];
    }

    return $orders;
}

/**
 * Zoekt de artikelgroep (Exact-database "005", GRV_SalesItems.[Item
 * Group]) op voor een lijst artikelen, in 1 databaseronde i.p.v. per
 * artikel - zelfde batch-patroon als findArtikelExactDataBatch() in
 * index.php (Locatie/Voorraad). GRV_SalesItems (ItemCode, [Item Group])
 * is dezelfde tabel die /stauff gebruikt om artikelgroep 67 te vinden
 * (zie portal-README, "Database-koppeling Exact") - hier gebruikt om
 * artikelgroep 0 (slangen) te herkennen.
 *
 * Geeft bij een connectiefout (of lege root-.env) een lege array terug -
 * de aanroeper filtert dan niets weg, zodat een ontbrekende
 * Exact-koppeling de rest van de zoekfunctie niet blokkeert.
 *
 * @param string[] $artikelen
 * @return array<string, string> artikel => artikelgroep (ontbrekende sleutel = niet gevonden)
 */
function findArtikelItemGroepenBatch(array $artikelen): array
{
    $artikelen = array_values(array_unique(array_filter(
        $artikelen,
        static fn(string $artikel): bool => $artikel !== ''
    )));
    if ($artikelen === []) {
        return [];
    }

    try {
        $pdo = getExactPdoConnection();
    } catch (Throwable $exception) {
        return [];
    }

    $placeholders = [];
    $params = [];
    foreach ($artikelen as $index => $artikel) {
        $placeholders[] = ":code{$index}";
        $params["code{$index}"] = $artikel;
    }
    $inClause = implode(', ', $placeholders);

    $result = [];
    try {
        $stmt = $pdo->prepare(
            "SELECT ItemCode, [Item Group] FROM GRV_SalesItems WHERE ItemCode IN ({$inClause})"
        );
        $stmt->execute($params);
        while (($row = $stmt->fetch()) !== false) {
            $result[(string) $row['ItemCode']] = trim((string) ($row['Item Group'] ?? ''));
        }
    } catch (Throwable $exception) {
        return [];
    }

    return $result;
}

/**
 * Zoekt slangregels op (een deel van) het slangnummer (GHnr, het
 * hose-artikelnummer - zie HOSE_KEY_COLUMNS). Dit is GEEN order-unieke
 * sleutel (zie findHoseCardsByKeys()), dus dit kan regels uit meerdere
 * orders/klanten opleveren. Toont ze als een lijst, net als
 * findOrdersByCustomer() bij zoeken op klantnaam; de gebruiker kiest
 * daarna de order om verder te gaan naar het normale regel-overzicht
 * (stap 2) - geen aparte printflow nodig.
 *
 * Alleen artikelen uit artikelgroep 0 (echte slangen, zie
 * findArtikelItemGroepenBatch()) komen in het resultaat - dit voorkomt
 * dat een korte zoekterm ook niet-slangartikelen laat matchen. Is de
 * Exact-koppeling niet beschikbaar, dan wordt niet gefilterd (zie
 * findArtikelItemGroepenBatch()).
 *
 * Fuzzy: gebruikt tryColumnsFuzzyLikeQuery() i.p.v. tryColumnsLikeQuery(),
 * zodat koppeltekens/spaties/punten in het slangnummer niet precies hoeven
 * te matchen (zoeken op "482953010" vindt ook "48295-30-10").
 */
function findLinesByHoseNumber(PDO $pdo, string $hoseNumber): array
{
    $rows = tryColumnsFuzzyLikeQuery($pdo, '2500 Slangkaarten bij order', HOSE_KEY_COLUMNS, $hoseNumber);

    $itemGroepen = findArtikelItemGroepenBatch(array_map(
        static fn(array $row): string => pick($row, HOSE_KEY_COLUMNS),
        $rows
    ));
    if ($itemGroepen !== []) {
        $rows = array_values(array_filter(
            $rows,
            static function (array $row) use ($itemGroepen): bool {
                $hoseKey = pick($row, HOSE_KEY_COLUMNS);
                return ($itemGroepen[$hoseKey] ?? '') === '0';
            }
        ));
    }

    usort($rows, static function (array $a, array $b): int {
        $orderNumberA = pick($a, ORDER_NUMBER_COLUMNS);
        $orderNumberB = pick($b, ORDER_NUMBER_COLUMNS);

        if (is_numeric($orderNumberA) && is_numeric($orderNumberB)) {
            return $orderNumberB <=> $orderNumberA;
        }

        return strcmp($orderNumberB, $orderNumberA);
    });

    return $rows;
}

/**
 * Parseert een datum/tijd-tekst zoals die op de kaart staat ("26-9-2026
 * 00:00:00", dag-maand-jaar zonder voorloopnullen) naar een sorteerbare
 * DateTimeImmutable. Geeft null bij een onbekend/leeg formaat. Formaten
 * zonder tijdcomponent (j-n-Y/Y-m-d) krijgen expliciet 00:00:00 - anders
 * vult createFromFormat() de ontbrekende tijd met de huidige servertijd.
 */
function parseDutchDateTime(string $value): ?DateTimeImmutable
{
    if ($value === '') {
        return null;
    }

    foreach (['j-n-Y H:i:s', 'j-n-Y', 'Y-m-d H:i:s.v', 'Y-m-d H:i:s', 'Y-m-d'] as $format) {
        $date = DateTimeImmutable::createFromFormat($format, $value);
        if ($date !== false) {
            return str_contains($format, 'H:i:s') ? $date : $date->setTime(0, 0, 0);
        }
    }

    return null;
}

/**
 * Vult elke regel van het regel-overzicht aan met de koppeling(en) van
 * zijde A en B, als synthetische arrayvelden "_KoppelingAList"/
 * "_KoppelingBList" (1 array-item per koppelonderdeel - een zijde kan er
 * meerdere hebben). Dit zijn geen kolommen van "2500 Slangkaarten bij
 * order" zelf, maar worden per regel bijgehaald uit de Zijde A/B-tabellen
 * - het regel-overzicht (index.php) zet ze om in losse tabelkolommen,
 * 1 per artikel, i.p.v. ze in 1 cel te proppen.
 */
function enrichHoseLinesWithCouplings(PDO $pdo, array $hoseLines): array
{
    $hoseKeys = [];
    foreach ($hoseLines as $line) {
        $hoseKey = pick($line, HOSE_KEY_COLUMNS);
        if ($hoseKey !== '') {
            $hoseKeys[$hoseKey] = true;
        }
    }
    $hoseKeys = array_keys($hoseKeys);

    $sideARows = tryColumnsInQuery($pdo, '93004 hv 3001 Slangonderdelen Zijde A', HOSE_KEY_COLUMNS, $hoseKeys);
    $sideBRows = tryColumnsInQuery($pdo, '93004 hv 3001 Slangonderdelen Zijde B', HOSE_KEY_COLUMNS, $hoseKeys);
    $groupedA = groupRowsByHoseKey($sideARows);
    $groupedB = groupRowsByHoseKey($sideBRows);

    foreach ($hoseLines as &$line) {
        $hoseKey = pick($line, HOSE_KEY_COLUMNS);
        $line['_KoppelingAList'] = collectCouplingArticles($groupedA[$hoseKey] ?? []);
        $line['_KoppelingBList'] = collectCouplingArticles($groupedB[$hoseKey] ?? []);
    }
    unset($line);

    return $hoseLines;
}

/** Lijst van artikelnummers uit koppelzijde-rijen (1 waarde per onderdeel). */
function collectCouplingArticles(array $rows): array
{
    $articles = [];
    foreach ($rows as $row) {
        $article = pick($row, ARTIKELNUMMER_CANDIDATES);
        if ($article !== '' && !isZeroPlaceholder($article)) {
            $articles[] = $article;
        }
    }

    return $articles;
}

/**
 * "koppeling" (fallback-kandidaat in ARTIKELNUMMER_CANDIDATES) bevat bij
 * ontbrekend koppel-artikel een letterlijke "0" i.p.v. een lege waarde -
 * geen echt artikelnummer is ooit gewoon "0". Die placeholder onderdrukken
 * we, anders toont het overzicht "0, 0, 0" i.p.v. een streepje.
 */
function isZeroPlaceholder(string $value): bool
{
    return (bool) preg_match('/^0+([.,]0+)?$/', trim($value));
}

/**
 * Bouwt de slangkaarten op voor een expliciete lijst geselecteerde
 * slangnummers (GHnr), zoals aangevinkt in het regel-overzicht. Haalt
 * de hoofdrijen en de koppelingen van zijde A/B in slechts 3 queries op
 * (1 per tabel, met een IN-clausule), ongeacht het aantal geselecteerde
 * regels - i.p.v. 3 queries per regel (was bij een order met veel
 * regels merkbaar traag).
 *
 * $orderNumber filtert de hoofdrijen ook op ordernummer - hetzelfde
 * slangnummer (GHnr) kan namelijk in meerdere orders van dezelfde klant
 * voorkomen (het is het hose-artikelnummer, geen order-unieke sleutel).
 * Zonder deze filter kwamen bij het printen ook slangkaarten van andere
 * orders van die klant mee die toevallig hetzelfde slangnummer gebruiken.
 * Bij een leeg ordernummer (zou niet moeten voorkomen, het regel-
 * overzicht geeft het altijd mee) wordt niet op order gefilterd.
 */
function findHoseCardsByKeys(PDO $pdo, array $hoseKeys, string $orderNumber = ''): array
{
    $hoseKeys = array_values(array_unique(array_filter(
        $hoseKeys,
        static fn(string $key): bool => $key !== ''
    )));

    if ($hoseKeys === []) {
        return [];
    }

    $mainRows = $orderNumber !== ''
        ? tryColumnsInQueryWithEquals(
            $pdo,
            '2500 Slangkaarten bij order',
            HOSE_KEY_COLUMNS,
            $hoseKeys,
            ORDER_NUMBER_COLUMNS,
            $orderNumber
        )
        : tryColumnsInQuery($pdo, '2500 Slangkaarten bij order', HOSE_KEY_COLUMNS, $hoseKeys);
    $sideARows = tryColumnsInQuery($pdo, '93004 hv 3001 Slangonderdelen Zijde A', HOSE_KEY_COLUMNS, $hoseKeys);
    $sideBRows = tryColumnsInQuery($pdo, '93004 hv 3001 Slangonderdelen Zijde B', HOSE_KEY_COLUMNS, $hoseKeys);
    $groupedA = groupRowsByHoseKey($sideARows);
    $groupedB = groupRowsByHoseKey($sideBRows);

    $cards = [];
    $foundKeys = [];
    foreach ($mainRows as $row) {
        $hoseKey = pick($row, HOSE_KEY_COLUMNS);
        $foundKeys[$hoseKey] = true;
        $cards[] = [
            'row'   => $row,
            'sideA' => $groupedA[$hoseKey] ?? [],
            'sideB' => $groupedB[$hoseKey] ?? [],
        ];
    }

    // Slangkaarten die nog in geen enkele order/offerte zitten (zie
    // findHoseNumbersWithoutOrder()): geen rij in de order-tabel, dus alleen
    // het slangnummer en de koppelonderdelen - de orderdelen blijven leeg.
    // Alleen zonder ordernummer; binnen een order is een ontbrekende regel
    // gewoon "niet in deze order".
    if ($orderNumber === '') {
        $missingKeys = array_values(array_filter($hoseKeys, static fn(string $key): bool => !isset($foundKeys[$key])));
        $detailSides = $missingKeys !== [] ? findCouplingRowsFromDetails($pdo, $missingKeys) : [];
        foreach ($missingKeys as $hoseKey) {
            $sideA = $groupedA[$hoseKey] ?? ($detailSides[$hoseKey]['A'] ?? []);
            $sideB = $groupedB[$hoseKey] ?? ($detailSides[$hoseKey]['B'] ?? []);
            if ($sideA === [] && $sideB === []) {
                continue;
            }
            $cards[] = [
                'row'   => [HOSE_KEY_COLUMNS[0] => $hoseKey],
                'sideA' => $sideA,
                'sideB' => $sideB,
            ];
        }
    }

    return $cards;
}

/**
 * Koppelonderdelen per slangnummer uit "Details slangen" (1 rij per
 * onderdeel: artnr, Type A/B voor de zijde, Regel voor de volgorde) - voor
 * slangkaarten die niet in "93004 hv 3001 Slangonderdelen Zijde A/B" staan.
 * De tabel bevat dubbele rijen (join op omschrijvingstabellen), dus per
 * zijde en artikel gededupliceerd. Geeft rijen in dezelfde vorm als Zijde
 * A/B ('Zijde AA' resp. 'Zijde BB' = artikelnummer, zie
 * ARTIKELNUMMER_CANDIDATES).
 *
 * @param string[] $hoseKeys
 * @return array<string, array{A: array<int, array<string, string>>, B: array<int, array<string, string>>}>
 */
function findCouplingRowsFromDetails(PDO $pdo, array $hoseKeys): array
{
    $hoseKeys = array_values(array_unique($hoseKeys));
    if ($hoseKeys === []) {
        return [];
    }

    try {
        $placeholders = implode(', ', array_fill(0, count($hoseKeys), '?'));
        $statement = $pdo->prepare(
            "SELECT [GHnr], [Type], [Regel], [artnr] FROM [dbo].[Details slangen] WHERE [GHnr] IN ({$placeholders}) " .
            'GROUP BY [GHnr], [Type], [Regel], [artnr] ORDER BY [GHnr], [Type], [Regel]'
        );
        $statement->execute($hoseKeys);
        $rows = $statement->fetchAll();
    } catch (PDOException $exception) {
        return [];
    }

    $result = [];
    foreach ($rows as $row) {
        $key = trim((string) $row['GHnr']);
        $side = strtoupper(trim((string) $row['Type']));
        $article = trim((string) $row['artnr']);
        if ($article === '' || ($side !== 'A' && $side !== 'B')) {
            continue;
        }
        $result[$key] ??= ['A' => [], 'B' => []];
        // "Details slangen" heeft geen aantallen: 1 per onderdeel is een AANNAME
        // (1 koppeling + 1 huls per zijde), anders toont de picklijst 0.
        $result[$key][$side][] = [
            $side === 'A' ? 'Zijde AA' : 'Zijde BB' => $article,
            $side === 'A' ? 'Aantal_A' : 'Aantal_B' => '1',
        ];
    }

    return $result;
}

/**
 * Slangkaarten (slangnummers) die WEL bestaan maar nog in geen enkele order
 * of offerte zitten, dus niet in "2500 Slangkaarten bij order" voorkomen -
 * zoekopdracht op slangnummer vond die voorheen niet. Bron: "Details
 * slangen" (1 rij per kaart-onderdeel, GHnr = slangnummer; bevestigd voor
 * slang 507500-2, die in de order-tabellen en in "2000 overzicht slangkaart
 * obv exact" ontbreekt). Zelfde fuzzy zoeklogica als de order-zoekopdracht
 * (streepjes/spaties/punten negeren). Slangnummers die al in de
 * order-resultaten staan ($excludeKeys) worden overgeslagen.
 *
 * Geeft per slangkaart een regel met de koppelonderdelen erbij (zelfde
 * vorm als enrichHoseLinesWithCouplings()). Een fout (bijv. tabel ontbreekt
 * in een andere omgeving) geeft een lege lijst - dit mag de gewone
 * zoekopdracht nooit blokkeren.
 *
 * @param string[] $excludeKeys
 */
function findHoseNumbersWithoutOrder(PDO $pdo, string $searchTerm, array $excludeKeys): array
{
    $normalizedTerm = preg_replace('/[-\s._]+/', '', $searchTerm) ?? $searchTerm;
    if ($normalizedTerm === '') {
        $normalizedTerm = $searchTerm;
    }

    try {
        $statement = $pdo->prepare(
            "SELECT TOP 50 [GHnr] FROM [dbo].[Details slangen] WHERE " .
            "REPLACE(REPLACE(REPLACE(REPLACE([GHnr], '-', ''), ' ', ''), '.', ''), '_', '') LIKE :value " .
            'GROUP BY [GHnr] ORDER BY [GHnr]'
        );
        $statement->execute(['value' => '%' . $normalizedTerm . '%']);
        $keys = array_map('trim', $statement->fetchAll(PDO::FETCH_COLUMN));
    } catch (PDOException $exception) {
        return [];
    }

    $exclude = array_flip(array_map('strtolower', array_map('trim', $excludeKeys)));
    $keys = array_values(array_filter(
        $keys,
        static fn(string $key): bool => $key !== '' && !isset($exclude[strtolower($key)])
    ));
    if ($keys === []) {
        return [];
    }

    $lines = array_map(static fn(string $key): array => [HOSE_KEY_COLUMNS[0] => $key], $keys);
    try {
        $lines = enrichHoseLinesWithCouplings($pdo, $lines);
    } catch (PDOException | DatabaseConfigException $exception) {
        // Zonder koppelonderdelen tonen we de slangnummers toch.
    }

    $detailSides = findCouplingRowsFromDetails($pdo, $keys);
    foreach ($lines as &$line) {
        $key = (string) $line[HOSE_KEY_COLUMNS[0]];
        if (($line['_KoppelingAList'] ?? []) === [] && ($line['_KoppelingBList'] ?? []) === []) {
            $line['_KoppelingAList'] = collectCouplingArticles($detailSides[$key]['A'] ?? []);
            $line['_KoppelingBList'] = collectCouplingArticles($detailSides[$key]['B'] ?? []);
        }
    }
    unset($line);

    return $lines;
}

/**
 * Zoekt een waarde in een databaserij op basis van een lijst kandidaat-
 * kolomnamen, ongevoelig voor spaties/underscores/hoofdletters. Geeft de
 * eerste kandidaat terug die een niet-lege waarde heeft.
 */
function pick(array $row, array $candidateColumns, string $default = ''): string
{
    $normalized = [];
    foreach ($row as $column => $value) {
        $normalized[normalizeColumnKey((string) $column)] = $value;
    }

    foreach ($candidateColumns as $candidate) {
        $key = normalizeColumnKey($candidate);
        if (array_key_exists($key, $normalized)) {
            $value = $normalized[$key];
            if ($value !== null && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }
    }

    return $default;
}

function normalizeColumnKey(string $column): string
{
    return strtolower(str_replace([' ', '_', '-', '/'], '', $column));
}
