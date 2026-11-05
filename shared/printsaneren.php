<?php

declare(strict_types=1);

/**
 * Saneert HTML dat een browser naar shared/print-api.php stuurt (de inhoud van een afdrukblok) voordat Chromium het rendert.
 *
 * Waarom: Chromium rendert dit als lokaal bestand (file://) op de server. Ongecontroleerde HTML kan daar een iframe/meta-refresh
 * naar een serverbestand (bijv. .settings.key), een afbeelding of stylesheet van een ander adres, of een gebeurtenisattribuut
 * (onerror=...) in zetten. Een zwarte lijst met verboden woorden laat altijd iets door (bijv. een pad zonder "file:"), daarom
 * is dit een witte lijst: alleen de tags en attributen die de afdrukblokken echt gebruiken (div/span/tabel en de SVG van de
 * barcode) blijven, de rest wordt weggelaten en de HTML wordt opnieuw opgebouwd met genormaliseerde attributen.
 *
 * Toevoegen van een tag of attribuut dat een afdrukblok nodig heeft: GEEVE_PRINT_TAGS / GEEVE_PRINT_ATTRIBUTEN.
 */

/** Toegestane elementen (kleine letters). */
const GEEVE_PRINT_TAGS = [
    'div', 'span', 'p', 'br', 'b', 'strong', 'i', 'em', 'u', 'small', 'sub', 'sup',
    'table', 'thead', 'tbody', 'tfoot', 'tr', 'td', 'th',
    'svg', 'g', 'rect', 'path', 'circle', 'line', 'text', 'tspan',
];

/** Toegestane attributen (kleine letters); daarnaast data-* en aria-*. */
const GEEVE_PRINT_ATTRIBUTEN = [
    'class', 'id', 'style', 'title', 'colspan', 'rowspan',
    'width', 'height', 'x', 'y', 'cx', 'cy', 'r', 'rx', 'ry', 'x1', 'y1', 'x2', 'y2', 'd', 'transform', 'viewbox',
    'preserveaspectratio', 'fill', 'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin', 'text-anchor',
    'shape-rendering', 'version', 'xmlns',
];

/** Elementen waarvan ook de inhoud verdwijnt (anders zou hun tekst, bijv. CSS of script, zichtbaar worden). */
const GEEVE_PRINT_TAGS_MET_INHOUD_WEG = [
    'script', 'style', 'noscript', 'template', 'textarea', 'title', 'iframe', 'frame', 'frameset', 'object', 'embed',
    'applet', 'xmp', 'plaintext', 'noembed', 'noframes', 'select', 'option', 'math', 'foreignobject',
];

/** Is deze waarde (na het ontcijferen van HTML-tekens) veilig om in een attribuut te laten staan? */
function geevePrintWaardeVeilig(string $waarde): bool
{
    $kandidaten = [$waarde, html_entity_decode($waarde, ENT_QUOTES | ENT_HTML5, 'UTF-8')];
    foreach ($kandidaten as $tekst) {
        if (preg_match('/[\\\\\x00-\x08\x0B\x0C\x0E-\x1F]|url\s*\(|@import|expression\s*\(|javascript:|vbscript:|data:text|file:|https?:|\/\*|<|-moz-binding|behavior\s*:/i', $tekst) === 1) {
            return false;
        }
    }
    return true;
}

function geeveSaneerPrintHtml(string $html): string
{
    // Eén tag: <naam attributen> of </naam>. Waarden mogen > bevatten tussen aanhalingstekens.
    $tagPatroon = '/<(\/?)([a-zA-Z][a-zA-Z0-9]*)((?:\s+[^\s"\'<>\/=]+(?:\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s"\'=<>`]+))?)*)\s*(\/?)>/A';
    $attribuutPatroon = '/([^\s"\'<>\/=]+)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'=<>`]+)))?/';

    $uit = '';
    $lengte = strlen($html);
    $pos = 0;
    while ($pos < $lengte) {
        $volgende = strpos($html, '<', $pos);
        $tekst = substr($html, $pos, ($volgende === false ? $lengte : $volgende) - $pos);
        $uit .= str_replace(["\0", '>'], ['', '&gt;'], $tekst);
        if ($volgende === false) {
            break;
        }
        $pos = $volgende;

        if (preg_match($tagPatroon, $html, $m, 0, $pos) !== 1) {
            // Geen geldige tag (commentaar, <?, <!DOCTYPE, rommel): als gewone tekst tonen, nooit als markup.
            $uit .= '&lt;';
            $pos++;
            continue;
        }
        $pos += strlen($m[0]);
        $sluit = $m[1] === '/';
        $naam = strtolower($m[2]);

        if (in_array($naam, GEEVE_PRINT_TAGS_MET_INHOUD_WEG, true)) {
            if (!$sluit && $m[4] !== '/') {
                // Tot en met de bijbehorende sluittag weglaten (ontbreekt die: de rest van het bericht).
                if (preg_match('/<\/' . preg_quote($naam, '/') . '\s*>/i', $html, $einde, PREG_OFFSET_CAPTURE, $pos) === 1) {
                    $pos = $einde[0][1] + strlen($einde[0][0]);
                } else {
                    $pos = $lengte;
                }
            }
            continue;
        }
        if (!in_array($naam, GEEVE_PRINT_TAGS, true)) {
            continue; // onbekend element: tag weg, tekst erin blijft
        }
        if ($sluit) {
            $uit .= '</' . $naam . '>';
            continue;
        }

        $attributen = '';
        if (trim($m[3]) !== '' && preg_match_all($attribuutPatroon, $m[3], $gevonden, PREG_SET_ORDER) > 0) {
            $gezien = [];
            foreach ($gevonden as $a) {
                $attribuut = strtolower($a[1]);
                $waarde = ($a[2] ?? '') !== '' ? $a[2] : (($a[3] ?? '') !== '' ? $a[3] : ($a[4] ?? ''));
                $toegestaan = in_array($attribuut, GEEVE_PRINT_ATTRIBUTEN, true)
                    || preg_match('/^(data|aria)-[a-z0-9-]+$/', $attribuut) === 1;
                if (!$toegestaan || isset($gezien[$attribuut]) || strpos($waarde, "\0") !== false) {
                    continue;
                }
                if ($attribuut === 'xmlns') {
                    if ($waarde !== 'http://www.w3.org/2000/svg') {
                        continue;
                    }
                } elseif (!in_array($attribuut, ['class', 'id', 'title'], true)
                    && preg_match('/^(data|aria)-/', $attribuut) !== 1
                    && !geevePrintWaardeVeilig($waarde)) {
                    continue; // style, d, transform, ...: geen url(), @import, javascript:, adressen of CSS-escapes
                }
                $gezien[$attribuut] = true;
                // Hoofdlettergevoelige SVG-naam herstellen; alles tussen dubbele aanhalingstekens.
                $schrijfwijze = $attribuut === 'viewbox' ? 'viewBox' : ($attribuut === 'preserveaspectratio' ? 'preserveAspectRatio' : $attribuut);
                $attributen .= ' ' . $schrijfwijze . '="' . str_replace('"', '&quot;', $waarde) . '"';
            }
        }
        $uit .= '<' . $naam . $attributen . ($m[4] === '/' ? '/' : '') . '>';
    }
    return $uit;
}
