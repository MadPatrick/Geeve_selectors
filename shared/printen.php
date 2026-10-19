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

/**
 * Tegels met een printknop (tegel-id => naam; de Selector heeft er geen: die downloadt alleen de pdf-catalogus). Alleen
 * voor die tegels is in Config een printerkeuze te maken. Een tegel waar een printknop bijkomt, hier toevoegen.
 */
const GEEVE_PRINT_TILES = [
    'slangkaarten' => 'Slangkaarten Printen',
    'stickers-order' => 'Artikelen per Verkooporder',
    'stickers-productie' => 'Artikelen per Productieorder',
    'stickers-artikel' => 'Artikelnummer',
    'stickers-artikel-groot' => "Accu's",
    'stickers-slanglabels' => 'Slanglabels',
];

/** Soort printer per tegel: 'a4' (gewone printer, PCL) of 'label' (kale TSPL-labelprinter). Standaard 'a4'. */
const GEEVE_PRINT_TILE_SOORT = [
    'stickers-order' => 'label',
    'stickers-productie' => 'label',
    'stickers-artikel' => 'label',
    'stickers-artikel-groot' => 'label',
    'stickers-slanglabels' => 'label',
];

/** Tegels waarin direct printen al werkt; bij de eerste import staan de bestaande A4-printers hierbij aangevinkt. */
const GEEVE_PRINT_TILES_ACTIEF = ['slangkaarten'];

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
    'stickers-order' => ['sheet' => 'soSheet', 'class' => 'so-preview', 'css' => ['klantartikel/assets/style.css', 'reports/msp/assets/style.css', 'stickers/assets/art-sticker.css', 'stickers/assets/order-stickers.css']],
];

/** Wel/niet omkeren van de bitmap voor TSPL: bij TSC is een 0-bit een zwarte punt. */
/** Labels die 90° gedraaid in de printer liggen, per sheet-class van de tegel: 'ccw' of 'cw'. */
const GEEVE_LABEL_DRAAI = ['sl-printsheet' => 'cw'];

/** Medium en afdrukinstellingen van de Windows-driver (RTMLBSlanglabels01), per sheet-class: mm, mm/s als TSPL-SPEED (inch/s), zwarting 0-15. */
const GEEVE_LABEL_MEDIUM = ['sl-printsheet' => ['breedte' => 90, 'hoogte' => 27, 'snelheid' => 2, 'zwarting' => 7]];

const GEEVE_TSPL_ZWART_IS_NUL = true;

/** Het soort van een printer ('a4' of 'label'); oude regels zonder soort zijn 'a4'. */
function geevePrinterSoort(array $printer): string
{
    return ($printer['soort'] ?? 'a4') === 'label' ? 'label' : 'a4';
}

/** Past een printer bij een tegel (zelfde soort)? */
function geevePrinterPastBijTegel(array $printer, string $tileId): bool
{
    return geevePrinterSoort($printer) === (GEEVE_PRINT_TILE_SOORT[$tileId] ?? 'a4');
}

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
            if (preg_match('#^device for (geeve_[\w-]+): socket://([^:/\s]+)#', trim($line), $m) === 1 && isset(GEEVE_PRINTERS_OUD[$m[1]])) {
                $list[$m[1]] = [
                    'name' => GEEVE_PRINTERS_OUD[$m[1]] ?? $m[1],
                    'host' => $m[2],
                    'color' => $m[1] !== 'geeve_balie',
                    'soort' => 'a4',
                    'tiles' => GEEVE_PRINT_TILES_ACTIEF,
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
        if (in_array($queue, $cups, true) && in_array($tile, $printer['tiles'] ?? [], true) && geevePrinterPastBijTegel($printer, $tile)) {
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
/**
 * Splitst een adres als "10.209.192.172" of "10.209.192.172:9101" in [host, poort]. De poort is standaard 9100: een
 * USB-printserver (bijv. Silex DS-510) geeft elke aangesloten printer een eigen poort op hetzelfde IP.
 *
 * @return array{0: string, 1: int}
 */
function geeveSplitHost(string $adres): array
{
    if (preg_match('/^(.*):(\d{1,5})$/', $adres, $m) === 1) {
        return [$m[1], (int) $m[2]];
    }
    return [$adres, 9100];
}

function geeveAddPrinter(string $name, string $host, string $kleurKeuze, array $tiles, string $soort = 'a4'): array
{
    $name = trim($name);
    $host = strtolower(trim($host));
    $soort = $soort === 'label' ? 'label' : 'a4';
    if ($name === '' || strlen($name) > 60) {
        return [false, 'Geef de printer een naam (maximaal 60 tekens).'];
    }
    if (preg_match('/^[a-z0-9]([a-z0-9.-]{0,60}[a-z0-9])?(:\d{1,5})?$/', $host) !== 1) {
        return [false, 'Het IP-adres of de netwerknaam is ongeldig (eventueel met poort, bijv. 10.209.192.172:9101).'];
    }
    [$hostNaam, $poort] = geeveSplitHost($host);
    $ip = filter_var($hostNaam, FILTER_VALIDATE_IP) !== false ? $hostNaam : gethostbyname($hostNaam);
    if (filter_var($ip, FILTER_VALIDATE_IP) === false || !geeveIsPrivateAddress($ip)) {
        return [false, 'Dit adres is niet gevonden op het interne netwerk.'];
    }
    foreach (geevePrinterList() as $existing) {
        // Meerdere printers (bijv. twee labelmaten) mogen hetzelfde adres hebben, zolang de naam verschilt.
        if ($existing['host'] === $host && strcasecmp($existing['name'], $name) === 0) {
            return [false, 'Er staat al een printer met deze naam op dit adres: ' . $existing['name'] . '.'];
        }
    }
    $socket = @fsockopen($ip, $poort, $errno, $errstr, 3);
    if ($socket === false) {
        return [false, 'De printer antwoordt niet op poort ' . $poort . ' (' . ($errstr !== '' ? $errstr : 'geen verbinding') . ').'];
    }
    fclose($socket);

    $info = ['model' => null, 'color' => null];
    $color = false;
    $driver = null;
    if ($soort === 'a4') {
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
    }

    $list = geevePrinterList();
    // Staat er al een wachtrij naar dit adres in CUPS (bijv. door de beheerder aangemaakt)? Dan die overnemen.
    $queue = geeveFindQueueForHost($ip, $poort);
    if ($queue === null || isset($list[$queue])) {
        $base = 'geeve_' . trim((string) preg_replace('/[^a-z0-9]+/', '_', strtolower($name)), '_');
        $queue = substr($base, 0, 40);
        for ($n = 2; isset($list[$queue]); $n++) {
            $queue = substr($base, 0, 36) . '_' . $n;
        }
        $args = ['/usr/sbin/lpadmin', '-p', $queue, '-E', '-v', 'socket://' . $ip . ':' . $poort,
            '-m', $driver ?? 'raw', '-o', 'printer-is-shared=false', '-D', $name];
        if ($soort === 'a4') {
            $args = array_merge($args, ['-o', 'media=A4']);
        }
        $make = geeveRun($args, 20);
        if ($make['code'] !== 0) {
            return [false, 'De printerwachtrij kon niet worden aangemaakt: ' . geeveLastLine($make['err'] . $make['out'])];
        }
        $created = true;
    }
    $list[$queue] = [
        'name' => $name,
        'host' => $host,
        'color' => $color,
        'soort' => $soort,
        'tiles' => array_values(array_intersect($tiles, array_keys(GEEVE_PRINT_TILES))),
    ];
    if ($soort === 'label') {
        $list[$queue]['dpi'] = 300;
    }
    if (!geevePrinterListSave($list)) {
        if (!empty($created)) {
            geeveRun(['/usr/sbin/lpadmin', '-x', $queue], 10);
        }
        return [false, 'De lijst met printers kon niet worden opgeslagen (' . geevePrintersFile() . ' niet schrijfbaar).'];
    }
    return [true, $name . ' is toegevoegd' . ($soort === 'label' ? ' (labelprinter).' : ($info['model'] !== null ? ' (' . $info['model'] . ', ' : ' (') . ($color ? 'kleur' : 'zwart-wit') . ').')];
}

/** De CUPS-wachtrij (geeve_*) die al naar dit IP-adres op poort 9100 wijst, of null. */
function geeveFindQueueForHost(string $ip, int $poort = 9100): ?string
{
    $result = geeveRun(['/usr/bin/lpstat', '-v'], 5);
    foreach (explode("\n", $result['out']) as $line) {
        if (preg_match('#^device for (geeve_[\w-]+): socket://' . preg_quote($ip, '#') . '(?::(\d+))?/?$#', trim($line), $m) === 1 && (int) ($m[2] ?? 9100) === $poort) {
            return $m[1];
        }
    }
    return null;
}

/**
 * Wijzigt naam, adres en/of kleur van een printer uit de lijst en past de CUPS-wachtrij aan. $kleurKeuze: 'auto'
 * (ongewijzigd laten), 'kleur' of 'zwartwit'. Geeft [true, melding] of [false, foutmelding].
 *
 * @return array{0: bool, 1: string}
 */
function geeveUpdatePrinter(string $queue, string $name, string $host, string $kleurKeuze): array
{
    $list = geevePrinterList();
    if (!isset($list[$queue])) {
        return [false, 'Onbekende printer.'];
    }
    $name = trim($name);
    $host = strtolower(trim($host));
    if ($name === '' || strlen($name) > 60) {
        return [false, 'Geef de printer een naam (maximaal 60 tekens).'];
    }
    if (preg_match('/^[a-z0-9]([a-z0-9.-]{0,60}[a-z0-9])?(:\d{1,5})?$/', $host) !== 1) {
        return [false, 'Het IP-adres of de netwerknaam is ongeldig (eventueel met poort, bijv. 10.209.192.172:9101).'];
    }
    foreach ($list as $otherQueue => $other) {
        if ($otherQueue !== $queue && $other['host'] === $host && strcasecmp($other['name'], $name) === 0) {
            return [false, 'Er staat al een printer met deze naam op dit adres: ' . $other['name'] . '.'];
        }
    }
    $current = $list[$queue];
    $args = [];
    $hostChanged = $host !== $current['host'];
    if ($hostChanged) {
        [$hostNaam, $poort] = geeveSplitHost($host);
        $ip = filter_var($hostNaam, FILTER_VALIDATE_IP) !== false ? $hostNaam : gethostbyname($hostNaam);
        if (filter_var($ip, FILTER_VALIDATE_IP) === false || !geeveIsPrivateAddress($ip)) {
            return [false, 'Dit adres is niet gevonden op het interne netwerk.'];
        }
        $socket = @fsockopen($ip, $poort, $errno, $errstr, 3);
        if ($socket === false) {
            return [false, 'De printer antwoordt niet op poort ' . $poort . ' (' . ($errstr !== '' ? $errstr : 'geen verbinding') . ').'];
        }
        fclose($socket);
        $args = array_merge($args, ['-v', 'socket://' . $ip . ':' . $poort]);
    }
    $isLabel = geevePrinterSoort($current) === 'label';
    $color = $kleurKeuze === 'kleur' ? true : ($kleurKeuze === 'zwartwit' ? false : $current['color']);
    if (!$isLabel && $color !== $current['color']) {
        $driver = geeveFindDriver($color, false);
        if ($driver === null) {
            return [false, 'Er is geen passende printerdriver (' . ($color ? 'PCL 6 kleur' : 'PCL 6 zwart-wit') . ') op de server gevonden.'];
        }
        $args = array_merge($args, ['-m', $driver]);
    }
    if ($name !== $current['name']) {
        $args = array_merge($args, ['-D', $name]);
    }
    if ($args !== []) {
        $change = geeveRun(array_merge(['/usr/sbin/lpadmin', '-p', $queue], $args), 20);
        if ($change['code'] !== 0) {
            return [false, 'De wachtrij kon niet worden aangepast: ' . geeveLastLine($change['err'] . $change['out'])];
        }
    }
    $new = ['name' => $name, 'host' => $host, 'color' => $color] + $current;
    $list[$queue] = $new;
    return geevePrinterListSave($list)
        ? [true, $name . ' is gewijzigd.']
        : [false, 'De lijst met printers kon niet worden opgeslagen.'];
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
        $list[$queue]['tiles'] = array_values(array_intersect($tilesPerQueue[$queue] ?? [], array_keys(GEEVE_PRINT_TILES)));
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
    if (geevePrinterSoort($printer) === 'label') {
        return geeveLabelTest($queue);
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
 * Print een HTML-fragment (de inhoud van #printSheet) op een CUPS-wachtrij van een A4-printer. Geeft het CUPS-jobnummer
 * terug; gooit een RuntimeException met een leesbare melding als het misgaat.
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

/**
 * Print de labels in een HTML-fragment op een kale TSPL-labelprinter: PDF op labelformaat (de @page-regel van de app),
 * per pagina een zwart-wit bitmap op de resolutie van de printer, als BITMAP-opdracht naar de wachtrij (lp -o raw).
 * Heeft pdftoppm (poppler-utils) nodig. Geeft het CUPS-jobnummer.
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
        $raster = geeveRun(['/usr/bin/pdftoppm', '-mono', '-r', (string) $dpi, $pdfFile, $workDir . '/pagina'], 60);
        $pages = glob($workDir . '/pagina-*.pbm') ?: [];
        sort($pages);
        if ($raster['code'] !== 0 || $pages === []) {
            throw new RuntimeException('De labels konden niet worden omgezet naar een afbeelding' . ($raster['code'] === 127 ? ' (pdftoppm ontbreekt: installeer poppler-utils).' : ': ' . geeveLastLine($raster['err'])));
        }
        $tspl = '';
        foreach ($pages as $page) {
            $tspl .= geevePbmToTspl((string) file_get_contents($page), $dpi, GEEVE_LABEL_DRAAI[$sheetClass] ?? '', GEEVE_LABEL_MEDIUM[$sheetClass] ?? []);
        }
        $file = $workDir . '/labels.tspl';
        file_put_contents($file, $tspl);
        $print = geeveRun(['/usr/bin/lp', '-d', $queue, '-t', $title, '-o', 'raw', $file], 20);
        if ($print['code'] !== 0) {
            throw new RuntimeException('De printer weigerde de opdracht: ' . geeveLastLine($print['err'] . $print['out']));
        }
        return preg_match('/request id is (\S+)/', $print['out'], $match) === 1 ? $match[1] : trim($print['out']);
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

/**
 * Het labelformaat van een tegel in mm als [breedte, hoogte], gelezen uit de @page-regel van de stylesheet van de app
 * ("@page { size: 25mm 95mm; ... }"), of null als die er niet is.
 *
 * @return array{0: float, 1: float}|null
 */
function geeveTileLabelSize(string $tileId): ?array
{
    $page = GEEVE_PRINT_TILE_PAGE[$tileId] ?? null;
    if ($page === null) {
        return null;
    }
    foreach (array_reverse($page['css']) as $css) {
        $text = (string) @file_get_contents(geevePortalDir() . '/' . $css);
        if (preg_match('/@page\s*\{[^}]*size:\s*([\d.]+)mm\s+([\d.]+)mm/', $text, $m) === 1) {
            return [(float) $m[1], (float) $m[2]];
        }
    }
    return null;
}

/**
 * Testlabels in TSPL (kader rondom, tekst en een barcode), één per labelformaat van de tegels waaraan de printer is
 * gekoppeld. Gooit een RuntimeException als de printer nog bij geen enkele tegel staat. Geeft het jobnummer.
 */
function geeveLabelTest(string $queue): string
{
    $printer = geevePrinterList()[$queue] ?? null;
    if ($printer === null || geevePrinterSoort($printer) !== 'label') {
        throw new RuntimeException('Dit is geen labelprinter.');
    }
    $dpi = (int) ($printer['dpi'] ?? 300);
    $sizes = [];
    foreach ($printer['tiles'] ?? [] as $tileId) {
        $size = geeveTileLabelSize((string) $tileId);
        if ($size !== null) {
            $sizes[implode('x', $size)] = [$size, GEEVE_PRINT_TILES[$tileId] ?? $tileId];
        }
    }
    if ($sizes === []) {
        throw new RuntimeException('Vink deze printer eerst aan bij een tegel (Config > Tegels): het labelformaat komt uit de tegel.');
    }
    $name = preg_replace('/[^\x20-\x7e]/', '', str_replace('"', "'", $printer['name'])) ?? '';
    $tspl = '';
    foreach ($sizes as [[$widthMm, $heightMm], $tileName]) {
        $w = (int) round($widthMm / 25.4 * $dpi);
        $h = (int) round($heightMm / 25.4 * $dpi);
        $tileText = preg_replace('/[^\x20-\x7e]/', '', str_replace('"', "'", (string) $tileName)) ?? '';
        $tspl .= 'SIZE ' . $widthMm . ' mm,' . $heightMm . " mm\r\nGAP 3 mm,0 mm\r\nDIRECTION 0\r\nCLS\r\n"
            . 'BOX 0,0,' . ($w - 1) . ',' . ($h - 1) . ",3\r\n"
            . 'TEXT 20,20,"3",0,1,1,"TESTLABEL"' . "\r\n"
            . 'TEXT 20,70,"2",0,1,1,"' . substr($name, 0, 24) . '"' . "\r\n"
            . 'TEXT 20,110,"2",0,1,1,"' . substr($tileText, 0, 24) . '"' . "\r\n"
            . 'TEXT 20,150,"2",0,1,1,"' . round($widthMm) . 'x' . round($heightMm) . ' mm"' . "\r\n"
            . 'BARCODE 20,200,"128",60,1,0,2,2,"12345678"' . "\r\n"
            . "PRINT 1,1\r\n";
    }
    $workDir = geeveWorkDir();
    try {
        $file = $workDir . '/test.tspl';
        file_put_contents($file, $tspl);
        $print = geeveRun(['/usr/bin/lp', '-d', $queue, '-t', 'Testlabel', '-o', 'raw', $file], 20);
        if ($print['code'] !== 0) {
            throw new RuntimeException('De printer weigerde de opdracht: ' . geeveLastLine($print['err'] . $print['out']));
        }
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
 * De printerkeuze naast een printknop: een keuzelijst "Printer" met de printers van deze tegel (Config > Tegels), plus
 * het script dat de printknop dan naar de server laat printen. Leeg als er voor de tegel geen printers zijn.
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
