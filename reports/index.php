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
            <?= tileIcon('msp') ?>
            <h2><?= h(tileTitle('msp')) ?></h2>
            <p>Parker MSP claim report voor de afgelopen maand</p>
            <?= tileLockIcon('msp') ?>
        </a>
        <?php endif; $tileHtml['msp'] = ob_get_clean(); ?>
        <?php ob_start(); if (tileVisible('inkoop-groep')): ?>
        <a class="<?= tileClass('inkoop-groep') ?>" href="<?= h(geeveAppUrl('inkoop-groep')) ?>"<?= tileAttrs('inkoop-groep') ?>>
            <?= tileIcon('inkoop-groep') ?>
            <h2><?= h(tileTitle('inkoop-groep')) ?></h2>
            <p>Ingekochte aantallen per artikel van een artikelgroep in een periode</p>
            <?= tileLockIcon('inkoop-groep') ?>
        </a>
        <?php endif; $tileHtml['inkoop-groep'] = ob_get_clean(); ?>
        <?php ob_start(); if (tileVisible('productiviteit')): ?>
        <a class="<?= tileClass('productiviteit') ?>" href="<?= h(geeveAppUrl('productiviteit')) ?>"<?= tileAttrs('productiviteit') ?>>
            <?= tileIcon('productiviteit') ?>
            <h2><?= h(tileTitle('productiviteit')) ?></h2>
            <p>Afgehandelde orders en orderregels per medewerker per dag</p>
            <?= tileLockIcon('productiviteit') ?>
        </a>
        <?php endif; $tileHtml['productiviteit'] = ob_get_clean(); ?>
        <?php ob_start(); if (tileVisible('artikel-marge')): ?>
        <a class="<?= tileClass('artikel-marge') ?>" href="<?= h(geeveAppUrl('artikel-marge')) ?>"<?= tileAttrs('artikel-marge') ?>>
            <?= tileIcon('artikel-marge') ?>
            <h2><?= h(tileTitle('artikel-marge')) ?></h2>
            <p>Verkoopprijs, inkoopprijs, toeslag en marge per artikel</p>
            <?= tileLockIcon('artikel-marge') ?>
        </a>
        <?php endif; $tileHtml['artikel-marge'] = ob_get_clean(); ?>
        <?php ob_start(); if (tileVisible('slangkaart-netto')): ?>
        <a class="<?= tileClass('slangkaart-netto') ?>" href="<?= h(geeveAppUrl('slangkaart-netto')) ?>"<?= tileAttrs('slangkaart-netto') ?>>
            <?= tileIcon('slangkaart-netto') ?>
            <h2><?= h(tileTitle('slangkaart-netto')) ?></h2>
            <p>Slangassemblages met nettoprijs: nummer, klantnaam en nettoprijs, te filteren en te downloaden als Excel</p>
            <?= tileLockIcon('slangkaart-netto') ?>
        </a>
        <?php endif; $tileHtml['slangkaart-netto'] = ob_get_clean(); ?>
        <?php ob_start(); if (tileVisible('inkoopprijzen-gr14-gr55')): ?>
        <a class="<?= tileClass('inkoopprijzen-gr14-gr55') ?>" href="<?= h(geeveAppUrl('inkoopprijzen-gr14-gr55')) ?>"<?= tileAttrs('inkoopprijzen-gr14-gr55') ?>>
            <?= tileIcon('inkoopprijzen-gr14-gr55') ?>
            <h2><?= h(tileTitle('inkoopprijzen-gr14-gr55')) ?></h2>
            <p>Inkoop- en verkoopprijzen van artikelgroep 14 naast groep 55 (Zoekcode + VZ/VZX), te downloaden als Excel</p>
            <?= tileLockIcon('inkoopprijzen-gr14-gr55') ?>
        </a>
        <?php endif; $tileHtml['inkoopprijzen-gr14-gr55'] = ob_get_clean(); ?>
        <?php foreach (tileOrder('reports') as $tileId) { echo $tileHtml[$tileId] ?? ''; } ?>
    </div>

    <p class="page-footer">(c) 2026 PdB - Geeve Hydraulics - Versie <?= h(APP_VERSION) ?></p>
</main>

<?php require __DIR__ . '/../shared/lock_modal.php'; ?>
</body>
</html>
