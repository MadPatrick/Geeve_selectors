<?php

declare(strict_types=1);

// Beveiligde tegel (Config > Tegels): zonder ontgrendelen terug naar het overzicht, waar de code wordt gevraagd.
require_once __DIR__ . '/../shared/tiles.php';
require_once __DIR__ . '/../shared/paths.php';
tileRequirePage('stickers', '../index.php');

// Eén gedeeld versienummer voor hoofdscherm + alle subapps (version.php op
// rootniveau) - valt terug op deze waarde als dat bestand ontbreekt (bv.
// deze map los buiten de portal gedeployed).
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
tilesRelock('stickers');
$lockRoot = '../';
$csrf = csrfToken();
?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Stickers | Geeve Hydraulics</title>
    <!-- Tegel-layout (.tile-grid/.tile), popup en "IN PROGRESS"-stempel komen uit de stijl van het hoofdscherm (assets/style.css), zodat alle tegels even groot zijn. -->
    <?php
    $headCss = ['assets/style.css'];   // vanaf de portal-root, in laadvolgorde
    require __DIR__ . '/../shared/head.php';
    ?>
</head>
<body>
<main class="page-shell">
    <?php
    $headerTitle = 'Stickers';
    $headerTopline = ''; // het versienummer staat onderaan de pagina, achter de copyright-regel
    require __DIR__ . '/../shared/header.php';
    ?>

    <div class="tile-grid">
        <?php ob_start(); if (tileVisible('stickers-order')): ?>
        <a class="<?= tileClass('stickers-order') ?>" href="<?= h(geeveAppUrl('stickers-order')) ?>"<?= tileAttrs('stickers-order') ?>>
            <?= tileIcon('stickers-order') ?>
            <h2><?= h(tileTitle('stickers-order')) ?></h2>
            <p>Per orderregel een artikelsticker, op volgorde van orderregel of looplocatie.</p>
            <span class="tile-cta">Openen</span>
            <?= tileLockIcon('stickers-order') ?>
        </a>
        <?php endif; $tileHtml['stickers-order'] = ob_get_clean(); ?>
        <?php ob_start(); if (tileVisible('stickers-artikel')): ?>
        <a class="<?= tileClass('stickers-artikel') ?>" href="<?= h(geeveAppUrl('stickers-artikel')) ?>"<?= tileAttrs('stickers-artikel') ?>>
            <?= tileIcon('stickers-artikel') ?>
            <h2><?= h(tileTitle('stickers-artikel')) ?></h2>
            <p>Sticker met omschrijving en barcode, zoeken op artikelnummer.</p>
            <span class="tile-cta">Openen</span>
            <?= tileLockIcon('stickers-artikel') ?>
        </a>
        <?php endif; $tileHtml['stickers-artikel'] = ob_get_clean(); ?>
        <?php ob_start(); if (tileVisible('stickers-productie')): ?>
        <a class="<?= tileClass('stickers-productie') ?>" href="<?= h(geeveAppUrl('stickers-productie')) ?>"<?= tileAttrs('stickers-productie') ?>>
            <?= tileIcon('stickers-productie') ?>
            <h2><?= h(tileTitle('stickers-productie')) ?></h2>
            <p>Per productieorder in een verkooporder een sticker met aantal, artikelnummer en barcode.</p>
            <span class="tile-cta">Openen</span>
            <?= tileLockIcon('stickers-productie') ?>
        </a>
        <?php endif; $tileHtml['stickers-productie'] = ob_get_clean(); ?>
        <?php ob_start(); if (tileVisible('stickers-artikel-groot')): ?>
        <a class="<?= tileClass('stickers-artikel-groot') ?>" href="<?= h(geeveAppUrl('stickers-artikel-groot')) ?>"<?= tileAttrs('stickers-artikel-groot') ?>>
            <?= tileIcon('stickers-artikel-groot') ?>
            <h2><?= h(tileTitle('stickers-artikel-groot')) ?></h2>
            <p>Sticker voor op een accu: bedrijfsnaam, datum, voorvuldruk en een vrij veld.</p>
            <span class="tile-cta">Openen</span>
            <?= tileLockIcon('stickers-artikel-groot') ?>
        </a>
        <?php endif; $tileHtml['stickers-artikel-groot'] = ob_get_clean(); ?>
        <?php ob_start(); if (tileVisible('stickers-kabelmerkers')): ?>
        <div class="<?= tileClass('stickers-kabelmerkers') ?> tile-disabled" aria-disabled="true">
            <?= tileIcon('stickers-kabelmerkers') ?>
            <h2><?= h(tileTitle('stickers-kabelmerkers')) ?></h2>
            <p>Gele kabelmerkers printen.</p>
            <span class="tile-cta">Binnenkort beschikbaar</span>
        </div>
        <?php endif; $tileHtml['stickers-kabelmerkers'] = ob_get_clean(); ?>
        <?php ob_start(); if (tileVisible('stickers-slanglabels')): ?>
        <a class="<?= tileClass('stickers-slanglabels') ?>" href="<?= h(geeveAppUrl('stickers-slanglabels')) ?>"<?= tileAttrs('stickers-slanglabels') ?>>
            <?= tileIcon('stickers-slanglabels') ?>
            <h2><?= h(tileTitle('stickers-slanglabels')) ?></h2>
            <p>Labels voor op een slang, per order op te halen en aan te passen.</p>
            <span class="tile-cta">Openen</span>
            <?= tileLockIcon('stickers-slanglabels') ?>
        </a>
        <?php endif; $tileHtml['stickers-slanglabels'] = ob_get_clean(); ?>
        <?php ob_start(); if (tileVisible('stickers-ptouch')): ?>
        <a class="<?= tileClass('stickers-ptouch') ?>" href="<?= h(geeveAppUrl('stickers-ptouch')) ?>"<?= tileAttrs('stickers-ptouch') ?>>
            <?= tileIcon('stickers-ptouch') ?>
            <h2><?= h(tileTitle('stickers-ptouch')) ?></h2>
            <p>Zelf getypte tekst op een sticker, de letter vult de sticker.</p>
            <span class="tile-cta">Openen</span>
            <?= tileLockIcon('stickers-ptouch') ?>
        </a>
        <?php endif; $tileHtml['stickers-ptouch'] = ob_get_clean(); ?>
        <?php foreach (tileOrder('stickers') as $tileId) { echo $tileHtml[$tileId] ?? ''; } ?>
    </div>

    <p class="page-footer">(c) 2026 PdB - Geeve Hydraulics - Versie <?= h(APP_VERSION) ?></p>
</main>

<?php require __DIR__ . '/../shared/lock_modal.php'; ?>
</body>
</html>
