<?php

declare(strict_types=1);

// Eén gedeeld versienummer voor hoofdscherm + alle subapps (version.php op
// rootniveau) - valt terug op deze waarde als dat bestand ontbreekt.
require_once __DIR__ . '/../shared/access.php';

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

// Beveiligde tegel: alleen als dit onderdeel nog niet is ontgrendeld vraagt een klik om de code (popup).
accessLock('productiviteit'); // elke keer dat het Reports-scherm opent, vraagt de tegel opnieuw om de code
$productiviteitLocked = true;
$csrf = csrfToken();
?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Reports | Geeve Hydraulics</title>
    <link rel="icon" href="../favicon.ico?v=<?= h(assetVersion('../favicon.ico')) ?>" type="image/x-icon">
    <link rel="stylesheet" href="../shared/style.css?v=<?= h(assetVersion('../shared/style.css')) ?>">
    <!-- Tegel-layout (.tile-grid/.tile) is dezelfde als op het hoofdscherm. -->
    <link rel="stylesheet" href="../assets/style.css?v=<?= h(assetVersion('../assets/style.css')) ?>">
</head>
<body>
<main class="page-shell">
    <?php
    $headerTitle = 'Reports';
    $headerTopline = '<span class="version-inline">Versie ' . h(APP_VERSION) . '</span>';
    require __DIR__ . '/../shared/header.php';
    ?>


    <div class="tile-grid">
        <a class="tile" href="msp/index.php">
            <div class="tile-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <line x1="18" y1="20" x2="18" y2="10"></line>
                    <line x1="12" y1="20" x2="12" y2="4"></line>
                    <line x1="6" y1="20" x2="6" y2="14"></line>
                </svg>
            </div>
            <h2>MSP report</h2>
            <p>Parker MSP claim report voor de afgelopen maand</p>
        </a>
        <a class="tile" href="inkooporder/index.php">
            <div class="tile-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="9" cy="21" r="1"></circle>
                    <circle cx="20" cy="21" r="1"></circle>
                    <path d="M1 1h4l2.7 13.4a2 2 0 0 0 2 1.6h9.7a2 2 0 0 0 2-1.6L23 6H6"></path>
                </svg>
            </div>
            <h2>Inkooporder naar Excel</h2>
            <p>Inkooporder met regels uit Exact ophalen en als Excel downloaden</p>
        </a>
        <a class="tile" href="order-offerte/index.php">
            <div class="tile-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="8" y1="13" x2="16" y2="13"></line>
                    <line x1="8" y1="17" x2="16" y2="17"></line>
                </svg>
            </div>
            <h2>Order of Offerte naar Excel</h2>
            <p>Order- of offerteregels uit Exact ophalen en als Excel downloaden</p>
        </a>
        <a class="tile" href="inkoop-groep/index.php">
            <div class="tile-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect x="3" y="3" width="7" height="7"></rect>
                    <rect x="14" y="3" width="7" height="7"></rect>
                    <rect x="3" y="14" width="7" height="7"></rect>
                    <path d="M14 17.5h7M17.5 14v7"></path>
                </svg>
            </div>
            <h2>Inkoop per groep</h2>
            <p>Ingekochte aantallen per artikel van een artikelgroep in een periode</p>
        </a>
        <a class="tile" href="productiviteit/index.php"<?= $productiviteitLocked ? ' data-lock="productiviteit"' : '' ?>>
            <div class="tile-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
            </div>
            <h2>Productiviteit</h2>
            <p>Afgehandelde orders en orderregels per medewerker per dag</p>
        
            <span class="tile-lock" title="Beveiligd met een code" aria-label="Beveiligd met een code">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                </svg>
            </span>
        </a>
        <a class="tile" href="offerte-import/index.php">
            <div class="tile-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="12" y1="18" x2="12" y2="12"></line>
                    <polyline points="9 15 12 12 15 15"></polyline>
                </svg>
            </div>
            <h2>Offerte Import</h2>
            <p>Offerte via Excel importeren, controleren met Exact en als XML klaarzetten</p>
        </a>
        <a class="tile" href="inkooporder-import/index.php">
            <div class="tile-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="12" y1="18" x2="12" y2="12"></line>
                    <polyline points="9 15 12 12 15 15"></polyline>
                </svg>
            </div>
            <h2>Inkooporder Import</h2>
            <p>Inkooporder via Excel importeren, controleren met Exact en als XML klaarzetten</p>
        </a>
    </div>

    <p class="page-footer">Geeve Hydraulics</p>
</main>

<div class="lock-modal" id="lockModal" hidden role="dialog" aria-modal="true" aria-labelledby="lockTitle">
    <form class="lock-dialog" id="lockForm" data-csrf="<?= h($csrf) ?>" autocomplete="off">
        <h2 id="lockTitle">Toegangscode</h2>
        <p>Dit rapport is beveiligd. Vul de code in om het te openen.</p>
        <input type="password" id="lockCode" class="lock-input" inputmode="numeric" autocomplete="off" aria-label="Toegangscode" required>
        <div class="lock-error" id="lockError" hidden></div>
        <div class="lock-actions">
            <button type="button" id="lockCancel" class="lock-button lock-button--secondary">Annuleren</button>
            <button type="submit" class="lock-button">Openen</button>
        </div>
    </form>
</div>
<script src="assets/lock.js?v=<?= h(assetVersion('assets/lock.js')) ?>"></script>
</body>
</html>
