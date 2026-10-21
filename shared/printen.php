<?php

declare(strict_types=1);

/**
 * Direct printen vanaf de server (zonder het printvenster van de browser).
 *
 * De printers zijn de printershares op de Windows-printserver (GEEVE_SMB_SERVER) waarvan de naam met GEEVE_SMB_PREFIX
 * begint. De lijst en de printerdrivers komen van de printserver zelf (rpcclient), inloggen gebeurt met het account
 * uit GEEVE_SMB_CRED_FILES (het Exact-account van Slangkaart). Een app bouwt dezelfde HTML als voor de browserprint;
 * geevePrintHtml() maakt daar met headless Chromium een PDF van (zelfde print-CSS, @page A4), ghostscript maakt er
 * PCL XL van (of TSPL / Brother-raster voor labelprinters) en smbclient zet die in de wachtrij van de share. Zie
 * shared/README.md, "Direct printen".
 */

require_once __DIR__ . '/paths.php';

/** De printserver en het begin van de sharenamen die in de apps te kiezen zijn. */
const GEEVE_SMB_SERVER = 'SVNL-VWP-PS01';
const GEEVE_SMB_PREFIX = 'RTM';

/**
 * Inlogbestand voor de printserver (smbclient -A: "username = ...", "password = ...", "domain = ..."), buiten de
 * webmap, eigendom van root en leesbaar voor de webserver. De omgevingsvariabele GEEVE_SMB_CRED gaat voor.
 */
const GEEVE_SMB_CRED_FILES = ['/etc/geeve/smbprint.cred'];

/** Zo lang (seconden) blijft de printerlijst van de printserver bewaard voordat hij opnieuw wordt opgehaald. */
const GEEVE_SMB_CACHE_SECONDEN = 1800;

/**
 * Tegels met een printknop (tegel-id => naam; de Selector heeft er geen: die downloadt alleen de pdf-catalogus). Een
 * tegel waar een printknop bijkomt, hier toevoegen.
 */
const GEEVE_PRINT_TILES = [
    'slangkaarten' => 'Slangkaarten Printen',
    'stickers-order' => 'Artikelen per Verkooporder',
    'stickers-productie' => 'Artikelen per Productieorder',
    'stickers-artikel' => 'Artikelnummer',
    'stickers-artikel-groot' => "Accu's",
    'stickers-slanglabels' => 'Slanglabels',
    'stickers-ptouch' => 'P-Touch',
];

/** Soort printer per tegel: 'a4' (gewone printer, PCL) of 'label' (labelprinter: TSPL of Brother QL). Standaard 'a4'. */
const GEEVE_PRINT_TILE_SOORT = [
    'stickers-order' => 'label',
    'stickers-productie' => 'label',
    'stickers-artikel' => 'label',
    'stickers-artikel-groot' => 'label',
    'stickers-slanglabels' => 'label',
    'stickers-ptouch' => 'label',
];

/**
 * Per tegel met een printknop die in de browser een afdrukblok opbouwt: welk element (id) dat blok is, welke klasse het
 * in de print moet hebben, en welke stylesheets de pagina laadt (vanaf de portal-root). Het blok wordt bij direct printen
 * door stickers/assets/direct-print.js naar shared/print-api.php gestuurd.
 */
const GEEVE_PRINT_TILE_PAGE = [
    'stickers-slanglabels' => ['sheet' => 'slPrintSheet', 'class' => 'sl-printsheet', 'css' => ['klantartikel/assets/style.css', 'reports/msp/assets/style.css', 'stickers/assets/slanglabels.css']],
    'stickers-artikel-groot' => ['sheet' => 'acPrintSheet', 'class' => 'ac-printsheet', 'css' => ['klantartikel/assets/style.css', 'reports/msp/assets/style.css', 'stickers/assets/accu.css']],
    'stickers-artikel' => ['sheet' => 'saPrintSheet', 'class' => 'sa-printsheet', 'css' => ['klantartikel/assets/style.css', 'reports/msp/assets/style.css', 'stickers/assets/art-sticker.css', 'stickers/assets/artikel-stickers.css']],
    'stickers-productie' => ['sheet' => 'psPrintSheet', 'class' => 'ps-printsheet', 'css' => ['klantartikel/assets/style.css', 'reports/msp/assets/style.css', 'stickers/assets/productie-stickers.css']],
    'stickers-ptouch' => ['sheet' => 'ptPrintSheet', 'class' => 'pt-printsheet', 'css' => ['klantartikel/assets/style.css', 'reports/msp/assets/style.css', 'stickers/assets/ptouch.css']],
    'stickers-order' => ['sheet' => 'soSheet', 'class' => 'so-preview', 'css' => ['klantartikel/assets/style.css', 'reports/msp/assets/style.css', 'stickers/assets/art-sticker.css', 'stickers/assets/order-stickers.css']],
];

/** Labels die 90° gedraaid in de printer liggen, per sheet-class van de tegel: 'ccw' of 'cw'. */
const GEEVE_LABEL_DRAAI = ['sl-printsheet' => 'cw'];

/** Medium en afdrukinstellingen van de Windows-driver (RTMLBSlanglabels01), per sheet-class: mm, mm/s als TSPL-SPEED (inch/s), zwarting 0-15. */
const GEEVE_LABEL_MEDIUM = ['sl-printsheet' => ['breedte' => 90, 'hoogte' => 27, 'snelheid' => 2, 'zwarting' => 7]];

/**
 * Sheet-classes van tegels waarvan de labels als één opdracht gaan en de printer pas na het laatste label snijdt
 * (niet na elk label).
 */
const GEEVE_LABEL_SNIJD_EINDE = ['so-preview', 'ps-printsheet', 'sa-printsheet', 'ac-printsheet', 'pt-printsheet'];

/** Wel/niet omkeren van de bitmap voor TSPL: bij TSC is een 0-bit een zwarte punt. */
const GEEVE_TSPL_ZWART_IS_NUL = true;

/** Het soort van een printer ('a4' of 'label'). */
function geevePrinterSoort(array $printer): string
{
    return ($printer['soort'] ?? 'a4') === 'label' ? 'label' : 'a4';
}

/** Past een printer bij een tegel (zelfde soort)? */
function geevePrinterPastBijTegel(array $printer, string $tileId): bool
{
    return geevePrinterSoort($printer) === (GEEVE_PRINT_TILE_SOORT[$tileId] ?? 'a4');
}

/** Het inlogbestand voor de printserver, of null als er geen leesbaar bestand is. */
function geeveSmbCredFile(): ?string
{
    $custom = getenv('GEEVE_SMB_CRED');
    foreach ($custom !== false && $custom !== '' ? [$custom] : GEEVE_SMB_CRED_FILES as $file) {
        if (is_readable($file)) {
            return $file;
        }
    }
    return null;
}

/** Bestand met de laatst opgehaalde printerlijst, en het bestand dat inloggen blokkeert na een inlogfout. */
function geeveSmbCacheFile(): string
{
    return geeveDataDir() . '/printers/smb-printers.json';
}

function geeveSmbBlockFile(): string
{
    return geeveDataDir() . '/printers/smb-geblokkeerd.json';
}

/**
 * Na een inlogfout probeert de portal niet opnieuw tot het inlogbestand is gewijzigd: elke pagina met een printerkeuze
 * zou anders een nieuwe mislukte poging doen, en dan gaat het account (ook dat van de Exact-koppeling) op slot.
 * Geeft de melding als inloggen geblokkeerd is, anders null.
 */
function geeveSmbBlocked(): ?string
{
    $cred = geeveSmbCredFile();
    if ($cred === null) {
        return 'Het inlogbestand voor de printserver ontbreekt (' . implode(' of ', GEEVE_SMB_CRED_FILES) . ').';
    }
    $block = json_decode((string) @file_get_contents(geeveSmbBlockFile()), true);
    if (is_array($block) && ($block['cred'] ?? '') === $cred . '@' . (string) @filemtime($cred)) {
        return 'Inloggen op de printserver is gestopt na de fout "' . ($block['fout'] ?? '?') . '" (' . ($block['tijd'] ?? '') . '), zodat het account niet op slot gaat. Pas ' . $cred . ' aan; daarna probeert de portal het weer.';
    }
    return null;
}

/**
 * Draait smbclient of rpcclient met het inlogbestand. Een inlogfout (verkeerd wachtwoord, account op slot of verlopen)
 * blokkeert verdere pogingen, zie geeveSmbBlocked(). Gooit een RuntimeException met een leesbare melding.
 *
 * @param list<string> $args
 * @return array{code: int, out: string, err: string}
 */
function geeveSmbRun(string $program, array $args, int $timeoutSeconds): array
{
    $blocked = geeveSmbBlocked();
    if ($blocked !== null) {
        throw new RuntimeException($blocked);
    }
    $cred = (string) geeveSmbCredFile();
    $result = geeveRun(array_merge(['/usr/bin/' . $program, '-A', $cred], $args), $timeoutSeconds);
    $text = $result['out'] . "\n" . $result['err'];
    if (preg_match('/NT_STATUS_(LOGON_FAILURE|ACCOUNT_LOCKED_OUT|ACCOUNT_DISABLED|PASSWORD_EXPIRED|PASSWORD_MUST_CHANGE|WRONG_PASSWORD|ACCOUNT_RESTRICTION)/', $text, $m) === 1) {
        $dir = dirname(geeveSmbBlockFile());
        if (is_dir($dir) || @mkdir($dir, 0775, true)) {
            @file_put_contents(geeveSmbBlockFile(), json_encode(['cred' => $cred . '@' . (string) @filemtime($cred), 'fout' => $m[0], 'tijd' => date('d-m-Y H:i')]), LOCK_EX);
        }
        throw new RuntimeException('Inloggen op de printserver mislukt (' . $m[0] . '). De portal probeert het niet opnieuw tot ' . $cred . ' is aangepast.');
    }
    if ($result['code'] !== 0) {
        $status = preg_match('/NT_STATUS_\w+/', $text, $s) === 1 ? $s[0] : geeveLastLine($text);
        throw new RuntimeException('De printserver ' . GEEVE_SMB_SERVER . ' gaf een fout: ' . $status);
    }
    return $result;
}

/**
 * Bepaalt aan de hand van de driver op de printserver (en de sharenaam) wat voor printer een share is. Null voor
 * printers waarop de portal niet kan printen (Zebra/ZPL en onbekende drivers).
 *
 * @return array{soort: string, taal: string, color: bool, model?: string}|null
 */
function geeveSmbPrinterType(string $share, string $driver): ?array
{
    if (preg_match('/brother[\s_-]*ql[\s_-]*(\d{3,4}\w*)/i', $share, $m) === 1 || preg_match('/brother[\s_-]*ql[\s_-]*(\d{3,4}\w*)/i', $driver, $m) === 1) {
        return ['soort' => 'label', 'taal' => 'brother', 'color' => false, 'model' => 'QL-' . strtoupper($m[1])];
    }
    if (preg_match('/altec|\btsc\b|ttp-/i', $driver) === 1) {
        return ['soort' => 'label', 'taal' => 'tspl', 'color' => false];
    }
    if (preg_match('/zdesigner|zebra|zpl|dymo|brady/i', $share . ' ' . $driver) === 1) {
        return null;
    }
    if (preg_match('/pcl|universal/i', $driver) === 1) {
        // Kleur volgens de driver; de Canon-multifunctionals (Generic Plus PCL6) printen in kleur.
        return ['soort' => 'a4', 'taal' => 'pcl', 'color' => preg_match('/colou?r|canon/i', $driver) === 1];
    }
    return null;
}

/**
 * Alle printershares op de printserver die met GEEVE_SMB_PREFIX beginnen en waarop de portal kan printen, gesorteerd
 * op naam: [share => ['name', 'driver', 'host', 'soort', 'taal', 'color', 'model'?]]. De lijst wordt GEEVE_SMB_CACHE_SECONDEN
 * bewaard; lukt ophalen niet, dan geldt de laatst bekende lijst (en anders een lege lijst).
 *
 * @return array<string, array<string, mixed>>
 */
function geevePrinterList(): array
{
    static $list = null;
    if ($list !== null) {
        return $list;
    }
    $cache = json_decode((string) @file_get_contents(geeveSmbCacheFile()), true);
    $cached = is_array($cache) && is_array($cache['printers'] ?? null) ? $cache['printers'] : null;
    if ($cached !== null && (int) ($cache['tijd'] ?? 0) > time() - GEEVE_SMB_CACHE_SECONDEN) {
        return $list = $cached;
    }
    try {
        // Sharenamen (met een omschrijving) en de driver per printer; enumprinters 1 is veel sneller dan 2.
        $shares = geeveSmbRun('smbclient', ['-L', '//' . GEEVE_SMB_SERVER, '-g'], 20);
        $drivers = geeveSmbRun('rpcclient', [GEEVE_SMB_SERVER, '-c', 'enumprinters 1'], 20);
    } catch (RuntimeException $e) {
        return $list = $cached ?? [];
    }
    // "description:[\\SVNL-VWP-PS01\RTMMFSB01_L1_Blanco,Canon Generic Plus PCL6,NL - Rotterdam Geeve]"
    $driverOf = [];
    preg_match_all('/^\s*description:\[\\\\{2}[^\\\\]+\\\\(.+),([^,]*),([^,]*)\]\s*$/m', $drivers['out'], $matches, PREG_SET_ORDER);
    foreach ($matches as $m) {
        $driverOf[strtolower(trim($m[1]))] = trim($m[2]);
    }
    $found = [];
    // "Printer|RTMMFSB01_L1_Blanco|10.209.192.183"
    foreach (explode("\n", $shares['out']) as $line) {
        $parts = explode('|', trim($line), 3);
        if (count($parts) < 2 || $parts[0] !== 'Printer') {
            continue;
        }
        $share = $parts[1];
        $driver = $driverOf[strtolower($share)] ?? null;
        if ($driver === null || stripos($share, GEEVE_SMB_PREFIX) !== 0 || preg_match('/[\x00-\x1f\/\\\\]/', $share) === 1) {
            continue;
        }
        $type = geeveSmbPrinterType($share, $driver);
        if ($type !== null) {
            $found[$share] = ['name' => $share, 'driver' => $driver, 'host' => trim($parts[2] ?? '')] + $type;
        }
    }
    if ($found === [] && $cached !== null) {
        return $list = $cached;
    }
    uksort($found, 'strnatcasecmp');
    $dir = dirname(geeveSmbCacheFile());
    if (is_dir($dir) || @mkdir($dir, 0775, true)) {
        @file_put_contents(geeveSmbCacheFile(), json_encode(['tijd' => time(), 'printers' => $found], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }
    return $list = $found;
}

/** De printers die in een tegel te kiezen zijn (het soort past bij de tegel), als [share => naam]. */
function geevePrinters(string $tile = 'slangkaarten'): array
{
    $printers = [];
    foreach (geevePrinterList() as $share => $printer) {
        if (geevePrinterPastBijTegel($printer, $tile)) {
            $printers[$share] = $printer['name'];
        }
    }
    return $printers;
}

/**
 * Zet een bestand in de wachtrij van een share op de printserver (smbclient "print"; de printserver geeft de data
 * ongewijzigd door, datatype RAW). $title wordt de naam van de opdracht in de wachtrij. Geeft een omschrijving terug.
 */
function geeveSmbPrint(string $share, string $file, string $title): string
{
    // smbclient gebruikt de bestandsnaam als naam van de opdracht.
    $name = trim((string) preg_replace('/[^A-Za-z0-9_-]+/', '_', $title), '_');
    $named = dirname($file) . '/' . ($name !== '' ? substr($name, 0, 60) : 'Geeve') . '.prn';
    if (!@rename($file, $named)) {
        $named = $file;
    }
    $result = geeveSmbRun('smbclient', ['//' . GEEVE_SMB_SERVER . '/' . $share, '-c', 'print ' . $named], 60);
    if (preg_match('/putting file .* as (\S+)/', $result['out'] . $result['err'], $m) !== 1) {
        throw new RuntimeException('De printserver bevestigde de opdracht niet: ' . geeveLastLine($result['out'] . $result['err']));
    }
    return GEEVE_SMB_SERVER . '\\' . $share;
}

/**
 * Zet een PDF (A4) om in PCL XL voor een gewone printer: 600 dpi, kleur of zwart-wit, en passend verkleind tot het
 * printbare vlak (12 pt rondom, fit-to-page); zonder die verkleining viel op de Canons aan de rechterkant net iets weg.
 */
function geevePdfToPcl(string $pdfFile, bool $color, string $workDir): string
{
    $out = $workDir . '/print.pxl';
    // A4 = 595 x 842 pt, printbaar 571 x 818 pt: schaal 571/595, horizontaal 12 pt en verticaal gecentreerd.
    $scale = 571 / 595;
    $fit = sprintf('<< /BeginPage { pop 12 %.3F translate %.5F dup scale } >> setpagedevice', (842 - 842 * $scale) / 2, $scale);
    $render = geeveRun(['/usr/bin/gs', '-q', '-dSAFER', '-dBATCH', '-dNOPAUSE', '-sDEVICE=' . ($color ? 'pxlcolor' : 'pxlmono'),
        '-r600', '-sPAPERSIZE=a4', '-dFIXEDMEDIA', '-dPDFFitPage', '-sOutputFile=' . $out, '-c', $fit, '-f', $pdfFile], 120);
    if ($render['code'] !== 0 || !is_file($out) || filesize($out) === 0) {
        throw new RuntimeException('De print kon niet worden omgezet voor de printer' . ($render['code'] === 127 ? ' (ghostscript ontbreekt).' : ': ' . geeveLastLine($render['err'] . $render['out'])));
    }
    return $out;
}

/**
 * Rendert een HTML-fragment (de inhoud van #printSheet) met headless Chromium en de print-CSS naar een PDF in $workDir
 * en geeft het pad ervan. $cssFiles zijn paden vanaf de portal-root, in dezelfde volgorde als in de <head> van de app.
 * $sheetClass: de klasse die #printSheet in de app heeft (sommige apps verbergen in de print alles buiten die klasse).
 * $passend: zie geevePrintHtml(). Gooit een RuntimeException met een leesbare melding.
 */
function geeveRenderPdf(string $bodyHtml, array $cssFiles, string $title, string $workDir, ?array $passend = null, string $sheetClass = ''): string
{
    $chromium = geeveChromiumBinary();
    if ($chromium === null) {
        throw new RuntimeException('Chromium staat niet op de server, direct printen kan daardoor niet.');
    }
    $htmlFile = $workDir . '/print.html';
    $pdfFile = $workDir . '/print.pdf';

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
    $class = preg_match('/^[\w -]*$/', $sheetClass) === 1 && $sheetClass !== '' ? ' class="' . $sheetClass . '"' : '';
    $document = "<!doctype html>\n<html lang=\"nl\"><head><meta charset=\"utf-8\"><title>"
        . htmlspecialchars($title, ENT_QUOTES) . "</title>\n" . $links . '</head><body>'
        . '<div id="printSheet"' . $class . '>' . $bodyHtml . '</div>' . $passendScript . '</body></html>';
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
    return $pdfFile;
}

/** Maakt een tijdelijke werkmap (0700); de aanroeper ruimt hem op met geeveRemoveDir(). */
function geeveWorkDir(): string
{
    $workDir = rtrim(sys_get_temp_dir(), '/') . '/geeve-print-' . bin2hex(random_bytes(6));
    if (!@mkdir($workDir, 0700)) {
        throw new RuntimeException('Kon geen tijdelijke map maken voor de print.');
    }
    return $workDir;
}

/**
 * Print een HTML-fragment (de inhoud van #printSheet) op een printershare (A4- of labelprinter). Geeft een omschrijving
 * van de opdracht terug; gooit een RuntimeException met een leesbare melding als het misgaat.
 *
 * $passend: optioneel [selector, maximale hoogte in mm]. Elk element dat in de print hoger is dan die hoogte, wordt
 * met CSS-zoom precies zo veel verkleind dat het op één pagina past (bijv. een slangkaart met een lange notitie).
 */
function geevePrintHtml(string $bodyHtml, array $cssFiles, string $queue, string $title, ?array $passend = null, string $sheetClass = ''): string
{
    $printer = geevePrinterList()[$queue] ?? null;
    if ($printer === null) {
        throw new RuntimeException('Onbekende printer: ' . $queue);
    }
    if (geevePrinterSoort($printer) === 'label') {
        return geevePrintLabels($bodyHtml, $cssFiles, $queue, $title, $sheetClass);
    }
    $workDir = geeveWorkDir();
    try {
        $pdfFile = geeveRenderPdf($bodyHtml, $cssFiles, $title, $workDir, $passend, $sheetClass);
        return geeveSmbPrint($queue, geevePdfToPcl($pdfFile, (bool) $printer['color'], $workDir), $title);
    } finally {
        geeveRemoveDir($workDir);
    }
}

/** brother_ql (eigen Python-omgeving op de server) en het label waarop de Brother QL-printers staan: 102 x 51 mm. */
const GEEVE_BROTHER_QL_DIR = '/opt/brother_ql';
const GEEVE_BROTHER_QL_LABEL = ['maat' => '102x51', 'breedte' => 1164, 'hoogte' => 526];

/**
 * Print een PDF op een Brother QL-1100/1050: elke pagina wordt een bitmap van 1164 x 526 punten (300 dpi, het
 * printbare vlak van een 102 x 51 mm-label; de pagina wordt passend verkleind en gecentreerd), brother_ql zet die om
 * in QL-rasteropdrachten en die gaan kaal (raw) naar de share.
 */
function geeveBrotherPrint(string $pdfFile, array $printer, string $queue, string $title, string $workDir, bool $snijdEinde = false): string
{
    $python = GEEVE_BROTHER_QL_DIR . '/bin/python3';
    $create = GEEVE_BROTHER_QL_DIR . '/bin/brother_ql_create';
    if (!is_executable($create) || !is_executable($python)) {
        throw new RuntimeException('brother_ql staat niet op de server (' . GEEVE_BROTHER_QL_DIR . '), Brother-labelprinters kunnen daardoor niet printen.');
    }
    $dots = GEEVE_BROTHER_QL_LABEL;
    $raster = geeveRun(['/usr/bin/pdftoppm', '-mono', '-r', '300', '-scale-to-y', (string) $dots['hoogte'], '-scale-to-x', '-1', $pdfFile, $workDir . '/ql'], 60);
    $pages = glob($workDir . '/ql-*.pbm') ?: [];
    sort($pages);
    if ($raster['code'] !== 0 || $pages === []) {
        throw new RuntimeException('De labels konden niet worden omgezet naar een afbeelding' . ($raster['code'] === 127 ? ' (pdftoppm ontbreekt: installeer poppler-utils).' : ': ' . geeveLastLine($raster['err'])));
    }
    // Eén Python-start voor alle pagina's (PIL laden kost per start merkbaar tijd), daarna de brother_ql-omzettingen
    // tegelijk i.p.v. na elkaar.
    $pad = 'import sys\nfrom PIL import Image\n'
        . 'w, h = int(sys.argv[1]), int(sys.argv[2])\n'
        . 'for pad in sys.argv[3:]:\n'
        . '    src = Image.open(pad).convert("1")\n'
        . '    if src.width > w or src.height > h:\n'
        . '        f = min(w / src.width, h / src.height)\n'
        . '        src = src.resize((max(1, int(src.width * f)), max(1, int(src.height * f))))\n'
        . '    canvas = Image.new("1", (w, h), 1)\n'
        . '    canvas.paste(src, ((w - src.width) // 2, (h - src.height) // 2))\n'
        . '    canvas.save(pad[:-4] + ".png")\n';
    $fit = geeveRun(array_merge([$python, '-c', str_replace('\\n', "\n", $pad), (string) $dots['breedte'], (string) $dots['hoogte']], $pages), 60);
    if ($fit['code'] !== 0) {
        throw new RuntimeException('De labels konden niet worden klaargemaakt: ' . geeveLastLine($fit['err']));
    }
    $commands = [];
    foreach ($pages as $i => $page) {
        // Oudere brother_ql_create kent alleen --no-cut (snijden is de standaard); --cut geeft een fout.
        $opties = $snijdEinde && $i < count($pages) - 1 ? ['--no-cut'] : [];
        $commands[$i] = array_merge([$create, '--model', (string) ($printer['model'] ?? 'QL-1100'), '--label-size', $dots['maat']], $opties, [substr($page, 0, -4) . '.png', $workDir . '/label' . $i . '.bin']);
    }
    $bin = '';
    foreach (geeveRunParallel($commands, 60) as $i => $make) {
        $out = $workDir . '/label' . $i . '.bin';
        if ($make['code'] !== 0 || !is_file($out)) {
            throw new RuntimeException('brother_ql kon het label niet omzetten: ' . geeveLastLine($make['err'] . $make['out']));
        }
        $bin .= (string) file_get_contents($out);
    }
    $file = $workDir . '/labels.bin';
    file_put_contents($file, $bin);
    return geeveSmbPrint($queue, $file, $title);
}

/**
 * Print de labels in een HTML-fragment op een kale TSPL-labelprinter: PDF op labelformaat (de @page-regel van de app),
 * per pagina een zwart-wit bitmap op de resolutie van de printer, als BITMAP-opdracht naar de share.
 * Heeft pdftoppm (poppler-utils) nodig.
 */
function geevePrintLabels(string $bodyHtml, array $cssFiles, string $queue, string $title, string $sheetClass = ''): string
{
    $printer = geevePrinterList()[$queue] ?? null;
    if ($printer === null || geevePrinterSoort($printer) !== 'label') {
        throw new RuntimeException('Dit is geen labelprinter.');
    }
    // Voorkom dat een aangeleverd label lokale bestanden of andere pagina's binnenhaalt.
    if (preg_match('#(file:|<iframe|<object|<embed|<link|@import|<base)#i', $bodyHtml) === 1) {
        throw new RuntimeException('Het label bevat niet-toegestane inhoud.');
    }
    $dpi = (int) ($printer['dpi'] ?? 300);
    $workDir = geeveWorkDir();
    try {
        $pdfFile = geeveRenderPdf($bodyHtml, $cssFiles, $title, $workDir, null, $sheetClass);
        if (($printer['taal'] ?? '') === 'brother') {
            return geeveBrotherPrint($pdfFile, $printer, $queue, $title, $workDir, in_array($sheetClass, GEEVE_LABEL_SNIJD_EINDE, true));
        }
        $raster = geeveRun(['/usr/bin/pdftoppm', '-mono', '-r', (string) $dpi, $pdfFile, $workDir . '/pagina'], 60);
        $pages = glob($workDir . '/pagina-*.pbm') ?: [];
        sort($pages);
        if ($raster['code'] !== 0 || $pages === []) {
            throw new RuntimeException('De labels konden niet worden omgezet naar een afbeelding' . ($raster['code'] === 127 ? ' (pdftoppm ontbreekt: installeer poppler-utils).' : ': ' . geeveLastLine($raster['err'])));
        }
        $tspl = '';
        $snijdEinde = in_array($sheetClass, GEEVE_LABEL_SNIJD_EINDE, true);
        foreach ($pages as $i => $page) {
            // Snijden na het laatste label: snijder uit bij alle labels ervoor, aan (na elk 1 label) bij het laatste, en dat laatste label doorgevoerd tot de afscheurrand.
            $snijder = $snijdEinde ? ($i < count($pages) - 1 ? "SET CUTTER OFF\r\n" : "SET CUTTER 1\r\nSET TEAR ON\r\n") : '';
            $tspl .= $snijder . geevePbmToTspl((string) file_get_contents($page), $dpi, GEEVE_LABEL_DRAAI[$sheetClass] ?? '', GEEVE_LABEL_MEDIUM[$sheetClass] ?? []);
        }
        $file = $workDir . '/labels.tspl';
        file_put_contents($file, $tspl);
        return geeveSmbPrint($queue, $file, $title);
    } finally {
        geeveRemoveDir($workDir);
    }
}

/**
 * Draait een 1-bit bitmap (rijen van intdiv(breedte+7,8) bytes, 1 = zwart) een kwartslag.
 *
 * @return array{0: string, 1: int, 2: int} nieuwe data, breedte, hoogte
 */
function geevePbmDraai(string $data, int $width, int $height, bool $clockwise): array
{
    $rowBytes = intdiv($width + 7, 8);
    $newRowBytes = intdiv($height + 7, 8);
    $out = array_fill(0, $newRowBytes * $width, 0);
    for ($y = 0; $y < $height; $y++) {
        $rowStart = $y * $rowBytes;
        for ($x = 0; $x < $width; $x++) {
            if ((ord($data[$rowStart + ($x >> 3)]) >> (7 - ($x & 7))) & 1) {
                // cw: (x,y) -> (H-1-y, x); ccw: (x,y) -> (y, W-1-x)
                $nx = $clockwise ? $height - 1 - $y : $y;
                $ny = $clockwise ? $x : $width - 1 - $x;
                $out[$ny * $newRowBytes + ($nx >> 3)] |= 1 << (7 - ($nx & 7));
            }
        }
    }
    return [pack('C*', ...$out), $height, $width];
}

/**
 * Zet een 1-bit bitmap op een canvas van newW x newH punten: rechts uitgelijnd (te brede kant links wordt afgesneden),
 * verticaal gecentreerd. Rijen van intdiv(breedte+7,8) bytes, 1 = zwart.
 *
 * @return array{0: string, 1: int, 2: int}
 */
function geevePbmCanvas(string $data, int $width, int $height, int $newW, int $newH): array
{
    $rowBytes = intdiv($width + 7, 8);
    $newRowBytes = intdiv($newW + 7, 8);
    $out = array_fill(0, $newRowBytes * $newH, 0);
    $dx = $newW - $width;
    $dy = intdiv($newH - $height, 2);
    for ($y = 0; $y < $height; $y++) {
        $ny = $y + $dy;
        if ($ny < 0 || $ny >= $newH) {
            continue;
        }
        for ($x = 0; $x < $width; $x++) {
            $nx = $x + $dx;
            if ($nx >= 0 && $nx < $newW && (ord($data[$y * $rowBytes + ($x >> 3)]) >> (7 - ($x & 7))) & 1) {
                $out[$ny * $newRowBytes + ($nx >> 3)] |= 1 << (7 - ($nx & 7));
            }
        }
    }
    return [pack('C*', ...$out), $newW, $newH];
}

/** Zet één PBM-pagina (P4, 1 bit per punt, 1 = zwart) om in een TSPL-label: SIZE, BITMAP, PRINT. */
function geevePbmToTspl(string $pbm, int $dpi, string $draai = '', array $medium = []): string
{
    if (preg_match('/^P4\s+(?:#[^\n]*\n\s*)*(\d+)\s+(\d+)\s/', $pbm, $m, PREG_OFFSET_CAPTURE) !== 1) {
        throw new RuntimeException('Onverwacht afbeeldingsformaat van pdftoppm.');
    }
    $width = (int) $m[1][0];
    $height = (int) $m[2][0];
    $rowBytes = intdiv($width + 7, 8);
    $data = substr($pbm, $m[0][1] + strlen($m[0][0]), $rowBytes * $height);
    if (strlen($data) !== $rowBytes * $height) {
        throw new RuntimeException('De afbeelding van het label is onvolledig.');
    }
    if ($draai !== '') {
        // Het label ligt 90° gedraaid in de printer: de bitmap gaat gedraaid mee, breedte en hoogte wisselen.
        [$data, $width, $height] = geevePbmDraai($data, $width, $height, $draai === 'cw');
        $rowBytes = intdiv($width + 7, 8);
    }
    if (isset($medium['breedte'], $medium['hoogte'])) {
        // Medium zoals de Windows-driver het had (bijv. 90 x 27 mm): te brede kant links bijsnijden (daar is niets bedrukt),
        // de hoogte aanvullen en de afdruk daarin verticaal centreren.
        $newW = (int) round($medium['breedte'] / 25.4 * $dpi);
        $newH = (int) round($medium['hoogte'] / 25.4 * $dpi);
        [$data, $width, $height] = geevePbmCanvas($data, $width, $height, $newW, $newH);
        $rowBytes = intdiv($width + 7, 8);
    }
    if (GEEVE_TSPL_ZWART_IS_NUL) {
        $data = ~$data;
        // Opvulbits aan het eind van elke rij blijven wit (1).
        $pad = $rowBytes * 8 - $width;
        if ($pad > 0) {
            $mask = chr((1 << $pad) - 1);
            for ($y = 0; $y < $height; $y++) {
                $i = $y * $rowBytes + $rowBytes - 1;
                $data[$i] = $data[$i] | $mask;
            }
        }
    }
    // 25,1 mm (afronding van de pdf-maat) wordt 25 mm; echte halve maten blijven staan.
    $mm = static fn(int $dots): float => abs($dots / $dpi * 25.4 - round($dots / $dpi * 25.4)) < 0.3 ? round($dots / $dpi * 25.4) : round($dots / $dpi * 25.4, 1);
    $widthMm = $mm($width);
    $heightMm = $mm($height);
    $instelling = isset($medium['snelheid'], $medium['zwarting'])
        ? 'SPEED ' . $medium['snelheid'] . "\r\nDENSITY " . $medium['zwarting'] . "\r\n" : '';
    // Met een vast medium geen GAP-regel: de printer gebruikt zijn eigen gemeten tussenruimte, net als de Windows-driver.
    $gap = $medium === [] ? "GAP 3 mm,0 mm\r\n" : '';
    return 'SIZE ' . $widthMm . ' mm,' . $heightMm . " mm\r\n" . $gap . $instelling . "DIRECTION 0\r\nCLS\r\n"
        . 'BITMAP 0,0,' . $rowBytes . ',' . $height . ',0,' . $data . "\r\nPRINT 1,1\r\n";
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

/** Voert meerdere programma's tegelijk uit (zelfde sleutels als $commands); geeft per sleutel ['code', 'out', 'err']. */
function geeveRunParallel(array $commands, int $timeoutSeconds): array
{
    $running = [];
    $result = [];
    foreach ($commands as $key => $command) {
        $process = @proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, ['PATH' => '/usr/local/bin:/usr/bin:/bin']);
        if (!is_resource($process)) {
            $result[$key] = ['code' => 127, 'out' => '', 'err' => 'Programma niet gevonden: ' . $command[0]];
            continue;
        }
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $running[$key] = ['process' => $process, 'pipes' => $pipes, 'out' => '', 'err' => ''];
    }
    $deadline = microtime(true) + $timeoutSeconds;
    while ($running !== []) {
        foreach ($running as $key => &$job) {
            $job['out'] .= (string) stream_get_contents($job['pipes'][1]);
            $job['err'] .= (string) stream_get_contents($job['pipes'][2]);
            $status = proc_get_status($job['process']);
            $timeout = microtime(true) > $deadline;
            if (!$status['running'] || $timeout) {
                if ($timeout) {
                    proc_terminate($job['process'], 9);
                }
                $job['out'] .= (string) stream_get_contents($job['pipes'][1]);
                $job['err'] .= (string) stream_get_contents($job['pipes'][2]);
                fclose($job['pipes'][1]);
                fclose($job['pipes'][2]);
                proc_close($job['process']);
                $result[$key] = ['code' => $timeout ? 124 : (int) $status['exitcode'], 'out' => $job['out'], 'err' => $job['err']];
                unset($running[$key]);
            }
        }
        unset($job);
        if ($running !== []) {
            usleep(20000);
        }
    }
    ksort($result);
    return $result;
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

/**
 * Onthoudt de printer waarop de gebruiker het laatst heeft geprint (cookie geeve_printer_<tegel>, een jaar), zodat die de
 * volgende keer voorgeselecteerd staat. Alleen zolang er nog geen uitvoer is verstuurd.
 */
function geeveRememberPrinter(string $tileId, string $share): void
{
    if (!headers_sent()) {
        setcookie('geeve_printer_' . $tileId, $share, ['expires' => time() + 31536000, 'path' => '/', 'samesite' => 'Lax']);
    }
}

/**
 * De <option>s van een printerkeuze: de printers van de tegel, en als laatste "Printvenster van de browser" (waarde "").
 * Voorgeselecteerd is de keuze uit het cookie geeve_printer_<tegel> (de gebruiker koos eerder een printer of het
 * browservenster), anders de eerste printer.
 *
 * @param array<string, string> $printers wachtrij => naam
 */
function geevePrinterOptionsHtml(string $tileId, array $printers): string
{
    $h = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $saved = (string) ($_COOKIE['geeve_printer_' . $tileId] ?? '');
    $selected = $saved === 'browser' || isset($printers[$saved]) ? $saved : (string) array_key_first($printers);
    $options = '';
    foreach ($printers as $queue => $name) {
        $options .= '<option value="' . $h($queue) . '"' . ($selected === $queue ? ' selected' : '') . '>' . $h($name) . '</option>';
    }
    return $options . '<option value=""' . ($selected === 'browser' ? ' selected' : '') . '>Printvenster van de browser</option>';
}

/**
 * De printerkeuze naast een printknop: een keuzelijst "Printer" met de RTM-printers van de printserver die bij deze tegel
 * passen (A4 of label), plus het script dat de printknop dan naar de server laat printen. Leeg als er voor de tegel geen printers zijn.
 * Voorgeselecteerd: de printer die de gebruiker de vorige keer koos (cookie), anders de eerste printer; "Printvenster
 * van de browser" staat als laatste optie.
 */
function geevePrinterSelectHtml(string $tileId, string $csrf): string
{
    $page = GEEVE_PRINT_TILE_PAGE[$tileId] ?? null;
    $printers = geevePrinters($tileId);
    if ($page === null || $printers === []) {
        return '';
    }
    $h = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $options = geevePrinterOptionsHtml($tileId, $printers);
    return '<label class="direct-print" id="directPrint" data-tile="' . $h($tileId) . '" data-sheet="' . $h($page['sheet'])
        . '" data-endpoint="' . $h(geeveUrl('shared/print-api.php')) . '" data-csrf="' . $h($csrf) . '">'
        . '<span>Printer</span><select id="directPrinter">' . $options . '</select><span class="direct-print-status" id="directPrintStatus"></span></label>';
}
