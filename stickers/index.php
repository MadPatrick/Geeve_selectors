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
    <link rel="icon" href="../favicon.ico?v=<?= h(assetVersion('../favicon.ico')) ?>" type="image/x-icon">
    <link rel="stylesheet" href="../shared/style.css?v=<?= h(assetVersion('../shared/style.css')) ?>">
    <!-- Popup voor beveiligde tegels en de "IN PROGRESS"-stempel komen uit de stijl van het hoofdscherm. -->
    <link rel="stylesheet" href="../assets/style.css?v=<?= h(assetVersion('../assets/style.css')) ?>">
    <link rel="stylesheet" href="assets/style.css?v=<?= h(assetVersion('assets/style.css')) ?>">
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
            <div class="tile-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M3 5v14M7 5v14M10 5v14M14 5v14M17 5v14M21 5v14"></path>
                </svg>
            </div>
            <h2><?= h(tileTitle('stickers-order')) ?></h2>
            <p>Per orderregel een artikelsticker, op volgorde van orderregel of looplocatie.</p>
            <span class="tile-cta">Openen</span>
            <?= tileLockIcon('stickers-order') ?>
        </a>
        <?php endif; $tileHtml['stickers-order'] = ob_get_clean(); ?>
        <?php ob_start(); if (tileVisible('stickers-artikel')): ?>
        <a class="<?= tileClass('stickers-artikel') ?>" href="<?= h(geeveAppUrl('stickers-artikel')) ?>"<?= tileAttrs('stickers-artikel') ?>>
            <div class="tile-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M20.59 13.41 13.42 20.58a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82Z"></path>
                    <line x1="7" y1="7" x2="7.01" y2="7"></line>
                </svg>
            </div>
            <h2><?= h(tileTitle('stickers-artikel')) ?></h2>
            <p>Sticker met omschrijving en barcode, zoeken op artikelnummer.</p>
            <span class="tile-cta">Openen</span>
            <?= tileLockIcon('stickers-artikel') ?>
        </a>
        <?php endif; $tileHtml['stickers-artikel'] = ob_get_clean(); ?>
        <?php ob_start(); if (tileVisible('stickers-productie')): ?>
        <a class="<?= tileClass('stickers-productie') ?>" href="<?= h(geeveAppUrl('stickers-productie')) ?>"<?= tileAttrs('stickers-productie') ?>>
            <div class="tile-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="12" cy="12" r="3"></circle>
                    <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1Z"></path>
                </svg>
            </div>
            <h2><?= h(tileTitle('stickers-productie')) ?></h2>
            <p>Per productieorder in een verkooporder een sticker met aantal, artikelnummer en barcode.</p>
            <span class="tile-cta">Openen</span>
            <?= tileLockIcon('stickers-productie') ?>
        </a>
        <?php endif; $tileHtml['stickers-productie'] = ob_get_clean(); ?>
        <?php ob_start(); if (tileVisible('stickers-artikel-groot')): ?>
        <a class="<?= tileClass('stickers-artikel-groot') ?>" href="<?= h(geeveAppUrl('stickers-artikel-groot')) ?>"<?= tileAttrs('stickers-artikel-groot') ?>>
            <div class="tile-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M20.59 13.41 13.42 20.58a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82Z"></path>
                    <line x1="7" y1="7" x2="7.01" y2="7"></line>
                    <path d="M14 4h6v6M20 4l-5 5"></path>
                </svg>
            </div>
            <h2><?= h(tileTitle('stickers-artikel-groot')) ?></h2>
            <p>Sticker voor op een accu: bedrijfsnaam, datum, voorvuldruk en een vrij veld.</p>
            <span class="tile-cta">Openen</span>
            <?= tileLockIcon('stickers-artikel-groot') ?>
        </a>
        <?php endif; $tileHtml['stickers-artikel-groot'] = ob_get_clean(); ?>
        <?php ob_start(); if (tileVisible('stickers-kabelmerkers')): ?>
        <div class="<?= tileClass('stickers-kabelmerkers') ?> tile-disabled" aria-disabled="true">
            <div class="tile-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M2 12h5M17 12h5"></path>
                    <rect x="7" y="8" width="10" height="8" rx="1.5"></rect>
                    <path d="M10 8v8M14 8v8"></path>
                </svg>
            </div>
            <h2><?= h(tileTitle('stickers-kabelmerkers')) ?></h2>
            <p>Gele kabelmerkers printen.</p>
            <span class="tile-cta">Binnenkort beschikbaar</span>
        </div>
        <?php endif; $tileHtml['stickers-kabelmerkers'] = ob_get_clean(); ?>
        <?php ob_start(); if (tileVisible('stickers-slanglabels')): ?>
        <a class="<?= tileClass('stickers-slanglabels') ?>" href="<?= h(geeveAppUrl('stickers-slanglabels')) ?>"<?= tileAttrs('stickers-slanglabels') ?>>
            <div class="tile-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M3 17c4 0 4-10 9-10s5 10 9 10"></path>
                    <rect x="9" y="3" width="6" height="4" rx="1"></rect>
                </svg>
            </div>
            <h2><?= h(tileTitle('stickers-slanglabels')) ?></h2>
            <p>Labels voor op een slang, per order op te halen en aan te passen.</p>
            <span class="tile-cta">Openen</span>
            <?= tileLockIcon('stickers-slanglabels') ?>
        </a>
        <?php endif; $tileHtml['stickers-slanglabels'] = ob_get_clean(); ?>
        <?php foreach (tileOrder('stickers') as $tileId) { echo $tileHtml[$tileId] ?? ''; } ?>
    </div>

    <p class="page-footer">(c) 2026 PdB - Geeve Hydraulics - Versie <?= h(APP_VERSION) ?></p>
</main>

<?php require __DIR__ . '/../shared/lock_modal.php'; ?>
</body>
</html>
