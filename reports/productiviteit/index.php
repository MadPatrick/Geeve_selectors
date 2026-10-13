<?php

declare(strict_types=1);

require_once __DIR__ . '/../../shared/csrf.php';
require_once __DIR__ . '/../../shared/access.php';

// Eén gedeeld versienummer voor hoofdscherm + alle subapps (version.php op
// rootniveau) - valt terug op deze waarde als dat bestand ontbreekt.
define('APP_VERSION', is_file(__DIR__ . '/../../version.php') ? (string) require __DIR__ . '/../../version.php' : '0.2.1');

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

$csrf = csrfToken();

// Niet laten bewaren door de browser (terugknop): de inhoud is alleen voor wie net de code heeft ingevuld.
header('Cache-Control: no-store');

// Beveiligd met een toegangscode (zie shared/access.php); een juiste code ontgrendelt dit onderdeel voor de sessie.
$locked = !accessUnlocked('productiviteit');
$codeError = null;
if ($locked && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['action'] ?? '') === 'unlock') {
    if (!csrfValid($_POST['csrf'] ?? null)) {
        $codeError = 'Sessie verlopen. Probeer het opnieuw.';
    } else {
        $outcome = accessTryUnlock('productiviteit', (string) ($_POST['code'] ?? ''));
        if ($outcome === 'ok') {
            header('Location: index.php');
            exit;
        }
        $codeError = $outcome === 'wacht' ? 'Te veel foute pogingen. Wacht een minuut en probeer het opnieuw.' : 'Onjuiste code.';
    }
}

// Standaard de huidige maand tot vandaag.
$defaultTo = date('Y-m-d');
$defaultFrom = date('Y-m-d', strtotime('first day of this month'));
?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Productiviteit per medewerker | Geeve Hydraulics</title>
    <link rel="icon" href="../../favicon.ico?v=<?= h(assetVersion('../../favicon.ico')) ?>" type="image/x-icon">
    <link rel="stylesheet" href="../../shared/style.css?v=<?= h(assetVersion('../../shared/style.css')) ?>">
    <link rel="stylesheet" href="../../assets/style.css?v=<?= h(assetVersion('../../assets/style.css')) ?>">
    <!-- Knoppen, tabel en meldingen zijn dezelfde als in Klant Artikelnummers. -->
    <link rel="stylesheet" href="../../klantartikel/assets/style.css?v=<?= h(assetVersion('../../klantartikel/assets/style.css')) ?>">
    <link rel="stylesheet" href="../msp/assets/style.css?v=<?= h(assetVersion('../msp/assets/style.css')) ?>">
</head>
<body>
<main class="page-shell page-shell--wide">
    <?php
    $headerTitle = 'Productiviteit per medewerker';
    $headerImagesPath = '../../images/';
    $headerHomeHref = '../index.php';
    $headerTopline = '<span class="version-inline">Versie ' . h(APP_VERSION) . '</span>';
    require __DIR__ . '/../../shared/header.php';
    ?>

    <?php if ($locked): ?>
    <section class="panel">
        <h2>Productiviteit per medewerker</h2>
        <p class="ka-empty">Dit onderdeel is beveiligd. Vul de toegangscode in om het rapport te openen.</p>
        <form method="post" class="ka-actions rp-lock-form" autocomplete="off">
            <input type="hidden" name="action" value="unlock">
            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
            <label class="msp-field">
                <span>Toegangscode</span>
                <input type="password" name="code" class="ka-input" inputmode="numeric" autocomplete="off" autofocus required>
            </label>
            <button type="submit" class="ka-button">Openen</button>
        </form>
        <?php if ($codeError !== null): ?>
            <div class="ka-message ka-message--error"><?= h($codeError) ?></div>
        <?php endif; ?>
    </section>
    <?php else: ?>
    <section class="panel">
        <h2>Productiviteit per medewerker</h2>
        <p class="ka-empty">Toont per medewerker het aantal orders of orderregels. <strong>Productie</strong> telt de logistiek afgehandelde (geleverde) verkooporders, <strong>Verkoop</strong> telt de verwerkte verkooporders: een order is verwerkt op het moment dat de eerste orderbevestiging is gemaakt (tabblad Bijlage van de order), door degene die die heeft aangemaakt. <strong>Offertes</strong> telt de ingevoerde offertes op de dag van invoer, bij de vertegenwoordiger die in de offerte staat (ook offertes die inmiddels tot order zijn verwerkt). Het <strong>Overzicht</strong> toont de werkdagen van deze week, deze week, de weken van deze maand en de hele maand; bij <strong>Eigen periode</strong> kies je zelf de datums (maximaal 62 dagen, een kolom per dag). Productieorders (werkorders) zijn nog niet opgenomen.</p>
        <div class="ka-actions" id="rpActions" data-endpoint="api/data.php" data-csrf="<?= h($csrf) ?>" data-label="" data-sheet="Productiviteit" data-file="Productiviteit">
            <input type="hidden" id="rpWeergave" data-field="weergave" value="overzicht">
            <div class="rp-toggle" id="rpView" role="group" aria-label="Weergave">
                <button type="button" data-view="overzicht" class="is-active">Overzicht</button>
                <button type="button" data-view="periode">Eigen periode</button>
            </div>
            <label class="msp-field rp-dates" hidden>
                <span>Datum van</span>
                <input type="date" class="ka-input" data-field="van" data-name="de datum van" value="<?= h($defaultFrom) ?>">
            </label>
            <label class="msp-field rp-dates" hidden>
                <span>Datum tot</span>
                <input type="date" class="ka-input" data-field="tot" data-name="de datum tot" value="<?= h($defaultTo) ?>">
            </label>
            <input type="hidden" id="rpModus" data-field="modus" value="productie">
            <div class="rp-toggle" role="group" aria-label="Soort">
                <button type="button" data-mode="productie" class="is-active">Productie</button>
                <button type="button" data-mode="verkoop">Verkoop</button>
                <button type="button" data-mode="offerte">Offertes</button>
            </div>
            <button type="button" id="rpFetch" class="ka-button">Gegevens ophalen</button>
            <button type="button" id="rpDownload" class="ka-button ka-button--secondary" disabled>Download Excel</button>
            <span id="rpStatus" class="msp-status"></span>
        </div>
        <div id="rpMessage" class="ka-message ka-message--error" hidden></div>
    </section>

    <section class="panel" id="rpResult" hidden>
        <div class="rp-bar">
            <div class="rp-toggle" id="rpMeasure" role="group" aria-label="Telling" hidden>
                <button type="button" data-measure="Orders" class="is-active">Orders</button>
                <button type="button" data-measure="Orderregels">Orderregels</button>
            </div>
        </div>
        <div class="msp-summary" id="rpSummary"></div>
        <div class="ka-table-wrap msp-scroll"><table class="ka-table" id="rpTable"></table></div>
    </section>

    <?php endif; ?>

    <p class="page-footer">Geeve Hydraulics</p>
</main>
<?php if (!$locked): ?>
<script src="../assets/vendor/xlsx-style.min.js"></script>
<script src="../assets/sp-report.js?v=<?= h(assetVersion('../assets/sp-report.js')) ?>"></script>
<?php endif; ?>
</body>
</html>
