<?php

declare(strict_types=1);

// Zelfde Exact-database (005), verbinding en staffel-korting-logica als de tegel Kortingstructuur.
require_once __DIR__ . '/../klantkorting/inc.php';

/** Zoektermen: woorden, zonder tekens die in een LIKE-patroon iets betekenen. @return list<string> */
function gbSearchWords(string $text): array
{
    $text = preg_replace('/[%_\[\]\\\\]+/', ' ', $text) ?? $text;
    return array_slice(array_values(array_filter(preg_split('/\s+/', trim($text)) ?: [], static fn (string $w): bool => $w !== '')), 0, 5);
}

/** Zonder koppeltekens, spaties, punten, komma's en underscores (zoals de fuzzy artikelzoeker van Stauff). */
function gbStripSql(string $expr): string
{
    return "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE({$expr}, '-', ''), ' ', ''), '.', ''), ',', ''), '_', '')";
}

/** Eerste kandidaat-kolom die in een tabel/view bestaat, of null. */
function gbFindColumn(PDO $pdo, string $table, array $candidates): ?string
{
    return findColumn(tableColumns($pdo, [$table])[$table] ?? [], $candidates);
}

/** Kolom met de (bruto) verkoopprijs in GRV_SalesItems; dezelfde kandidaten als de Stauff-tegel. */
function gbPriceColumn(PDO $pdo): ?string
{
    return gbFindColumn($pdo, 'GRV_SalesItems', ['Sales Price', 'SalesPrice', 'Price 1', 'Price1', 'Price', 'Verkoopprijs', 'Prijs']);
}

/**
 * Beschikbare voorraad per artikel (zelfde definitie als de Stauff-tegel): voorraad t/m vandaag minus het nog niet
 * geleverde deel van open verkooporders; mag negatief zijn.
 *
 * @param list<string> $codes
 * @return array<string,string> artikelcode => voorraad
 */
function gbFreeStock(PDO $pdo, array $codes): array
{
    if ($codes === []) {
        return [];
    }
    $in = implode(', ', array_fill(0, count($codes), '?'));
    $stmt = $pdo->prepare(
        'WITH st AS (' .
        'SELECT ItemCode, SUM(CASE WHEN [Date] <= GETDATE() THEN Quantity END) AS Voorraad ' .
        "FROM StockBalances WITH (NOLOCK) WHERE ItemCode IN ({$in}) GROUP BY ItemCode" .
        '), oo AS (' .
        'SELECT s.artcode AS ItemCode, SUM(s.esr_aantal - s.aant_gelev) AS OpenOrders ' .
        'FROM orsrg s WITH (NOLOCK) JOIN orkrg k WITH (NOLOCK) ON k.ordernr = s.ordernr ' .
        "WHERE k.ord_soort = 'V' AND s.esr_aantal > s.aant_gelev AND s.artcode IN ({$in}) GROUP BY s.artcode" .
        ') SELECT st.ItemCode, ISNULL(st.Voorraad, 0) - ISNULL(oo.OpenOrders, 0) AS Beschikbaar ' .
        'FROM st LEFT JOIN oo ON oo.ItemCode = st.ItemCode'
    );
    $stmt->execute(array_merge($codes, $codes));
    $out = [];
    while (($row = $stmt->fetch()) !== false) {
        $out[trim((string) $row['ItemCode'])] = $row['Beschikbaar'] !== null ? formatNumber($row['Beschikbaar']) : '';
    }
    return $out;
}

/**
 * Korting (%) voor een klant op een artikelgroep bij een aantal, uit de staffel-kortingsregels van de prijslijst van de klant
 * (dezelfde regels als in Kortingstructuur). Een klantspecifieke regel gaat voor op een regel voor alle klanten; verlopen en
 * nog niet geldende regels tellen niet mee; per regel geldt de hoogste staffel waarvan het aantal gehaald is.
 *
 * @param list<array<string,mixed>> $lines resultaat van findDiscountLines()
 * @return array{pct: float, note: string}
 */
function gbDiscountFor(array $lines, string $debcode, int $group, float $qty): array
{
    $today = time();
    $best = null;
    $bestSpecific = false;
    foreach ($lines as $line) {
        if ((int) ($line['ItemGroup'] ?? -1) !== $group) {
            continue;
        }
        $lineDeb = trim((string) ($line['debcode'] ?? ''));
        if ($lineDeb !== '' && $lineDeb !== $debcode) {
            continue;
        }
        $from = formatDateShort($line['validfrom'] ?? null) !== '' ? strtotime((string) $line['validfrom']) : false;
        $to = formatDateShort($line['validto'] ?? null) !== '' ? strtotime((string) $line['validto'] . ' 23:59:59') : false;
        if (($from !== false && $from > $today) || ($to !== false && $to < $today)) {
            continue;
        }
        $specific = $lineDeb !== '';
        if ($best === null || ($specific && !$bestSpecific)) {
            $best = $line;
            $bestSpecific = $specific;
        }
    }
    if ($best === null) {
        return ['pct' => 0.0, 'note' => 'geen korting op deze artikelgroep'];
    }
    $kind = trim((string) ($best['kort_pbn'] ?? 'P'));
    if ($kind !== 'P' && $kind !== '') {
        return ['pct' => 0.0, 'note' => 'kortingsoort ' . $kind . ' (geen percentage) wordt niet toegepast'];
    }
    $pct = 0.0;
    foreach (discountTiers($best) as $tier) {
        if ((float) str_replace(',', '.', $tier['qty']) <= $qty) {
            $pct = (float) str_replace(',', '.', $tier['discount']);
        }
    }
    return ['pct' => $pct, 'note' => ''];
}
