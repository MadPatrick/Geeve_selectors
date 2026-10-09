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

$priceList = trim((string) ($_GET['prijslijst'] ?? $_POST['prijslijst'] ?? ''));
$priceColumn = trim((string) ($_GET['pcol'] ?? ''));
$customers = [];
$articles = [];
$schema = null;
$diag = null;
$usedLink = null;
$error = null;

if ($priceList !== '') {
    try {
        $pdo = getPdoConnection();
        $schema = detectSchema($pdo, $priceColumn);
        $customers = findCustomersByPriceList($pdo, $schema, $priceList);

        // XML-export van de (in de pagina bewerkte) regels: elke regel geldt voor alle klanten op de prijslijst.
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'export') {
            $rows = [];
            $postedItems = (array) ($_POST['artikel'] ?? []);
            $postedCodes = (array) ($_POST['klantartikel'] ?? []);
            foreach ($postedItems as $i => $item) {
                $item = trim((string) $item);
                $code = trim((string) ($postedCodes[$i] ?? ''));
                if ($item !== '' && $code !== '') {
                    $rows[] = ['artikel' => $item, 'klantartikel' => $code];
                }
            }
            header('Content-Type: application/xml; charset=utf-8');
            header('Content-Disposition: attachment; filename="klantartikelen_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $priceList) . '.xml"');
            echo buildExactXmlForRows($customers, $rows);
            exit;
        }

        [, $usedLink] = findCustomerArticlesAny($pdo, $schema, $priceList, null, 1);
        if ($usedLink !== null) {
            $articles = findConsolidatedArticles($pdo, $schema, $priceList, $usedLink, null);
        }
        if ($customers !== [] && $articles === []) {
            $diag = diagnoseItemAccounts($pdo, $schema, $priceList);
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
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
                <div class="ka-chips">
                    <?php foreach ($customers as $c): ?>
                        <span class="ka-chip"><strong><?= h(trim((string) $c['debnr'])) ?></strong> <?= h((string) $c['naam']) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="panel">
            <form method="post" id="kaForm" data-lookup="api/item_lookup.php">
                <input type="hidden" name="action" value="export">
                <input type="hidden" name="prijslijst" value="<?= h($priceList) ?>">
                <div class="ka-heading">
                    <h2>Artikelen met klantartikelnummer <small id="kaCount">(<?= count($articles) ?>)</small></h2>
                    <div class="ka-actions">
                        <button type="button" id="kaAdd" class="ka-add" title="Regel toevoegen" aria-label="Regel toevoegen">+</button>
                        <button type="submit" class="ka-button ka-button--secondary" id="kaExport">Exporteer XML</button>
                    </div>
                </div>
                <p class="ka-empty">Geldt voor alle <?= count($customers) ?> klanten op deze prijslijst. De XML bevat alleen gewijzigde en nieuwe regels. Het klantartikelnummer is te wijzigen; met + voeg je bovenaan een regel toe, waarvan het artikelnummer direct in Exact wordt gecontroleerd.</p>
                <div id="kaMessage" class="ka-message ka-message--error" hidden></div>
                <div class="ka-table-wrap"><table class="ka-table" id="kaTable">
                    <thead>
                        <tr class="ka-filter-row">
                            <th><input type="search" id="kaFilterItem" class="ka-input" placeholder="Zoek artikelnummer" autocomplete="off"></th>
                            <th></th>
                            <th><input type="search" id="kaFilterCode" class="ka-input" placeholder="Zoek klantartikelnummer" autocomplete="off"></th>
                            <th></th>
                        </tr>
                        <tr><th>Artikelnummer</th><th>Omschrijving</th><th>Klantartikelnummer</th><th></th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($articles as $a): ?>
                        <tr class="is-found" data-orig="<?= h(trim((string) $a['klantartikel'])) ?>">
                            <td class="ka-itemcell"><span class="ka-itemtext"><?= h(trim((string) $a['artikel'])) ?></span><input type="hidden" name="artikel[]" value="<?= h(trim((string) $a['artikel'])) ?>" class="ka-item"></td>
                            <td class="ka-desc"><?= h((string) $a['omschrijving']) ?></td>
                            <td><input type="text" name="klantartikel[]" value="<?= h(trim((string) $a['klantartikel'])) ?>" class="ka-input ka-code" autocomplete="off"></td>
                            <td><button type="button" class="ka-remove" title="Regel verwijderen" aria-label="Regel verwijderen">&times;</button></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
                <div class="ka-pager" id="kaPager" hidden>
                    <button type="button" class="ka-button ka-button--secondary" id="kaPrev">&larr; Vorige</button>
                    <span id="kaPageInfo"></span>
                    <button type="button" class="ka-button ka-button--secondary" id="kaNext">Volgende &rarr;</button>
                </div>
            </form>

            <?php if ($diag !== null): ?>
                <h3>Diagnose</h3>
                <p class="ka-empty">Geprobeerde koppelingen (in volgorde): <?= h(implode('; ', array_map(static fn ($l) => "ItemAccounts.{$l[0]} = cicmpy.{$l[1]}", $schema['links']))) ?>. Klantartikelnummer uit <code><?= h($schema['codeColumn']) ?></code>.</p>
                <?php if ($diag['error'] !== null): ?><div class="ka-message ka-message--error"><?= h($diag['error']) ?></div><?php endif; ?>
                <div class="ka-table-wrap"><table class="ka-table">
                    <thead><tr><th>Mogelijke koppeling</th><th>Rijen voor deze klanten</th><th>Waarvan klantartikelnr gevuld</th></tr></thead>
                    <tbody>
                    <?php foreach ($diag['links'] as $l): ?>
                        <tr><td><?= h($l['link']) ?></td><td><?= h($l['total']) ?></td><td><?= h($l['filled']) ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
            <?php endif; ?>
        </section>
        <script src="assets/klantartikel.js?v=<?= h(assetVersion('assets/klantartikel.js')) ?>"></script>
    <?php endif; ?>

    <p class="page-footer">Geeve Hydraulics</p>
</main>
</body>
</html>
