<?php

declare(strict_types=1);

// Eén gedeeld versienummer voor hoofdscherm + alle subapps (version.php op
// rootniveau) - valt terug op deze waarde als dat bestand ontbreekt.
require_once __DIR__ . '/shared/tiles.php';

define('APP_VERSION', is_file(__DIR__ . '/version.php') ? (string) require __DIR__ . '/version.php' : '0.2.1');

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Cache-busting op basis van de laatste wijzigingsdatum van het bestand
// zelf, zodat elke aanpassing aan style.css automatisch een nieuwe URL
// krijgt - geen handmatige versie-ophoging meer nodig.
function assetVersion(string $relativePath): string
{
    $full = __DIR__ . '/' . $relativePath;
    $mtime = @filemtime($full);
    return $mtime !== false ? (string) $mtime : APP_VERSION;
}

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
    <link rel="icon" href="favicon.ico?v=<?= h(assetVersion('favicon.ico')) ?>" type="image/x-icon">
    <link rel="stylesheet" href="shared/style.css?v=<?= h(assetVersion('shared/style.css')) ?>">
    <link rel="stylesheet" href="assets/style.css?v=<?= h(assetVersion('assets/style.css')) ?>">
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
        <a class="<?= tileClass('hoses') ?>" href="hoses/index.php"<?= tileAttrs('hoses') ?>>
            <div class="tile-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M3 7c2 0 2 3 4 3s2-3 4-3 2 3 4 3 2-3 4-3 2 3 4 3"></path>
                    <path d="M3 17c2 0 2-3 4-3s2 3 4 3 2-3 4-3 2 3 4 3 2-3 4-3"></path>
                </svg>
            </div>
            <h2><?= h(tileTitle('hoses')) ?></h2>
            <p>Zoek de juiste koppeling op basis van slangartikelnummer, maat en werkdruk &mdash; voor Staal, RVS en accessoires.</p>
            <?= tileLockIcon('hoses') ?>
        </a>
        <?php endif; $tileHtml['hoses'] = ob_get_clean(); ?>

        <?php ob_start(); if (tileVisible('adapters')): ?>
        <a class="<?= tileClass('adapters') ?>" href="adapters/index.php"<?= tileAttrs('adapters') ?>>
            <div class="tile-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M14.7 6.3a1 1 0 0 0 1.4 0l1.6-1.6a1 1 0 0 1 1.4 0l1.2 1.2a1 1 0 0 1 0 1.4l-1.6 1.6a1 1 0 0 0 0 1.4l3.1 3.1a1 1 0 0 1 0 1.4l-1.4 1.4a1 1 0 0 1-1.4 0l-3.1-3.1a1 1 0 0 0-1.4 0l-1.6 1.6a1 1 0 0 1-1.4 0l-1.2-1.2a1 1 0 0 1 0-1.4l1.6-1.6a1 1 0 0 0 0-1.4L9 6.3a1 1 0 0 0-1.4 0L6 7.9a1 1 0 0 1-1.4 0L3.4 6.7a1 1 0 0 1 0-1.4L5 3.7a1 1 0 0 1 1.4 0l1.6 1.6a1 1 0 0 0 1.4 0"></path>
                    <circle cx="18.5" cy="5.5" r="0.1"></circle>
                </svg>
            </div>
            <h2><?= h(tileTitle('adapters')) ?></h2>
            <p>Zoek de juiste adapter op basis van draadsoort, draadmaat, hoek en connectietype (buiten/binnen/wartelend).</p>
            <?= tileLockIcon('adapters') ?>
        </a>
        <?php endif; $tileHtml['adapters'] = ob_get_clean(); ?>

        <?php ob_start(); if (tileVisible('slangkaarten')): ?>
        <a class="<?= tileClass('slangkaarten') ?>" href="slkprinten/index.php"<?= tileAttrs('slangkaarten') ?>>
            <div class="tile-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M20.59 13.41 13.42 20.58a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82Z"></path>
                    <line x1="7" y1="7" x2="7.01" y2="7"></line>
                </svg>
            </div>
            <h2><?= h(tileTitle('slangkaarten')) ?></h2>
            <p>Zoek een order of klant op en print de slangkaarten (labels) van de geselecteerde slangregels.</p>
            <?= tileLockIcon('slangkaarten') ?>
        </a>
        <?php endif; $tileHtml['slangkaarten'] = ob_get_clean(); ?>

        <?php ob_start(); if (tileVisible('stickers')): ?>
        <a class="<?= tileClass('stickers') ?>" href="stickers/index.php"<?= tileAttrs('stickers') ?>>
            <div class="tile-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M4 4h12l4 4v12H4Z"></path>
                    <path d="M16 4v4h4"></path>
                </svg>
            </div>
            <h2><?= h(tileTitle('stickers')) ?></h2>
            <p>Stickers printen op artikelnummer of zakje.</p>
            <?= tileLockIcon('stickers') ?>
        </a>
        <?php endif; $tileHtml['stickers'] = ob_get_clean(); ?>
        <?php ob_start(); if (tileVisible('stauff')): ?>
        <a class="<?= tileClass('stauff') ?>" href="stauff/index.php"<?= tileAttrs('stauff') ?>>
            <div class="tile-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect x="4" y="10" width="16" height="7" rx="2"></rect>
                    <path d="M8 10V7a4 4 0 0 1 8 0v3"></path>
                    <path d="M9 17v2"></path>
                    <path d="M15 17v2"></path>
                </svg>
            </div>
            <h2><?= h(tileTitle('stauff')) ?></h2>
            <p>Stel de juiste beugelsamenstelling samen op basis van diameter, serie, uitvoering en materiaal.</p>
            <?= tileLockIcon('stauff') ?>
        </a>
        <?php endif; $tileHtml['stauff'] = ob_get_clean(); ?>
        <?php ob_start(); if (tileVisible('reports')): ?>
        <a class="<?= tileClass('reports') ?>" href="reports/index.php"<?= tileAttrs('reports') ?>>
            <div class="tile-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path>
                    <polyline points="14 3 14 9 20 9"></polyline>
                    <line x1="8" y1="17" x2="8" y2="13"></line>
                    <line x1="12" y1="17" x2="12" y2="11"></line>
                    <line x1="16" y1="17" x2="16" y2="15"></line>
                </svg>
            </div>
            <h2><?= h(tileTitle('reports')) ?></h2>
            <p>Rapportages en overzichten.</p>
            <?= tileLockIcon('reports') ?>
        </a>
        <?php endif; $tileHtml['reports'] = ob_get_clean(); ?>
        <?php ob_start(); if (tileVisible('templates')): ?>
        <a class="<?= tileClass('templates') ?>" href="templates/index.php"<?= tileAttrs('templates') ?>>
            <div class="tile-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect x="3" y="3" width="18" height="18" rx="2"></rect>
                    <line x1="3" y1="9" x2="21" y2="9"></line>
                    <line x1="9" y1="21" x2="9" y2="9"></line>
                </svg>
            </div>
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
