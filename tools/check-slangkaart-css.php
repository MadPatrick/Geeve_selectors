<?php
/**
 * Vergelijkt de slangkaart-opmaak van de portal (shared/slangkaart.css) met die van Slangkaart.Web (wwwroot/app.css).
 * De twee bestanden zijn bewust kopieën (de .NET-app laadt niets van de portal); dit script meldt waar ze uit elkaar lopen.
 *
 * Gebruik: php tools/check-slangkaart-css.php [pad naar slangkaart-web/src/Slangkaart.Web/wwwroot/app.css]
 * Standaard wordt ../slangkaart-web/src/Slangkaart.Web/wwwroot/app.css gebruikt. Exitcode 1 bij een nieuw verschil.
 * Vergeleken wordt de basisregel (de eerste) van elke .card-* en .coupling-* selector; bekende, bewuste verschillen staan
 * in tools/check-slangkaart-css-baseline.json (een nieuw bewust verschil: voeg de selector daar toe, met reden).
 */

function slangkaartRules(string $file): array
{
    $css = preg_replace('~/\*.*?\*/~s', '', (string) file_get_contents($file));
    $rules = [];
    if (preg_match_all('~([^{}]+)\{([^{}]*)\}~', $css, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $m) {
            $selector = trim(preg_replace('~\s+~', ' ', $m[1]));
            if (!preg_match('~^\.(card-|coupling-)~', $selector) || isset($rules[$selector])) {
                continue;
            }
            $props = [];
            foreach (explode(';', $m[2]) as $decl) {
                if (strpos($decl, ':') !== false) {
                    [$k, $v] = explode(':', $decl, 2);
                    $props[trim($k)] = trim(preg_replace('~\s+~', ' ', $v));
                }
            }
            ksort($props);
            $rules[$selector] = $props;
        }
    }
    return $rules;
}

$portal = __DIR__ . '/../shared/slangkaart.css';
$net = $argv[1] ?? __DIR__ . '/../../slangkaart-web/src/Slangkaart.Web/wwwroot/app.css';
if (!is_readable($net)) {
    fwrite(STDERR, "app.css van Slangkaart.Web niet gevonden: $net\n");
    exit(2);
}
$baselineFile = __DIR__ . '/check-slangkaart-css-baseline.json';
$baseline = is_readable($baselineFile) ? (array) json_decode((string) file_get_contents($baselineFile), true) : [];

$a = slangkaartRules($portal);
$b = slangkaartRules($net);
$new = [];
foreach ($a as $selector => $props) {
    if (!isset($b[$selector])) {
        $verschil = 'ontbreekt in Slangkaart.Web';
    } elseif ($b[$selector] !== $props) {
        $delen = [];
        foreach (array_unique(array_merge(array_keys($props), array_keys($b[$selector]))) as $k) {
            $x = $props[$k] ?? '(geen)';
            $y = $b[$selector][$k] ?? '(geen)';
            if ($x !== $y) {
                $delen[] = "$k: portal '$x' / Slangkaart.Web '$y'";
            }
        }
        $verschil = implode('; ', $delen);
    } else {
        continue;
    }
    if (!isset($baseline[$selector])) {
        $new[$selector] = $verschil;
    }
}
foreach ($new as $selector => $verschil) {
    echo "$selector\n    $verschil\n";
}
echo $new === [] ? "RESULTAAT: geen nieuwe verschillen\n" : "RESULTAAT: " . count($new) . " nieuwe verschillen\n";
exit($new === [] ? 0 : 1);
