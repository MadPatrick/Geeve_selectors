<?php

declare(strict_types=1);

/**
 * Voegt de lage-druk-adapters (artikelnummers_lage_druk.csv, uit "Malleable gr 32.xlsx") toe aan de adapters-CSV
 * in data/adapters/ en zorgt dat die CSV de kolom `drukklasse` heeft (hoog / laag).
 *
 *  - Bestaande artikelen zonder waarde in `drukklasse` worden 'hoog'.
 *  - Bestaande regels met drukklasse 'laag' worden eerst verwijderd, dus opnieuw draaien geeft hetzelfde resultaat.
 *  - Een lage-druk-artikel waarvan het artikelnummer al als hoge druk bestaat, wordt overgeslagen en gemeld.
 *  - Er komt altijd eerst een back-up in data/backups/adapters/.
 *
 * Gebruik (op de server, in /var/www/html):
 *   php tools/adapters-lage-druk/lage-druk-toevoegen.php --dry-run   # alleen laten zien wat er zou gebeuren
 *   php tools/adapters-lage-druk/lage-druk-toevoegen.php
 */

if (PHP_SAPI !== 'cli') {
    exit("Alleen vanaf de opdrachtregel.\n");
}

require_once __DIR__ . '/../../shared/paths.php';

$dryRun = in_array('--dry-run', $argv, true);
$bron = __DIR__ . '/artikelnummers_lage_druk.csv';

$doel = findFirstReadableFile(geeveDataCandidates('adapters', 'artikelnummers_adapters.csv'));
if ($doel === null) {
    fwrite(STDERR, "Geen adapters-CSV gevonden in data/adapters/. Niets gedaan.\n");
    exit(1);
}

/** @return array{0: list<string>, 1: list<array<string,string>>} */
function leesCsv(string $pad): array
{
    $h = fopen($pad, 'r');
    if ($h === false) {
        fwrite(STDERR, "Kan $pad niet openen.\n");
        exit(1);
    }
    $kop = fgetcsv($h, 0, ',', '"', '');
    if ($kop === false) {
        fwrite(STDERR, "$pad heeft geen kopregel.\n");
        exit(1);
    }
    $kop = array_map(static fn($k): string => trim((string) preg_replace('/^\xEF\xBB\xBF/', '', (string) $k)), $kop);
    $rijen = [];
    while (($data = fgetcsv($h, 0, ',', '"', '')) !== false) {
        if (count($data) !== count($kop)) {
            continue;
        }
        $rijen[] = array_combine($kop, $data);
    }
    fclose($h);
    return [$kop, $rijen];
}

[$kop, $bestaand] = leesCsv($doel);
[, $nieuw] = leesCsv($bron);

if (!in_array('drukklasse', $kop, true)) {
    $kop[] = 'drukklasse';
}

$verwijderdLaag = 0;
$behouden = [];
foreach ($bestaand as $rij) {
    $klasse = strtolower(trim($rij['drukklasse'] ?? ''));
    if ($klasse === 'laag') {
        $verwijderdLaag++;
        continue;
    }
    $rij['drukklasse'] = 'hoog';
    $behouden[] = $rij;
}

$bekend = [];
foreach ($behouden as $rij) {
    $bekend[strtolower(trim($rij['artnr']))] = true;
}

$toegevoegd = 0;
$dubbel = [];
foreach ($nieuw as $rij) {
    $sleutel = strtolower(trim($rij['artnr']));
    if (isset($bekend[$sleutel])) {
        $dubbel[] = $rij['artnr'];
        continue;
    }
    $bekend[$sleutel] = true;
    $rij['drukklasse'] = 'laag';
    $behouden[] = $rij;
    $toegevoegd++;
}

printf("Bestand: %s\n", $doel);
printf("Hoge druk (bestaand): %d\n", count($behouden) - $toegevoegd);
printf("Lage druk toegevoegd: %d (eerdere lage-drukregels vervangen: %d)\n", $toegevoegd, $verwijderdLaag);
printf("Overgeslagen, artikelnummer bestaat al als hoge druk: %d %s\n", count($dubbel), $dubbel === [] ? '' : '(' . implode(', ', $dubbel) . ')');

if ($dryRun) {
    echo "Dry-run: er is niets geschreven.\n";
    exit(0);
}

$backupDir = geeveBackupDir($doel);
if (!is_dir($backupDir) && !@mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
    fwrite(STDERR, "Kan back-upmap $backupDir niet maken. Niets gewijzigd.\n");
    exit(1);
}
$backup = $backupDir . '/' . pathinfo($doel, PATHINFO_FILENAME) . '-' . date('Ymd-His') . '-voor-lage-druk.csv';
if (!copy($doel, $backup)) {
    fwrite(STDERR, "Back-up naar $backup mislukt. Niets gewijzigd.\n");
    exit(1);
}
echo "Back-up: $backup\n";

$tmp = $doel . '.tmp';
$h = fopen($tmp, 'w');
if ($h === false) {
    fwrite(STDERR, "Kan $tmp niet schrijven (rechten op data/adapters/?). Niets gewijzigd.\n");
    exit(1);
}
fwrite($h, "\xEF\xBB\xBF");
fputcsv($h, $kop, ',', '"', '');
foreach ($behouden as $rij) {
    $regel = [];
    foreach ($kop as $k) {
        $regel[] = $rij[$k] ?? '';
    }
    fputcsv($h, $regel, ',', '"', '');
}
fclose($h);

// Controle voor de vervanging: even veel regels als verwacht.
[, $controle] = leesCsv($tmp);
if (count($controle) !== count($behouden)) {
    @unlink($tmp);
    fwrite(STDERR, "Controle mislukt (" . count($controle) . " ≠ " . count($behouden) . " regels). Niets gewijzigd.\n");
    exit(1);
}
if (!rename($tmp, $doel)) {
    @unlink($tmp);
    fwrite(STDERR, "Vervangen van $doel mislukt. Niets gewijzigd.\n");
    exit(1);
}
echo "Klaar. Terugzetten kan met de back-up hierboven.\n";
