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
    <title>Stauff Selector | Geeve Hydraulics</title>
    <link rel="stylesheet" href="assets/style.css?v=<?= h(APP_VERSION) ?>">
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
            <div class="section-actions">
                <div id="dataStatus" class="status-pill">Data laden...</div>
                <div id="resultCount" class="result-count">0 mogelijkheden</div>
            </div>
        </div>

        <div class="filter-grid">
            <label class="field compact-field diameter-field">
                <span>Diameter</span>
                <div class="autocomplete">
                    <input id="diameterInput" type="text" inputmode="decimal" autocomplete="off"
                           placeholder="Typ diameter, bijvoorbeeld 19">
                    <div id="diameterSuggestions" class="suggestions" hidden></div>
                </div>
                <small>Alleen diameters die in de beugellijst voorkomen worden voorgesteld.</small>
            </label>

            <div class="field compact-field serie-field">
                <span>Serie</span>
                <div class="segmented selection-toggle" id="serieButtons" role="radiogroup" aria-label="Serie">
                    <button type="button" class="segment" data-serie="Licht" disabled>Licht</button>
                    <button type="button" class="segment" data-serie="Zwaar" disabled>Zwaar</button>
                </div>
                <select id="serieSelect" class="logic-select" aria-hidden="true" tabindex="-1" disabled><option value="">Kies eerst diameter</option></select>
            </div>

            <div class="field compact-field uitvoering-field">
                <span>Uitvoering</span>
                <div class="segmented selection-toggle" id="uitvoeringButtons" role="radiogroup" aria-label="Uitvoering">
                    <button type="button" class="segment" data-uitvoering="Enkel" disabled>Enkel</button>
                    <button type="button" class="segment" data-uitvoering="Dubbel" disabled>Dubbel</button>
                </div>
                <select id="uitvoeringSelect" class="logic-select" aria-hidden="true" tabindex="-1" disabled><option value="">Kies eerst serie</option></select>
            </div>

            <label class="field compact-field clamp-material-field">
                <span>Beugelmateriaal</span>
                <select id="clampMaterialSelect" disabled><option value="">Kies eerst uitvoering</option></select>
            </label>

            <label class="field compact-field clamp-field">
                <span>Beugel</span>
                <select id="clampSelect" disabled><option value="">Kies een beugel</option></select>
            </label>
        </div>
        <small>Lasplaat en Dekplaat worden standaard voorgeselecteerd zodra een passende optie beschikbaar is; de overige onderdelen blijven optioneel.</small>

        <div id="clampFacts" class="clamp-facts is-empty">
            <div><span>Bouwgroep</span><strong id="factGroup">&mdash;</strong></div>
            <div><span>Diameter</span><strong id="factDiameter">&mdash;</strong></div>
            <div><span>Serie</span><strong id="factSerie">&mdash;</strong></div>
            <div><span>Uitvoering</span><strong id="factExecution">&mdash;</strong></div>
        </div>
    </section>

    <section class="panel material-panel">
        <div class="section-heading">
            <div>
                <span class="step">Materiaal</span>
                <h2>Bevestigingsdelen</h2>
            </div>
        </div>

        <div class="material-controls">
            <div class="segmented" role="radiogroup" aria-label="Materiaal bevestigingsdelen">
                <button type="button" class="segment active" data-metal="Staal">Staal</button>
                <button type="button" class="segment" data-metal="RVS">RVS</button>
            </div>
            <label class="field material-code-field">
                <span>Materiaalcode (locatie 6)</span>
                <select id="materialCodeSelect" disabled>
                    <option value="">Kies eerst een beugel</option>
                </select>
            </label>
        </div>
        <small>Staal gebruikt W1/W2/W3; RVS gebruikt W4/W5/W55. Alleen codes die bij de geselecteerde bouwgroep voorkomen worden getoond.</small>
    </section>

    <section class="panel assembly-panel">
        <div class="section-heading">
            <div>
                <span class="step">Samenstelling</span>
                <h2>Locaties 1 t/m 6</h2>
            </div>
        </div>

        <div class="assembly-list">
            <article class="location-card" data-location="1">
                <div class="location-number">1</div>
                <div class="location-content">
                    <div class="location-title"><strong>Lasplaat / Glijmoer</strong><span>Standaard Lasplaat &middot; Onderzijde</span></div>
                    <select id="location1Select" disabled><option value="">Kies eerst een beugel</option></select>
                </div>
            </article>

            <article class="location-card clamp-location" data-location="2">
                <div class="location-number">2</div>
                <div class="location-content">
                    <div class="location-title"><strong>Beugel</strong><span>Beugelcode (bouwgroep + maat + materiaal)</span></div>
                    <div id="location2Value" class="fixed-value">&mdash;</div>
                </div>
            </article>

            <article class="location-card" data-location="3">
                <div class="location-number">3</div>
                <div class="location-content">
                    <div class="location-title"><strong>Borgplaat</strong><span>Optioneel &middot; opties uit bouwgroep</span></div>
                    <select id="location3Select" disabled><option value="">Kies eerst een beugel</option></select>
                </div>
            </article>

            <article class="location-card" data-location="4">
                <div class="location-number">4</div>
                <div class="location-content">
                    <div class="location-title"><strong>Dekplaat</strong><span>Standaard geselecteerd &middot; opties uit bouwgroep</span></div>
                    <select id="location4Select" disabled><option value="">Kies eerst een beugel</option></select>
                </div>
            </article>

            <article class="location-card" data-location="5">
                <div class="location-number">5</div>
                <div class="location-content">
                    <div class="location-title"><strong>Bout</strong><span>Optioneel &middot; stapel-, inbus- of zeskantbout</span></div>
                    <select id="location5Select" disabled><option value="">Kies eerst een beugel</option></select>
                </div>
            </article>

            <article class="location-card material-location" data-location="6">
                <div class="location-number">6</div>
                <div class="location-content">
                    <div class="location-title"><strong>Materiaal</strong><span>Optioneel &middot; W-code bevestigingsdelen</span></div>
                    <div id="location6Value" class="fixed-value">&mdash;</div>
                </div>
            </article>
        </div>
    </section>

    <section class="code-panel">
        <div>
            <span class="code-label">SAMENSTELLINGSCODE</span>
            <div id="assemblyCode" class="assembly-code">Selecteer eerst een beugel</div>
            <div id="codeHint" class="code-hint">Lasplaat en Dekplaat worden standaard gekozen. Borgplaat en Bout blijven optioneel. Locatie 2 gebruikt de beugelcode, bijvoorbeeld <strong>215 PP</strong> of <strong>3015 PP</strong>.</div>
        </div>
        <button id="copyButton" type="button" class="copy-button" disabled>Kopieer code</button>
    </section>

    <section id="warningBox" class="warning-box" hidden></section>
</main>
<script src="assets/selector.js?v=<?= h(APP_VERSION) ?>"></script>
</body>
</html>
