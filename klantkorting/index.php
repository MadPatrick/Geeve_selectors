<?php

declare(strict_types=1);

// Eén gedeeld versienummer voor hoofdscherm + alle subapps (version.php op
// rootniveau) - valt terug op deze waarde als dat bestand ontbreekt.
define('APP_VERSION', is_file(__DIR__ . '/../version.php') ? (string) require __DIR__ . '/../version.php' : '0.2.1');

require_once __DIR__ . '/inc.php';

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
$schema = null;
$explore = null;
$error = null;
$exploreError = null;

try {
    $pdo = getPdoConnection();
    if ($priceList !== '') {
        $schema = detectSchema($pdo, $priceColumn);
        $customers = findCustomersByPriceList($pdo, $schema, $priceList);
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

if ($error === null) {
    try {
        $explore = exploreDiscountSchema($pdo);
    } catch (Throwable $e) {
        $exploreError = $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Kortingstructuur | Geeve Hydraulics</title>
    <link rel="icon" href="../favicon.ico?v=<?= h(assetVersion('../favicon.ico')) ?>" type="image/x-icon">
    <link rel="stylesheet" href="../shared/style.css?v=<?= h(assetVersion('../shared/style.css')) ?>">
    <link rel="stylesheet" href="../klantartikel/assets/style.css?v=<?= h(assetVersion('../klantartikel/assets/style.css')) ?>">
    <link rel="stylesheet" href="assets/style.css?v=<?= h(assetVersion('assets/style.css')) ?>">
</head>
<body>
<main class="page-shell page-shell--wide">
    <?php
    $headerTitle = 'Kortingstructuur';
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
        </form>
    </section>

    <?php if ($error !== null): ?>
        <section class="panel"><div class="ka-message ka-message--error"><?= h($error) ?></div></section>
    <?php else: ?>
        <?php if ($priceList !== ''): ?>
            <section class="panel">
                <h2>Klanten op prijslijst <?= h($priceList) ?> <small>(<?= count($customers) ?>)</small></h2>
                <?php if ($customers === []): ?>
                    <p class="ka-empty">Geen klanten gevonden op deze prijslijst (kolom <code><?= h($schema['pricelist']) ?></code>).</p>
                <?php else: ?>
                    <div class="ka-chips">
                        <?php foreach ($customers as $c): ?>
                            <span class="ka-chip"><strong><?= h(trim((string) $c['debnr'])) ?></strong> <?= h((string) $c['naam']) ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <section class="panel">
            <h2>Kortingstructuur</h2>
            <p class="ka-empty">De kortingstructuur wordt hier getoond en aanpasbaar zodra bekend is in welke Exact-tabellen die staat. Hieronder staat wat de database aan kortings- en prijslijsttabellen heeft.</p>
            <?php if ($exploreError !== null): ?>
                <div class="ka-message ka-message--error"><?= h($exploreError) ?></div>
            <?php elseif ($explore !== null): ?>
                <h3>Kolommen op cicmpy (debiteuren) die op prijs/korting lijken</h3>
                <p class="kk-columns"><?= $explore['debtorColumns'] === [] ? '(geen)' : h(implode(', ', $explore['debtorColumns'])) ?></p>
                <h3>Tabellen met korting/prijslijst in de naam</h3>
                <?php if ($explore['tables'] === []): ?>
                    <p class="ka-empty">(geen)</p>
                <?php else: ?>
                    <div class="ka-table-wrap"><table class="ka-table kk-schema">
                        <thead><tr><th>Tabel</th><th>Rijen</th><th>Kolommen</th></tr></thead>
                        <tbody>
                        <?php foreach ($explore['tables'] as $t): ?>
                            <tr>
                                <td><strong><?= h($t['name']) ?></strong></td>
                                <td><?= $t['rows'] === null ? '' : h((string) $t['rows']) ?></td>
                                <td class="kk-columns"><?= h(implode(', ', $t['columns'])) ?></td>
                            </tr>
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
