<?php

declare(strict_types=1);

/**
 * Gedeelde <link>-regels voor in de <head> - zie shared/README.md ("shared/head.php"). Zet in vaste volgorde:
 * favicon, shared/style.css (die zelf shared/tokens.css importeert), dan de extra stylesheets in $headCss.
 * Adressen zijn absoluut vanaf de portal-root (geeveUrl), dus het werkt ook vanaf een kort adres (/msp/ e.d.),
 * met cache-busting op de wijzigingsdatum van elk bestand.
 *
 * Vóór het includen zetten:
 *   $headCss (list<string>, optioneel) - stylesheets vanaf de portal-root, in laadvolgorde,
 *                                        bijv. ['shared/slangkaart.css', 'slkprinten/assets/style.css'].
 * Gebruikt h() van de app zelf (zoals shared/header.php).
 */

require_once __DIR__ . '/paths.php';

if (!function_exists('geeveAssetHref')) {
    /** Absoluut adres + ?v=<wijzigingsdatum> van een bestand vanaf de portal-root. */
    function geeveAssetHref(string $path): string
    {
        $mtime = @filemtime(geevePortalDir() . '/' . ltrim($path, '/'));
        return geeveUrl($path) . '?v=' . ($mtime !== false ? (string) $mtime : '0');
    }
}

$headCss = $headCss ?? [];
?>
    <link rel="icon" href="<?= h(geeveAssetHref('favicon.ico')) ?>" type="image/x-icon">
    <link rel="stylesheet" href="<?= h(geeveAssetHref('shared/style.css')) ?>">
<?php foreach ($headCss as $cssPath): ?>
    <link rel="stylesheet" href="<?= h(geeveAssetHref($cssPath)) ?>">
<?php endforeach; ?>
