<?php

declare(strict_types=1);

/**
 * Controle op de knopregels (zie docs/KNOPPEN.md): zoekt in alle CSS-bestanden naar knop-selectors waarvan een kleur
 * (color, background, border, ...) niet uit een --btn-*-variabele komt, en naar losse hex-/rgba-waarden in de eigen CSS van
 * een app. Alleen lezen. Gebruik: php tools/check-knoppen.php   (exitcode 1 als er iets gevonden is)
 */

$root = dirname(__DIR__);
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
$knop = '/button|btn|^#\w*(Fetch|Export)\b|\.ka-add|\.oi-del|\.segment/i';
$kleurProp = '/^(color|background|background-color|border|border-color|border-top|border-bottom|fill|stroke)$/';
$problemen = [];

foreach ($iterator as $file) {
    $path = $file->getPathname();
    $rel = ltrim(substr($path, strlen($root)), '/');
    if (!str_ends_with($rel, '.css') || str_starts_with($rel, 'tmp/') || str_starts_with($rel, '.git/') || $rel === 'shared/tokens.css') {
        continue;
    }
    $css = (string) preg_replace('~/\*.*?\*/~s', '', (string) file_get_contents($path));
    $css = (string) preg_replace('~url\([^)]*\)~', 'url()', $css);
    if (preg_match_all('~([^{}@]+)\{([^{}]*)\}~', $css, $rules, PREG_SET_ORDER) === false) {
        continue;
    }
    foreach ($rules as $rule) {
        $selector = trim((string) preg_replace('/\s+/', ' ', $rule[1]));
        foreach (explode(';', $rule[2]) as $declaration) {
            if (!preg_match('/^\s*([\w-]+)\s*:(.*)$/s', $declaration, $m)) {
                continue;
            }
            $property = $m[1];
            $value = trim($m[2]);
            if (preg_match('/#[0-9a-fA-F]{3,8}\b|rgba?\(/', $value) === 1 && !str_starts_with($rel, 'shared/')) {
                $problemen[] = "$rel | $selector | losse kleurwaarde: $property: $value";
            } elseif (preg_match($knop, $selector) === 1 && preg_match($kleurProp, $property) === 1
                && preg_match('/var\(--/', $value) === 1 && !str_contains($value, '--btn-')) {
                $problemen[] = "$rel | $selector | knopkleur niet uit --btn-*: $property: $value";
            }
        }
    }
}

if ($problemen === []) {
    echo "Geen afwijkingen gevonden.\n";
    exit(0);
}
echo implode("\n", $problemen) . "\n" . count($problemen) . " afwijking(en).\n";
exit(1);
