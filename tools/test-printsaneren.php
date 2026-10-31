<?php

declare(strict_types=1);

// Test voor shared/printsaneren.php: php tools/test-printsaneren.php   (exitcode 1 bij een fout)
require_once __DIR__ . '/../shared/printsaneren.php';

$fouten = 0;
function controleer(string $naam, bool $waar): void
{
    global $fouten;
    echo ($waar ? 'ok     ' : 'FOUT   ') . $naam . "\n";
    $fouten += $waar ? 0 : 1;
}
function bevat(string $html, string $naald): bool
{
    return stripos(geeveSaneerPrintHtml($html), $naald) !== false;
}

// Aanvallen: niets hiervan mag in de uitvoer terechtkomen.
$aanvallen = [
    'iframe file' => '<div><iframe src="file:///var/www/html/.settings.key"></iframe></div>',
    'iframe zonder sluittag' => '<div>a</div><iframe src="/etc/passwd">rest',
    'object' => '<object data="/etc/passwd"></object>',
    'embed' => '<embed src="/etc/passwd">',
    'meta refresh' => '<meta http-equiv="refresh" content="0;url=/var/www/html/.settings.key">',
    'link stylesheet' => '<link rel="stylesheet" href="http://10.0.0.1/x.css">',
    'base' => '<base href="http://evil/">',
    'script' => '<script>alert(1)</script>',
    'script hoofdletters' => '<ScRiPt >alert(1)</sCrIpT>',
    'img onerror' => '<img src=x onerror=alert(1)>',
    'div onclick' => '<div onclick="alert(1)">x</div>',
    'div onmouseover slash' => '<div/onmouseover=alert(1)>x</div>',
    'svg onload' => '<svg onload=alert(1)></svg>',
    'svg use' => '<svg><use href="file:///etc/passwd#x"/></svg>',
    'svg image' => '<svg><image href="/etc/passwd"/></svg>',
    'foreignObject' => '<svg><foreignObject><iframe src="/etc/passwd"></iframe></foreignObject></svg>',
    'style tag' => '<style>@import "/etc/passwd";</style>',
    'style url' => '<div style="background:url(/etc/passwd)">x</div>',
    'style url met entity' => '<div style="background:&#117;rl(/etc/passwd)">x</div>',
    'style css-escape' => '<div style="background:\\75rl(/etc/passwd)">x</div>',
    'style expression' => '<div style="width:expression(alert(1))">x</div>',
    'a href javascript' => '<a href="javascript:alert(1)">x</a>',
    'form' => '<form action="http://evil/"><input name=a></form>',
    'commentaar' => '<!-- <script>alert(1)</script> -->',
    'cdata/doctype' => '<!DOCTYPE html><?xml version="1.0"?>',
    'tag met > in waarde' => '<div title="a>b" onclick="alert(1)">x</div>',
    'dubbel attribuut' => '<div class="a" onclick="x" class="b">x</div>',
];
foreach ($aanvallen as $naam => $html) {
    $schoon = geeveSaneerPrintHtml($html);
    $gevaarlijk = preg_match('/<(iframe|object|embed|meta|link|base|script|style|img|use|image|foreignobject|a|form|input)\b|<[^>]*\bon[a-z]+\s*=|url\s*\(|javascript:|file:|\/etc\/passwd|<!--|<\?|<!/i', $schoon) === 1;
    controleer('geblokkeerd: ' . $naam . '  =>  ' . $schoon, !$gevaarlijk);
}

// Echte afdrukblokken moeten ongewijzigd (inhoudelijk) blijven.
$sticker = '<div class="so-sheet"><div class="sticker sticker--70x25" data-i="3"><div class="f nr">A&amp;B &lt;1&gt;</div>'
    . '<div class="bc"><svg data-code="12345" style="width: 26.5mm;" viewBox="0 0 70 100" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg" version="1.1">'
    . '<g style="fill:#000000;" transform="translate(10, 10)"><rect x="0" y="0" width="1" height="100" style="fill:#000000;"></rect>'
    . '<text style="font:20px monospace" text-anchor="middle" x="5" y="9">12345</text></g></svg></div></div></div>'
    . '<table class="t"><thead><tr><th colspan="2">K</th></tr></thead><tbody><tr><td class="num">1</td><td>x<br>y</td></tr></tbody></table>';
$schoon = geeveSaneerPrintHtml($sticker);
foreach (['class="so-sheet"', 'data-i="3"', 'A&amp;B &lt;1&gt;', 'data-code="12345"', 'style="width: 26.5mm;"', 'viewBox="0 0 70 100"',
    'preserveAspectRatio="none"', 'xmlns="http://www.w3.org/2000/svg"', 'transform="translate(10, 10)"', '<rect x="0"', '<text style="font:20px monospace"',
    'text-anchor="middle"', 'colspan="2"', '<br>', '</tbody>'] as $deel) {
    controleer('blijft staan: ' . $deel, strpos($schoon, $deel) !== false);
}
controleer('xmlns van een ander adres valt weg', !bevat('<svg xmlns="http://evil/x"></svg>', 'evil'));
controleer('onbekende tag valt weg, tekst blijft', geeveSaneerPrintHtml('<blink>hallo</blink>') === 'hallo');
controleer('tekst met < blijft veilig', geeveSaneerPrintHtml('a < b > c') === 'a &lt; b &gt; c');

echo $fouten === 0 ? "\nAlle controles geslaagd.\n" : "\n{$fouten} controle(s) mislukt.\n";
exit($fouten === 0 ? 0 : 1);
