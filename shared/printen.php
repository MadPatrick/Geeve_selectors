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

/** Tegels waarin een printer te kiezen is (tegel-id => naam). Nieuwe tegel met direct printen: hier toevoegen. */
const GEEVE_PRINT_TILES = [
    'slangkaarten' => 'Slangkaarten Printen',
];

/**
 * Alleen voor de eerste import: de namen van de drie printers die vóór het printerscherm in de code stonden. Heeft
 * data/printers/printers.json nog niet, dan worden de bestaande CUPS-wachtrijen geeve_* overgenomen.
 */
const GEEVE_PRINTERS_OUD = [
    'geeve_binnendienst_l1' => 'Binnendienst (RTMMFBINNENDIENST01, lade 1)',
    'geeve_sb_l1' => 'SB (RTMMFSB01, lade 1)',
    'geeve_balie' => 'Balie (RTMPRbalie01)',
];

/** Printerlijst van het printerscherm in Config: data/printers/printers.json. */
function geevePrintersFile(): string
{
    return geeveDataDir() . '/printers/printers.json';
}

/**
 * Alle bekende printers: [wachtrij => ['name', 'host', 'color' (bool), 'tiles' (list<string>)]]. Bestaat het bestand nog
 * niet, dan worden de CUPS-wachtrijen geeve_* eenmalig overgenomen (en vastgelegd als het kan).
 *
 * @return array<string, array{name: string, host: string, color: bool, tiles: list<string>}>
 */
function geevePrinterList(): array
{
    $file = geevePrintersFile();
    if (is_readable($file)) {
        $data = json_decode((string) file_get_contents($file), true);
        return is_array($data) ? $data : [];
    }
    $list = [];
    $result = geeveRun(['/usr/bin/lpstat', '-v'], 5);
    if ($result['code'] === 0) {
        foreach (explode("\n", $result['out']) as $line) {
            // "device for geeve_balie: socket://10.209.192.184:9100"
            if (preg_match('#^device for (geeve_[\w-]+): socket://([^:/\s]+)#', trim($line), $m) === 1) {
                $list[$m[1]] = [
                    'name' => GEEVE_PRINTERS_OUD[$m[1]] ?? $m[1],
                    'host' => $m[2],
                    'color' => $m[1] !== 'geeve_balie',
                    'tiles' => array_keys(GEEVE_PRINT_TILES),
                ];
            }
        }
        if ($list !== []) {
            geevePrinterListSave($list);
        }
    }
    return $list;
}

/** @param array<string, array{name: string, host: string, color: bool, tiles: list<string>}> $list */
function geevePrinterListSave(array $list): bool
{
    $file = geevePrintersFile();
    $dir = dirname($file);
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
        return false;
    }
    return @file_put_contents($file, json_encode($list, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX) !== false;
}

/**
 * De printers die in een tegel te kiezen zijn, als [wachtrij => naam]: in de lijst van het printerscherm, voor die
 * tegel aangevinkt, en aanwezig in CUPS. Leeg als CUPS ontbreekt.
 */
function geevePrinters(string $tile = 'slangkaarten'): array
{
    static $cups = null;
    if ($cups === null) {
        $result = geeveRun(['/usr/bin/lpstat', '-e'], 5);
        $cups = $result['code'] === 0 ? (preg_split('/\s+/', trim($result['out'])) ?: []) : [];
    }
    $printers = [];
    foreach (geevePrinterList() as $queue => $printer) {
        if (in_array($queue, $cups, true) && in_array($tile, $printer['tiles'] ?? [], true)) {
            $printers[$queue] = $printer['name'];
        }
    }
    return $printers;
}

/** True voor een IP-adres in een privé-/gereserveerd bereik (de printers staan op het interne netwerk). */
function geeveIsPrivateAddress(string $ip): bool
{
    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false
        && filter_var($ip, FILTER_VALIDATE_IP) !== false;
}

/**
 * Vraagt merk/model en kleurondersteuning aan de printer via IPP (poort 631). Geeft ['model' => string|null,
 * 'color' => bool|null]; null = de printer antwoordde niet of niet begrijpelijk.
 */
function geeveIppInfo(string $host): array
{
    $text = static fn(int $tag, string $name, string $value): string => chr($tag) . pack('n', strlen($name)) . $name . pack('n', strlen($value)) . $value;
    foreach (['/ipp/print', '/ipp'] as $path) {
        $uri = 'ipp://' . $host . $path;
        $body = "\x01\x01\x00\x0b\x00\x00\x00\x01\x01"
            . $text(0x47, 'attributes-charset', 'utf-8')
            . $text(0x48, 'attributes-natural-language', 'en')
            . $text(0x45, 'printer-uri', $uri)
            . $text(0x44, 'requested-attributes', 'color-supported')
            . $text(0x44, '', 'printer-make-and-model')
            . "\x03";
        $context = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/ipp\r\n",
            'content' => $body,
            'timeout' => 4,
            'ignore_errors' => true,
        ]]);
        $response = @file_get_contents('http://' . $host . ':631' . $path, false, $context);
        if (!is_string($response) || strlen($response) < 9 || substr($response, 0, 2) !== "\x01\x01") {
            continue;
        }
        $info = ['model' => null, 'color' => null];
        $i = 8;
        $length = strlen($response);
        $name = '';
        while ($i < $length) {
            $tag = ord($response[$i++]);
            if ($tag <= 0x05) {
                if ($tag === 0x03) {
                    break;
                }
                continue;
            }
            if ($i + 2 > $length) {
                break;
            }
            $nameLength = (int) unpack('n', substr($response, $i, 2))[1];
            $i += 2;
            if ($nameLength > 0) {
                $name = substr($response, $i, $nameLength);
            }
            $i += $nameLength;
            if ($i + 2 > $length) {
                break;
            }
            $valueLength = (int) unpack('n', substr($response, $i, 2))[1];
            $i += 2;
            $value = substr($response, $i, $valueLength);
            $i += $valueLength;
            if ($name === 'color-supported' && $tag === 0x22) {
                $info['color'] = $value === "\x01";
            } elseif ($name === 'printer-make-and-model') {
                $info['model'] = $value;
            }
        }
        if ($info['model'] !== null || $info['color'] !== null) {
            return $info;
        }
    }
    return ['model' => null, 'color' => null];
}

/**
 * De CUPS-driver ("-m"-waarde) voor een printer, gezocht in lpinfo -m. Kleur: een PCL 6-kleurendriver. Zwart-wit: een
 * generieke PCL 6/PCL 5-driver, anders (printer sprak IPP) "everywhere", anders de kleurendriver (die op een
 * zwart-witprinter gewoon grijs print). null als er niets bruikbaars is.
 */
function geeveFindDriver(bool $color, bool $ippBeschikbaar = false): ?string
{
    $result = geeveRun(['/usr/sbin/lpinfo', '-m'], 20);
    if ($result['code'] !== 0) {
        return null;
    }
    $models = [];
    foreach (explode("\n", $result['out']) as $line) {
        $line = trim($line);
        if ($line !== '') {
            $models[] = [strtok($line, ' '), $line];
        }
    }
    $find = static function (string $pattern, bool $noColor = false) use ($models): ?string {
        foreach ($models as [$id, $line]) {
            if (preg_match($pattern, $line) === 1 && (!$noColor || stripos($line, 'color') === false)) {
                return $id ?: null;
            }
        }
        return null;
    };
    $colorDriver = $find('/color\s*laserjet\s*series\s*pcl\s*6/i') ?? $find('/color.*pcl\s*6/i');
    if ($color) {
        return $colorDriver ?? $find('/pcl\s*6/i');
    }
    return $find('/generic.*pcl\s*6/i', true)
        ?? $find('/laserjet\s*series\s*pcl\s*6/i', true)
        ?? $find('/generic.*pcl/i', true)
        ?? $find('/pcl\s*6/i', true)
        ?? ($ippBeschikbaar ? $find('/^everywhere\b/i') : null)
        ?? $colorDriver;
}

/**
 * Voegt een printer toe: controleert het adres en poort 9100, leest kleur/zwart-wit via IPP (of gebruikt $kleurKeuze
 * 'kleur'/'zwartwit'), maakt de CUPS-wachtrij aan en legt de printer vast. Geeft [true, melding] of [false, foutmelding].
 *
 * @param list<string> $tiles
 * @return array{0: bool, 1: string}
 */
function geeveAddPrinter(string $name, string $host, string $kleurKeuze, array $tiles): array
{
    $name = trim($name);
    $host = strtolower(trim($host));
    if ($name === '' || strlen($name) > 60) {
        return [false, 'Geef de printer een naam (maximaal 60 tekens).'];
    }
    if (preg_match('/^[a-z0-9]([a-z0-9.-]{0,60}[a-z0-9])?$/', $host) !== 1) {
        return [false, 'Het IP-adres of de netwerknaam is ongeldig.'];
    }
    $ip = filter_var($host, FILTER_VALIDATE_IP) !== false ? $host : gethostbyname($host);
    if (filter_var($ip, FILTER_VALIDATE_IP) === false || !geeveIsPrivateAddress($ip)) {
        return [false, 'Dit adres is niet gevonden op het interne netwerk.'];
    }
    foreach (geevePrinterList() as $existing) {
        if ($existing['host'] === $host) {
            return [false, 'Er staat al een printer met dit adres: ' . $existing['name'] . '.'];
        }
    }
    $socket = @fsockopen($ip, 9100, $errno, $errstr, 3);
    if ($socket === false) {
        return [false, 'De printer antwoordt niet op poort 9100 (' . ($errstr !== '' ? $errstr : 'geen verbinding') . ').'];
    }
    fclose($socket);

    $info = geeveIppInfo($ip);
    if ($kleurKeuze === 'kleur' || $kleurKeuze === 'zwartwit') {
        $color = $kleurKeuze === 'kleur';
    } elseif ($info['color'] !== null) {
        $color = $info['color'];
    } else {
        return [false, 'De printer vertelt niet of hij kleur kan. Kies zelf Kleur of Zwart-wit en probeer het opnieuw.'];
    }
    $driver = geeveFindDriver($color, $info['model'] !== null);
    if ($driver === null) {
        return [false, 'Er is geen passende printerdriver (' . ($color ? 'PCL 6 kleur' : 'PCL 6 zwart-wit') . ') op de server gevonden' . ($info['model'] !== null ? ' voor ' . $info['model'] : ' (de printer meldde zijn model niet)') . '. Meld het model, dan wordt de juiste driver geïnstalleerd.'];
    }

    $base = 'geeve_' . trim((string) preg_replace('/[^a-z0-9]+/', '_', strtolower($name)), '_');
    $queue = substr($base, 0, 40);
    $list = geevePrinterList();
    for ($n = 2; isset($list[$queue]); $n++) {
        $queue = substr($base, 0, 36) . '_' . $n;
    }
    $make = geeveRun(['/usr/sbin/lpadmin', '-p', $queue, '-E', '-v', 'socket://' . $ip . ':9100', '-m', $driver,
        '-o', 'media=A4', '-o', 'printer-is-shared=false', '-D', $name], 20);
    if ($make['code'] !== 0) {
        return [false, 'De printerwachtrij kon niet worden aangemaakt: ' . geeveLastLine($make['err'] . $make['out'])];
    }
    $list[$queue] = [
        'name' => $name,
        'host' => $host,
        'color' => $color,
        'tiles' => array_values(array_intersect($tiles, array_keys(GEEVE_PRINT_TILES))),
    ];
    if (!geevePrinterListSave($list)) {
        geeveRun(['/usr/sbin/lpadmin', '-x', $queue], 10);
        return [false, 'De lijst met printers kon niet worden opgeslagen (' . geevePrintersFile() . ' niet schrijfbaar).'];
    }
    return [true, $name . ' is toegevoegd' . ($info['model'] !== null ? ' (' . $info['model'] . ', ' : ' (') . ($color ? 'kleur' : 'zwart-wit') . ').'];
}

/** Haalt een printer weg: de CUPS-wachtrij en de regel in de lijst. Alleen printers uit de lijst. */
function geeveRemovePrinter(string $queue): array
{
    $list = geevePrinterList();
    if (!isset($list[$queue])) {
        return [false, 'Onbekende printer.'];
    }
    $remove = geeveRun(['/usr/sbin/lpadmin', '-x', $queue], 20);
    // Bestond de wachtrij al niet meer, dan is verwijderen uit de lijst genoeg.
    if ($remove['code'] !== 0 && stripos($remove['err'] . $remove['out'], 'does not exist') === false && stripos($remove['err'] . $remove['out'], 'unknown') === false) {
        return [false, 'De wachtrij kon niet worden verwijderd: ' . geeveLastLine($remove['err'] . $remove['out'])];
    }
    $name = $list[$queue]['name'];
    unset($list[$queue]);
    return geevePrinterListSave($list)
        ? [true, $name . ' is verwijderd.']
        : [false, 'De lijst met printers kon niet worden opgeslagen.'];
}

/** Stelt per printer in in welke tegels hij te kiezen is. @param array<string, list<string>> $tilesPerQueue */
function geevePrinterTilesSave(array $tilesPerQueue): bool
{
    $list = geevePrinterList();
    foreach ($list as $queue => $printer) {
        $list[$queue]['tiles'] = array_values(array_intersect($tilesPerQueue[$queue] ?? [], array_keys(TILE_REGISTRY)));
    }
    return geevePrinterListSave($list);
}

/** Stuurt een testpagina (kaders op 5 en 12 mm, kleurvlakken) naar een printer uit de lijst. Geeft het jobnummer. */
function geevePrintTestPage(string $queue): string
{
    $printer = geevePrinterList()[$queue] ?? null;
    if ($printer === null) {
        throw new RuntimeException('Onbekende printer.');
    }
    $body = '<div style="font-family: Arial, sans-serif; font-size: 14px;">'
        . '<h1 style="margin: 0 0 8px;">Testpagina - ' . htmlspecialchars($printer['name'], ENT_QUOTES) . '</h1>'
        . '<p>Geeve Hydraulics portal, ' . htmlspecialchars($printer['host'], ENT_QUOTES) . ', ' . ($printer['color'] ? 'kleur' : 'zwart-wit') . '. Alle kaders moeten volledig zichtbaar zijn.</p>'
        . '<div style="border: 2px solid #000; padding: 10px; margin: 8px 0;">Kader van 2px</div>'
        . '<div style="display: flex; gap: 8px; margin: 8px 0;">'
        . '<div style="flex: 1; height: 40px; background: #aa1124;"></div><div style="flex: 1; height: 40px; background: #1d1950;"></div>'
        . '<div style="flex: 1; height: 40px; background: #ffe000;"></div><div style="flex: 1; height: 40px; background: #f3f6f9; border: 1px solid #666;"></div></div>'
        . '<div style="border: 1px solid #666; padding: 10px; margin: 8px 0; text-align: right;">Rechterrand: deze lijn moet helemaal zichtbaar zijn</div>'
        . '</div>';
    return geevePrintHtml($body, [], $queue, 'Testpagina ' . $printer['name']);
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
    if (!isset(geevePrinterList()[$queue])) {
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
        $passendScript = '';
        if ($passend !== null) {
            // Meten kan alleen op "scherm"; daarom gelden de printregels hier ook op scherm, bij de printbreedte
            // (vaste breedte 186 mm = A4 min 2 x 12 mm marge). Het script zet daarna per te hoog element een zoom.
            $links .= "<style>@media screen {\n" . $printRegels . "\nhtml, body { margin: 0; padding: 0; background: none; }\n#printSheet { width: 186mm; }\n}</style>\n";
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
            '--window-size=1200,1100', // breder dan 700 px, anders slaat de mobiele opmaak van slangkaart.css aan (kaartblokken onder elkaar)
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
