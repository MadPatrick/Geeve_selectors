<?php

declare(strict_types=1);

// Eén gedeeld versienummer voor hoofdscherm + alle subapps (version.php op
// rootniveau) - valt terug op deze waarde als dat bestand ontbreekt.
define('APP_VERSION', is_file(__DIR__ . '/../version.php') ? (string) require __DIR__ . '/../version.php' : '0.2.1');

require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/queries.php';

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

$priceList = trim((string) ($_GET['prijslijst'] ?? ''));
$priceColumn = trim((string) ($_GET['pcol'] ?? ''));
$customers = [];
$articles = [];
$truncated = false;
$schema = null;
$diag = null;
$error = null;

if ($priceList !== '') {
    try {
        $pdo = getPdoConnection();
        $schema = detectSchema($pdo, $priceColumn);
        $customers = findCustomersByPriceList($pdo, $schema, $priceList);
        $articles = findCustomerArticles($pdo, $schema, $priceList);
        if ($customers !== [] && $articles === []) {
            $diag = diagnoseItemAccounts($pdo, $schema, $priceList);
        }
        if (count($articles) > MAX_ARTICLE_ROWS) {
            $truncated = true;
            array_pop($articles);
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }

    if ($error === null && ($_GET['export'] ?? '') === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="klantartikelen_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $priceList) . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Debiteurnr', 'Klant', 'Artikelnummer', 'Omschrijving', 'Klantartikelnummer'], ';');
        foreach ($articles as $a) {
            fputcsv($out, [trim((string) $a['debnr']), $a['klant'], trim((string) $a['artikel']), $a['omschrijving'], trim((string) $a['klantartikel'])], ';');
        }
        fclose($out);
        exit;
    }
}
?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Klant Artikelnummers | Geeve Hydraulics</title>
    <link rel="icon" href="../favicon.ico?v=<?= h(assetVersion('../favicon.ico')) ?>" type="image/x-icon">
    <link rel="stylesheet" href="../shared/style.css?v=<?= h(assetVersion('../shared/style.css')) ?>">
    <link rel="stylesheet" href="assets/style.css?v=<?= h(assetVersion('assets/style.css')) ?>">
</head>
<body>
<main class="page-shell page-shell--wide">
    <?php
    $headerTitle = 'Klant Artikelnummers';
    $headerTopline = '<span class="version-inline">Versie ' . h(APP_VERSION) . '</span>';
    require __DIR__ . '/../shared/header.php';
    ?>

    <section class="panel">
        <form method="get" class="ka-form">
            <label class="field">
                <span>Prijslijstnummer</span>
                <input type="text" name="prijslijst" value="<?= h($priceList) ?>" placeholder="bijv. 100" autocomplete="off" autofocus required>
            </label>
            <button type="submit" class="ka-button">Zoeken</button>
            <?php if ($priceList !== '' && $error === null): ?>
                <a class="ka-button ka-button--secondary" href="?prijslijst=<?= h(rawurlencode($priceList)) ?>&amp;export=csv">Exporteer CSV</a>
            <?php endif; ?>
        </form>
    </section>

    <?php if ($error !== null): ?>
        <section class="panel"><div class="ka-message ka-message--error"><?= h($error) ?></div></section>
    <?php elseif ($priceList !== ''): ?>
        <section class="panel">
            <h2>Klanten op prijslijst <?= h($priceList) ?> <small>(<?= count($customers) ?>)</small></h2>
            <?php if ($customers === []): ?>
                <p class="ka-empty">Geen klanten gevonden op deze prijslijst (kolom <code><?= h($schema['pricelist']) ?></code>).</p>
            <?php else: ?>
                <div class="ka-table-wrap"><table class="ka-table">
                    <thead><tr><th>Debiteurnr</th><th>Klant</th></tr></thead>
                    <tbody>
                    <?php foreach ($customers as $c): ?>
                        <tr><td><?= h(trim((string) $c['debnr'])) ?></td><td><?= h((string) $c['naam']) ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
            <?php endif; ?>
        </section>

        <section class="panel">
            <h2>Artikelen met klantartikelnummer <small>(<?= count($articles) ?><?= $truncated ? '+' : '' ?>)</small></h2>
            <?php if ($truncated): ?>
                <div class="ka-message ka-message--error">Alleen de eerste <?= MAX_ARTICLE_ROWS ?> rijen worden getoond.</div>
            <?php endif; ?>
            <?php if ($articles === []): ?>
                <p class="ka-empty">Voor deze klanten is geen klantartikelnummer ingevuld.</p>
            <?php else: ?>
                <div class="ka-table-wrap"><table class="ka-table">
                    <thead><tr><th>Debiteurnr</th><th>Klant</th><th>Artikelnummer</th><th>Omschrijving</th><th>Klantartikelnummer</th></tr></thead>
                    <tbody>
                    <?php foreach ($articles as $a): ?>
                        <tr>
                            <td><?= h(trim((string) $a['debnr'])) ?></td>
                            <td><?= h((string) $a['klant']) ?></td>
                            <td><?= h(trim((string) $a['artikel'])) ?></td>
                            <td><?= h((string) $a['omschrijving']) ?></td>
                            <td><strong><?= h(trim((string) $a['klantartikel'])) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
            <?php endif; ?>
            <?php if ($diag !== null): ?>
                <h3>Diagnose</h3>
                <p class="ka-empty">Koppeling gebruikt: ItemAccounts.<?= h($schema['itemLink']) ?> = cicmpy.<?= h($schema['debtorLink']) ?>, klantartikelnummer uit <code><?= h($schema['codeColumn']) ?></code>.</p>
                <?php if ($diag['error'] !== null): ?><div class="ka-message ka-message--error"><?= h($diag['error']) ?></div><?php endif; ?>
                <div class="ka-table-wrap"><table class="ka-table">
                    <thead><tr><th>Mogelijke koppeling</th><th>Rijen voor deze klanten</th><th>Waarvan klantartikelnr gevuld</th></tr></thead>
                    <tbody>
                    <?php foreach ($diag['links'] as $l): ?>
                        <tr><td><?= h($l['link']) ?></td><td><?= h($l['total']) ?></td><td><?= h($l['filled']) ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
                <p class="ka-empty">Kolommen op ItemAccounts: <?= h(implode(', ', $diag['columns'])) ?></p>
                <?php if ($diag['sample'] !== []): ?>
                    <p class="ka-empty">Voorbeeldrijen (met ingevuld klantartikelnummer, willekeurige klanten):</p>
                    <div class="ka-table-wrap"><table class="ka-table">
                        <thead><tr><?php foreach (array_keys($diag['sample'][0]) as $col): ?><th><?= h((string) $col) ?></th><?php endforeach; ?></tr></thead>
                        <tbody>
                        <?php foreach ($diag['sample'] as $row): ?>
                            <tr><?php foreach ($row as $v): ?><td><?= h(is_scalar($v) || $v === null ? trim((string) $v) : '(binair)') ?></td><?php endforeach; ?></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table></div>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <p class="page-footer">Geeve Hydraulics</p>
</main>
</body>
</html>
