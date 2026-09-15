<?php

declare(strict_types=1);

const APP_VERSION = '0.1.1';
const UPDATE_CODE = '1308';

// Fallback-bron als de map op de server geen git-repository is (bijv. de
// map is via FTP gekopieerd zonder de verborgen .git-map mee te nemen).
// Haalt in dat geval de laatste stand rechtstreeks van GitHub op als zip.
const UPDATE_REPO_ZIP_URL = 'https://codeload.github.com/MadPatrick/Geeve_selectors/zip/refs/heads/main';

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

/**
 * @return array{ok: bool, output: string}
 */
function runUpdate(): array
{
    $repoRoot = __DIR__;

    // Een echte git-checkout (bijv. via SSH gekloond) wordt bijgewerkt met
    // git pull. Is er geen .git-map (bijv. de map is via FTP gekopieerd
    // zonder verborgen bestanden), dan is er geen SSH/git nodig - dan wordt
    // de laatste versie gewoon als zip van GitHub gedownload en uitgepakt.
    if (is_dir($repoRoot . '/.git')) {
        return runGitPull($repoRoot);
    }

    return runHttpUpdate($repoRoot);
}

/**
 * @return array{ok: bool, output: string}
 */
function runGitPull(string $repoRoot): array
{
    if (!function_exists('proc_open')) {
        return ['ok' => false, 'output' => 'De functie proc_open is uitgeschakeld op deze server. Vraag de hostingbeheerder dit in te schakelen, of voer "git pull" handmatig uit via SSH.'];
    }

    $descriptorSpec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    // De webserver-gebruiker is vaak niet de eigenaar van de bestanden op de
    // server (bijv. root bij het uitrollen, www-data die PHP draait). Git
    // weigert dan met "detected dubious ownership" - dit vertrouwt expliciet
    // en alleen voor dit ene commando de map waarin dit script zelf staat,
    // zodat er geen handmatige "git config --global" op de server nodig is.
    $process = @proc_open(
        ['git', '-c', 'safe.directory=' . $repoRoot, 'pull'],
        $descriptorSpec,
        $pipes,
        $repoRoot
    );

    if (!is_resource($process)) {
        return ['ok' => false, 'output' => 'Kon het git-commando niet starten op de server.'];
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    $output = trim(($stdout !== false ? $stdout : '') . (($stderr !== false && $stderr !== '') ? "\n" . $stderr : ''));

    return [
        'ok'     => $exitCode === 0,
        'output' => $output !== '' ? $output : '(geen uitvoer)',
    ];
}

/**
 * @return array{ok: bool, output: string}
 */
function runHttpUpdate(string $repoRoot): array
{
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'output' => 'De PHP curl-extensie ontbreekt op deze server. Nodig om de update zonder git te downloaden.'];
    }

    if (!class_exists('ZipArchive')) {
        return ['ok' => false, 'output' => 'De PHP zip-extensie ontbreekt op deze server. Nodig om de gedownloade update uit te pakken.'];
    }

    if (!is_writable($repoRoot)) {
        return ['ok' => false, 'output' => "De map {$repoRoot} is niet schrijfbaar voor de webserver. Vraag de hostingbeheerder om schrijfrechten te geven."];
    }

    @set_time_limit(180);

    $tmpZip = tempnam(sys_get_temp_dir(), 'geeve_update_');
    if ($tmpZip === false) {
        return ['ok' => false, 'output' => 'Kon geen tijdelijk bestand aanmaken op de server.'];
    }

    $download = downloadFile(UPDATE_REPO_ZIP_URL, $tmpZip);
    if (!$download['ok']) {
        @unlink($tmpZip);
        return $download;
    }

    $zip = new ZipArchive();
    if ($zip->open($tmpZip) !== true) {
        @unlink($tmpZip);
        return ['ok' => false, 'output' => 'Het gedownloade bestand kon niet als zip-archief worden geopend.'];
    }

    $tmpExtractDir = $tmpZip . '_uitgepakt';
    if (!@mkdir($tmpExtractDir, 0775, true)) {
        $zip->close();
        @unlink($tmpZip);
        return ['ok' => false, 'output' => 'Kon geen tijdelijke map aanmaken op de server om de update uit te pakken.'];
    }

    $zip->extractTo($tmpExtractDir);
    $zip->close();
    @unlink($tmpZip);

    // GitHub-zips bevatten altijd precies één map op het hoogste niveau,
    // bijv. "Geeve_selectors-main" - de daadwerkelijke inhoud staat daarin.
    $entries = array_values(array_diff((array) @scandir($tmpExtractDir), ['.', '..']));
    if (count($entries) !== 1 || !is_dir($tmpExtractDir . '/' . $entries[0])) {
        removeDirectoryRecursive($tmpExtractDir);
        return ['ok' => false, 'output' => 'Onverwachte inhoud in het gedownloade archief.'];
    }

    $sourceDir = $tmpExtractDir . '/' . $entries[0];
    $copiedCount = copyDirectoryOverwrite($sourceDir, $repoRoot);

    removeDirectoryRecursive($tmpExtractDir);

    return [
        'ok'     => true,
        'output' => "Geen git gevonden op de server - update rechtstreeks van GitHub gedownload en uitgepakt.\n{$copiedCount} bestand(en) bijgewerkt.\n\nLet op: bestanden die op GitHub zijn verwijderd, worden op deze manier niet automatisch van de server verwijderd.",
    ];
}

/**
 * @return array{ok: bool, output: string}
 */
function downloadFile(string $url, string $destination): array
{
    $fp = fopen($destination, 'wb');
    if ($fp === false) {
        return ['ok' => false, 'output' => 'Kon het tijdelijke downloadbestand niet openen voor schrijven.'];
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_FILE           => $fp,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_CONNECTTIMEOUT => 20,
        CURLOPT_TIMEOUT        => 150,
        CURLOPT_USERAGENT      => 'Geeve-Hydraulics-Update',
        CURLOPT_FAILONERROR    => true,
    ]);

    $success = curl_exec($ch);
    $error = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    fclose($fp);

    if ($success !== true) {
        @unlink($destination);
        return ['ok' => false, 'output' => "Download van de update is mislukt: {$error} (HTTP {$httpCode})."];
    }

    return ['ok' => true, 'output' => ''];
}

function copyDirectoryOverwrite(string $source, string $destination): int
{
    $count = 0;
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($items as $item) {
        $relative = substr((string) $item->getPathname(), strlen($source) + 1);
        $target = $destination . '/' . $relative;

        if ($item->isDir()) {
            if (!is_dir($target)) {
                @mkdir($target, 0775, true);
            }
            continue;
        }

        if (@copy((string) $item->getPathname(), $target)) {
            $count++;
        }
    }

    return $count;
}

function removeDirectoryRecursive(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($items as $item) {
        if ($item->isDir()) {
            @rmdir((string) $item->getPathname());
        } else {
            @unlink((string) $item->getPathname());
        }
    }

    @rmdir($dir);
}

$result = null;
$codeError = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedCode = (string) ($_POST['code'] ?? '');

    if (!hash_equals(UPDATE_CODE, $submittedCode)) {
        $codeError = true;
    } else {
        $result = runUpdate();
    }
}
?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Applicatie bijwerken | Geeve Hydraulics</title>
    <link rel="stylesheet" href="assets/style.css?v=<?= h(assetVersion('assets/style.css')) ?>">
</head>
<body>
<main class="page-shell">
    <header class="page-header">
        <div class="brand-panel">
            <div class="brand-copy">
                <div class="brand-logo-row">
                    <img src="images/geeve.jpg" alt="Geeve Hydraulics - know how in hydraulics" class="brand-logo-img">
                    <img src="images/rubix.jpg" alt="Powered by Rubix" class="brand-rubix-img">
                </div>
            </div>
            <div class="header-content">
                <h1>Applicatie bijwerken</h1>
            </div>
        </div>
    </header>

    <a href="index.php" class="back-link">&larr; Terug naar hoofdmenu</a>

    <section class="update-panel">
        <h2>Update ophalen</h2>
        <p>Haalt de laatste wijzigingen op (via <code>git pull</code> als de server een git-checkout is, anders rechtstreeks als download van GitHub) en werkt alle selectors op de server in &eacute;&eacute;n keer bij. Voer de 4-cijferige code in om te bevestigen. Dit kan bij een download-update even duren.</p>

        <?php if ($result !== null): ?>
            <div class="update-message <?= $result['ok'] ? 'ok' : 'error' ?>">
                <?= $result['ok'] ? 'Update voltooid.' : 'Update mislukt.' ?>
            </div>
            <pre class="update-output"><?= h($result['output']) ?></pre>
        <?php elseif ($codeError): ?>
            <div class="update-message error">Onjuiste code. Update is niet uitgevoerd.</div>
        <?php endif; ?>

        <form method="post" class="update-form">
            <label class="update-code-field" for="updateCode">
                <span>Code</span>
                <input id="updateCode" type="password" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" name="code" placeholder="&bull;&bull;&bull;&bull;" autocomplete="off" required>
            </label>
            <button type="submit" class="update-submit">Update uitvoeren</button>
        </form>
    </section>

    <p class="page-footer">Geeve Hydraulics</p>
</main>
</body>
</html>
