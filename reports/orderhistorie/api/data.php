<?php

declare(strict_types=1);

// Tegel-toegang (Config > Tegels): zonder ontgrendelen geen gegevens.
require_once __DIR__ . '/../../../shared/tiles.php';
tileApiGuard('orderhistorie');

/**
 * Orderhistorie: geleverde orderregels per klant, vanaf de eerste order (de oude Excel toonde alleen de laatste 2 jaar).
 * Kolommen als in die Excel: artnr, afldat, prijslijst, debnm, ordernr, refer, uw referentie, pakbon_dat, art_omsc, aant_gelev.
 *
 * Bron: dezelfde tabellen als de view [Exactrapportage].[dbo].[400 orderhistorie] van Carl, maar rechtstreeks bevraagd (die view
 * zet de datums om naar tekst en koppelt de hele artikelview, waardoor filteren op klant of datum erg traag is):
 *   orhkrg (orderkop historie) JOIN orhsrg (orderregels historie) ON ordernr + pakbon_nr, plus Items voor de omschrijving (INNER JOIN, zoals de view met de artikelen: tekstregels zonder artikel vallen weg).
 * Zelfde SQL Server en inloggegevens (via Config) als /stauff en de rapporten (database van Exact, zoals Items en cicmpy).
 */

require_once __DIR__ . '/../../inc.php';

const ORDERHISTORIE_MAX = 50000;

const ORDERHISTORIE_KOLOMMEN = [
    'Artikelnr', 'Afleverdatum', 'Prijslijst', 'Debiteur', 'Ordernr', 'Referentie', 'Uw referentie', 'Pakbondatum', 'Omschrijving', 'Aantal geleverd',
];
const ORDERHISTORIE_DATUM = [false, true, false, false, false, false, false, true, false, false];
const ORDERHISTORIE_GETAL = [false, false, false, false, false, false, false, false, false, true];

const ORDERHISTORIE_FROM =
    'orhkrg ohk WITH (NOLOCK) INNER JOIN orhsrg ohr WITH (NOLOCK) ON ohk.ordernr = ohr.ordernr AND ohk.pakbon_nr = ohr.pakbon_nr ' .
    'INNER JOIN Items i WITH (NOLOCK) ON i.ItemCode = ohr.artcode';

const ORDERHISTORIE_SELECT =
    'LTRIM(RTRIM(ohr.artcode)), ohr.afldat, LTRIM(RTRIM(ohr.prijslijst)), LTRIM(RTRIM(ohk.ord_debtor_name)), ohk.ordernr, ' .
    'LTRIM(RTRIM(ohk.refer)), LTRIM(RTRIM(ohk.docnumber)), ohk.pakbon_dat, LTRIM(RTRIM(i.Description)), ohr.aant_gelev';

/** Waarde uit pdo_sqlsrv (string of DateTime) als YYYY-MM-DD. */
function orderhistorieDatum(mixed $v): ?string
{
    if ($v === null || $v === '') {
        return null;
    }
    if ($v instanceof DateTimeInterface) {
        return $v->format('Y-m-d');
    }
    return preg_match('/^(\d{4}-\d{2}-\d{2})/', (string) $v, $m) === 1 ? $m[1] : trim((string) $v);
}

function orderhistorieIso(string $value, string $label): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) !== 1 || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
        throw new RuntimeException('Vul een geldige ' . $label . ' in.');
    }
    return $value;
}

function orderhistorieLike(string $text): string
{
    return '%' . str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $text) . '%';
}

/** Tekstrapport voor ?diagnose=1: aantallen en tijden per stap, en een paar voorbeeldregels. */
function orderhistorieDiagnose(PDO $pdo, string $debnr): string
{
    $out = ['DIAGNOSE orderhistorie (rechtstreeks op orhkrg/orhsrg/Items)'];
    $run = static function (string $label, string $sql, array $params) use ($pdo, &$out): void {
        $t = microtime(true);
        try {
            $stmt = $pdo->prepare('SET NOCOUNT ON; ' . $sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_NUM);
            $out[] = $label . ' (' . (int) round((microtime(true) - $t) * 1000) . ' ms, ' . count($rows) . ' regels):';
            foreach ($rows as $r) {
                $out[] = '  ' . implode(' | ', array_map(static fn ($v): string => $v instanceof DateTimeInterface ? $v->format('Y-m-d') : trim((string) $v), $r));
            }
        } catch (Throwable $e) {
            $out[] = $label . ': FOUT ' . $e->getMessage();
        }
    };
    $run('Totaal orderkoppen / oudste en nieuwste pakbondatum', 'SELECT COUNT(*), MIN(pakbon_dat), MAX(pakbon_dat) FROM orhkrg WITH (NOLOCK)', []);
    if ($debnr !== '') {
        $run('Aantal koppen en regels voor debnr ' . $debnr, 'SELECT COUNT(DISTINCT ohk.ordernr), COUNT(*) FROM ' . ORDERHISTORIE_FROM . ' WHERE LTRIM(RTRIM(ohk.debnr)) = ?', [$debnr]);
        $run('Nieuwste 3 regels voor debnr ' . $debnr, 'SELECT TOP 3 ' . ORDERHISTORIE_SELECT . ' FROM ' . ORDERHISTORIE_FROM . ' WHERE LTRIM(RTRIM(ohk.debnr)) = ? ORDER BY ohr.afldat DESC', [$debnr]);
    }
    return implode("\n", $out);
}

reportApi(function (array $in): array {
    $van = orderhistorieIso((string) ($in['van'] ?? ''), 'begindatum');
    $tot = orderhistorieIso((string) ($in['tot'] ?? ''), 'einddatum');
    $debnr = trim((string) ($in['debnr'] ?? ''));
    $klant = trim((string) ($in['klant'] ?? ''));
    $klantZoek = trim((string) ($in['klantzoek'] ?? ''));
    $artnr = trim((string) ($in['artnr'] ?? ''));
    $omsc = trim((string) ($in['omsc'] ?? ''));
    foreach ([$debnr, $klant, $klantZoek, $artnr, $omsc] as $text) {
        if (strlen($text) > 80) {
            throw new RuntimeException('Een zoekterm is te lang (maximaal 80 tekens).');
        }
    }
    if ($van !== '' && $tot !== '' && $van > $tot) {
        throw new RuntimeException('De begindatum ligt na de einddatum.');
    }

    // Sessie niet vergrendeld houden tijdens een lange query: anders hangen andere pagina's van dezelfde gebruiker erop.
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    $pdo = reportConnection();

    if (($in['diagnose'] ?? '') !== '') {
        throw new RuntimeException(orderhistorieDiagnose($pdo, $debnr));
    }
    // "Tot en met" staat standaard op vandaag en telt dus niet als filter.
    if ($debnr === '' && $klant === '' && $klantZoek === '' && $van === '' && $artnr === '' && $omsc === '') {
        throw new RuntimeException('Kies een klant of vul een begindatum, artikelnummer of omschrijving in.');
    }

    $where = [];
    $params = [];
    if ($debnr !== '') {
        $where[] = 'LTRIM(RTRIM(ohk.debnr)) = ?';
        $params[] = $debnr;
    } elseif ($klant !== '') {
        $where[] = 'LTRIM(RTRIM(ohk.ord_debtor_name)) = ?';
        $params[] = $klant;
    }
    if ($klantZoek !== '') {
        $where[] = 'ohk.ord_debtor_name LIKE ?';
        $params[] = orderhistorieLike($klantZoek);
    }
    if ($van !== '') {
        $where[] = 'ohr.afldat >= CONVERT(datetime, ?, 112)';
        $params[] = str_replace('-', '', $van);
    }
    if ($tot !== '') {
        $where[] = 'ohr.afldat < DATEADD(day, 1, CONVERT(datetime, ?, 112))';
        $params[] = str_replace('-', '', $tot);
    }
    if ($artnr !== '') {
        $where[] = 'ohr.artcode LIKE ?';
        $params[] = orderhistorieLike($artnr);
    }
    if ($omsc !== '') {
        $where[] = 'i.Description LIKE ?';
        $params[] = orderhistorieLike($omsc);
    }

    $stmt = $pdo->prepare(
        'SET NOCOUNT ON; SELECT TOP ' . (ORDERHISTORIE_MAX + 1) . ' ' . ORDERHISTORIE_SELECT . ' FROM ' . ORDERHISTORIE_FROM .
        ' WHERE ' . implode(' AND ', $where) . ' ORDER BY ohr.afldat DESC, ohk.ordernr, ohr.artcode'
    );
    $stmt->setAttribute(PDO::SQLSRV_ATTR_QUERY_TIMEOUT, 120); // een vastlopende query stopt na 2 minuten i.p.v. de server te blokkeren
    $t1 = microtime(true);
    $stmt->execute($params);

    $rows = [];
    while ($r = $stmt->fetch(PDO::FETCH_NUM)) {
        foreach ($r as $i => $v) {
            if (ORDERHISTORIE_DATUM[$i]) {
                $r[$i] = orderhistorieDatum($v);
            } elseif ($v === null) {
                $r[$i] = null;
            } else {
                $v = trim((string) $v);
                $r[$i] = ORDERHISTORIE_GETAL[$i] && is_numeric($v) ? $v + 0 : $v;
            }
        }
        $rows[] = $r;
    }
    $msOphalen = (int) round((microtime(true) - $t1) * 1000);
    $capped = count($rows) > ORDERHISTORIE_MAX;
    if ($capped) {
        array_pop($rows);
    }

    $kop = [];
    if ($klant !== '' || $klantZoek !== '') {
        $kop[] = ['label' => 'Klant', 'value' => $klant !== '' ? $klant : $klantZoek];
    }
    if ($van !== '' || $tot !== '') {
        $kop[] = ['label' => 'Afgeleverd', 'value' => ($van !== '' ? implode('-', array_reverse(explode('-', $van))) : 'begin') . ' t/m ' . ($tot !== '' ? implode('-', array_reverse(explode('-', $tot))) : 'nu')];
    }
    if ($capped) {
        $kop[] = ['label' => 'Let op', 'value' => 'alleen de nieuwste ' . ORDERHISTORIE_MAX . ' regels, verfijn het filter'];
    }

    return [
        'columns' => ORDERHISTORIE_KOLOMMEN,
        'numeric' => ORDERHISTORIE_GETAL,
        'dates' => ORDERHISTORIE_DATUM,
        'rows' => $rows,
        'bron' => 'orhkrg + orhsrg',
        'tijden' => ['bron' => 0, 'ophalen' => $msOphalen],
        'kop' => $kop,
        'klantNaam' => $klant !== '' ? $klant : ($klantZoek !== '' ? $klantZoek : 'alle klanten'),
    ];
}, false);
