<?php

declare(strict_types=1);

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
?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Stickers | Geeve Hydraulics</title>
    <link rel="icon" href="../favicon.ico?v=<?= h(assetVersion('../favicon.ico')) ?>" type="image/x-icon">
    <link rel="stylesheet" href="assets/style.css?v=<?= h(assetVersion('assets/style.css')) ?>">
</head>
<body>
<main class="page-shell">
    <header class="page-header">
        <div class="brand-panel">
            <div class="brand-copy">
                <div class="brand-logo-row">
                    <img src="../images/geeve.jpg" alt="Geeve Hydraulics - know how in hydraulics" class="brand-logo-img">
                    <img src="../images/rubix.jpg" alt="Powered by Rubix" class="brand-rubix-img">
                </div>
            </div>
            <a href="../index.php" class="header-home-button" title="Terug naar hoofdmenu" aria-label="Terug naar hoofdmenu">
                <svg viewBox="0 0 24 24" width="36" height="36" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M3 11.5 12 4l9 7.5"></path>
                    <path d="M5.5 9.5V20a1 1 0 0 0 1 1H10v-5a2 2 0 1 1 4 0v5h3.5a1 1 0 0 0 1-1V9.5"></path>
                </svg>
            </a>
            <div class="header-content">
                <div class="header-topline">
                    <span class="version-inline">Versie <?= h(APP_VERSION) ?></span>
                </div>
                <h1>Stickers</h1>
            </div>
        </div>
    </header>

    <p class="intro">Kies wat je wilt printen.</p>

    <div class="tile-grid">
        <div class="tile tile-disabled" aria-disabled="true">
            <div class="tile-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M20.59 13.41 13.42 20.58a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82Z"></path>
                    <line x1="7" y1="7" x2="7.01" y2="7"></line>
                </svg>
            </div>
            <h2>Stickers op Artikelnummer</h2>
            <p>Sticker printen op basis van een artikelnummer.</p>
            <span class="tile-cta">Binnenkort beschikbaar</span>
        </div>

        <div class="tile tile-disabled" aria-disabled="true">
            <div class="tile-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M20.59 13.41 13.42 20.58a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82Z"></path>
                    <line x1="7" y1="7" x2="7.01" y2="7"></line>
                    <path d="M14 4h6v6M20 4l-5 5"></path>
                </svg>
            </div>
            <h2>Stickers op Artikelnummer (groot)</h2>
            <p>Zelfde sticker op artikelnummer, in een groter formaat.</p>
            <span class="tile-cta">Binnenkort beschikbaar</span>
        </div>

        <div class="tile tile-disabled" aria-disabled="true">
            <div class="tile-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M6 8h12l-1.2 12.2a1 1 0 0 1-1 .8H8.2a1 1 0 0 1-1-.8Z"></path>
                    <path d="M9 8V6a3 3 0 0 1 6 0v2"></path>
                </svg>
            </div>
            <h2>Stickers op Zakjes</h2>
            <p>Sticker printen voor op een zakje.</p>
            <span class="tile-cta">Binnenkort beschikbaar</span>
        </div>
    </div>

    <p class="page-footer">Geeve Hydraulics</p>
</main>
</body>
</html>
