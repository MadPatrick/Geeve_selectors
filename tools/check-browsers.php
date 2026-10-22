<?php

declare(strict_types=1);

/**
 * Browsercompatibiliteit-check (zie CLAUDE.md, "Browsercompatibiliteit"). Scant PHP (inline <script>/<style> en markup),
 * JS en CSS op functies die in oudere browsers niet werken zonder fallback, en PHP op mb_* (geen mbstring op de server).
 * Alleen lezen. Dit is een statische controle op bekende patronen, GEEN vervanging van een test in een echte browser.
 *
 * Gebruik:  php tools/check-browsers.php [--doel=ie11|edge|modern] [--alles] [--gewijzigd] [--baseline-schrijven]
 *   --doel      oudste browser die moet werken. ie11 = alles melden (standaard), edge = Edge Legacy (EdgeHTML 18) en
 *               Firefox ESR 60 en nieuwer, modern = alleen wat ook in browsers van ~2019/2020 stukgaat.
 *   --alles     elke vondst met bestand en regel tonen i.p.v. drie voorbeelden per regel.
 *   --gewijzigd alleen bestanden die verschillen van origin/main (voor een snelle check vlak voor een push).
 *   --baseline-schrijven  huidige stand vastleggen in tools/check-browsers-baseline.json. Daarna faalt de check (exitcode 1)
 *               alleen op NIEUWE vondsten t.o.v. die stand; mb_* faalt altijd. Zonder baseline faalt elke vondst.
 */

$root = dirname(__DIR__);
$baselineFile = __DIR__ . '/check-browsers-baseline.json';
$opts = getopt('', ['doel::', 'alles', 'gewijzigd', 'baseline-schrijven']);
$doelNamen = ['ie11' => 1, 'edge' => 2, 'modern' => 3];
$doelNaam = (string) ($opts['doel'] ?? 'ie11');
if (!isset($doelNamen[$doelNaam])) {
    fwrite(STDERR, "Onbekend --doel. Kies ie11, edge of modern.\n");
    exit(2);
}
$doel = $doelNamen[$doelNaam];

/*
 * Regels: [id, omschrijving, patroon, bron, faalt-vanaf]. "faalt" = hoogste doel waarbij het nog stukgaat:
 * 1 = alleen IE11, 2 = ook Edge Legacy / Firefox ESR 60, 3 = ook browsers van ~2019/2020.
 * Bron: js (gestript van commentaar en tekst), css (gestript van commentaar), html (ruwe markup in PHP/HTML).
 */
$regels = [
    // JavaScript
    ['js-pijl', 'pijlfunctie =>', '/=>/', 'js', 1],
    ['js-template', 'template literal `...`', '/`/', 'js', 1],
    ['js-class', 'class-declaratie', '/\bclass\s+[A-Za-z_$][\w$]*/', 'js', 1],
    ['js-async', 'async/await', '/\basync\s+(function\b|\(|[A-Za-z_$])|\bawait\s/', 'js', 1],
    ['js-spread', 'spread/rest ...', '/(?<![.\d])\.\.\.(?=[\w$(\[{])/', 'js', 1],
    ['js-defaultparam', 'standaardwaarde in parameter', '/\bfunction\s*[\w$]*\s*\([^)]*=[^=>)][^)]*\)/', 'js', 1],
    ['js-forof', 'for...of', '/\bfor\s*\(\s*(const|let|var)\s+[^;)]*\sof\s/', 'js', 1],
    ['js-destructuring', 'destructuring', '/\b(const|let|var)\s*[\[{][^=;]*[\]}]\s*=[^=]/', 'js', 1],
    ['js-optchain', 'optional chaining ?.', '/\?\.(?![\d])/', 'js', 3],
    ['js-nullish', 'nullish coalescing ??', '/\?\?/', 'js', 3],
    ['js-fetch', 'fetch()', '/(?<![.\w$])fetch\s*\(/', 'js', 1],
    ['js-promise', 'Promise', '/\bPromise\b/', 'js', 1],
    ['js-assign', 'Object.assign', '/\bObject\.assign\s*\(/', 'js', 1],
    ['js-entries', 'Object.entries/values/fromEntries', '/\bObject\.(entries|values|fromEntries)\s*\(/', 'js', 1],
    ['js-arrayfrom', 'Array.from / Array.of', '/\bArray\.(from|of)\s*\(/', 'js', 1],
    ['js-includes', '.includes()', '/\.includes\s*\(/', 'js', 1],
    ['js-startswith', '.startsWith/.endsWith/.repeat', '/\.(startsWith|endsWith|repeat)\s*\(/', 'js', 1],
    ['js-find', 'Array .find/.findIndex', '/\.(find|findIndex)\s*\(/', 'js', 1],
    ['js-pad', '.padStart/.padEnd', '/\.(padStart|padEnd)\s*\(/', 'js', 1],
    ['js-flat', '.flat/.flatMap', '/\.(flat|flatMap)\s*\(/', 'js', 2],
    ['js-replaceall', '.replaceAll', '/\.replaceAll\s*\(/', 'js', 3],
    ['js-at', '.at()', '/\.at\s*\(\s*-?\d/', 'js', 3],
    ['js-structuredclone', 'structuredClone', '/\bstructuredClone\s*\(/', 'js', 3],
    ['js-collections', 'Map/Set/WeakMap', '/\bnew\s+(Map|Set|WeakMap|WeakSet)\b/', 'js', 1],
    ['js-symbol', 'Symbol', '/\bSymbol\b/', 'js', 1],
    ['js-urlsp', 'URLSearchParams / new URL', '/\bURLSearchParams\b|\bnew\s+URL\s*\(/', 'js', 1],
    ['js-closest', 'Element.closest/.matches/.remove()', '/\.(closest|matches)\s*\(|(?<!classList)\.remove\s*\(\s*\)/', 'js', 1],
    ['js-observer', 'IntersectionObserver/ResizeObserver', '/\b(IntersectionObserver|ResizeObserver)\b/', 'js', 1],
    ['js-abort', 'AbortController', '/\bAbortController\b/', 'js', 2],
    ['js-clipboard', 'navigator.clipboard (alleen https/localhost)', '/\bnavigator\.clipboard\b/', 'js', 3],
    ['js-fileblob', 'new File()/new Blob()', '/\bnew\s+(File|Blob)\s*\(/', 'js', 1],
    // CSS
    ['css-var', 'var() zonder fallback (IE11 kent geen CSS-variabelen)', '/var\(\s*--[\w-]+\s*\)/', 'css', 1],
    ['css-grid', 'CSS grid (IE11: oude syntax)', '/display\s*:\s*(inline-)?grid|grid-template|grid-area|grid-column|grid-row/', 'css', 1],
    ['css-gap', 'gap (in flex pas Chrome 84/Firefox 63/Edge 84)', '/(?<![\w-])(row-|column-)?gap\s*:/', 'css', 2],
    ['css-sticky', 'position: sticky', '/position\s*:\s*sticky/', 'css', 1],
    ['css-objectfit', 'object-fit/object-position', '/object-(fit|position)\s*:/', 'css', 1],
    ['css-clamp', 'clamp()/min()/max()', '/(?<![\w-])(clamp|min|max)\s*\(/', 'css', 2],
    ['css-aspect', 'aspect-ratio', '/aspect-ratio\s*:/', 'css', 3],
    ['css-inset', 'inset (shorthand)', '/(?<![\w-])inset\s*:/', 'css', 3],
    ['css-has', ':has()', '/:has\s*\(/', 'css', 3],
    ['css-is', ':is()/:where()', '/:(is|where)\s*\(/', 'css', 3],
    ['css-focusvisible', ':focus-visible', '/:focus-visible/', 'css', 3],
    ['css-backdrop', 'backdrop-filter', '/backdrop-filter\s*:/', 'css', 3],
    ['css-supports', '@supports', '/@supports/', 'css', 1],
    ['css-dvh', 'dvh/svh/lvh-eenheden', '/\d(dvh|svh|lvh|dvw|svw|lvw)\b/', 'css', 3],
    ['css-colormix', 'color-mix()/accent-color/rgb met spaties', '/color-mix\s*\(|accent-color\s*:|rgba?\(\s*\d+\s+\d+\s+\d+/', 'css', 3],
    ['css-container', '@container/@layer', '/@container|@layer/', 'css', 3],
    ['css-transformprops', 'losse translate/rotate/scale-eigenschappen', '/(?<![\w-])(translate|rotate|scale)\s*:/', 'css', 3],
    ['css-scrollbehavior', 'scroll-behavior', '/scroll-behavior\s*:/', 'css', 1],
    ['css-textwrap', 'text-wrap/overflow: clip/place-*', '/text-wrap\s*:|overflow(-[xy])?\s*:\s*clip|place-(items|content|self)\s*:/', 'css', 3],
    // HTML in PHP/HTML
    ['html-inputtype', 'input type date/time/month/week/color/range (IE11 toont tekstveld)', '/<input[^>]*\btype\s*=\s*["\']?(date|time|datetime-local|month|week|color|range)\b/i', 'html', 1],
    ['html-dialog', '<dialog>/<details>/<template>', '/<(dialog|details|template)\b/i', 'html', 1],
    ['html-lazy', 'loading="lazy"/decoding=', '/\b(loading\s*=\s*["\']lazy|decoding\s*=)/i', 'html', 3],
];

/** Vervangt commentaar en tekst door spaties (regelnummers blijven kloppen). Template literals: alleen backticks blijven. */
function stripJs(string $s): string
{
    $out = '';
    $n = strlen($s);
    $prev = '';
    for ($i = 0; $i < $n; $i++) {
        $c = $s[$i];
        $d = $s[$i + 1] ?? '';
        if ($c === '/' && $d === '/') {
            while ($i < $n && $s[$i] !== "\n") {
                $i++;
            }
            $i--;
            continue;
        }
        if ($c === '/' && $d === '*') {
            $end = strpos($s, '*/', $i + 2);
            $end = $end === false ? $n : $end + 2;
            $out .= preg_replace('/[^\n]/', ' ', substr($s, $i, $end - $i));
            $i = $end - 1;
            continue;
        }
        if ($c === '"' || $c === "'" || $c === '`') {
            $q = $c;
            $out .= $q === '`' ? '`' : ' ';
            for ($i++; $i < $n && $s[$i] !== $q; $i++) {
                if ($s[$i] === '\\') {
                    $i++;
                    $out .= ' ';
                }
                $out .= ($s[$i] ?? '') === "\n" ? "\n" : ' ';
            }
            $out .= $q === '`' ? '`' : ' ';
            $prev = 'x';
            continue;
        }
        // regex-literal (heuristiek: na operator/haakje/begin)
        if ($c === '/' && ($prev === '' || strpos('(,=:[!&|?{};+-*%<>~^', $prev) !== false)) {
            $j = $i + 1;
            $klasse = false;
            while ($j < $n && $s[$j] !== "\n" && ($s[$j] !== '/' || $klasse)) {
                if ($s[$j] === '\\') {
                    $j++;
                } elseif ($s[$j] === '[') {
                    $klasse = true;
                } elseif ($s[$j] === ']') {
                    $klasse = false;
                }
                $j++;
            }
            if ($j < $n && $s[$j] === '/') {
                $out .= str_repeat(' ', $j - $i + 1);
                $i = $j;
                $prev = 'x';
                continue;
            }
        }
        $out .= $c;
        if (!ctype_space($c)) {
            $prev = $c;
        }
    }
    return $out;
}

function stripCss(string $s): string
{
    $s = (string) preg_replace_callback('~/\*.*?\*/~s', static fn(array $m): string => (string) preg_replace('/[^\n]/', ' ', $m[0]), $s);
    return (string) preg_replace_callback('~url\([^)]*\)|"[^"\n]*"|\'[^\'\n]*\'~', static fn(array $m): string => str_repeat(' ', strlen($m[0])), $s);
}

/** Haalt inline <script>/<style>-blokken uit PHP/HTML; regelnummers blijven kloppen. PHP-tags worden onschadelijk gemaakt. */
function inlineBlokken(string $s, string $tag): string
{
    $s = (string) preg_replace_callback('~<\?(?:php|=).*?\?>~s', static fn(array $m): string => '0' . (string) preg_replace('/[^\n]/', '', $m[0]), $s);
    $out = '';
    if (preg_match_all('~<' . $tag . '\b(?![^>]*\bsrc=)[^>]*>(.*?)</' . $tag . '>~is', $s, $m, PREG_OFFSET_CAPTURE)) {
        $pos = 0;
        foreach ($m[1] as $blok) {
            $out .= str_repeat("\n", substr_count(substr($s, $pos, $blok[1] - $pos), "\n")) . $blok[0];
            $pos = $blok[1] + strlen($blok[0]);
        }
    }
    return $out;
}

// bestanden verzamelen
$bestanden = [];
if (isset($opts['gewijzigd'])) {
    $lijst = (string) shell_exec('cd ' . escapeshellarg($root) . ' && git diff --name-only origin/main 2>/dev/null; git ls-files --others --exclude-standard 2>/dev/null');
    foreach (array_filter(explode("\n", $lijst)) as $rel) {
        if (is_file("$root/$rel")) {
            $bestanden[] = $rel;
        }
    }
} else {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        $bestanden[] = ltrim(substr($f->getPathname(), strlen($root)), '/');
    }
}
sort($bestanden);

$vondsten = [];   // [regel-id => [[bestand, regel, tekst], ...]]
$mb = [];
$gescand = 0;
foreach ($bestanden as $rel) {
    if (preg_match('~^(\.git|tmp|data|node_modules)/|^tools/check-browsers\.php$|(^|/)vendor/|\.min\.(js|css)$~', $rel) === 1) {
        continue;
    }
    $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
    if (!in_array($ext, ['php', 'js', 'css', 'html'], true)) {
        continue;
    }
    $raw = (string) file_get_contents("$root/$rel");
    $rawRegels = explode("\n", $raw);
    $gescand++;
    $bronnen = [];
    if ($ext === 'js') {
        $bronnen['js'] = stripJs($raw);
    }
    if ($ext === 'css') {
        $bronnen['css'] = stripCss($raw);
    }
    if ($ext === 'php' || $ext === 'html') {
        $bronnen['js'] = stripJs(inlineBlokken($raw, 'script'));
        $bronnen['css'] = stripCss(inlineBlokken($raw, 'style'));
        $bronnen['html'] = $raw;
    }
    if ($ext === 'php') {
        $tokens = token_get_all($raw);
        foreach ($tokens as $i => $t) {
            if (is_array($t) && $t[0] === T_STRING && stripos($t[1], 'mb_') === 0 && ($tokens[$i + 1] ?? '') === '(') {
                $mb[] = [$rel, $t[2], $t[1] . '()'];
            }
        }
    }
    foreach ($regels as [$id, , $patroon, $bron, $faalt]) {
        if ($faalt < $doel || !isset($bronnen[$bron])) {
            continue;
        }
        foreach (explode("\n", $bronnen[$bron]) as $nr => $regel) {
            if ($regel !== '' && preg_match($patroon, $regel) === 1) {
                $vondsten[$id][] = [$rel, $nr + 1, trim(substr($rawRegels[$nr] ?? $regel, 0, 110))];
            }
        }
    }
}

// tellingen per regel en bestand, vergelijken met baseline
$tel = [];
foreach ($vondsten as $id => $lijst) {
    foreach ($lijst as [$rel]) {
        $tel["$id|$rel"] = ($tel["$id|$rel"] ?? 0) + 1;
    }
}
if (isset($opts['baseline-schrijven'])) {
    ksort($tel);
    file_put_contents($baselineFile, json_encode($tel, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    echo 'Baseline geschreven: ' . count($tel) . " combinaties in tools/check-browsers-baseline.json\n";
}
$baseline = is_file($baselineFile) && !isset($opts['baseline-schrijven'])
    ? (array) json_decode((string) file_get_contents($baselineFile), true) : [];
$nieuw = 0;

$omschr = [];
foreach ($regels as $r) {
    $omschr[$r[0]] = $r[1];
}
echo "Browsercompatibiliteit - doel: $doelNaam - $gescand bestanden gescand\n";
echo str_repeat('-', 78) . "\n";
if ($mb !== []) {
    echo 'FOUT mb_* (geen mbstring op de server): ' . count($mb) . "\n";
    foreach ($mb as [$rel, $nr, $t]) {
        echo "  $rel:$nr  $t\n";
    }
}
foreach ($vondsten as $id => $lijst) {
    $nieuwHier = 0;
    foreach (array_count_values(array_column($lijst, 0)) as $rel => $aantal) {
        $nieuwHier += max(0, $aantal - (int) ($baseline["$id|$rel"] ?? 0));
    }
    $nieuw += $nieuwHier;
    printf("%-19s %5d x in %3d bestand(en)%s  %s\n", $id, count($lijst), count(array_unique(array_column($lijst, 0))),
        $baseline !== [] ? sprintf(' (%d nieuw)', $nieuwHier) : '', $omschr[$id]);
    foreach (array_slice($lijst, 0, isset($opts['alles']) ? PHP_INT_MAX : 3) as [$rel, $nr, $t]) {
        echo "      $rel:$nr  $t\n";
    }
}
if ($vondsten === [] && $mb === []) {
    echo "Niets gevonden.\n";
}
echo str_repeat('-', 78) . "\n";
echo "Let op: statische patroonscan. Niet gedekt: JS-runtimegedrag, polyfills die elders geladen worden, layoutverschillen.\n";
echo "IE11 en Edge Legacy kunnen hier niet echt getest worden.\n";
$totaal = array_sum(array_map('count', $vondsten));
if ($mb !== [] || ($baseline !== [] && $nieuw > 0) || ($baseline === [] && $totaal > 0)) {
    echo $baseline !== [] ? "RESULTAAT: mislukt ($nieuw nieuwe vondst(en) t.o.v. de baseline)\n" : "RESULTAAT: $totaal vondst(en), geen baseline vastgelegd\n";
    exit(1);
}
echo "RESULTAAT: geen nieuwe vondsten\n";
