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

$priceList = trim((string) ($_GET['prijslijst'] ?? $_POST['prijslijst'] ?? ''));
$priceColumn = trim((string) ($_GET['pcol'] ?? ''));
$customers = [];
$schema = null;
$explore = null;
$error = null;
$exploreError = null;
$lines = [];
$listDescription = null;

try {
    $pdo = getPdoConnection();
    if ($priceList !== '') {
        $schema = detectSchema($pdo, $priceColumn);
        $customers = findCustomersByPriceList($pdo, $schema, $priceList);
        $listDescription = findPriceListDescription($pdo, $priceList);

        // XML-export van de gewijzigde/nieuwe regels uit het scherm.
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'export') {
            $rows = [];
            foreach ((array) ($_POST['group'] ?? []) as $i => $group) {
                $group = trim((string) $group);
                $tiers = parseTierLists((string) ($_POST['aantal'][$i] ?? ''), (string) ($_POST['korting'][$i] ?? ''));
                if ($group === '' || $tiers === null) {
                    continue;
                }
                $rows[] = [
                    'id' => trim((string) ($_POST['id'][$i] ?? '')),
                    'group' => $group,
                    'debcode' => trim((string) ($_POST['debcode'][$i] ?? '')),
                    'kind' => trim((string) ($_POST['soort'][$i] ?? 'P')) ?: 'P',
                    'from' => trim((string) ($_POST['from'][$i] ?? '')),
                    'to' => trim((string) ($_POST['to'][$i] ?? '')),
                    'tiers' => $tiers,
                ];
            }
            $xml = buildDiscountXml($priceList, $rows);
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            header('Content-Type: application/xml; charset=utf-8');
            header('Content-Disposition: attachment; filename="DISCOUNTS.xml"');
            echo $xml;
            exit;
        }

        $lines = findDiscountLines($pdo, $priceList);
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Export mislukt: ' . $error;
        exit;
    }
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

        <?php if ($priceList !== ''): ?>
        <section class="panel">
            <h2>Kortingstructuur prijslijst <?= h($priceList) ?><?= $listDescription ? ' - ' . h($listDescription) : '' ?> <small>(<?= count($lines) ?> regels)</small></h2>
            <?php if ($listDescription === null): ?>
                <div class="ka-message ka-message--error">Prijslijst <?= h($priceList) ?> staat niet als staffel-prijslijst (stfoms, type S) in Exact.</div>
            <?php else: ?>
            <form method="post" id="kkForm" data-lookup="api/group_lookup.php" data-pricelist="<?= h($priceList) ?>">
                <input type="hidden" name="action" value="export">
                <input type="hidden" name="prijslijst" value="<?= h($priceList) ?>">
                <div class="ka-heading">
                    <p class="ka-empty">Meestal één staffel: aantal <code>1</code>, korting <code>25</code>. Meerdere staffels zet je in beide velden achter elkaar, gescheiden door <code>;</code> (aantal <code>1; 10</code>, korting <code>25; 30</code>). De XML bevat alleen gewijzigde en nieuwe regels.</p>
                    <div class="ka-actions">
                        <button type="button" id="kkTemplate" class="ka-button ka-button--secondary">Download Excel</button>
                        <button type="button" id="kkAdd" class="ka-add" title="Regel toevoegen" aria-label="Regel toevoegen">+</button>
                        <button type="submit" class="ka-button ka-button--secondary">Exporteer XML</button>
                    </div>
                </div>
                <div id="kkDrop" class="ka-drop" tabindex="0" role="button">
                    <strong>Excel importeren</strong> - sleep een ingevuld bestand hierheen of <u>kies een bestand</u>
                    <input type="file" id="kkFile" accept=".xlsx,.xls,.xlsm,.csv" hidden>
                </div>
                <div id="kkMessage" class="ka-message ka-message--error" hidden></div>
                <div id="kkInfo" class="ka-message ka-message--ok" hidden></div>
                <div class="ka-table-wrap"><table class="ka-table kk-lines" id="kkTable">
                    <thead><tr><th>Artikelgroep</th><th>Omschrijving</th><th>Geldt voor</th><th>Geldig van</th><th>Geldig tot</th><th>Aantal</th><th>Korting (%)</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($lines as $l): ?>
                        <?php
                        $debcode = trim((string) ($l['debcode'] ?? ''));
                        $orig = tierQtyText($l) . '|' . tierDiscountText($l) . '|' . dateToIso($l['validfrom']) . '|' . dateToIso($l['validto']);
                        ?>
                        <tr class="is-found" data-orig="<?= h($orig) ?>" data-kort="<?= h(trim((string) $l['kort_pbn'])) ?>">
                            <td class="ka-itemcell"><strong class="ka-itemtext"><?= h((string) $l['ItemGroup']) ?></strong>
                                <input type="hidden" name="group[]" class="kk-group" value="<?= h((string) $l['ItemGroup']) ?>">
                                <input type="hidden" name="id[]" value="<?= h((string) $l['ID']) ?>">
                                <input type="hidden" name="debcode[]" class="kk-debcode" value="<?= h($debcode) ?>"></td>
                            <td class="ka-desc"><?= h((string) $l['ItemGroupDescr']) ?></td>
                            <td><?php if ($debcode !== ''): ?><span class="kk-badge"><?= h($debcode) ?> <?= h((string) $l['klant']) ?></span><?php else: ?>Alle klanten<?php endif; ?></td>
                            <td><input type="date" name="from[]" class="ka-input kk-from" value="<?= h(dateToIso($l['validfrom'])) ?>"></td>
                            <td><input type="date" name="to[]" class="ka-input kk-to" value="<?= h(dateToIso($l['validto'])) ?>"></td>
                            <td><input type="text" name="aantal[]" class="ka-input kk-qty" value="<?= h(tierQtyText($l)) ?>" autocomplete="off"></td>
                            <td><input type="text" name="korting[]" class="ka-input kk-disc" value="<?= h(tierDiscountText($l)) ?>" autocomplete="off">
                                <input type="hidden" name="soort[]" value="<?= h(trim((string) $l['kort_pbn']) ?: 'P') ?>">
                                <?php if (trim((string) $l['kort_pbn']) !== 'P' && trim((string) $l['kort_pbn']) !== ''): ?><span class="kk-badge" title="Soort <?= h(trim((string) $l['kort_pbn'])) ?>: geen percentage">soort <?= h(trim((string) $l['kort_pbn'])) ?></span><?php endif; ?></td>
                            <td></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
                <?php if ($lines === []): ?><p class="ka-empty" id="kkEmpty">Nog geen kortingsregels op deze prijslijst - voeg er een toe met +.</p><?php endif; ?>
            </form>
            <script src="../klantartikel/assets/vendor/xlsx.core.min.js"></script>
            <script src="assets/klantkorting.js?v=<?= h(assetVersion('assets/klantkorting.js')) ?>"></script>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <details class="panel">
            <summary>Database-verkenning (korting-/prijslijsttabellen)</summary>
            <?php if ($exploreError !== null): ?>
                <div class="ka-message ka-message--error"><?= h($exploreError) ?></div>
            <?php elseif ($explore !== null): ?>
                <h3>Kolommen op cicmpy (debiteuren) die op prijs/korting lijken</h3>
                <p class="kk-columns"><?= $explore['debtorColumns'] === [] ? '(geen)' : h(implode(', ', $explore['debtorColumns'])) ?></p>
                <h3>Tabellen met korting/prijslijst in de naam</h3>
                <div class="ka-table-wrap"><table class="ka-table kk-schema">
                    <thead><tr><th>Tabel</th><th>Rijen</th><th>Kolommen</th></tr></thead>
                    <tbody>
                    <?php foreach ($explore['tables'] as $t): ?>
                        <tr><td><strong><?= h($t['name']) ?></strong></td><td><?= $t['rows'] === null ? '' : h((string) $t['rows']) ?></td><td class="kk-columns"><?= h(implode(', ', $t['columns'])) ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
            <?php endif; ?>
        </details>
    <?php endif; ?>

    <p class="page-footer">Geeve Hydraulics</p>
</main>
</body>
</html>
