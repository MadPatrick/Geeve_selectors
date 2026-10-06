<?php

declare(strict_types=1);

/**
 * Diagnosepagina om de databaseverbinding naar de Exact-database "005" te
 * testen en tabellen/kolommen te browsen (bijv. de nog onbevestigde
 * verkoopprijs-kolom op GRV_SalesItems, zie api/exact_prices.php) - geen
 * onderdeel van de eigenlijke Stauff-selector, die zijn kandidaat-artikelen
 * rechtstreeks via api/exact_search.php/api/exact_location_search.php haalt.
 */

define('APP_VERSION', is_file(__DIR__ . '/../version.php') ? (string) require __DIR__ . '/../version.php' : '0.2.1');

require_once __DIR__ . '/inc/db.php';

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

$searchTerm = trim((string) ($_GET['zoek'] ?? 'groep'));
$columnSearchTerm = trim((string) ($_GET['kolom'] ?? ''));
$selectedTable = trim((string) ($_GET['tabel'] ?? ''));
$groupValue = trim((string) ($_GET['waarde'] ?? '67'));
$filterColumn = trim((string) ($_GET['filterkolom'] ?? ''));
$filterValue = trim((string) ($_GET['filterwaarde'] ?? ''));

$errorMessage = null;
$connectionOk = false;
$tables = [];
$columnMatches = [];
$columns = [];
$previewRows = [];
$groupRows = null;
$filterRows = null;

try {
    $pdo = getPdoConnection();
    $connectionOk = true;

    if ($columnSearchTerm !== '') {
        // Zoekt (anders dan de tabelnaam-zoekopdracht hieronder) over ALLE
        // tabellen heen naar een kolomnaam - handig als je wel weet hoe het
        // veld heet (bv. "levwijze") maar niet in welke tabel het staat.
        $colSearchStmt = $pdo->prepare(
            "SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS " .
            "WHERE COLUMN_NAME LIKE :pattern ORDER BY TABLE_NAME, ORDINAL_POSITION"
        );
        $colSearchStmt->execute(['pattern' => '%' . $columnSearchTerm . '%']);
        $columnMatches = $colSearchStmt->fetchAll();
    }

    $stmt = $pdo->prepare(
        "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES " .
        "WHERE TABLE_TYPE = 'BASE TABLE' AND TABLE_NAME LIKE :pattern ORDER BY TABLE_NAME"
    );
    $stmt->execute(['pattern' => '%' . $searchTerm . '%']);
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // $selectedTable komt uit de resultaten van de query hierboven (via de
    // klikbare links) - toch defensief een identifier-whitelist erop, een
    // tabelnaam kan niet als parameter gebonden worden zoals een waarde.
    if ($selectedTable !== '' && preg_match('/^[A-Za-z0-9_ ]+$/', $selectedTable) === 1) {
        $colStmt = $pdo->prepare(
            'SELECT COLUMN_NAME, DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS ' .
            'WHERE TABLE_NAME = :table ORDER BY ORDINAL_POSITION'
        );
        $colStmt->execute(['table' => $selectedTable]);
        $columns = $colStmt->fetchAll();

        if ($columns !== []) {
            $previewStmt = $pdo->query("SELECT TOP 20 * FROM [dbo].[{$selectedTable}]");
            $previewRows = $previewStmt->fetchAll();

            // Probeer meteen te filteren op de opgegeven groepswaarde (bv.
            // 67) tegen elke kolom met "groep"/"group" in de naam - dat
            // bespaart een handmatige ronde zodra de juiste kolom er
            // tussen zit.
            $groupColumns = array_values(array_filter(
                array_column($columns, 'COLUMN_NAME'),
                static fn(string $name): bool => stripos($name, 'groep') !== false || stripos($name, 'group') !== false
            ));

            if ($groupColumns !== [] && $groupValue !== '') {
                $groupRows = [];
                foreach ($groupColumns as $column) {
                    try {
                        $filterStmt = $pdo->prepare(
                            "SELECT TOP 20 * FROM [dbo].[{$selectedTable}] WHERE [{$column}] = :value"
                        );
                        $filterStmt->execute(['value' => $groupValue]);
                        $rows = $filterStmt->fetchAll();
                        if ($rows !== []) {
                            $groupRows[$column] = $rows;
                        }
                    } catch (PDOException $exception) {
                        continue;
                    }
                }
            }

            // Expliciet kolom+waarde-filter (bv. "ordernr" = 36019177) - i.p.v.
            // het automatisch geraden groep-filter hierboven, voor het
            // opzoeken van een specifieke rij/order om te zien wat erin staat.
            if ($filterColumn !== '' && $filterValue !== '' && in_array($filterColumn, array_column($columns, 'COLUMN_NAME'), true)) {
                try {
                    $filterStmt = $pdo->prepare(
                        "SELECT TOP 20 * FROM [dbo].[{$selectedTable}] WHERE [{$filterColumn}] = :value"
                    );
                    $filterStmt->execute(['value' => $filterValue]);
                    $filterRows = $filterStmt->fetchAll();
                } catch (PDOException $exception) {
                    $filterRows = [];
                }
            }
        }
    }
} catch (Throwable $exception) {
    $errorMessage = $exception->getMessage();
}
?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>DB-test | Geeve Hydraulics</title>
    <link rel="icon" href="../favicon.ico?v=<?= h(assetVersion('../favicon.ico')) ?>" type="image/x-icon">
    <link rel="stylesheet" href="../shared/style.css?v=<?= h(assetVersion('../shared/style.css')) ?>">
    <link rel="stylesheet" href="assets/style.css?v=<?= h(assetVersion('assets/style.css')) ?>">
</head>
<body>
<main class="page-shell">
    <?php
    $headerTitle = 'DB-test: Exact database "005"';
    require __DIR__ . '/../shared/header.php';
    ?>

    <?php if ($errorMessage !== null): ?>
        <section class="warning-box">
            <strong><?= $connectionOk ? 'Verbinding gelukt, maar de query gaf een fout:' : 'Verbinding mislukt:' ?></strong>
            <p style="margin:8px 0 0"><?= h($errorMessage) ?></p>
        </section>
    <?php else: ?>
        <section class="panel">
            <p><strong>Verbinding met database "005" gelukt.</strong></p>
        </section>
    <?php endif; ?>

    <section class="panel">
        <form method="get" class="db-test-form">
            <label class="field">
                <span>Zoek tabelnaam (bevat)</span>
                <input type="text" name="zoek" value="<?= h($searchTerm) ?>" placeholder="bijv. groep">
            </label>
            <label class="field">
                <span>Zoek kolomnaam (bevat, over alle tabellen)</span>
                <input type="text" name="kolom" value="<?= h($columnSearchTerm) ?>" placeholder="bijv. levwijze">
            </label>
            <label class="field">
                <span>Groepswaarde</span>
                <input type="text" name="waarde" value="<?= h($groupValue) ?>" placeholder="67">
            </label>
            <label class="field">
                <span>Filter kolomnaam (exact, binnen geselecteerde tabel)</span>
                <input type="text" name="filterkolom" value="<?= h($filterColumn) ?>" placeholder="bijv. ordernr">
            </label>
            <label class="field">
                <span>Filter waarde</span>
                <input type="text" name="filterwaarde" value="<?= h($filterValue) ?>" placeholder="bijv. 36019177">
            </label>
            <?php if ($selectedTable !== ''): ?>
                <input type="hidden" name="tabel" value="<?= h($selectedTable) ?>">
            <?php endif; ?>
            <button type="submit" class="submit-button">Zoeken</button>
        </form>

        <?php if ($columnSearchTerm !== ''): ?>
            <?php if ($columnMatches === [] && $errorMessage === null): ?>
                <p style="margin-top:16px">Geen kolommen gevonden met "<?= h($columnSearchTerm) ?>" in de naam.</p>
            <?php else: ?>
                <p style="margin-top:16px"><?= count($columnMatches) ?> kolom(men) gevonden met "<?= h($columnSearchTerm) ?>" in de naam:</p>
                <table class="db-test-table">
                    <thead><tr><th>Tabel</th><th>Kolomnaam</th><th>Type</th></tr></thead>
                    <tbody>
                        <?php foreach ($columnMatches as $match): ?>
                            <tr>
                                <td>
                                    <a href="?<?= h(http_build_query(['zoek' => $searchTerm, 'kolom' => $columnSearchTerm, 'waarde' => $groupValue, 'filterkolom' => $filterColumn, 'filterwaarde' => $filterValue, 'tabel' => $match['TABLE_NAME']])) ?>">
                                        <?= h((string) $match['TABLE_NAME']) ?>
                                    </a>
                                </td>
                                <td><?= h((string) $match['COLUMN_NAME']) ?></td>
                                <td><?= h((string) $match['DATA_TYPE']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($tables === [] && $errorMessage === null): ?>
            <p style="margin-top:16px">Geen tabellen gevonden met "<?= h($searchTerm) ?>" in de naam.</p>
        <?php elseif ($tables !== []): ?>
            <p style="margin-top:16px"><?= count($tables) ?> tabel(len) gevonden:</p>
            <ul class="db-test-table-list">
                <?php foreach ($tables as $tableName): ?>
                    <li>
                        <a href="?<?= h(http_build_query(['zoek' => $searchTerm, 'kolom' => $columnSearchTerm, 'waarde' => $groupValue, 'filterkolom' => $filterColumn, 'filterwaarde' => $filterValue, 'tabel' => $tableName])) ?>">
                            <?= h($tableName) ?>
                        </a>
                        <?= $tableName === $selectedTable ? ' &larr; geselecteerd' : '' ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <?php if ($selectedTable !== '' && $columns !== []): ?>
        <section class="panel">
            <h2>Kolommen van "<?= h($selectedTable) ?>"</h2>
            <table class="db-test-table">
                <thead><tr><th>Kolomnaam</th><th>Type</th></tr></thead>
                <tbody>
                    <?php foreach ($columns as $column): ?>
                        <tr><td><?= h($column['COLUMN_NAME']) ?></td><td><?= h($column['DATA_TYPE']) ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <?php if ($groupRows !== null && $groupRows !== []): ?>
            <?php foreach ($groupRows as $column => $rows): ?>
                <section class="panel">
                    <h2>Resultaat: [<?= h($column) ?>] = <?= h($groupValue) ?> (max. 20 rijen)</h2>
                    <div class="db-test-scroll">
                        <table class="db-test-table">
                            <thead><tr><?php foreach (array_keys($rows[0]) as $col): ?><th><?= h((string) $col) ?></th><?php endforeach; ?></tr></thead>
                            <tbody>
                                <?php foreach ($rows as $row): ?>
                                    <tr><?php foreach ($row as $value): ?><td><?= h((string) ($value ?? '')) ?></td><?php endforeach; ?></tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endforeach; ?>
        <?php elseif ($groupRows !== null): ?>
            <section class="warning-box">
                Geen kolom met "groep"/"group" in de naam gaf resultaten voor waarde "<?= h($groupValue) ?>".
                Bekijk de kolommenlijst hierboven en zoek zelf de juiste kolom - pas dan de query in
                <code>db-test.php</code> aan, of geef door welke kolom het moet zijn.
            </section>
        <?php endif; ?>

        <?php if ($filterRows !== null): ?>
            <?php if ($filterRows !== []): ?>
                <section class="panel">
                    <h2>Resultaat: [<?= h($filterColumn) ?>] = <?= h($filterValue) ?> (max. 20 rijen)</h2>
                    <div class="db-test-scroll">
                        <table class="db-test-table">
                            <thead><tr><?php foreach (array_keys($filterRows[0]) as $col): ?><th><?= h((string) $col) ?></th><?php endforeach; ?></tr></thead>
                            <tbody>
                                <?php foreach ($filterRows as $row): ?>
                                    <tr><?php foreach ($row as $value): ?><td><?= h((string) ($value ?? '')) ?></td><?php endforeach; ?></tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php else: ?>
                <section class="warning-box">
                    Geen rijen gevonden in "<?= h($selectedTable) ?>" met [<?= h($filterColumn) ?>] = "<?= h($filterValue) ?>".
                </section>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($previewRows !== []): ?>
            <section class="panel">
                <h2>Losse preview: eerste 20 rijen van "<?= h($selectedTable) ?>" (ongefilterd)</h2>
                <div class="db-test-scroll">
                    <table class="db-test-table">
                        <thead><tr><?php foreach (array_keys($previewRows[0]) as $col): ?><th><?= h((string) $col) ?></th><?php endforeach; ?></tr></thead>
                        <tbody>
                            <?php foreach ($previewRows as $row): ?>
                                <tr><?php foreach ($row as $value): ?><td><?= h((string) ($value ?? '')) ?></td><?php endforeach; ?></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endif; ?>
    <?php endif; ?>

    <p class="page-footer">Geeve Hydraulics</p>
</main>
</body>
</html>
