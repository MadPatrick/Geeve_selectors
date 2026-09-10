<?php

declare(strict_types=1);

const APP_VERSION = '0.1.0';

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Geeve Hydraulics | Selectors</title>
    <link rel="stylesheet" href="assets/style.css?v=<?= h(APP_VERSION) ?>">
</head>
<body>
<main class="page-shell">
    <header class="page-header">
        <div class="brand-panel">
            <div class="brand-copy">
                <div class="brand-logo-row">
                    <img src="images/geeve.jpg" alt="Geeve Hydraulics - know how in hydraulics" class="brand-logo-img">
                    <img src="images/rubix.jpg" alt="Powered by Rubix" class="brand-rubix-img">
                </div>
            </div>
            <div class="header-content">
                <h1>Selectors</h1>
            </div>
        </div>
    </header>

    <p class="intro">Kies een selector om het juiste artikel te vinden op basis van maat, draadsoort en aansluiteigenschappen.</p>

    <div class="tile-grid">
        <a class="tile" href="hoses/index.php">
            <div class="tile-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M3 7c2 0 2 3 4 3s2-3 4-3 2 3 4 3 2-3 4-3 2 3 4 3"></path>
                    <path d="M3 17c2 0 2-3 4-3s2 3 4 3 2-3 4-3 2 3 4 3 2-3 4-3"></path>
                </svg>
            </div>
            <h2>Slangen fitting Selector</h2>
            <p>Zoek de juiste koppeling op basis van slangartikelnummer, maat en werkdruk &mdash; voor Staal, RVS en accessoires.</p>
            <span class="tile-cta">Open selector &rarr;</span>
        </a>

        <a class="tile" href="adapters/index.php">
            <div class="tile-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M14.7 6.3a1 1 0 0 0 1.4 0l1.6-1.6a1 1 0 0 1 1.4 0l1.2 1.2a1 1 0 0 1 0 1.4l-1.6 1.6a1 1 0 0 0 0 1.4l3.1 3.1a1 1 0 0 1 0 1.4l-1.4 1.4a1 1 0 0 1-1.4 0l-3.1-3.1a1 1 0 0 0-1.4 0l-1.6 1.6a1 1 0 0 1-1.4 0l-1.2-1.2a1 1 0 0 1 0-1.4l1.6-1.6a1 1 0 0 0 0-1.4L9 6.3a1 1 0 0 0-1.4 0L6 7.9a1 1 0 0 1-1.4 0L3.4 6.7a1 1 0 0 1 0-1.4L5 3.7a1 1 0 0 1 1.4 0l1.6 1.6a1 1 0 0 0 1.4 0"></path>
                    <circle cx="18.5" cy="5.5" r="0.1"></circle>
                </svg>
            </div>
            <h2>Adapters Selector</h2>
            <p>Zoek de juiste adapter op basis van draadsoort, draadmaat, hoek en connectietype (buiten/binnen/wartelend).</p>
            <span class="tile-cta">Open selector &rarr;</span>
        </a>

        <a class="tile" href="stauff/index.php">
            <div class="tile-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect x="4" y="10" width="16" height="7" rx="2"></rect>
                    <path d="M8 10V7a4 4 0 0 1 8 0v3"></path>
                    <path d="M9 17v2"></path>
                    <path d="M15 17v2"></path>
                </svg>
            </div>
            <h2>Stauff Selector</h2>
            <p>Stel de juiste beugelsamenstelling samen op basis van diameter, serie, uitvoering en materiaal.</p>
            <span class="tile-cta">Open selector &rarr;</span>
        </a>
    </div>

    <p class="page-footer">Geeve Hydraulics</p>
</main>
</body>
</html>
