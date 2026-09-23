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
// zelf, zodat elke aanpassing aan style.css automatisch een nieuwe URL
// krijgt - geen handmatige versie-ophoging meer nodig.
function assetVersion(string $relativePath): string
{
    $full = __DIR__ . '/' . $relativePath;
    $mtime = @filemtime($full);
    return $mtime !== false ? (string) $mtime : APP_VERSION;
}

$uploadStatus = $_GET['upload'] ?? '';
$uploadMessage = $_GET['msg'] ?? '';
?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Data beheer | Geeve Hydraulics</title>
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
            <div class="header-content">
                <div class="header-topline">
                    <a href="data.php" class="header-icon-button" title="Data downloaden / uploaden" aria-label="Data downloaden / uploaden">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <ellipse cx="12" cy="5" rx="8" ry="3"></ellipse>
                            <path d="M4 5v6c0 1.66 3.58 3 8 3s8-1.34 8-3V5"></path>
                            <path d="M4 11v6c0 1.66 3.58 3 8 3s8-1.34 8-3v-6"></path>
                        </svg>
                    </a>
                </div>
                <h1>Data beheer</h1>
            </div>
        </div>
    </header>

    <section class="panel data-panel">
        <div class="section-heading">
            <div>
                <span class="step">Beheer</span>
                <h2>Data downloaden / uploaden</h2>
            </div>
            <div class="section-actions">
                <a href="index.php" class="pdf-button">&larr; Terug naar zoeken</a>
            </div>
        </div>

        <?php if ($uploadStatus === 'ok'): ?>
            <div class="upload-message upload-ok"><?= h($uploadMessage) ?></div>
        <?php elseif ($uploadStatus === 'error'): ?>
            <div class="upload-message upload-error"><?= h($uploadMessage) ?></div>
        <?php endif; ?>

        <div class="data-panel-grid">
            <div class="data-panel-block">
                <h3>Downloaden</h3>
                <p>Download het huidige CSV-bestand om te bewerken.</p>
                <div class="data-download-links">
                    <a class="data-download-link" href="download.php?dataset=adapters">Adapters (CSV)</a>
                </div>
            </div>

            <div class="data-panel-block">
                <h3>Uploaden</h3>
                <p>Zet een bewerkt CSV-bestand terug. Het databestand wordt herkend aan de
                    bestandsnaam &mdash; laat die dus ongewijzigd (bijv. <code>artikelnummers_adapters.csv</code>).
                    Er wordt automatisch een backup van het huidige bestand bewaard.</p>
                <form action="upload.php" method="post" enctype="multipart/form-data" class="upload-form">
                    <label class="field" for="uploadFile">
                        <span>CSV-bestand</span>
                        <input id="uploadFile" type="file" name="csv_file" accept=".csv,text/csv" required>
                    </label>
                    <button type="submit" class="upload-button">Uploaden</button>
                </form>
            </div>
        </div>
    </section>
</main>
</body>
</html>
