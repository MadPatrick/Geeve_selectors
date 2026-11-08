<?php

declare(strict_types=1);

/**
 * Controle op losse kleuren: elke kleur hoort als variabele in shared/tokens.css te staan (zie CLAUDE.md, "CSS-variabelen").
 * Zoekt buiten shared/tokens.css naar hex-, rgb(a)- en hsl(a)-waarden in CSS-declaraties, in style-attributen en in
 * PHP/JS-teksten. Commentaar telt niet mee. Uitzonderingen (merkkleuren van een logo, SVG's) staan in $toegestaan.
 * Alleen lezen. Gebruik: php tools/check-kleuren.php   (exitcode 1 als er iets gevonden is)
 */

$root = dirname(__DIR__);
$toegestaan = [];   // bestandspad (vanaf de root) => reden; SVG's worden niet gecontroleerd
$overslaan = '~^(tmp|data|\.git|node_modules)/|/vendor/|^tools/test-~';
$kleur = '~(?<![&\w])#[0-9a-fA-F]{6}\b|(?<![&\w])#[0-9a-fA-F]{3}\b(?![\w-])|\b(?:rgba?|hsla?)\(~';
$problemen = [];

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    $rel = ltrim(substr($file->getPathname(), strlen($root)), '/');
    if ($rel === 'shared/tokens.css' || preg_match($overslaan, $rel) === 1 || isset($toegestaan[$rel])) {
        continue;
    }
    $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
    if (!in_array($ext, ['css', 'php', 'js'], true)) {
        continue;
    }
    $tekst = (string) file_get_contents($file->getPathname());
    $tekst = (string) preg_replace_callback('~/\*.*?\*/~s', static fn (array $c): string => str_repeat("\n", substr_count($c[0], "\n")), $tekst);
    if ($ext !== 'css') {
        $tekst = (string) preg_replace('~(?<![:\'"])//[^\n]*~', '', $tekst);
    }
    foreach (explode("\n", $tekst) as $nr => $regel) {
        if (preg_match_all($kleur, $regel, $m) > 0) {
            $problemen[] = $rel . ':' . ($nr + 1) . ' | losse kleur ' . implode(', ', $m[0]) . ' - maak er een variabele van in shared/tokens.css';
        }
    }
}

if ($problemen === []) {
    echo "Geen losse kleuren gevonden buiten shared/tokens.css.\n";
    exit(0);
}
echo implode("\n", $problemen) . "\n" . count($problemen) . " losse kleur(en).\n";
exit(1);
