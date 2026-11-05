<?php

declare(strict_types=1);

require_once __DIR__ . '/shared/helpers.php';

// Eén gedeeld versienummer voor hoofdscherm + alle subapps (version.php op
// rootniveau) - valt terug op deze waarde als dat bestand ontbreekt.
require_once __DIR__ . '/shared/tiles.php';
require_once __DIR__ . '/shared/paths.php';

define('APP_VERSION', is_file(__DIR__ . '/version.php') ? (string) require __DIR__ . '/version.php' : '0.2.1');

// Beveiligde tegels: elke keer dat het hoofdscherm opent, vragen ze opnieuw om de code.
tilesRelock('home');
$csrf = csrfToken();
$lockRoot = '';
?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Geeve Hydraulics | Intranet</title>
    <?php
    $headCss = ['assets/style.css'];   // vanaf de portal-root, in laadvolgorde
    require __DIR__ . '/shared/head.php';
    ?>
</head>
<body>
<main class="page-shell">
    <?php
    $headerTitle = 'Intranet';
    $headerImagesPath = 'images/';
    $headerShowHome = false;
    ob_start();
    ?>
    <a href="update.php" id="configButton" class="header-icon-button" title="Config" aria-label="Config">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="12" cy="12" r="3"></circle>
            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
        </svg>
    </a>
    <?php
    $headerTopline = ob_get_clean();
    require __DIR__ . '/shared/header.php';
    ?>


    <div class="tile-grid">
        <?php ob_start(); if (tileVisible('hoses')): ?>
        <a class="<?= tileClass('hoses') ?>" href="<?= h(geeveAppUrl('hoses')) ?>"<?= tileAttrs('hoses') ?>>
            <?= tileIcon('hoses') ?>
            <h2><?= h(tileTitle('hoses')) ?></h2>
            <p>Zoek de juiste koppeling op basis van slangartikelnummer, maat en werkdruk &mdash; voor Staal, RVS en accessoires.</p>
            <?= tileLockIcon('hoses') ?>
        </a>
        <?php endif; $tileHtml['hoses'] = ob_get_clean(); ?>

        <?php ob_start(); if (tileVisible('adapters')): ?>
        <a class="<?= tileClass('adapters') ?>" href="<?= h(geeveAppUrl('adapters')) ?>"<?= tileAttrs('adapters') ?>>
            <?= tileIcon('adapters') ?>
            <h2><?= h(tileTitle('adapters')) ?></h2>
            <p>Zoek de juiste adapter op basis van draadsoort, draadmaat, hoek en connectietype (buiten/binnen/wartelend).</p>
            <?= tileLockIcon('adapters') ?>
        </a>
        <?php endif; $tileHtml['adapters'] = ob_get_clean(); ?>

        <?php ob_start(); if (tileVisible('slangkaarten')): ?>
        <a class="<?= tileClass('slangkaarten') ?>" href="<?= h(geeveAppUrl('slangkaarten')) ?>"<?= tileAttrs('slangkaarten') ?>>
            <?= tileIcon('slangkaarten') ?>
            <h2><?= h(tileTitle('slangkaarten')) ?></h2>
            <p>Zoek een order of klant op en print de slangkaarten (labels) van de geselecteerde slangregels.</p>
            <?= tileLockIcon('slangkaarten') ?>
        </a>
        <?php endif; $tileHtml['slangkaarten'] = ob_get_clean(); ?>

        <?php ob_start(); if (tileVisible('slangkaarten-app')): ?>
        <a class="<?= tileClass('slangkaarten-app') ?>" href="<?= h(geeveAppUrl('slangkaarten-app')) ?>"<?= tileAttrs('slangkaarten-app') ?>>
            <?= tileIcon('slangkaarten-app') ?>
            <h2><?= h(tileTitle('slangkaarten-app')) ?></h2>
            <p>In voorbereiding.</p>
            <?= tileLockIcon('slangkaarten-app') ?>
        </a>
        <?php endif; $tileHtml['slangkaarten-app'] = ob_get_clean(); ?>

        <?php ob_start(); if (tileVisible('stickers')): ?>
        <a class="<?= tileClass('stickers') ?>" href="<?= h(geeveAppUrl('stickers')) ?>"<?= tileAttrs('stickers') ?>>
            <?= tileIcon('stickers') ?>
            <h2><?= h(tileTitle('stickers')) ?></h2>
            <p>Stickers printen op artikelnummer of zakje.</p>
            <?= tileLockIcon('stickers') ?>
        </a>
        <?php endif; $tileHtml['stickers'] = ob_get_clean(); ?>
        <?php ob_start(); if (tileVisible('stauff')): ?>
        <a class="<?= tileClass('stauff') ?>" href="<?= h(geeveAppUrl('stauff')) ?>"<?= tileAttrs('stauff') ?>>
            <?= tileIcon('stauff') ?>
            <h2><?= h(tileTitle('stauff')) ?></h2>
            <p>Stel de juiste beugelsamenstelling samen op basis van diameter, serie, uitvoering en materiaal.</p>
            <?= tileLockIcon('stauff') ?>
        </a>
        <?php endif; $tileHtml['stauff'] = ob_get_clean(); ?>
        <?php ob_start(); if (tileVisible('reports')): ?>
        <a class="<?= tileClass('reports') ?>" href="<?= h(geeveAppUrl('reports')) ?>"<?= tileAttrs('reports') ?>>
            <?= tileIcon('reports') ?>
            <h2><?= h(tileTitle('reports')) ?></h2>
            <p>Rapportages en overzichten.</p>
            <?= tileLockIcon('reports') ?>
        </a>
        <?php endif; $tileHtml['reports'] = ob_get_clean(); ?>
        <?php ob_start(); if (tileVisible('templates')): ?>
        <a class="<?= tileClass('templates') ?>" href="<?= h(geeveAppUrl('templates')) ?>"<?= tileAttrs('templates') ?>>
            <?= tileIcon('templates') ?>
            <h2><?= h(tileTitle('templates')) ?></h2>
            <p>Excel-exports, imports en klantgegevens.</p>
            <?= tileLockIcon('templates') ?>
        </a>
        <?php endif; $tileHtml['templates'] = ob_get_clean(); ?>
        <?php foreach (tileOrder('home') as $tileId) { echo $tileHtml[$tileId] ?? ''; } ?>
    </div>

    <p class="page-footer">(c) 2026 PdB - Geeve Hydraulics - Versie <?= h(APP_VERSION) ?></p>
</main>

<?php require __DIR__ . '/shared/lock_modal.php'; ?>
</body>
</html>
