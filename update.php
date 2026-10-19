<?php

declare(strict_types=1);

session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Lax']);

// Eén gedeeld versienummer voor hoofdscherm + alle subapps (version.php op
// rootniveau) - valt terug op deze waarde als dat bestand ontbreekt.
define('APP_VERSION', is_file(__DIR__ . '/version.php') ? (string) require __DIR__ . '/version.php' : '0.2.1');
require_once __DIR__ . '/shared/secure_settings.php';
require_once __DIR__ . '/shared/tiles.php';
require_once __DIR__ . '/shared/printen.php';

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

$requestError = false;
$settingsMessage = null;
$dbFromFile = secureSettingsFileValues() !== null;

if (empty($_SESSION['config_csrf'])) {
    $_SESSION['config_csrf'] = bin2hex(random_bytes(16));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save-settings') {
    if (!hash_equals((string) $_SESSION['config_csrf'], (string) ($_POST['csrf'] ?? ''))) {
        $requestError = true;
    } else {
        $values = secureSettingsReadStored();
        foreach (SECURE_SETTINGS_KEYS as $settingKey) {
            if ($settingKey === 'TILE_FLAGS') {
                continue; // wordt apart opgeslagen (formulier Tegels)
            }
            if ($dbFromFile && in_array($settingKey, SECURE_SETTINGS_FILE_KEYS, true)) {
                continue; // beheerd op de server (/etc/geeve/portal.json)
            }
            $posted = trim((string) ($_POST[$settingKey] ?? ''));
            if ($settingKey === 'EXACT_DB_TRUST_SERVER_CERT') {
                $posted = $posted === 'yes' ? 'yes' : 'no';
            }
            // Wachtwoord leeg laten = ongewijzigd laten.
            if (in_array($settingKey, ['EXACT_DB_PASSWORD', 'DB_PASSWORD', 'ACCESS_CODE'], true) && $posted === '') {
                continue;
            }
            if ($posted === '') {
                unset($values[$settingKey]);
            } else {
                $values[$settingKey] = $posted;
            }
        }
        $settingsMessage = secureSettingsWrite($values)
            ? ['ok' => true, 'text' => 'Instellingen versleuteld opgeslagen.']
            : ['ok' => false, 'text' => 'Opslaan mislukt: ' . secureSettingsLastError()];
    }
}

$tilesMessage = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save-tiles') {
    if (!hash_equals((string) $_SESSION['config_csrf'], (string) ($_POST['csrf'] ?? ''))) {
        $requestError = true;
    } else {
        $flags = [];
        foreach (array_keys(TILE_REGISTRY) as $tileId) {
            $row = $_POST['tile'][$tileId] ?? [];
            $flags[$tileId] = [
                'visible' => isset($row['visible']),
                'locked' => isset($row['locked']),
                'wip' => isset($row['wip']),
                // Eigen naam; leeg of gelijk aan de standaardnaam = standaard gebruiken.
                'title' => ($t = tileCleanTitle((string) ($row['title'] ?? ''))) !== TILE_REGISTRY[$tileId][1] ? $t : '',
            ];
        }
        // Volgorde (slepen): per groep de id's in de volgorde van de formulier-rijen; onbekende id's worden genegeerd.
        $flags['_order'] = [];
        foreach (['home', 'reports', 'templates', 'stickers'] as $orderGroup) {
            $posted = $_POST['order'][$orderGroup] ?? [];
            $flags['_order'][$orderGroup] = is_array($posted)
                ? array_values(array_filter(array_map('strval', $posted), static fn ($id) => isset(TILE_REGISTRY[$id]) && TILE_REGISTRY[$id][0] === $orderGroup))
                : [];
        }
        $values = secureSettingsReadStored();
        $values['TILE_FLAGS'] = (string) json_encode($flags);
        // Printers per tegel (alleen tegels met direct printen): printers.json bewaart per printer de tegels.
        $tilesPerQueue = [];
        foreach (array_keys(geevePrinterList()) as $queue) {
            $tilesPerQueue[$queue] = [];
            foreach (array_keys(GEEVE_PRINT_TILES) as $printTile) {
                if (in_array($queue, array_map('strval', (array) ($_POST['printers'][$printTile] ?? [])), true)) {
                    $tilesPerQueue[$queue][] = $printTile;
                }
            }
        }
        $printersSaved = geevePrinterList() === [] || geevePrinterTilesSave($tilesPerQueue);
        $tilesMessage = secureSettingsWrite($values) && $printersSaved
            ? ['ok' => true, 'text' => 'Tegel-instellingen opgeslagen.']
            : ['ok' => false, 'text' => 'Opslaan mislukt: ' . secureSettingsLastError()];
    }
}

$printersMessage = null;
$editQueue = (string) ($_GET['wijzig'] ?? '');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && str_starts_with((string) ($_POST['action'] ?? ''), 'printer-')) {
    if (!hash_equals((string) $_SESSION['config_csrf'], (string) ($_POST['csrf'] ?? ''))) {
        $requestError = true;
    } else {
        $printerAction = (string) $_POST['action'];
        $printerQueue = (string) ($_POST['queue'] ?? '');
        if ($printerAction === 'printer-add') {
            // Een nieuwe printer staat eerst in geen enkele tegel; kies hem bij Tegels.
            [$ok, $text] = geeveAddPrinter((string) ($_POST['new_name'] ?? ''), (string) ($_POST['new_host'] ?? ''), (string) ($_POST['new_color'] ?? 'auto'), [], (string) ($_POST['new_soort'] ?? 'a4'));
        } elseif ($printerAction === 'printer-edit') {
            [$ok, $text] = geeveUpdatePrinter($printerQueue, (string) ($_POST['edit_name'] ?? ''), (string) ($_POST['edit_host'] ?? ''), (string) ($_POST['edit_color'] ?? 'auto'));
            if (!$ok) {
                $editQueue = $printerQueue; // formulier open laten met de melding
            }
        } elseif ($printerAction === 'printer-remove') {
            [$ok, $text] = geeveRemovePrinter($printerQueue);
        } elseif ($printerAction === 'printer-test') {
            try {
                $job = geevePrintTestPage($printerQueue);
                [$ok, $text] = [true, 'Testpagina verstuurd (opdracht ' . $job . ').'];
            } catch (RuntimeException $e) {
                [$ok, $text] = [false, $e->getMessage()];
            }
        } else {
            [$ok, $text] = [false, 'Onbekende actie.'];
        }
        $printersMessage = ['ok' => $ok, 'text' => $text];
    }
}

$storedSettings = secureSettingsRead();
?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Config | Geeve Hydraulics</title>
    <link rel="icon" href="favicon.ico?v=<?= h(assetVersion('favicon.ico')) ?>" type="image/x-icon">
    <link rel="stylesheet" href="shared/style.css?v=<?= h(assetVersion('shared/style.css')) ?>">
    <link rel="stylesheet" href="assets/style.css?v=<?= h(assetVersion('assets/style.css')) ?>">
</head>
<body>
<main class="page-shell">
    <?php
    $headerTitle = 'Config';
    $headerImagesPath = 'images/';
    $headerShowHome = false;
    require __DIR__ . '/shared/header.php';
    ?>

    <div class="config-bar">
        <a href="index.php" class="back-link">&larr; Terug naar hoofdmenu</a>
    </div>

    <?php if ($requestError): ?>
        <div class="update-message error">Ongeldig verzoek (sessie verlopen). Laad de pagina opnieuw en probeer het nog eens.</div>
    <?php endif; ?>

    <section class="update-panel">
        <h2>Database-instellingen</h2>
        <?php if ($dbFromFile): ?>
        <p>De database-inloggegevens (Exact en Slangkaarten) worden beheerd op de server, in <code><?= h(secureSettingsFilePath()) ?></code>, buiten de webmap. Ze zijn hier niet in te stellen; wijzigen kan alleen op de server (bestand van root, daarna geen herstart nodig). Hieronder staat alleen wat daarin is ingesteld.</p>
        <?php else: ?>
        <p>Inloggegevens voor de Exact-database (gedeeld door Stauff, Klant Artikelnummers, Kortingstructuur, MSP report en Slangkaarten) en voor de eigen Slangkaarten-database. Ze worden versleuteld opgeslagen, niet als leesbaar bestand. Een <code>.env</code>-bestand wordt niet meer gelezen en mag worden verwijderd. Wachtwoord leeg laten = ongewijzigd.</p>
        <?php endif; ?>

        <?php if ($settingsMessage !== null): ?>
            <div class="update-message <?= $settingsMessage['ok'] ? 'ok' : 'error' ?>"><?= h($settingsMessage['text']) ?></div>
        <?php endif; ?>

        <form method="post" class="update-form settings-form" autocomplete="off">
            <input type="hidden" name="action" value="save-settings">
            <input type="hidden" name="csrf" value="<?= h((string) $_SESSION['config_csrf']) ?>">
            <?php
            $val = static fn (string $k): string => $storedSettings[$k] ?? '';
            $trustOn = strtolower($val('EXACT_DB_TRUST_SERVER_CERT') ?: 'yes') !== 'no';
            $hasPassword = isset($storedSettings['EXACT_DB_PASSWORD']);
            $hasSlangPassword = isset($storedSettings['DB_PASSWORD']);
            ?>
            <div class="settings-cols">
                <div class="settings-col">
            <?php if ($dbFromFile): $fileValues = secureSettingsFileValues(); ?>
            <h3 class="settings-subtitle">Databases</h3>
            <ul class="settings-help">
                <li>Exact-database: <?= h(($fileValues['EXACT_DB_HOST'] ?? 'GEEVE-SQL-2019') . (isset($fileValues['EXACT_DB_PORT']) ? ':' . $fileValues['EXACT_DB_PORT'] : '') . ' / ' . ($fileValues['EXACT_DB_NAME'] ?? '005') . ', gebruiker ' . ($fileValues['EXACT_DB_USER'] ?? '(niet ingesteld)') . ', wachtwoord ' . (isset($fileValues['EXACT_DB_PASSWORD']) ? 'ingesteld' : 'ontbreekt')) ?></li>
                <li>Slangkaarten-database: gebruiker <?= h($fileValues['DB_USER'] ?? '(niet ingesteld)') ?>, wachtwoord <?= isset($fileValues['DB_PASSWORD']) ? 'ingesteld' : 'ontbreekt' ?></li>
            </ul>
            <?php else: ?>
            <h3 class="settings-subtitle">Exact-database</h3>
            <p class="settings-help">Server, poort en certificaat gelden ook voor de Slangkaarten-database.</p>
            <div class="settings-row">
                <label class="update-code-field">
                    <span>Server</span>
                    <input type="text" name="EXACT_DB_HOST" value="<?= h($val('EXACT_DB_HOST')) ?>" placeholder="GEEVE-SQL-2019" size="20" autocomplete="off">
                </label>
                <label class="update-code-field">
                    <span>Poort</span>
                    <input type="text" name="EXACT_DB_PORT" value="<?= h($val('EXACT_DB_PORT')) ?>" size="6" autocomplete="off">
                </label>
            </div>
            <div class="settings-row">
                <label class="update-code-field">
                    <span>Database</span>
                    <input type="text" name="EXACT_DB_NAME" value="<?= h($val('EXACT_DB_NAME')) ?>" placeholder="005" size="20" autocomplete="off">
                </label>
            </div>
            <div class="settings-row">
                <label class="update-code-field">
                    <span>Gebruiker</span>
                    <input type="text" name="EXACT_DB_USER" value="<?= h($val('EXACT_DB_USER')) ?>" size="20" autocomplete="off">
                </label>
                <label class="update-code-field">
                    <span>Wachtwoord</span>
                    <input type="password" name="EXACT_DB_PASSWORD" value="" placeholder="<?= $hasPassword ? 'Opgeslagen' : '' ?>" size="20" autocomplete="new-password">
                </label>
            </div>
            <div class="settings-row">
                <label class="settings-switch">
                    <input type="checkbox" name="EXACT_DB_TRUST_SERVER_CERT" value="yes"<?= $trustOn ? ' checked' : '' ?>>
                    <span class="settings-switch-track"></span>
                    <span>Server-certificaat vertrouwen</span>
                </label>
            </div>
            <?php endif; ?>
                </div>
                <div class="settings-col">
            <?php if (!$dbFromFile): ?>
            <h3 class="settings-subtitle">Slangkaarten-database</h3>
            <p class="settings-help">Eigen account voor de database <code>Slangkaarten</code> (slangkaarten, orders en kaartgegevens). Server, poort en certificaat zijn dezelfde als hierboven.</p>
            <div class="settings-row">
                <label class="update-code-field">
                    <span>Gebruiker</span>
                    <input type="text" name="DB_USER" value="<?= h($val('DB_USER')) ?>" size="20" autocomplete="off">
                </label>
                <label class="update-code-field">
                    <span>Wachtwoord</span>
                    <input type="password" name="DB_PASSWORD" value="" placeholder="<?= $hasSlangPassword ? 'Opgeslagen' : '' ?>" size="20" autocomplete="new-password">
                </label>
            </div>
            <?php endif; ?>
            <h3 class="settings-subtitle">Toegangscode</h3>
            <p class="settings-help">Code voor de beveiligde tegels (zie Tegels hieronder; standaard alleen <strong>Productiviteit</strong>). Leeg laten = ongewijzigd.</p>
            <div class="settings-row">
                <label class="update-code-field">
                    <span>Code</span>
                    <input type="password" name="ACCESS_CODE" value="" placeholder="Opgeslagen" size="20" autocomplete="new-password">
                </label>
            </div>
                </div>
            </div>
            <button type="submit" class="update-submit">Opslaan</button>
        </form>
    </section>

    <section class="update-panel">
        <h2>Tegels</h2>
        <p>Alle tegels van het hoofdscherm en van Reports. <strong>Zichtbaar</strong>: uit = de tegel staat niet op het overzicht. <strong>Beveiligd</strong>: een klik op de tegel vraagt om de toegangscode (de pagina is dan ook niet rechtstreeks te openen). <strong>In progress</strong>: de tegel krijgt de stempel "IN PROGRESS". Pas de naam aan in het tekstveld (leeg laten = standaardnaam). Sleep een tegel aan het blokje links om de volgorde te wijzigen (binnen Hoofdscherm of binnen Reports) en klik op Opslaan.</p>

        <?php if ($tilesMessage !== null): ?>
            <div class="update-message <?= $tilesMessage['ok'] ? 'ok' : 'error' ?>"><?= h($tilesMessage['text']) ?></div>
        <?php endif; ?>

        <form method="post" class="update-form tiles-form" autocomplete="off">
            <input type="hidden" name="action" value="save-tiles">
            <input type="hidden" name="csrf" value="<?= h((string) $_SESSION['config_csrf']) ?>">
            <?php $tilePrinters = geevePrinterList(); ?>
            <div class="tiles-table" role="table" style="--printer-cols: <?= count($tilePrinters) ?>">
                <div class="tiles-row tiles-head tiles-supergroup" role="row">
                    <span></span>
                    <span class="tiles-supergroup-label" style="grid-column: span 3">Dashboard</span>
                    <?php if ($tilePrinters !== []): ?>
                    <span class="tiles-supergroup-label tiles-sep" style="grid-column: span <?= count($tilePrinters) ?>">Printers</span>
                    <?php endif; ?>
                </div>
                <div class="tiles-row tiles-head" role="row">
                    <span role="columnheader">Tegel</span>
                    <span role="columnheader">Zichtbaar</span>
                    <span role="columnheader">Beveiligd</span>
                    <span role="columnheader">In progress</span>
                    <?php foreach (array_keys($tilePrinters) as $printerIndex => $queue): $printer = $tilePrinters[$queue]; ?>
                        <span role="columnheader" class="tiles-printer-head<?= $printerIndex === 0 ? ' tiles-sep' : '' ?>" title="<?= h($printer['name']) ?>"><?= h(trim(strtok($printer['name'], '('))) ?></span>
                    <?php endforeach; ?>
                </div>
                <?php
                $tileGroups = ['home' => 'Hoofdscherm', 'reports' => 'Reports', 'templates' => 'Templates', 'stickers' => 'Stickers (tegels op het Stickers-scherm)'];
                $tileFlags = tileFlagsAll();
                foreach ($tileGroups as $groupId => $groupTitle):
                ?>
                    <div class="tiles-group" role="row"><?= h($groupTitle) ?></div>
                    <div class="tiles-list" data-group="<?= h($groupId) ?>">
                    <?php foreach (tileOrder($groupId) as $tileId): $tile = TILE_REGISTRY[$tileId]; ?>
                        <div class="tiles-row" role="row" data-id="<?= h($tileId) ?>">
                            <span role="cell" class="tiles-name">
                                <span class="tiles-handle" title="Sleep om de volgorde te wijzigen" aria-label="Sleep om de volgorde te wijzigen">&#8942;&#8942;</span>
                                <input type="text" class="tiles-title" name="tile[<?= h($tileId) ?>][title]" value="<?= h($tileFlags[$tileId]['title']) ?>" placeholder="<?= h($tile[1]) ?>" maxlength="30" aria-label="Naam van de tegel (<?= h($tile[1]) ?>)" autocomplete="off">
                                <input type="hidden" name="order[<?= h($groupId) ?>][]" value="<?= h($tileId) ?>">
                            </span>
                            <?php foreach (['visible' => 'Zichtbaar', 'locked' => 'Beveiligd', 'wip' => 'In progress'] as $flag => $flagLabel): ?>
                                <label role="cell" class="settings-switch tiles-switch" title="<?= h($tile[1] . ': ' . $flagLabel) ?>">
                                    <input type="checkbox" name="tile[<?= h($tileId) ?>][<?= h($flag) ?>]" value="1"<?= $tileFlags[$tileId][$flag] ? ' checked' : '' ?>>
                                    <span class="settings-switch-track"></span>
                                </label>
                            <?php endforeach; ?>
                            <?php $printerIndex = 0; foreach ($tilePrinters as $queue => $printer): $sepClass = $printerIndex++ === 0 ? ' tiles-sep' : ''; ?>
                                <?php if (!isset(GEEVE_PRINT_TILES[$tileId]) || !geevePrinterPastBijTegel($printer, $tileId)): ?>
                                <span role="cell" class="tiles-check<?= $sepClass ?>"></span>
                                <?php continue; endif; ?>
                                <label role="cell" class="tiles-check<?= $sepClass ?>" title="<?= h($tile[1] . ': ' . $printer['name']) ?>">
                                    <input type="checkbox" name="printers[<?= h($tileId) ?>][]" value="<?= h($queue) ?>"<?= in_array($tileId, $printer['tiles'] ?? [], true) ? ' checked' : '' ?>>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="submit" class="update-submit">Opslaan</button>
        </form>
    </section>

    <section class="update-panel" id="printers">
        <h2>Printers</h2>
        <p>Printers waarop de portal direct kan printen (zonder printvenster van de browser). Een nieuwe printer vul je in met naam en IP-adres of netwerknaam: de portal controleert de verbinding, herkent kleur of zwart-wit en maakt de wachtrij zelf aan. Per tegel kies je bij Tegels (hierboven) welke printers daar beschikbaar zijn.</p>

        <?php if ($printersMessage !== null): ?>
            <div class="update-message <?= $printersMessage['ok'] ? 'ok' : 'error' ?>"><?= h($printersMessage['text']) ?></div>
        <?php endif; ?>

        <?php $printerList = geevePrinterList(); ?>
        <?php if ($printerList !== []): ?>
        <div class="printers-form">
            <div class="printers-table" role="table" style="--printer-tiles: <?= count(GEEVE_PRINT_TILES) ?>">
                <div class="printers-row printers-head" role="row">
                    <span role="columnheader">Printer</span>
                    <span role="columnheader">Adres</span>
                    <span role="columnheader">Soort</span>
                    <span role="columnheader"></span>
                </div>
                <?php foreach ($printerList as $queue => $printer): ?>
                    <div class="printers-row" role="row">
                        <span role="cell"><?= h($printer['name']) ?></span>
                        <span role="cell"><?= h($printer['host']) ?></span>
                        <span role="cell"><?= geevePrinterSoort($printer) === 'label' ? 'Label' : ($printer['color'] ? 'Kleur' : 'Zwart-wit') ?></span>
                        <span role="cell" class="printers-actions">
                            <a class="update-submit printers-edit-link" href="?wijzig=<?= h(rawurlencode($queue)) ?>#printers">Wijzigen</a>
                            <button type="submit" form="printer-test-<?= h($queue) ?>" class="update-submit">Testpagina</button>
                            <button type="submit" form="printer-remove-<?= h($queue) ?>" class="update-submit" onclick="return confirm('Printer verwijderen?');">Verwijderen</button>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php foreach ($printerList as $queue => $printer): ?>
            <?php foreach (['test', 'remove'] as $printerButton): ?>
                <form method="post" id="printer-<?= h($printerButton) ?>-<?= h($queue) ?>" hidden>
                    <input type="hidden" name="action" value="printer-<?= h($printerButton) ?>">
                    <input type="hidden" name="csrf" value="<?= h((string) $_SESSION['config_csrf']) ?>">
                    <input type="hidden" name="queue" value="<?= h($queue) ?>">
                </form>
            <?php endforeach; ?>
        <?php endforeach; ?>
        <?php else: ?>
            <p>Er zijn nog geen printers.</p>
        <?php endif; ?>

        <?php if ($editQueue !== '' && isset($printerList[$editQueue])): $editPrinter = $printerList[$editQueue]; ?>
        <h3 class="printers-add-title">Printer wijzigen: <?= h($editPrinter['name']) ?></h3>
        <form method="post" class="update-form printers-add" autocomplete="off">
            <input type="hidden" name="action" value="printer-edit">
            <input type="hidden" name="csrf" value="<?= h((string) $_SESSION['config_csrf']) ?>">
            <input type="hidden" name="queue" value="<?= h($editQueue) ?>">
            <label class="update-code-field"><span>Naam</span>
                <input type="text" name="edit_name" maxlength="60" value="<?= h($editPrinter['name']) ?>" required>
            </label>
            <label class="update-code-field"><span>IP-adres of netwerknaam</span>
                <input type="text" name="edit_host" maxlength="64" value="<?= h($editPrinter['host']) ?>" required>
            </label>
            <?php if (geevePrinterSoort($editPrinter) !== 'label'): ?>
            <label class="update-code-field"><span>Kleur</span>
                <select name="edit_color">
                    <option value="auto">Ongewijzigd (<?= $editPrinter['color'] ? 'kleur' : 'zwart-wit' ?>)</option>
                    <option value="kleur">Kleur</option>
                    <option value="zwartwit">Zwart-wit</option>
                </select>
            </label>
            <?php endif; ?>
            <button type="submit" class="update-submit">Opslaan</button>
            <a class="update-submit printers-edit-link" href="update.php#printers">Annuleren</a>
        </form>
        <?php endif; ?>

        <h3 class="printers-add-title">Printer toevoegen</h3>
        <form method="post" class="update-form printers-add" autocomplete="off">
            <input type="hidden" name="action" value="printer-add">
            <input type="hidden" name="csrf" value="<?= h((string) $_SESSION['config_csrf']) ?>">
            <label class="update-code-field"><span>Naam</span>
                <input type="text" name="new_name" maxlength="60" placeholder="Bijvoorbeeld Balie" required>
            </label>
            <label class="update-code-field"><span>IP-adres of netwerknaam</span>
                <input type="text" name="new_host" maxlength="64" placeholder="10.209.192.185" required>
            </label>
            <label class="update-code-field"><span>Soort</span>
                <select name="new_soort" id="newSoort">
                    <option value="a4">Gewone printer (A4)</option>
                    <option value="label">Labelprinter (TSPL, rechtstreeks)</option>
                </select>
            </label>
            <label class="update-code-field" data-soort="a4"><span>Kleur</span>
                <select name="new_color">
                    <option value="auto">Automatisch herkennen</option>
                    <option value="kleur">Kleur</option>
                    <option value="zwartwit">Zwart-wit</option>
                </select>
            </label>
            <button type="submit" class="update-submit">Toevoegen</button>
        </form>
    </section>

    <p class="page-footer">(c) 2026 PdB - Geeve Hydraulics - Versie <?= h(APP_VERSION) ?></p>
</main>
<script src="assets/tiles-sort.js?v=<?= h(assetVersion('assets/tiles-sort.js')) ?>"></script>
<script>
(function () {
    var soort = document.getElementById('newSoort');
    if (!soort) { return; }
    function toggle() {
        Array.prototype.forEach.call(document.querySelectorAll('[data-soort]'), function (el) {
            el.hidden = el.getAttribute('data-soort') !== soort.value;
        });
    }
    soort.addEventListener('change', toggle);
    toggle();
}());
</script>
</body>
</html>
