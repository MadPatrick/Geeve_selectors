<?php

declare(strict_types=1);

// Beveiligde tegel (Config > Tegels): zonder ontgrendelen geen gegevens.
require_once __DIR__ . '/../../../shared/tiles.php';
tileApiGuard('stickers-order');

/**
 * Artikelstickers per Verkooporder: de regels van één order uit temp_Artikel_Stikkers_order in de data-database (standaard 005_Data_Geeve)
 * (dezelfde query als de NiceLabel-template), gesorteerd op orderregel. Zelfde SQL Server en inloggegevens (via Config)
 * als /stauff en de rapporten.
 */

require_once __DIR__ . '/../../../reports/inc.php';

const STICKER_COLUMNS = [
    'looplocatie', 'bonnr', 'locatie', 'debnr', 'debnm', 'uw_ref', 'artikelnummer', 'artikelnaam', 'besteld', 'geleverd',
    'ord_rgl', 'uw_artikelnummer', 'ia_CountryOfOrigin', 'soort', 'artgrpFin',
];

reportApi(function (string $nummer): array {
    $columns = implode(', ', array_map(fn ($c) => '[' . $c . ']', STICKER_COLUMNS));
    // bonnr kan een char-kolom met opvulspaties zijn: daarom LTRIM/RTRIM.
    $stmt = reportConnection('stickers-order')->prepare(
        "SET NOCOUNT ON; SELECT {$columns} FROM " . geeveDbNaamSql('data') . ".[dbo].[temp_Artikel_Stikkers_order] WHERE LTRIM(RTRIM([bonnr])) = ?"
    );
    $stmt->execute([$nummer]);

    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $out = [];
        foreach (STICKER_COLUMNS as $c) {
            $v = $row[$c] ?? null;
            $out[$c] = $v instanceof DateTimeInterface ? $v->format('Y-m-d') : ($v === null ? '' : trim((string) $v));
        }
        $rows[] = $out;
    }

    if ($rows === []) {
        // Geen regels: laat zien waarom (alleen lezen), zodat duidelijk is of het aan de bron, het nummerformaat of de order zelf ligt.
        return ['rows' => [], 'hint' => stickerOrderHint($nummer)];
    }

    // Ordervolgorde: op regelnummer (numeriek, ook als de kolom tekst is).
    usort($rows, fn ($a, $b) => strnatcmp($a['ord_rgl'], $b['ord_rgl']));

    return ['rows' => $rows];
});

/**
 * Diagnose bij een lege uitkomst: elke controle apart in try/catch, zodat een ontbrekende kolom of tabel de rest niet blokkeert.
 */
function stickerOrderHint(string $nummer): string
{
    $pdo = reportConnection('stickers-order');
    $db = geeveDbNaamSql('data');
    $delen = [];
    try {
        $st = $pdo->prepare("SET NOCOUNT ON; SELECT COUNT(*) AS n FROM {$db}.[dbo].[temp_Artikel_Stikkers_order] WHERE [bonnr] LIKE ?");
        $st->execute(['%' . $nummer . '%']);
        $n = (int) ($st->fetch()['n'] ?? 0);
        $delen[] = $n > 0 ? "bron bevat {$n} regels met dit nummer in een afwijkend formaat" : 'bron bevat dit ordernummer niet';
        $st = $pdo->query("SET NOCOUNT ON; SELECT COUNT(*) AS n, MAX(LTRIM(RTRIM([bonnr]))) AS hoogste FROM {$db}.[dbo].[temp_Artikel_Stikkers_order]");
        $r = $st->fetch();
        $delen[] = 'bron heeft ' . (int) ($r['n'] ?? 0) . ' regels, hoogste bonnr ' . trim((string) ($r['hoogste'] ?? ''));
    } catch (Throwable $e) {
        $delen[] = 'bron niet te lezen';
    }
    try {
        $st = $pdo->prepare("SET NOCOUNT ON; SELECT TOP 1 [ord_soort] FROM {$db}.[dbo].[orkrg] WHERE [ordernr] = ?");
        $st->execute([$nummer]);
        $r = $st->fetch();
        $delen[] = $r ? 'order bestaat in Exact (soort ' . trim((string) $r['ord_soort']) . ')' : 'order niet gevonden in Exact (orkrg)';
    } catch (Throwable $e) {
        $delen[] = 'orkrg niet te controleren';
    }
    return implode('; ', $delen) . '.';
}
