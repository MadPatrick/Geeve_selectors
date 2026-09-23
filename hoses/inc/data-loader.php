<?php

declare(strict_types=1);

// Gedeeld door index.php en save.php, zodat beide exact dezelfde CSV-inlees-
// en samenvoeglogica gebruiken (voorkomt dat de bewerkbare weergave ooit
// afwijkt van wat er na het opslaan daadwerkelijk in de CSV's staat).

function cleanValue($value): string
{
    if ($value === null) {
        return '';
    }

    $value = str_replace(["\xC2\xA0", "_x000D_"], ' ', (string) $value);
    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

    return trim($value);
}

function normalizeHeader($value): string
{
    $value = preg_replace('/^\xEF\xBB\xBF/', '', (string) $value) ?? (string) $value;
    return strtolower(cleanValue($value));
}

function getColumn(array $row, string $columnName): string
{
    $target = normalizeHeader($columnName);

    foreach ($row as $column => $value) {
        if (normalizeHeader($column) === $target) {
            return cleanValue($value);
        }
    }

    return '';
}

// Kolomnamen voor een 2delig_N-veld, in dezelfde volgorde als de CSV-header
// en als de velden in de bewerkbare weergave.
function comboColumns(int $number): array
{
    $prefix = '2delig_' . $number;

    return [
        'huls'        => $prefix . ' - Huls',
        'pilaar'      => $prefix . ' - Pilaar',
        'persmaat'    => $prefix . ' - Persmaat (mm)',
        'schilIntern' => $prefix . ' - Schilmaat intern (mm)',
        'schilExtern' => $prefix . ' - Schilmaat extern (mm)',
    ];
}

// Kolomnamen voor een 1delig_N-veld.
function couplingColumns(int $number): array
{
    $prefix = '1delig_' . $number;

    return [
        'koppeling'     => $prefix,
        'persmaat'      => $prefix . ' - Persmaat (mm)',
        'insteekdiepte' => $prefix . ' - Insteekdiepte (mm)',
        'schilIntern'   => $prefix . ' - Schilmaat intern (mm)',
        'schilExtern'   => $prefix . ' - Schilmaat extern (mm)',
    ];
}

// Alle bewerkbare kolomnamen voor artikelnummers_staal.csv / _rvs.csv (2
// combo-slots + 3 koppeling-slots), in vaste volgorde.
function editableMaterialColumns(): array
{
    $columns = [];
    for ($number = 1; $number <= 2; $number++) {
        $columns = array_merge($columns, array_values(comboColumns($number)));
    }
    for ($number = 1; $number <= 3; $number++) {
        $columns = array_merge($columns, array_values(couplingColumns($number)));
    }
    return $columns;
}

// Kolomnaam-mapping voor de bewerkbare accessoire-velden (JS-sleutel =>
// exacte CSV-kolomnaam).
function accessoryColumnMap(): array
{
    return [
        'outside'        => 'Buitenmaat slang (mm)',
        'rvsOmvlechting' => 'RVS Omvlechting',
        'polyGuard'      => 'PolyGuard',
        'parKoil'        => 'ParKoil',
        'springGuard'    => 'Spring Guard',
        'firesleeve'     => 'Firesleeve',
        'spiralGuard'    => 'SpiralGuard',
        'texsleeve'      => 'Texsleeve',
        'hulsTexStaal'   => 'Huls tex staal',
        'hulsTexRvs'     => 'Huls tex RVS',
    ];
}

function readVariant(array $row, string $type, int $number): array
{
    if ($type === 'combo') {
        $columns = comboColumns($number);

        return [
            'number'      => $number,
            'huls'        => getColumn($row, $columns['huls']),
            'pilaar'      => getColumn($row, $columns['pilaar']),
            'persmaat'    => getColumn($row, $columns['persmaat']),
            'schilIntern' => getColumn($row, $columns['schilIntern']),
            'schilExtern' => getColumn($row, $columns['schilExtern']),
        ];
    }

    $columns = couplingColumns($number);

    return [
        'number'        => $number,
        'koppeling'     => getColumn($row, $columns['koppeling']),
        'persmaat'      => getColumn($row, $columns['persmaat']),
        'insteekdiepte' => getColumn($row, $columns['insteekdiepte']),
        'schilIntern'   => getColumn($row, $columns['schilIntern']),
        'schilExtern'   => getColumn($row, $columns['schilExtern']),
    ];
}

function hasCombo(array $variant): bool
{
    return $variant['huls'] !== '' || $variant['pilaar'] !== '';
}

function hasCoupling(array $variant): bool
{
    return $variant['koppeling'] !== '';
}

function articleKey(string $articleNumber): string
{
    return '@' . strtolower(trim($articleNumber));
}

function loadAccessoryRows(?string $csvFile, array &$errors): array
{
    if ($csvFile === null) {
        $errors[] = 'Bestand artikelnummers_accessoires.csv is niet gevonden. Accessoires worden niet getoond.';
        return [];
    }

    $handle = fopen($csvFile, 'r');
    if ($handle === false) {
        $errors[] = 'CSV-bestand artikelnummers_accessoires.csv kan niet worden geopend.';
        return [];
    }

    $headers = fgetcsv($handle, 0, ',');
    if ($headers === false) {
        fclose($handle);
        $errors[] = 'CSV-bestand artikelnummers_accessoires.csv bevat geen geldige kopregel.';
        return [];
    }

    $headers = array_map('cleanValue', $headers);
    $rows = [];
    $accessoryColumnMap = accessoryColumnMap();

    while (($data = fgetcsv($handle, 0, ',')) !== false) {
        if (count($data) !== count($headers)) {
            continue;
        }

        $row = array_combine($headers, $data);
        if ($row === false) {
            continue;
        }

        $articleNumber = getColumn($row, 'artnr');
        if ($articleNumber === '') {
            continue;
        }

        $entry = [];
        foreach ($accessoryColumnMap as $jsKey => $column) {
            $entry[$jsKey] = getColumn($row, $column);
        }
        $rows[articleKey($articleNumber)] = $entry;
    }

    fclose($handle);
    return $rows;
}

function loadMaterialRows(?string $csvFile, string $label, array &$errors): array
{
    $expectedName = $label === 'Staal' ? 'artikelnummers_staal.csv' : 'artikelnummers_rvs.csv';
    if ($csvFile === null) {
        $errors[] = "Bestand {$expectedName} voor {$label} is niet gevonden.";
        return [];
    }

    $basename = basename($csvFile);

    if (!is_readable($csvFile)) {
        $errors[] = "Bestand {$basename} voor {$label} kan niet worden gelezen.";
        return [];
    }

    $handle = fopen($csvFile, 'r');
    if ($handle === false) {
        $errors[] = "CSV-bestand {$basename} voor {$label} kan niet worden geopend.";
        return [];
    }

    $headers = fgetcsv($handle, 0, ',');
    if ($headers === false) {
        fclose($handle);
        $errors[] = "CSV-bestand {$basename} voor {$label} bevat geen geldige kopregel.";
        return [];
    }

    $headers = array_map('cleanValue', $headers);
    $rows = [];

    while (($data = fgetcsv($handle, 0, ',')) !== false) {
        if (count($data) !== count($headers)) {
            continue;
        }

        $row = array_combine($headers, $data);
        if ($row === false) {
            continue;
        }

        $articleNumber = getColumn($row, 'artnr');
        if ($articleNumber === '') {
            continue;
        }

        $combos = [];
        $couplings = [];

        for ($number = 1; $number <= 2; $number++) {
            $variant = readVariant($row, 'combo', $number);
            if (hasCombo($variant)) {
                $combos[] = $variant;
            }
        }

        usort($combos, static function (array $a, array $b): int {
            $hulsRank = static function (array $variant): int {
                if (str_contains($variant['huls'], '13002')) {
                    return 0;
                }
                if (str_contains($variant['huls'], '13001')) {
                    return 1;
                }
                return 2;
            };

            return $hulsRank($a) <=> $hulsRank($b);
        });

        for ($number = 1; $number <= 3; $number++) {
            $variant = readVariant($row, 'coupling', $number);
            if (hasCoupling($variant)) {
                $couplings[] = $variant;
            }
        }

        $rows[] = [
            'artnr'      => $articleNumber,
            'artnm'      => getColumn($row, 'artnm'),
            'vendor'     => getColumn($row, 'Leverancier'),
            'supplier'   => getColumn($row, 'Artikelnr leverancier'),
            'werkdruk'   => getColumn($row, 'Werkdruk (bar)'),
            'combo'      => $combos,
            'couplings'  => $couplings,
        ];
    }

    fclose($handle);
    return $rows;
}

// Laadt + voegt samen op dezelfde manier als index.php dat voor de hele
// dataset doet, maar dan voor gebruik door save.php (dat na het schrijven
// de bijgewerkte rij in exact dezelfde vorm als window.ARTICLES teruggeeft).
function loadMergedArticles(array $csvFiles, ?string $accessoryCsvFile, array &$errors): array
{
    $materialRows = [
        'staal' => loadMaterialRows($csvFiles['staal'], 'Staal', $errors),
        'rvs'   => loadMaterialRows($csvFiles['rvs'], 'RVS', $errors),
    ];

    $accessoryRows = loadAccessoryRows($accessoryCsvFile, $errors);

    $merged = [];
    $order = [];

    foreach (['staal', 'rvs'] as $material) {
        foreach ($materialRows[$material] as $row) {
            $key = articleKey($row['artnr']);

            if (!isset($merged[$key])) {
                $merged[$key] = [
                    'artnr'          => $row['artnr'],
                    'artnm'          => $row['artnm'],
                    'vendor'         => $row['vendor'],
                    'supplier'       => $row['supplier'],
                    'werkdruk'       => $row['werkdruk'],
                    'comboStaal'     => [],
                    'koppelingStaal' => [],
                    'comboRvs'       => [],
                    'koppelingRvs'   => [],
                    'accessories'    => $accessoryRows[$key] ?? [],
                ];
                $order[] = $key;
            } else {
                foreach (['artnm', 'vendor', 'supplier', 'werkdruk'] as $field) {
                    if ($merged[$key][$field] === '' && $row[$field] !== '') {
                        $merged[$key][$field] = $row[$field];
                    }
                }
            }

            if ($material === 'staal') {
                $merged[$key]['comboStaal'] = $row['combo'];
                $merged[$key]['koppelingStaal'] = $row['couplings'];
            } else {
                $merged[$key]['comboRvs'] = $row['combo'];
                $merged[$key]['koppelingRvs'] = $row['couplings'];
            }

            if (isset($accessoryRows[$key])) {
                $merged[$key]['accessories'] = $accessoryRows[$key];
            }
        }
    }

    $articles = [];
    foreach ($order as $key) {
        $articles[] = $merged[$key];
    }

    usort($articles, static fn(array $a, array $b): int => strnatcasecmp($a['artnr'], $b['artnr']));

    return $articles;
}
