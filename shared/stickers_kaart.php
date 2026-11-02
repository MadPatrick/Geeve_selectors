<?php

declare(strict_types=1);

/**
 * Gedeelde bouwstenen van de Stickers-apps (stickers/*): titel met labelmaat en de stickerkaart rechts.
 * Opmaak staat in shared/stickers.css; de app laadt die in $headCss (na reports/msp/assets/style.css, vóór het eigen
 * stylesheet). Vereist shared/printen.php (printerkeuze en afdrukinstellingen) en een csrf-token.
 *
 * stickerTitelHtml()  - <div class="sk-titel"> met h2, labelmaat-badge en één regel uitleg.
 * stickerKaartHtml()  - de kaart: voorbeeld links, instellingen ernaast, knoppen eronder.
 */

require_once __DIR__ . '/printen.php';

function stickerTitelHtml(string $titel, string $maat, string $uitleg, string $meer = '', string $maatId = ''): string
{
    $h = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    return '<div class="sk-titel"><h2>' . $h($titel) . '</h2><span class="sk-maat"' . ($maatId !== '' ? ' id="' . $h($maatId) . '"' : '') . '>' . $h($maat) . '</span></div>'
        . '<p class="sk-uitleg">' . $h($uitleg) . '</p>'
        . ($meer !== '' ? '<details class="sk-meer"><summary>Uitleg</summary><p>' . $h($meer) . '</p></details>' : '');
}

/**
 * @param array{
 *   tileId: string,            tegel-id voor printerkeuze en afdrukinstellingen
 *   csrf: string,
 *   preview: string,           HTML van het voorbeeld (bijv. <div class="so-preview" id="soSheet"></div>)
 *   copiesId?: string,         id van het veld "x afdrukken" (weglaten = geen aantalveld)
 *   copiesValue?: int,
 *   extra?: string,            extra HTML in de instellingenkolom, boven de printerkeuze
 *   clearId?: string,          id van de knop Leegmaken (weglaten = geen knop)
 *   printId: string,           id van de Print-knop
 *   printDisabled?: bool,
 *   printAttrs?: string,       extra attributen op de Print-knop (al ge-escaped)
 *   printClass?: string,
 *   id?: string                id van de kaart
 * } $o
 */
function stickerKaartHtml(array $o): string
{
    $h = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $icon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9V2h12v7"></path><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>';
    $html = '<aside class="sk-kaart"' . (isset($o['id']) ? ' id="' . $h($o['id']) . '"' : '') . '>'
        . '<div class="sk-midden"><div class="sk-prev">' . $o['preview'] . '</div><div class="sk-inst">';
    if (isset($o['copiesId'])) {
        $html .= '<label class="sk-aantal"><input type="number" id="' . $h($o['copiesId']) . '" class="ka-input" min="1" max="99" value="'
            . (int) ($o['copiesValue'] ?? 1) . '"> x afdrukken</label>';
    }
    $html .= ($o['extra'] ?? '')
        . geevePrinterSelectHtml($o['tileId'], $o['csrf'])
        . geevePrintAfdrukInstellingenHtml($o['tileId'])
        . '</div></div><div class="sk-acties">';
    if (isset($o['clearId'])) {
        $html .= '<button type="button" id="' . $h($o['clearId']) . '" class="ka-button ka-button--secondary">Leegmaken</button>';
    }
    $html .= '<button type="button" id="' . $h($o['printId']) . '" class="print-btn' . (isset($o['printClass']) ? ' ' . $h($o['printClass']) : '') . '"'
        . (!empty($o['printDisabled']) ? ' disabled' : '') . ($o['printAttrs'] ?? '') . '>' . $icon . '<span>Print</span></button>'
        . '</div></aside>';
    return $html;
}
