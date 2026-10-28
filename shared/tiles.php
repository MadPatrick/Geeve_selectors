<?php

declare(strict_types=1);

/**
 * Overzicht van alle tegels (hoofdscherm, Reports, Templates en Stickers) met per tegel drie opties die in Config worden ingesteld:
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
    'slangkaarten' => ['home', 'Slangkaarten printen', 'slkprinten/index.php', true, false, false],
    // Nieuwe app Slangkaarten (map slangkaarten, in voorbereiding) - eigen id, want 'slangkaarten' is Slangkaarten Printen.
    'slangkaarten-app' => ['home', 'Slangkaarten', 'slangkaarten/index.php', true, false, true],
    'stickers' => ['home', 'Stickers', 'stickers/index.php', true, false, false],
    'stauff' => ['home', 'Stauff Selector', 'stauff/index.php', true, false, true],
    'reports' => ['home', 'Reports', 'reports/index.php', true, false, false],
    'msp' => ['reports', 'MSP report', 'reports/msp/index.php', true, false, false],
    'inkoop-groep' => ['reports', 'Inkoop per Artikelgroep', 'reports/inkoop-groep/index.php', true, false, false],
    'productiviteit' => ['reports', 'Productiviteit', 'reports/productiviteit/index.php', true, true, false],
    'artikel-marge' => ['reports', 'Artikelmarges', 'reports/artikel-marge/index.php', true, true, true],
    'slangkaart-netto' => ['reports', 'Slangkaart Nettoprijzen', 'reports/slangkaart-netto/index.php', true, false, false],
    'templates' => ['home', 'Templates', 'templates/index.php', true, false, false],
    'klantartikel' => ['templates', 'Klant Artikelnummers', 'klantartikel/index.php', true, false, false],
    'klantkorting' => ['templates', 'Kortingstructuur', 'klantkorting/index.php', true, false, true],
    'inkooporder' => ['templates', 'Export Inkooporder naar Excel', 'reports/inkooporder/index.php', true, false, false],
    'order-offerte' => ['templates', 'Export Order/Offerte naar Excel', 'reports/order-offerte/index.php', true, false, false],
    'offerte-import' => ['templates', 'Import Offerte', 'reports/offerte-import/index.php', true, false, false],
    'inkooporder-import' => ['templates', 'Import Inkooporder', 'reports/inkooporder-import/index.php', true, false, false],
    'export-info' => ['templates', 'Export info', 'reports/export-info/index.php', true, false, false],
    'artikel-import' => ['templates', 'Artikelgegevens Import', 'reports/artikel-import/index.php', true, false, false],
    'orderhistorie' => ['templates', 'Orderhistorie', 'reports/orderhistorie/index.php', true, false, true],
    'productieorders' => ['templates', 'Productie Orders', 'reports/productieorders/index.php', true, false, false],
    // Onderdelen van het Stickers-scherm (instelbaar in Config > Tegels); tegels zonder pad zijn nog plaatshouders zonder eigen pagina.
    'stickers-order' => ['stickers', 'Artikelen per Verkooporder', 'stickers/order/index.php', true, false, true],
    'stickers-productie' => ['stickers', 'Artikelen per Productieorder', 'stickers/productie/index.php', true, false, true],
    'stickers-artikel' => ['stickers', 'Artikelnummer', 'stickers/artikel/index.php', true, false, true],
    'stickers-artikel-groot' => ['stickers', "Accu's", 'stickers/accu/index.php', true, false, true],
    'stickers-kabelmerkers' => ['stickers', 'Kabelmerkers Geel', '', true, false, true],
    'stickers-slanglabels' => ['stickers', 'Slanglabels', 'stickers/slanglabels/index.php', true, false, true],
    'stickers-ptouch' => ['stickers', 'Naamstickers', 'stickers/ptouch/index.php', true, false, false],
];

/** @return array<string,array{visible:bool,locked:bool,wip:bool,title:string}> */
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
            // Eigen naam van de tegel (Config > Tegels); leeg = de standaardnaam uit TILE_REGISTRY.
            'title' => isset($own['title']) && is_string($own['title']) && trim($own['title']) !== '' ? trim($own['title']) : TILE_REGISTRY[$id][1],
        ];
    }
    return $cache;
}

/** Opgeslagen volgorde van de tegels van een groep ("home"/"reports"/"templates"/"stickers"); nieuwe tegels komen achteraan, in registry-volgorde. @return list<string> */
function tileOrder(string $group): array
{
    $ids = [];
    foreach (TILE_REGISTRY as $id => $tile) {
        if ($tile[0] === $group) {
            $ids[] = $id;
        }
    }
    $saved = [];
    $settings = secureSettingsRead();
    if (isset($settings['TILE_FLAGS'])) {
        $decoded = json_decode((string) $settings['TILE_FLAGS'], true);
        $list = is_array($decoded) ? ($decoded['_order'][$group] ?? []) : [];
        $saved = is_array($list) ? array_values(array_filter($list, static fn ($id) => is_string($id) && in_array($id, $ids, true))) : [];
    }
    return array_values(array_unique(array_merge($saved, $ids)));
}

function tileVisible(string $id): bool
{
    return tileFlagsAll()[$id]['visible'] ?? true;
}

function tileLocked(string $id): bool
{
    return tileFlagsAll()[$id]['locked'] ?? false;
}

/** Eigen tegelnaam opschonen: spaties weg, maximaal 60 tekens (werkt ook zonder PHP-extensie mbstring). */
function tileCleanTitle(string $title): string
{
    $title = trim($title);
    return preg_match('/^.{0,60}/su', $title, $m) === 1 ? $m[0] : substr($title, 0, 60);
}

/** Naam van de tegel zoals die op het overzicht staat. */
function tileTitle(string $id): string
{
    return tileFlagsAll()[$id]['title'] ?? (TILE_REGISTRY[$id][1] ?? $id);
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

/**
 * Pictogram per tegel: Phosphor "duotone" (MIT, zie shared/icons/LICENSE-phosphor.txt), als bestand in shared/icons/ en inline
 * in de pagina gezet zodat de kleur (currentColor) uit de CSS komt. De kleur hangt af van de groep waartoe de tegel hoort
 * (home, reports, templates, stickers): variabelen --tile-<groep>-* in shared/tokens.css, klassen .tile-icon.g-<groep>.
 * De drie hoofdschermtegels naar Reports, Templates en Stickers hebben de kleur van hun eigen groep.
 */
const TILE_ICONS = [
    'hoses' => 'wave-sine',
    'adapters' => 'plugs-connected',
    'slangkaarten' => 'printer',
    'slangkaarten-app' => 'list-checks',
    'stickers' => 'sticker',
    'stauff' => 'gear',
    'reports' => 'chart-bar',
    'templates' => 'files',
    'klantartikel' => 'identification-card',
    'klantkorting' => 'percent',
    'inkooporder' => 'file-xls',
    'order-offerte' => 'file-arrow-down',
    'offerte-import' => 'file-arrow-up',
    'inkooporder-import' => 'file-arrow-up',
    'export-info' => 'info',
    'artikel-import' => 'upload-simple',
    'orderhistorie' => 'clock-counter-clockwise',
    'productieorders' => 'factory',
    'msp' => 'chart-pie',
    'inkoop-groep' => 'shopping-cart',
    'productiviteit' => 'gauge',
    'artikel-marge' => 'trend-up',
    'slangkaart-netto' => 'currency-eur',
    'stickers-order' => 'receipt',
    'stickers-artikel' => 'barcode',
    'stickers-productie' => 'package',
    'stickers-artikel-groot' => 'battery-charging',
    'stickers-kabelmerkers' => 'tag',
    'stickers-slanglabels' => 'tag-simple',
    'stickers-ptouch' => 'identification-badge',
];

function tileIcon(string $id): string
{
    static $cache = [];
    if (!isset($cache[$id])) {
        $file = __DIR__ . '/icons/' . (TILE_ICONS[$id] ?? 'square') . '.svg';
        $svg = is_readable($file) ? (string) file_get_contents($file) : '';
        $svg = str_replace('<svg ', '<svg aria-hidden="true" focusable="false" ', $svg);
        $cache[$id] = $svg;
    }
    $group = in_array($id, ['reports', 'templates', 'stickers'], true) ? $id : (TILE_REGISTRY[$id][0] ?? 'home');
    return '<div class="tile-icon g-' . $group . '">' . $cache[$id] . '</div>';
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
