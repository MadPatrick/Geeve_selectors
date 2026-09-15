<?php

declare(strict_types=1);

const APP_VERSION = '0.1.1';
const UPDATE_CODE = '1308';

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
function runGitPull(): array
{
    $repoRoot = __DIR__;

    if (!is_dir($repoRoot . '/.git')) {
        return ['ok' => false, 'output' => 'Geen git-repository gevonden op de server (map .git ontbreekt).'];
    }

    if (!function_exists('proc_open')) {
        return ['ok' => false, 'output' => 'De functie proc_open is uitgeschakeld op deze server. Vraag de hostingbeheerder dit in te schakelen, of voer "git pull" handmatig uit via SSH.'];
    }

    $descriptorSpec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = @proc_open(['git', 'pull'], $descriptorSpec, $pipes, $repoRoot);

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

$result = null;
$codeError = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedCode = (string) ($_POST['code'] ?? '');

    if (!hash_equals(UPDATE_CODE, $submittedCode)) {
        $codeError = true;
    } else {
        $result = runGitPull();
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
        <p>Haalt de laatste wijzigingen op uit git (<code>git pull</code>) en werkt alle selectors op de server in &eacute;&eacute;n keer bij. Voer de 4-cijferige code in om te bevestigen.</p>

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
