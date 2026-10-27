<?php

declare(strict_types=1);

// Eén gedeeld versienummer voor hoofdscherm + alle subapps (version.php op
// rootniveau) - valt terug op deze waarde als dat bestand ontbreekt.
require_once __DIR__ . '/../shared/tiles.php';
require_once __DIR__ . '/../shared/paths.php';

// Dit overzicht is zelf ook een tegel (Templates): is die beveiligd, dan eerst de code.
tileRequirePage('templates', '../index.php');

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
tilesRelock('templates');
$lockRoot = '../';
$csrf = csrfToken();
?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Templates | Geeve Hydraulics</title>
    <!-- Tegel-layout (.tile-grid/.tile) is dezelfde als op het hoofdscherm. -->
    <?php
    $headCss = ['assets/style.css'];   // vanaf de portal-root, in laadvolgorde
    require __DIR__ . '/../shared/head.php';
    ?>
</head>
<body>
<main class="page-shell">
    <?php
    $headerTitle = 'Templates';
    $headerTopline = ''; // het versienummer staat onderaan de pagina, achter de copyright-regel
    require __DIR__ . '/../shared/header.php';
    ?>


    <div class="tile-grid">
        <?php ob_start(); if (tileVisible('klantartikel')): ?>
        <a class="<?= tileClass('klantartikel') ?>" href="<?= h(geeveAppUrl('klantartikel')) ?>"<?= tileAttrs('klantartikel') ?>>
            <div class="tile-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8z"></path>
                    <circle cx="7.5" cy="7.5" r="1.5"></circle>
                </svg>
            </div>
            <h2><?= h(tileTitle('klantartikel')) ?></h2>
            <p>Zoek op prijslijstnummer de gekoppelde klanten en hun artikelen met klantartikelnummer uit Exact.</p>
            <?= tileLockIcon('klantartikel') ?>
        </a>
        <?php endif; $tileHtml['klantartikel'] = ob_get_clean(); ?>
        <?php ob_start(); if (tileVisible('klantkorting')): ?>
        <a class="<?= tileClass('klantkorting') ?>" href="<?= h(geeveAppUrl('klantkorting')) ?>"<?= tileAttrs('klantkorting') ?>>
            <div class="tile-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <line x1="19" y1="5" x2="5" y2="19"></line>
                    <circle cx="6.5" cy="6.5" r="2.5"></circle>
                    <circle cx="17.5" cy="17.5" r="2.5"></circle>
                </svg>
            </div>
            <h2><?= h(tileTitle('klantkorting')) ?></h2>
            <p>Bekijk en pas de kortingstructuur van klanten aan op basis van hun prijslijst.</p>
            <?= tileLockIcon('klantkorting') ?>
        </a>
        <?php endif; $tileHtml['klantkorting'] = ob_get_clean(); ?>
        <?php ob_start(); if (tileVisible('inkooporder')): ?>
        <a class="<?= tileClass('inkooporder') ?>" href="<?= h(geeveAppUrl('inkooporder')) ?>"<?= tileAttrs('inkooporder') ?>>
            <div class="tile-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="9" cy="21" r="1"></circle>
                    <circle cx="20" cy="21" r="1"></circle>
                    <path d="M1 1h4l2.7 13.4a2 2 0 0 0 2 1.6h9.7a2 2 0 0 0 2-1.6L23 6H6"></path>
                </svg>
            </div>
            <h2><?= h(tileTitle('inkooporder')) ?></h2>
            <p>Inkooporder met regels uit Exact ophalen en als Excel downloaden</p>
            <?= tileLockIcon('inkooporder') ?>
        </a>
        <?php endif; $tileHtml['inkooporder'] = ob_get_clean(); ?>
        <?php ob_start(); if (tileVisible('order-offerte')): ?>
        <a class="<?= tileClass('order-offerte') ?>" href="<?= h(geeveAppUrl('order-offerte')) ?>"<?= tileAttrs('order-offerte') ?>>
            <div class="tile-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="8" y1="13" x2="16" y2="13"></line>
                    <line x1="8" y1="17" x2="16" y2="17"></line>
                </svg>
            </div>
            <h2><?= h(tileTitle('order-offerte')) ?></h2>
            <p>Order- of offerteregels uit Exact ophalen en als Excel downloaden</p>
            <?= tileLockIcon('order-offerte') ?>
        </a>
        <?php endif; $tileHtml['order-offerte'] = ob_get_clean(); ?>
        <?php ob_start(); if (tileVisible('offerte-import')): ?>
        <a class="<?= tileClass('offerte-import') ?>" href="<?= h(geeveAppUrl('offerte-import')) ?>"<?= tileAttrs('offerte-import') ?>>
            <div class="tile-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="12" y1="18" x2="12" y2="12"></line>
                    <polyline points="9 15 12 12 15 15"></polyline>
                </svg>
            </div>
            <h2><?= h(tileTitle('offerte-import')) ?></h2>
            <p>Offerte via Excel importeren, controleren met Exact en als XML klaarzetten</p>
            <?= tileLockIcon('offerte-import') ?>
        </a>
        <?php endif; $tileHtml['offerte-import'] = ob_get_clean(); ?>
        <?php ob_start(); if (tileVisible('inkooporder-import')): ?>
        <a class="<?= tileClass('inkooporder-import') ?>" href="<?= h(geeveAppUrl('inkooporder-import')) ?>"<?= tileAttrs('inkooporder-import') ?>>
            <div class="tile-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="12" y1="18" x2="12" y2="12"></line>
                    <polyline points="9 15 12 12 15 15"></polyline>
                </svg>
            </div>
            <h2><?= h(tileTitle('inkooporder-import')) ?></h2>
            <p>Inkooporder via Excel importeren, controleren met Exact en als XML klaarzetten</p>
            <?= tileLockIcon('inkooporder-import') ?>
        </a>
        <?php endif; $tileHtml['inkooporder-import'] = ob_get_clean(); ?>
        <?php ob_start(); if (tileVisible('export-info')): ?>
        <a class="<?= tileClass('export-info') ?>" href="<?= h(geeveAppUrl('export-info')) ?>"<?= tileAttrs('export-info') ?>>
            <div class="tile-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                    <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                    <line x1="12" y1="22.08" x2="12" y2="12"></line>
                </svg>
            </div>
            <h2><?= h(tileTitle('export-info')) ?></h2>
            <p>Orderregels met collo, gewicht en afmetingen voor de exportdocumenten</p>
            <?= tileLockIcon('export-info') ?>
        </a>
        <?php endif; $tileHtml['export-info'] = ob_get_clean(); ?>
        <?php ob_start(); if (tileVisible('artikel-import')): ?>
        <a class="<?= tileClass('artikel-import') ?>" href="<?= h(geeveAppUrl('artikel-import')) ?>"<?= tileAttrs('artikel-import') ?>>
            <div class="tile-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path>
                    <line x1="7" y1="7" x2="7.01" y2="7"></line>
                </svg>
            </div>
            <h2><?= h(tileTitle('artikel-import')) ?></h2>
            <p>Artikelgegevens via Excel importeren, controleren met Exact en als XML klaarzetten</p>
            <?= tileLockIcon('artikel-import') ?>
        </a>
        <?php endif; $tileHtml['artikel-import'] = ob_get_clean(); ?>
        <?php ob_start(); if (tileVisible('orderhistorie')): ?>
        <a class="<?= tileClass('orderhistorie') ?>" href="<?= h(geeveAppUrl('orderhistorie')) ?>"<?= tileAttrs('orderhistorie') ?>>
            <div class="tile-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="12" cy="12" r="9"></circle>
                    <polyline points="12 7 12 12 15.5 14"></polyline>
                </svg>
            </div>
            <h2><?= h(tileTitle('orderhistorie')) ?></h2>
            <p>Alle geleverde orderregels per klant, doorzoekbaar per kolom en te downloaden als Excel</p>
            <?= tileLockIcon('orderhistorie') ?>
        </a>
        <?php endif; $tileHtml['orderhistorie'] = ob_get_clean(); ?>
        <?php ob_start(); if (tileVisible('productieorders')): ?>
        <a class="<?= tileClass('productieorders') ?>" href="<?= h(geeveAppUrl('productieorders')) ?>"<?= tileAttrs('productieorders') ?>>
            <div class="tile-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect x="4" y="3" width="16" height="18" rx="2"></rect>
                    <polyline points="8 10 10.5 12.5 16 7"></polyline>
                    <line x1="8" y1="17" x2="16" y2="17"></line>
                </svg>
            </div>
            <h2><?= h(tileTitle('productieorders')) ?></h2>
            <p>Overzicht van de gereed te melden productieorders, te filteren en te downloaden als Excel</p>
            <?= tileLockIcon('productieorders') ?>
        </a>
        <?php endif; $tileHtml['productieorders'] = ob_get_clean(); ?>
        <?php foreach (tileOrder('templates') as $tileId) { echo $tileHtml[$tileId] ?? ''; } ?>
    </div>

    <p class="page-footer">(c) 2026 PdB - Geeve Hydraulics - Versie <?= h(APP_VERSION) ?></p>
</main>

<?php require __DIR__ . '/../shared/lock_modal.php'; ?>
</body>
</html>
