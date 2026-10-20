<?php

declare(strict_types=1);

// Nieuwe tegel Slangkaarten (in voorbereiding). Let op: de tegel-id is 'slangkaarten-app', want
// 'slangkaarten' is de bestaande tegel Slangkaarten Printen (map slkprinten).
// Beveiligde tegel (Config > Tegels): zonder ontgrendelen terug naar het overzicht, waar de code wordt gevraagd.
require_once __DIR__ . '/../shared/tiles.php';
tileRequirePage('slangkaarten-app', '../index.php');

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
    <title>Slangkaarten | Geeve Hydraulics</title>
    <?php
    $headCss = ['slangkaarten/assets/style.css'];   // vanaf de portal-root, in laadvolgorde
    require __DIR__ . '/../shared/head.php';
    ?>
</head>
<body>
<main class="page-shell page-shell--wide">
    <?php
    $headerTitle = 'Slangkaarten';
    $headerTopline = ''; // het versienummer staat onderaan de pagina, achter de copyright-regel
    require __DIR__ . '/../shared/header.php';
    ?>

    <section class="panel">
        <h2>Slangkaarten</h2>
        <p>Deze app is in voorbereiding.</p>
    </section>

    <p class="page-footer">(c) 2026 PdB - Geeve Hydraulics - Versie <?= h(APP_VERSION) ?></p>
</main>
</body>
</html>
