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
            <?= tileIcon('klantartikel') ?>
            <h2><?= h(tileTitle('klantartikel')) ?></h2>
            <p>Zoek op prijslijstnummer de gekoppelde klanten en hun artikelen met klantartikelnummer uit Exact.</p>
            <?= tileLockIcon('klantartikel') ?>
        </a>
        <?php endif; $tileHtml['klantartikel'] = ob_get_clean(); ?>
        <?php ob_start(); if (tileVisible('klantkorting')): ?>
        <a class="<?= tileClass('klantkorting') ?>" href="<?= h(geeveAppUrl('klantkorting')) ?>"<?= tileAttrs('klantkorting') ?>>
            <?= tileIcon('klantkorting') ?>
            <h2><?= h(tileTitle('klantkorting')) ?></h2>
            <p>Bekijk en pas de kortingstructuur van klanten aan op basis van hun prijslijst.</p>
            <?= tileLockIcon('klantkorting') ?>
        </a>
        <?php endif; $tileHtml['klantkorting'] = ob_get_clean(); ?>
        <?php ob_start(); if (tileVisible('inkooporder')): ?>
        <a class="<?= tileClass('inkooporder') ?>" href="<?= h(geeveAppUrl('inkooporder')) ?>"<?= tileAttrs('inkooporder') ?>>
            <?= tileIcon('inkooporder') ?>
            <h2><?= h(tileTitle('inkooporder')) ?></h2>
            <p>Inkooporder met regels uit Exact ophalen en als Excel downloaden</p>
            <?= tileLockIcon('inkooporder') ?>
        </a>
        <?php endif; $tileHtml['inkooporder'] = ob_get_clean(); ?>
        <?php ob_start(); if (tileVisible('order-offerte')): ?>
        <a class="<?= tileClass('order-offerte') ?>" href="<?= h(geeveAppUrl('order-offerte')) ?>"<?= tileAttrs('order-offerte') ?>>
            <?= tileIcon('order-offerte') ?>
            <h2><?= h(tileTitle('order-offerte')) ?></h2>
            <p>Order- of offerteregels uit Exact ophalen en als Excel downloaden</p>
            <?= tileLockIcon('order-offerte') ?>
        </a>
        <?php endif; $tileHtml['order-offerte'] = ob_get_clean(); ?>
        <?php ob_start(); if (tileVisible('offerte-import')): ?>
        <a class="<?= tileClass('offerte-import') ?>" href="<?= h(geeveAppUrl('offerte-import')) ?>"<?= tileAttrs('offerte-import') ?>>
            <?= tileIcon('offerte-import') ?>
            <h2><?= h(tileTitle('offerte-import')) ?></h2>
            <p>Offerte via Excel importeren, controleren met Exact en als XML klaarzetten</p>
            <?= tileLockIcon('offerte-import') ?>
        </a>
        <?php endif; $tileHtml['offerte-import'] = ob_get_clean(); ?>
        <?php ob_start(); if (tileVisible('inkooporder-import')): ?>
        <a class="<?= tileClass('inkooporder-import') ?>" href="<?= h(geeveAppUrl('inkooporder-import')) ?>"<?= tileAttrs('inkooporder-import') ?>>
            <?= tileIcon('inkooporder-import') ?>
            <h2><?= h(tileTitle('inkooporder-import')) ?></h2>
            <p>Inkooporder via Excel importeren, controleren met Exact en als XML klaarzetten</p>
            <?= tileLockIcon('inkooporder-import') ?>
        </a>
        <?php endif; $tileHtml['inkooporder-import'] = ob_get_clean(); ?>
        <?php ob_start(); if (tileVisible('export-info')): ?>
        <a class="<?= tileClass('export-info') ?>" href="<?= h(geeveAppUrl('export-info')) ?>"<?= tileAttrs('export-info') ?>>
            <?= tileIcon('export-info') ?>
            <h2><?= h(tileTitle('export-info')) ?></h2>
            <p>Orderregels met collo, gewicht en afmetingen voor de exportdocumenten</p>
            <?= tileLockIcon('export-info') ?>
        </a>
        <?php endif; $tileHtml['export-info'] = ob_get_clean(); ?>
        <?php ob_start(); if (tileVisible('artikel-import')): ?>
        <a class="<?= tileClass('artikel-import') ?>" href="<?= h(geeveAppUrl('artikel-import')) ?>"<?= tileAttrs('artikel-import') ?>>
            <?= tileIcon('artikel-import') ?>
            <h2><?= h(tileTitle('artikel-import')) ?></h2>
            <p>Artikelgegevens via Excel importeren, controleren met Exact en als XML klaarzetten</p>
            <?= tileLockIcon('artikel-import') ?>
        </a>
        <?php endif; $tileHtml['artikel-import'] = ob_get_clean(); ?>
        <?php ob_start(); if (tileVisible('orderhistorie')): ?>
        <a class="<?= tileClass('orderhistorie') ?>" href="<?= h(geeveAppUrl('orderhistorie')) ?>"<?= tileAttrs('orderhistorie') ?>>
            <?= tileIcon('orderhistorie') ?>
            <h2><?= h(tileTitle('orderhistorie')) ?></h2>
            <p>Alle geleverde orderregels per klant, doorzoekbaar per kolom en te downloaden als Excel</p>
            <?= tileLockIcon('orderhistorie') ?>
        </a>
        <?php endif; $tileHtml['orderhistorie'] = ob_get_clean(); ?>
        <?php ob_start(); if (tileVisible('productieorders')): ?>
        <a class="<?= tileClass('productieorders') ?>" href="<?= h(geeveAppUrl('productieorders')) ?>"<?= tileAttrs('productieorders') ?>>
            <?= tileIcon('productieorders') ?>
            <h2><?= h(tileTitle('productieorders')) ?></h2>
            <p>Overzicht van de gereed te melden productieorders, te filteren en te downloaden als Excel</p>
            <?= tileLockIcon('productieorders') ?>
        </a>
        <?php endif; $tileHtml['productieorders'] = ob_get_clean(); ?>
        <?php ob_start(); if (tileVisible('slangkaart-netto')): ?>
        <a class="<?= tileClass('slangkaart-netto') ?>" href="<?= h(geeveAppUrl('slangkaart-netto')) ?>"<?= tileAttrs('slangkaart-netto') ?>>
            <div class="tile-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect x="4" y="3" width="16" height="18" rx="2"></rect>
                    <line x1="8" y1="8" x2="16" y2="8"></line>
                    <line x1="8" y1="12" x2="16" y2="12"></line>
                    <line x1="8" y1="16" x2="13" y2="16"></line>
                </svg>
            </div>
            <h2><?= h(tileTitle('slangkaart-netto')) ?></h2>
            <p>Slangassemblages met nettoprijs: nummer, klantnaam en nettoprijs, te filteren en te downloaden als Excel</p>
            <?= tileLockIcon('slangkaart-netto') ?>
        </a>
        <?php endif; $tileHtml['slangkaart-netto'] = ob_get_clean(); ?>
        <?php foreach (tileOrder('templates') as $tileId) { echo $tileHtml[$tileId] ?? ''; } ?>
    </div>

    <p class="page-footer">(c) 2026 PdB - Geeve Hydraulics - Versie <?= h(APP_VERSION) ?></p>
</main>

<?php require __DIR__ . '/../shared/lock_modal.php'; ?>
</body>
</html>
