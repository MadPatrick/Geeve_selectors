<?php

declare(strict_types=1);

const APP_VERSION = '0.1.11';

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
?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Geeve Hydraulics | Selectors</title>
    <link rel="icon" href="favicon.ico?v=<?= h(assetVersion('favicon.ico')) ?>" type="image/x-icon">
    <link rel="stylesheet" href="assets/style.css?v=<?= h(assetVersion('assets/style.css')) ?>">
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
                <div class="header-topline">
                    <a href="update.php" id="configButton" class="header-icon-button" title="Config" aria-label="Config">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <circle cx="12" cy="12" r="3"></circle>
                            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                        </svg>
                    </a>
                    <span class="version-inline">Versie <?= h(APP_VERSION) ?></span>
                </div>
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

        <div class="tile tile-disabled" aria-disabled="true">
            <div class="tile-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect x="3" y="10" width="4" height="4" rx="1"></rect>
                    <rect x="17" y="10" width="4" height="4" rx="1"></rect>
                    <path d="M7 12h10"></path>
                </svg>
            </div>
            <h2>Slang configurator</h2>
            <p>Stel zelf een slang samen: koppeling 1, slangtype, koppeling 2, lengte en optioneel textsleeve.</p>
            <span class="tile-cta">Binnenkort beschikbaar</span>
        </div>

        <div class="tile tile-disabled" aria-disabled="true">
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
            <span class="tile-cta">Binnenkort beschikbaar</span>
        </div>
    </div>

    <p class="page-footer">Geeve Hydraulics</p>
</main>

<div id="configOverlay" class="modal-overlay" hidden>
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="configModalTitle">
        <div class="modal-header">
            <h2 id="configModalTitle">Config</h2>
            <button type="button" id="configModalClose" class="modal-close" aria-label="Sluiten">&times;</button>
        </div>
        <p>Voer de 4-cijferige code in om naar de update-pagina te gaan.</p>

        <div id="configMessage" class="update-message" hidden></div>

        <form id="configForm" class="update-form">
            <label class="update-code-field" for="configCode">
                <span>Code</span>
                <input id="configCode" type="password" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" name="code" placeholder="&bull;&bull;&bull;&bull;" autocomplete="off" required>
            </label>
            <button type="submit" id="configSubmit" class="update-submit">Doorgaan</button>
        </form>
    </div>
</div>

<script>
(function () {
    var openButton = document.getElementById('configButton');
    var overlay = document.getElementById('configOverlay');
    var closeButton = document.getElementById('configModalClose');
    var form = document.getElementById('configForm');
    var codeInput = document.getElementById('configCode');
    var submitButton = document.getElementById('configSubmit');
    var messageBox = document.getElementById('configMessage');

    function resetModal() {
        codeInput.value = '';
        messageBox.hidden = true;
        submitButton.disabled = false;
        submitButton.textContent = 'Doorgaan';
    }

    function openModal(event) {
        event.preventDefault();
        resetModal();
        overlay.hidden = false;
        codeInput.focus();
    }

    function closeModal() {
        overlay.hidden = true;
    }

    openButton.addEventListener('click', openModal);
    closeButton.addEventListener('click', closeModal);
    overlay.addEventListener('click', function (event) {
        if (event.target === overlay) {
            closeModal();
        }
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !overlay.hidden) {
            closeModal();
        }
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        submitButton.disabled = true;
        submitButton.textContent = 'Bezig...';
        messageBox.hidden = true;

        var body = new URLSearchParams();
        body.set('action', 'check-code');
        body.set('code', codeInput.value);

        fetch('update.php', { method: 'POST', body: body })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (data.ok) {
                    window.location.href = 'update.php';
                    return;
                }

                submitButton.disabled = false;
                submitButton.textContent = 'Doorgaan';
                messageBox.className = 'update-message error';
                messageBox.textContent = 'Onjuiste code.';
                messageBox.hidden = false;
            })
            .catch(function () {
                submitButton.disabled = false;
                submitButton.textContent = 'Doorgaan';
                messageBox.className = 'update-message error';
                messageBox.textContent = 'Er ging iets mis. Probeer het opnieuw.';
                messageBox.hidden = false;
            });
    });
})();
</script>
</body>
</html>
