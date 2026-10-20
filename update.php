<?php

declare(strict_types=1);

session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Lax']);

// Eén gedeeld versienummer voor hoofdscherm + alle subapps (version.php op
// rootniveau) - valt terug op deze waarde als dat bestand ontbreekt.
define('APP_VERSION', is_file(__DIR__ . '/version.php') ? (string) require __DIR__ . '/version.php' : '0.2.1');
require_once __DIR__ . '/shared/secure_settings.php';
require_once __DIR__ . '/shared/sneeuw.php';
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
            if ($settingKey === 'TILE_FLAGS' || str_starts_with($settingKey, 'SNEEUW_')) {
                continue; // worden apart opgeslagen (formulieren Tegels en Topbalk)
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

$sneeuwMessage = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save-sneeuw') {
    if (!hash_equals((string) $_SESSION['config_csrf'], (string) ($_POST['csrf'] ?? ''))) {
        $requestError = true;
    } else {
        // Dag en maand apart gekozen; de periode geldt elk jaar.
        $dag = static fn (string $key, string $standaard): string => sneeuwDag(
            sprintf('%02d-%02d', (int) ($_POST[$key . '_MAAND'] ?? 0), (int) ($_POST[$key . '_DAG'] ?? 0)),
            $standaard
        );
        $values = secureSettingsReadStored();
        $values['SNEEUW_AAN'] = isset($_POST['SNEEUW_AAN']) ? 'yes' : 'no';
        $values['SNEEUW_VAN'] = $dag('SNEEUW_VAN', SNEEUW_STANDAARD_VAN);
        $values['SNEEUW_TOT'] = $dag('SNEEUW_TOT', SNEEUW_STANDAARD_TOT);
        $sneeuwMessage = secureSettingsWrite($values)
            ? ['ok' => true, 'text' => 'Topbalk-instellingen opgeslagen.']
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
        $tilesMessage = secureSettingsWrite($values)
            ? ['ok' => true, 'text' => 'Tegel-instellingen opgeslagen.']
            : ['ok' => false, 'text' => 'Opslaan mislukt: ' . secureSettingsLastError()];
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
        <p>De database-inloggegevens (Exact en Slangkaarten) worden beheerd op de server. Hieronder staat alleen wat daarin is ingesteld.</p>
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
            <p class="settings-help">Code voor de beveiligde tegels.</p>
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
        <h2>Topbalk</h2>
        <p>Sneeuw in de topbalk. Staat dit aan en valt vandaag tussen de twee datums, dan sneeuwt het in plaats van de glow; anders is de glow te zien. De periode geldt elk jaar (zonder jaartal) en mag over de jaarwisseling lopen.</p>

        <?php if ($sneeuwMessage !== null): ?>
            <div class="update-message <?= $sneeuwMessage['ok'] ? 'ok' : 'error' ?>"><?= h($sneeuwMessage['text']) ?></div>
        <?php endif; ?>

        <form method="post" class="update-form" autocomplete="off">
            <input type="hidden" name="action" value="save-sneeuw">
            <input type="hidden" name="csrf" value="<?= h((string) $_SESSION['config_csrf']) ?>">
            <?php $sneeuw = sneeuwInstellingen(); $sneeuwMaanden = ['januari', 'februari', 'maart', 'april', 'mei', 'juni', 'juli', 'augustus', 'september', 'oktober', 'november', 'december']; ?>
            <div class="settings-row">
                <label class="settings-switch">
                    <input type="checkbox" name="SNEEUW_AAN" value="yes"<?= $sneeuw['aan'] ? ' checked' : '' ?>>
                    <span class="settings-switch-track"></span>
                    <span>Sneeuw rond de kerst</span>
                </label>
            </div>
            <div class="settings-row">
                <?php foreach (['SNEEUW_VAN' => ['Van', 'van'], 'SNEEUW_TOT' => ['Tot en met', 'tot']] as $veld => [$label, $sleutel]): ?>
                <div class="update-code-field sneeuw-dag">
                    <span><?= h($label) ?></span>
                    <span class="sneeuw-dag-keuze">
                        <select name="<?= h($veld) ?>_DAG" aria-label="<?= h($label) ?>: dag">
                            <?php for ($d = 1; $d <= 31; $d++): ?><option value="<?= $d ?>"<?= (int) substr($sneeuw[$sleutel], 3) === $d ? ' selected' : '' ?>><?= $d ?></option><?php endfor; ?>
                        </select>
                        <select name="<?= h($veld) ?>_MAAND" aria-label="<?= h($label) ?>: maand">
                            <?php foreach ($sneeuwMaanden as $i => $maand): ?><option value="<?= $i + 1 ?>"<?= (int) substr($sneeuw[$sleutel], 0, 2) === $i + 1 ? ' selected' : '' ?>><?= h($maand) ?></option><?php endforeach; ?>
                        </select>
                    </span>
                </div>
                <?php endforeach; ?>
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
            <div class="tiles-table" role="table">
                <div class="tiles-row tiles-head" role="row">
                    <span role="columnheader">Tegel</span>
                    <span role="columnheader">Zichtbaar</span>
                    <span role="columnheader">Beveiligd</span>
                    <span role="columnheader">In progress</span>
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
                        </div>
                    <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="submit" class="update-submit">Opslaan</button>
        </form>
    </section>

    <p class="page-footer">(c) 2026 PdB - Geeve Hydraulics - Versie <?= h(APP_VERSION) ?></p>
</main>
<script src="assets/tiles-sort.js?v=<?= h(assetVersion('assets/tiles-sort.js')) ?>"></script>
</body>
</html>
