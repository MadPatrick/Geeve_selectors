<?php

declare(strict_types=1);

// Beveiligde tegel (Config > Tegels): zonder ontgrendelen terug naar het overzicht, waar de code wordt gevraagd.
require_once __DIR__ . '/../shared/tiles.php';
tileRequirePage('gebogenbuis', '../index.php');

// Eén gedeeld versienummer voor hoofdscherm + alle subapps (version.php op
// rootniveau) - valt terug op deze waarde als dat bestand ontbreekt.
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
    <title>Gebogen buis | Geeve Hydraulics</title>
    <?php
    $headCss = ['klantartikel/assets/style.css', 'gebogenbuis/assets/style.css']; // .ka-table/.ka-input/.ka-button zijn gedeeld
    require __DIR__ . '/../shared/head.php';
    ?>
</head>
<body>
<main class="page-shell page-shell--wide">
    <?php
    $headerTitle = 'Gebogen buis';
    require __DIR__ . '/../shared/header.php';
    ?>

    <section class="panel" id="gbApp" data-klanten="api/klanten.php" data-artikelen="api/artikelen.php" data-korting="api/korting.php">
        <label class="field gb-klant">
            <span>Klant</span>
            <input type="text" id="gbKlant" placeholder="Debiteurnummer of naam" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="gbKlantHits">
            <ul class="gb-hits" id="gbKlantHits" role="listbox" hidden></ul>
        </label>
        <div class="gb-klantinfo" id="gbKlantInfo" hidden></div>
        <div class="ka-message ka-message--error" id="gbMessage" hidden></div>

        <div class="ka-table-wrap"><table class="ka-table gb-lines" id="gbTable">
            <thead><tr><th>Artikel</th><th>Aantal</th><th class="gb-num">Bruto prijs /stuk</th><th class="gb-num">Bruto prijs totaal</th><th class="gb-num">Vrije voorraad</th><th class="gb-num">Korting</th><th></th></tr></thead>
            <tbody id="gbBody"></tbody>
        </table></div>
        <button type="button" class="ka-add gb-add" id="gbAdd" title="Artikel toevoegen" aria-label="Artikel toevoegen">+</button>

        <div class="gb-extra">
            <label class="field">
                <span>Montage</span>
                <input type="text" id="gbMontage" class="ka-input gb-montage" inputmode="decimal" placeholder="0,00" autocomplete="off">
            </label>
            <label class="field gb-notes">
                <span>Notities</span>
                <textarea id="gbNotes" rows="4" class="ka-input"></textarea>
            </label>
        </div>

        <dl class="gb-totals">
            <div><dt>Bruto prijs</dt><dd id="gbBruto">&euro; 0,00</dd></div>
            <div><dt>Korting <span id="gbKortingPct"></span></dt><dd id="gbKorting">&euro; 0,00</dd></div>
            <div class="gb-totals-netto"><dt>Netto prijs</dt><dd id="gbNetto">&euro; 0,00</dd></div>
        </dl>
        <div class="gb-actions"><button type="button" class="ka-button" id="gbNaarExact" disabled title="Nog niet gekoppeld aan Exact">Naar Exact</button></div>
    </section>

    <p class="page-footer">(c) 2026 PdB - Geeve Hydraulics - Versie <?= h(APP_VERSION) ?></p>
</main>
<script src="assets/gebogenbuis.js?v=<?= h(assetVersion('assets/gebogenbuis.js')) ?>"></script>
</body>
</html>
