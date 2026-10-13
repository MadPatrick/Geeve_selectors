<?php

declare(strict_types=1);

/**
 * Overzicht van alle tegels (hoofdscherm en Reports) met per tegel drie opties die in Config worden ingesteld:
 *  - zichtbaar: uit = de tegel staat niet op het overzicht (de pagina zelf blijft via de directe link bereikbaar);
 *  - beveiligd: een klik op de tegel vraagt om de toegangscode (zie shared/access.php); de pagina en de bijbehorende API's
 *    zijn zonder ontgrendelen ook niet te openen;
 *  - in progress: de tegel krijgt de stempel "IN PROGRESS" (alleen visueel).
 * De instellingen staan versleuteld in de Config-instellingen (sleutel TILE_FLAGS, JSON); ontbreekt een tegel daarin, dan gelden
 * de standaardwaarden uit TILE_REGISTRY.
 */
require_once __DIR__ . '/access.php';

/** id => [groep, titel, pad vanaf de portal-root, zichtbaar, beveiligd, in progress] (de laatste drie zijn de standaardwaarden). */
const TILE_REGISTRY = [
    'hoses' => ['home', 'Slangen fitting Selector', 'hoses/index.php', true, false, false],
    'adapters' => ['home', 'Adapters Selector', 'adapters/index.php', true, false, false],
    'slangkaarten' => ['home', 'Slangkaarten Printen', 'slangkaarten/index.php', true, false, false],
    'stauff' => ['home', 'Stauff Selector', 'stauff/index.php', true, false, true],
    'reports' => ['home', 'Reports', 'reports/index.php', true, false, false],
    'klantartikel' => ['home', 'Klant Artikelnummers', 'klantartikel/index.php', true, false, false],
    'klantkorting' => ['home', 'Kortingstructuur', 'klantkorting/index.php', true, false, true],
    'msp' => ['reports', 'MSP report', 'reports/msp/index.php', true, false, false],
    'inkooporder' => ['reports', 'Inkooporder naar Excel', 'reports/inkooporder/index.php', true, false, false],
    'order-offerte' => ['reports', 'Order of Offerte naar Excel', 'reports/order-offerte/index.php', true, false, false],
    'inkoop-groep' => ['reports', 'Inkoop per Artikelgroep', 'reports/inkoop-groep/index.php', true, false, false],
    'productiviteit' => ['reports', 'Productiviteit', 'reports/productiviteit/index.php', true, true, false],
    'offerte-import' => ['reports', 'Offerte Import', 'reports/offerte-import/index.php', true, false, false],
    'inkooporder-import' => ['reports', 'Inkooporder Import', 'reports/inkooporder-import/index.php', true, false, false],
    'export-info' => ['reports', 'Export info', 'reports/export-info/index.php', true, false, false],
];

/** @return array<string,array{visible:bool,locked:bool,wip:bool}> */
function tileFlagsAll(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $stored = [];
    $settings = secureSettingsRead();
    if (isset($settings['TILE_FLAGS'])) {
        $decoded = json_decode((string) $settings['TILE_FLAGS'], true);
        $stored = is_array($decoded) ? $decoded : [];
    }
    $cache = [];
    foreach (TILE_REGISTRY as $id => [, , , $visible, $locked, $wip]) {
        $own = is_array($stored[$id] ?? null) ? $stored[$id] : [];
        $cache[$id] = [
            'visible' => array_key_exists('visible', $own) ? (bool) $own['visible'] : $visible,
            'locked' => array_key_exists('locked', $own) ? (bool) $own['locked'] : $locked,
            'wip' => array_key_exists('wip', $own) ? (bool) $own['wip'] : $wip,
        ];
    }
    return $cache;
}

function tileVisible(string $id): bool
{
    return tileFlagsAll()[$id]['visible'] ?? true;
}

function tileLocked(string $id): bool
{
    return tileFlagsAll()[$id]['locked'] ?? false;
}

function tileWip(string $id): bool
{
    return tileFlagsAll()[$id]['wip'] ?? false;
}

/** Klassen van de tegel (<a class="...">). */
function tileClass(string $id): string
{
    return 'tile' . (tileWip($id) ? ' tile--wip' : '');
}

/** Extra attributen van de tegel: data-lock bij een beveiligde tegel (de popup vraagt dan om de code). */
function tileAttrs(string $id): string
{
    return tileLocked($id) ? ' data-lock="' . htmlspecialchars($id, ENT_QUOTES) . '"' : '';
}

/** Het slotje rechtsonder in een beveiligde tegel. */
function tileLockIcon(string $id): string
{
    if (!tileLocked($id)) {
        return '';
    }
    return '<span class="tile-lock" title="Beveiligd met een code" aria-label="Beveiligd met een code">'
        . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . '<rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg></span>';
}

/** Vergrendelt alle beveiligde tegels van een groep opnieuw (bij elke keer dat dat overzicht opent). */
function tilesRelock(string $group): void
{
    foreach (TILE_REGISTRY as $id => $tile) {
        if ($tile[0] === $group && tileLocked($id)) {
            accessLock($id);
        }
    }
}

/** True als een van de tegels niet beveiligd is of ontgrendeld is. */
function tileAccessible(string ...$ids): bool
{
    foreach ($ids as $id) {
        if (!tileLocked($id) || accessUnlocked($id)) {
            return true;
        }
    }
    return false;
}

/**
 * Voor de pagina van een tegel (bovenaan, vóór enige uitvoer): is de tegel beveiligd en nog niet ontgrendeld, dan terug naar het
 * overzicht, waar de popup meteen om de code vraagt.
 */
function tileRequirePage(string $id, string $backHref): void
{
    if (!tileAccessible($id)) {
        header('Location: ' . $backHref . (str_contains($backHref, '?') ? '&' : '?') . 'unlock=' . rawurlencode($id));
        exit;
    }
}

/** Voor API-scripts: stopt met een 403 als geen van de tegels waar deze API bij hoort is ontgrendeld. */
function tileApiGuard(string ...$ids): void
{
    if (!tileAccessible(...$ids)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Vergrendeld: open de pagina en vul de toegangscode in.']);
        exit;
    }
}
