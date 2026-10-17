<?php

declare(strict_types=1);

/**
 * Controleert dat de korte adressen kloppen: elke tegel in TILE_REGISTRY heeft een adres in GEEVE_APP_URLS
 * (shared/paths.php), en elk kort adres dat niet gelijk is aan de echte map heeft zijn RewriteRules in de
 * root-.htaccess. Draaien vanaf de portal-map: php tools/check-adressen.php (exitcode 1 bij een fout).
 */

require __DIR__ . '/../shared/tiles.php';
require __DIR__ . '/../shared/paths.php';

$htaccess = (string) file_get_contents(__DIR__ . '/../.htaccess');
$errors = [];

foreach (TILE_REGISTRY as $id => $tile) {
    $path = $tile[2];
    if ($path === '') {
        continue; // plaatshouder zonder pagina
    }
    if (!isset(GEEVE_APP_URLS[$id])) {
        $errors[] = "Tegel '{$id}' heeft geen adres in GEEVE_APP_URLS.";
        continue;
    }
    if (!is_file(__DIR__ . '/../' . $path)) {
        $errors[] = "Tegel '{$id}': {$path} bestaat niet.";
    }
    $short = GEEVE_APP_URLS[$id];
    $realDir = dirname($path) . '/';
    if ($short === $realDir || !str_ends_with($short, '/')) {
        continue; // kort adres = echte map (of een vast pad): geen rewrite nodig
    }
    $slug = rtrim($short, '/');
    $expected = 'RewriteRule ^' . $slug . '/(.*)$ ' . rtrim($realDir, '/') . '/$1 [L]';
    if (!str_contains($htaccess, $expected)) {
        $errors[] = "Tegel '{$id}': regel ontbreekt in .htaccess: {$expected}";
    }
}

if ($errors !== []) {
    fwrite(STDERR, implode("\n", $errors) . "\n");
    exit(1);
}
echo "Korte adressen in orde (" . count(GEEVE_APP_URLS) . " adressen).\n";
