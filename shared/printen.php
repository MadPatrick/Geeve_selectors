<?php

declare(strict_types=1);

/**
 * Direct printen vanaf de server (zonder het printvenster van de browser).
 *
 * De printers zijn CUPS-wachtrijen op de server (lpadmin, socket://<ip>:9100). Een app bouwt dezelfde HTML als voor
 * de browserprint; geevePrintHtml() maakt daar met headless Chromium een PDF van (zelfde print-CSS, @page A4) en geeft
 * die met lp aan de gekozen wachtrij. Zie shared/README.md, "Direct printen".
 */

require_once __DIR__ . '/paths.php';

/** CUPS-wachtrij => naam in de keuzelijst. Alleen wachtrijen die echt op de server bestaan, worden getoond. */
const GEEVE_PRINTERS = [
    'geeve_binnendienst_l1' => 'Binnendienst (RTMMFBINNENDIENST01, lade 1)',
    'geeve_sb_l1' => 'SB (RTMMFSB01, lade 1)',
    'geeve_balie' => 'Balie (RTMPRbalie01)',
];

/** De printers uit GEEVE_PRINTERS die CUPS kent, als [wachtrij => naam]. Leeg als CUPS ontbreekt. */
function geevePrinters(): array
{
    static $printers = null;
    if ($printers !== null) {
        return $printers;
    }
    $printers = [];
    $result = geeveRun(['/usr/bin/lpstat', '-e'], 5);
    if ($result['code'] !== 0) {
        return $printers;
    }
    $queues = preg_split('/\s+/', trim($result['out'])) ?: [];
    foreach (GEEVE_PRINTERS as $queue => $label) {
        if (in_array($queue, $queues, true)) {
            $printers[$queue] = $label;
        }
    }
    return $printers;
}

/**
 * Print een HTML-fragment (de inhoud van #printSheet) op een CUPS-wachtrij. $cssFiles zijn paden vanaf de
 * portal-root, in dezelfde volgorde als in de <head> van de app. Geeft het CUPS-jobnummer terug; gooit een
 * RuntimeException met een leesbare melding als het misgaat.
 *
 * $passend: optioneel [selector, maximale hoogte in mm]. Elk element dat in de print hoger is dan die hoogte, wordt
 * met CSS-zoom precies zo veel verkleind dat het op één pagina past (bijv. een slangkaart met een lange notitie).
 */
function geevePrintHtml(string $bodyHtml, array $cssFiles, string $queue, string $title, ?array $passend = null): string
{
    if (!array_key_exists($queue, geevePrinters())) {
        throw new RuntimeException('Onbekende printer: ' . $queue);
    }
    $chromium = geeveChromiumBinary();
    if ($chromium === null) {
        throw new RuntimeException('Chromium staat niet op de server, direct printen kan daardoor niet.');
    }

    $workDir = rtrim(sys_get_temp_dir(), '/') . '/geeve-print-' . bin2hex(random_bytes(6));
    if (!@mkdir($workDir, 0700)) {
        throw new RuntimeException('Kon geen tijdelijke map maken voor de print.');
    }
    $htmlFile = $workDir . '/print.html';
    $pdfFile = $workDir . '/print.pdf';

    try {
        $links = '';
        $printRegels = '';
        foreach (array_merge(['shared/style.css'], $cssFiles) as $css) {
            $path = geevePortalDir() . '/' . ltrim($css, '/');
            $links .= '<link rel="stylesheet" href="file://' . htmlspecialchars($path, ENT_QUOTES) . '">' . "\n";
            $printRegels .= geeveMediaPrintBlocks((string) @file_get_contents($path));
        }
        // De Canons drukken op de rechterrand net iets minder af dan de PDF breed is (ook met fit-to-page vielen de
        // kaderlijnen rechts weg). Bij direct printen daarom rechts 17 mm marge i.p.v. 12 mm; de browserprint blijft gelijk.
        $links .= "<style>@page { margin-right: 17mm; }</style>\n";
        $passendScript = '';
        if ($passend !== null) {
            // Meten kan alleen op "scherm"; daarom gelden de printregels hier ook op scherm, bij de printbreedte
            // (venster = A4 min de marges). Het script zet daarna per te hoog element een zoom.
            $links .= "<style>@media screen {\n" . $printRegels . "\nhtml, body { margin: 0; padding: 0; background: none; }\n}</style>\n";
            $passendScript = '<script>(function () {'
                . 'var max = ' . (float) $passend[1] . ' * 96 / 25.4;'
                . 'document.querySelectorAll(' . json_encode((string) $passend[0]) . ').forEach(function (el) {'
                . 'var hoogte = el.scrollHeight;'
                . 'if (hoogte > max + 1) { el.style.zoom = Math.max(0.6, (max - 2) / hoogte).toFixed(3); }'
                . '});'
                . '})();</script>';
        }
        // Alleen het printblad, zonder scripts: Chromium rendert met print-CSS, dus #printSheet wordt zichtbaar en
        // .page-shell bestaat niet.
        $bodyHtml = preg_replace('#<script\b.*?</script>#is', '', $bodyHtml) ?? '';
        $document = "<!doctype html>\n<html lang=\"nl\"><head><meta charset=\"utf-8\"><title>"
            . htmlspecialchars($title, ENT_QUOTES) . "</title>\n" . $links . '</head><body>'
            . '<div id="printSheet">' . $bodyHtml . '</div>' . $passendScript . '</body></html>';
        if (file_put_contents($htmlFile, $document) === false) {
            throw new RuntimeException('Kon het printbestand niet wegschrijven.');
        }

        $render = geeveRun([
            $chromium,
            '--headless',
            '--no-sandbox',
            '--disable-gpu',
            '--disable-extensions',
            '--no-pdf-header-footer',
            '--print-to-pdf-no-header',
            '--window-size=684,1100', // printbreedte: A4 (210 mm) min 12 mm links en 17 mm rechts = 181 mm = 684 px
            '--user-data-dir=' . $workDir . '/profiel',
            '--print-to-pdf=' . $pdfFile,
            'file://' . $htmlFile,
        ], 60, ['HOME' => $workDir]);
        if (!is_file($pdfFile) || filesize($pdfFile) === 0) {
            throw new RuntimeException('Chromium kon geen PDF maken: ' . geeveLastLine($render['err']));
        }

        // fit-to-page: CUPS schaalt de A4 een fractie naar het printbare gebied van de printer (zoals de Windows-driver
        // bij de browserprint doet); zonder viel aan de rechterkant net iets weg op de Canons.
        $print = geeveRun(['/usr/bin/lp', '-d', $queue, '-t', $title, '-o', 'fit-to-page', $pdfFile], 20);
        if ($print['code'] !== 0) {
            throw new RuntimeException('De printer weigerde de opdracht: ' . geeveLastLine($print['err'] . $print['out']));
        }
        // "request id is geeve_binnendienst_l1-12 (1 file(s))"
        return preg_match('/request id is (\S+)/', $print['out'], $match) === 1 ? $match[1] : trim($print['out']);
    } finally {
        geeveRemoveDir($workDir);
    }
}

/** De inhoud van alle "@media print { ... }"-blokken in een stylesheet (zonder de omhulling). */
function geeveMediaPrintBlocks(string $css): string
{
    $result = '';
    $offset = 0;
    while (preg_match('/@media\s+print\s*\{/', $css, $match, PREG_OFFSET_CAPTURE, $offset) === 1) {
        $start = $match[0][1] + strlen($match[0][0]);
        $depth = 1;
        $i = $start;
        $length = strlen($css);
        while ($i < $length && $depth > 0) {
            if ($css[$i] === '{') {
                $depth++;
            } elseif ($css[$i] === '}') {
                $depth--;
            }
            $i++;
        }
        $result .= substr($css, $start, $i - 1 - $start) . "\n";
        $offset = $i;
    }
    // Commentaar eerst weg (daarin kan "@page" staan), daarna de @page-regel zelf: die hoort niet op scherm.
    $result = preg_replace('#/\*.*?\*/#s', '', $result) ?? $result;
    return preg_replace('/@page(?![\w-])[^{]*\{[^}]*\}/', '', $result) ?? $result;
}

function geeveChromiumBinary(): ?string
{
    foreach (['/usr/bin/chromium', '/usr/bin/chromium-headless-shell', '/usr/bin/chromium-browser', '/usr/bin/google-chrome'] as $binary) {
        if (is_executable($binary)) {
            return $binary;
        }
    }
    $configured = getenv('GEEVE_CHROMIUM');
    return is_string($configured) && $configured !== '' && is_executable($configured) ? $configured : null;
}

/** Voert een programma uit zonder shell; geeft ['code', 'out', 'err']. Code 124 = tijdslimiet. */
function geeveRun(array $command, int $timeoutSeconds, array $env = []): array
{
    $environment = array_merge(['PATH' => '/usr/local/bin:/usr/bin:/bin'], $env);
    $process = @proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $environment);
    if (!is_resource($process)) {
        return ['code' => 127, 'out' => '', 'err' => 'Programma niet gevonden: ' . $command[0]];
    }
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $out = '';
    $err = '';
    $deadline = microtime(true) + $timeoutSeconds;
    while (true) {
        $out .= (string) stream_get_contents($pipes[1]);
        $err .= (string) stream_get_contents($pipes[2]);
        $status = proc_get_status($process);
        if (!$status['running']) {
            $out .= (string) stream_get_contents($pipes[1]);
            $err .= (string) stream_get_contents($pipes[2]);
            $code = (int) $status['exitcode'];
            break;
        }
        if (microtime(true) > $deadline) {
            proc_terminate($process, 9);
            $code = 124;
            $err .= 'Tijdslimiet van ' . $timeoutSeconds . ' seconden overschreden.';
            break;
        }
        usleep(50000);
    }
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);
    return ['code' => $code, 'out' => $out, 'err' => $err];
}

function geeveLastLine(string $text): string
{
    $lines = array_values(array_filter(array_map('trim', explode("\n", $text)), static fn(string $l): bool => $l !== ''));
    return $lines === [] ? 'geen melding' : $lines[count($lines) - 1];
}

function geeveRemoveDir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        $item->isDir() && !$item->isLink() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($dir);
}
