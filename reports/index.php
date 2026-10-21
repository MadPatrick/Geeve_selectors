<?php

declare(strict_types=1);

// Eén gedeeld versienummer voor hoofdscherm + alle subapps (version.php op
// rootniveau) - valt terug op deze waarde als dat bestand ontbreekt.
require_once __DIR__ . '/../shared/tiles.php';
require_once __DIR__ . '/../shared/paths.php';

// Dit overzicht is zelf ook een tegel (Reports): is die beveiligd, dan eerst de code.
tileRequirePage('reports', '../index.php');

define('APP_VERSION', is_file(__DIR__ . '/../version.php') ? (string) require __DIR__ . '/../version.php' : '0.2.1');

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function assetVersion(string $relativePath): string
{
    $full = __DIR__ . '/' . $relativePath;
    $mtime = @filemtime($full);
    return $mtime !== false ? (string) $mtime : APP_VERSION;
}

// Beveiligde tegels: elke keer dat dit overzicht opent, vragen ze opnieuw om de code.
tilesRelock('reports');
$lockRoot = '../';
$csrf = csrfToken();
?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Reports | Geeve Hydraulics</title>
    <!-- Tegel-layout (.tile-grid/.tile) is dezelfde als op het hoofdscherm. -->
    <?php
    $headCss = ['assets/style.css'];   // vanaf de portal-root, in laadvolgorde
    require __DIR__ . '/../shared/head.php';
    ?>
</head>
<body>
<main class="page-shell">
    <?php
    $headerTitle = 'Reports';
    $headerTopline = ''; // het versienummer staat onderaan de pagina, achter de copyright-regel
    require __DIR__ . '/../shared/header.php';
    ?>


    <div class="tile-grid">
        <?php ob_start(); if (tileVisible('msp')): ?>
        <a class="<?= tileClass('msp') ?>" href="<?= h(geeveAppUrl('msp')) ?>"<?= tileAttrs('msp') ?>>
            <div class="tile-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <line x1="18" y1="20" x2="18" y2="10"></line>
                    <line x1="12" y1="20" x2="12" y2="4"></line>
                    <line x1="6" y1="20" x2="6" y2="14"></line>
                </svg>
            </div>
            <h2><?= h(tileTitle('msp')) ?></h2>
            <p>Parker MSP claim report voor de afgelopen maand</p>
            <?= tileLockIcon('msp') ?>
        </a>
        <?php endif; $tileHtml['msp'] = ob_get_clean(); ?>
        <?php ob_start(); if (tileVisible('inkoop-groep')): ?>
        <a class="<?= tileClass('inkoop-groep') ?>" href="<?= h(geeveAppUrl('inkoop-groep')) ?>"<?= tileAttrs('inkoop-groep') ?>>
            <div class="tile-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect x="3" y="3" width="7" height="7"></rect>
                    <rect x="14" y="3" width="7" height="7"></rect>
                    <rect x="3" y="14" width="7" height="7"></rect>
                    <path d="M14 17.5h7M17.5 14v7"></path>
                </svg>
            </div>
            <h2><?= h(tileTitle('inkoop-groep')) ?></h2>
            <p>Ingekochte aantallen per artikel van een artikelgroep in een periode</p>
            <?= tileLockIcon('inkoop-groep') ?>
        </a>
        <?php endif; $tileHtml['inkoop-groep'] = ob_get_clean(); ?>
        <?php ob_start(); if (tileVisible('productiviteit')): ?>
        <a class="<?= tileClass('productiviteit') ?>" href="<?= h(geeveAppUrl('productiviteit')) ?>"<?= tileAttrs('productiviteit') ?>>
            <div class="tile-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
            </div>
            <h2><?= h(tileTitle('productiviteit')) ?></h2>
            <p>Afgehandelde orders en orderregels per medewerker per dag</p>
            <?= tileLockIcon('productiviteit') ?>
        </a>
        <?php endif; $tileHtml['productiviteit'] = ob_get_clean(); ?>
        <?php ob_start(); if (tileVisible('artikel-marge')): ?>
        <a class="<?= tileClass('artikel-marge') ?>" href="<?= h(geeveAppUrl('artikel-marge')) ?>"<?= tileAttrs('artikel-marge') ?>>
            <div class="tile-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path>
                    <path d="M7 7h.01"></path>
                </svg>
            </div>
            <h2><?= h(tileTitle('artikel-marge')) ?></h2>
            <p>Verkoopprijs, inkoopprijs, toeslag en marge per artikel</p>
            <?= tileLockIcon('artikel-marge') ?>
        </a>
        <?php endif; $tileHtml['artikel-marge'] = ob_get_clean(); ?>
        <?php foreach (tileOrder('reports') as $tileId) { echo $tileHtml[$tileId] ?? ''; } ?>
    </div>

    <p class="page-footer">(c) 2026 PdB - Geeve Hydraulics - Versie <?= h(APP_VERSION) ?></p>
</main>

<?php require __DIR__ . '/../shared/lock_modal.php'; ?>
</body>
</html>
