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

// Cache-busting op basis van de laatste wijzigingsdatum van het bestand
// zelf, zodat elke aanpassing aan style.css/selector.js automatisch een
// nieuwe URL krijgt - geen handmatige versie-ophoging meer nodig.
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
    <title>Stauff Selector | Geeve Hydraulics</title>
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
                <h1>Stauff Selector</h1>
            </div>
        </div>
    </header>

    <section class="panel search-panel">
        <div class="section-heading">
            <div>
                <span class="step">Selectie</span>
                <h2>Beugel bepalen</h2>
            </div>
        </div>

        <div class="filter-grid">
            <div class="diameter-box">
                <div class="exact-live-header">
                    <span>Zoeken op beugel</span>
                </div>
                <div class="diameter-box-body">
                    <label class="field compact-field diameter-field">
                        <div class="autocomplete">
                            <input id="diameterInput" type="text" inputmode="decimal" autocomplete="off"
                                   placeholder="Typ diameter, bijvoorbeeld 19">
                        </div>
                    </label>
                </div>
            </div>

            <div id="exactLiveResults" class="exact-live-results" hidden>
                <div class="exact-live-header">
                    <span id="exactLiveStatus" class="exact-live-status"></span>
                </div>
                <ul id="exactLiveList" class="exact-live-list"></ul>
            </div>

            <div class="diameter-box material-code-field">
                <div class="exact-live-header">
                    <span>Materiaalcode (locatie 6)</span>
                </div>
                <div class="diameter-box-body">
                    <select id="materialCodeSelect" disabled>
                        <option value="">Kies eerst een beugel</option>
                    </select>
                </div>
            </div>
        </div>

        <small>Lasplaat en Dekplaat worden standaard voorgeselecteerd zodra een passende optie beschikbaar is; de overige onderdelen blijven optioneel.</small>
    </section>

    <section class="panel assembly-panel">
        <div class="section-heading">
            <div>
                <span class="step">Samenstelling</span>
                <h2>Samenstelling</h2>
            </div>
        </div>

        <div class="assembly-list">
            <article class="location-card" data-location="1">
                <button type="button" class="location-number" data-location="1" aria-label="Zoekfilter voor Lasplaat / Glijmoer instellen">1</button>
                <div class="location-image is-empty">
                    <img id="shapeImg1" alt="" hidden>
                    <span class="location-image-placeholder">&mdash;</span>
                </div>
                <div class="location-content">
                    <div class="location-title"><strong>Lasplaat / Glijmoer</strong><span>Standaard Lasplaat &middot; Onderzijde</span></div>
                    <input type="number" id="locationAantal1" class="location-aantal" min="1" value="1" aria-label="Aantal Lasplaat / Glijmoer">
                    <select id="location1Select" disabled><option value="">Kies eerst een beugel</option></select>
                    <span id="locationPrice1" class="location-price"></span>
                    <button type="button" class="location-add-button" data-location="1" aria-label="Extra artikel toevoegen">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                    </button>
                </div>
            </article>

            <article class="location-card" data-location="2">
                <div class="location-number">2</div>
                <div class="location-image is-empty">
                    <img id="shapeImg2" alt="" hidden>
                    <span class="location-image-placeholder">&mdash;</span>
                </div>
                <div class="location-content">
                    <div class="location-title"><strong>Beugel</strong><span>Beugelcode (bouwgroep + maat + materiaal)</span></div>
                    <input type="number" id="locationAantal2" class="location-aantal" min="1" value="1" aria-label="Aantal Beugel">
                    <div id="location2Value" class="fixed-value">&mdash;</div>
                    <span id="locationPrice2" class="location-price"></span>
                </div>
            </article>

            <article class="location-card" data-location="3">
                <button type="button" class="location-number" data-location="3" aria-label="Zoekfilter voor Borgplaat instellen">3</button>
                <div class="location-image is-empty">
                    <img id="shapeImg3" alt="" hidden>
                    <span class="location-image-placeholder">&mdash;</span>
                </div>
                <div class="location-content">
                    <div class="location-title"><strong>Borgplaat</strong><span>Optioneel &middot; opties uit bouwgroep</span></div>
                    <input type="number" id="locationAantal3" class="location-aantal" min="1" value="1" aria-label="Aantal Borgplaat">
                    <select id="location3Select" disabled><option value="">Kies eerst een beugel</option></select>
                    <span id="locationPrice3" class="location-price"></span>
                    <button type="button" class="location-add-button" data-location="3" aria-label="Extra artikel toevoegen">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                    </button>
                </div>
            </article>

            <article class="location-card" data-location="4">
                <button type="button" class="location-number" data-location="4" aria-label="Zoekfilter voor Dekplaat instellen">4</button>
                <div class="location-image is-empty">
                    <img id="shapeImg4" alt="" hidden>
                    <span class="location-image-placeholder">&mdash;</span>
                </div>
                <div class="location-content">
                    <div class="location-title"><strong>Dekplaat</strong><span>Standaard geselecteerd &middot; opties uit bouwgroep</span></div>
                    <input type="number" id="locationAantal4" class="location-aantal" min="1" value="1" aria-label="Aantal Dekplaat">
                    <select id="location4Select" disabled><option value="">Kies eerst een beugel</option></select>
                    <span id="locationPrice4" class="location-price"></span>
                    <button type="button" class="location-add-button" data-location="4" aria-label="Extra artikel toevoegen">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                    </button>
                </div>
            </article>

            <article class="location-card" data-location="5">
                <button type="button" class="location-number" data-location="5" aria-label="Zoekfilter voor Bout instellen">5</button>
                <div class="location-image is-empty">
                    <img id="shapeImg5" alt="" hidden>
                    <span class="location-image-placeholder">&mdash;</span>
                </div>
                <div class="location-content">
                    <div class="location-title"><strong>Bout</strong><span>Optioneel &middot; stapel-, inbus- of zeskantbout</span></div>
                    <input type="number" id="locationAantal5" class="location-aantal" min="1" value="2" aria-label="Aantal Bout">
                    <select id="location5Select" disabled><option value="">Kies eerst een beugel</option></select>
                    <span id="locationPrice5" class="location-price"></span>
                    <button type="button" class="location-add-button" data-location="5" aria-label="Extra artikel toevoegen">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                    </button>
                </div>
            </article>

            <article class="location-card" data-location="6">
                <div class="location-number">6</div>
                <div class="location-image is-empty">
                    <span class="location-image-placeholder">&mdash;</span>
                </div>
                <div class="location-content">
                    <div class="location-title"><strong>Materiaal</strong><span>Optioneel &middot; W-code bevestigingsdelen</span></div>
                    <input type="number" id="locationAantal6" class="location-aantal" min="1" value="1" aria-label="Aantal Materiaal">
                    <div id="location6Value" class="fixed-value">&mdash;</div>
                    <span id="locationPrice6" class="location-price"></span>
                </div>
            </article>
        </div>
    </section>

    <section class="code-panel">
        <div>
            <span class="code-label">SAMENSTELLINGSCODE</span>
            <div id="assemblyCode" class="assembly-code">Selecteer eerst een beugel</div>
            <div id="assemblyTotalPrice" class="assembly-total-price"></div>
            <div id="codeHint" class="code-hint">Lasplaat en Dekplaat worden standaard gekozen. Borgplaat en Bout blijven optioneel. Locatie 2 gebruikt de beugelcode, bijvoorbeeld <strong>215 PP</strong> of <strong>3015 PP</strong>.</div>
        </div>
        <button id="copyButton" type="button" class="copy-button" disabled>Kopieer code</button>
    </section>

    <section id="warningBox" class="warning-box" hidden></section>
</main>

<div id="locationFilterOverlay" class="modal-overlay" hidden>
    <div class="modal location-filter-modal" role="dialog" aria-modal="true" aria-labelledby="locationFilterTitle">
        <div class="modal-header">
            <h2 id="locationFilterTitle">Zoekfilter</h2>
        </div>
        <label class="field" for="locationFilterInput">
            <span>Artikelnummers die hiermee beginnen (; gescheiden)</span>
            <input id="locationFilterInput" type="text" placeholder="bijv. SP;SPAL;SPV">
        </label>
        <small>Zoekt live in Exact (artikelgroep 67) naar artikelen die met 1 van deze voorvoegsels
            beginnen, gecombineerd met de gekozen materiaalcode (locatie 6). Leeg = gewone
            CSV-lijst blijft gebruikt.</small>
        <div class="modal-actions">
            <button type="button" id="locationFilterClear" class="link-button">Wissen</button>
            <button type="button" id="locationFilterCancel" class="segment">Annuleren</button>
            <button type="button" id="locationFilterApply" class="submit-button">Toepassen</button>
        </div>
    </div>
</div>

<script src="assets/selector.js?v=<?= h(assetVersion('assets/selector.js')) ?>"></script>
</body>
</html>
