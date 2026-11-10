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
require_once __DIR__ . '/run.php';
require_once __DIR__ . '/printsaneren.php';

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
    'slangkaarten' => 'Slangkaarten printen',
    'stickers-order' => 'Artikelen per Verkooporder',
    'stickers-productie' => 'Artikelen per Productieorder',
    'stickers-artikel' => 'Artikelnummer',
    'stickers-artikel-groot' => "Accu's",
    'stickers-slanglabels' => 'Slanglabels',
    'stickers-ptouch' => 'Naamstickers',
    'stickers-kabelmerkers' => 'Kabelmerkers Geel',
];

/** Soort printer per tegel: 'a4' (gewone printer, PCL) of 'label' (labelprinter: TSPL of Brother QL). Standaard 'a4'. */
const GEEVE_PRINT_TILE_SOORT = [
    'stickers-order' => 'label',
    'stickers-productie' => 'label',
    'stickers-artikel' => 'label',
    'stickers-artikel-groot' => 'label',
    'stickers-slanglabels' => 'label',
    'stickers-ptouch' => 'label',
    'stickers-kabelmerkers' => 'label',
];

/**
 * Per tegel met een printknop die in de browser een afdrukblok opbouwt: welk element (id) dat blok is, welke klasse het
 * in de print moet hebben, en welke stylesheets de pagina laadt (vanaf de portal-root). Zelfde lijst en volgorde als $headCss van de pagina:
 * mist er een (bijv. shared/stickers.css voor de gedeelde .sticker-regels), dan klopt de print niet meer met het voorbeeld. Het blok wordt bij direct printen
 * door shared/direct-print.js naar shared/print-api.php gestuurd.
 */
const GEEVE_PRINT_TILE_PAGE = [
    'stickers-slanglabels' => ['sheet' => 'slPrintSheet', 'class' => 'sl-printsheet', 'css' => ['shared/tabel.css', 'shared/rapport.css', 'shared/stickers.css', 'stickers/assets/slanglabels.css']],
    'stickers-artikel-groot' => ['sheet' => 'acPrintSheet', 'class' => 'ac-printsheet', 'css' => ['shared/tabel.css', 'shared/rapport.css', 'shared/stickers.css', 'stickers/assets/accu.css']],
    'stickers-artikel' => ['sheet' => 'saPrintSheet', 'class' => 'sa-printsheet', 'css' => ['shared/tabel.css', 'shared/rapport.css', 'shared/stickers.css', 'stickers/assets/art-sticker.css', 'stickers/assets/artikel-stickers.css']],
    'stickers-productie' => ['sheet' => 'psPrintSheet', 'class' => 'ps-printsheet', 'css' => ['shared/tabel.css', 'shared/rapport.css', 'shared/stickers.css', 'stickers/assets/productie-stickers.css']],
    'stickers-ptouch' => ['sheet' => 'ptPrintSheet', 'class' => 'pt-printsheet', 'css' => ['shared/tabel.css', 'shared/rapport.css', 'shared/stickers.css', 'stickers/assets/ptouch.css']],
    'stickers-kabelmerkers' => ['sheet' => 'kmPrintSheet', 'class' => 'km-printsheet', 'css' => ['shared/tabel.css', 'shared/rapport.css', 'shared/stickers.css', 'stickers/assets/kabelmerkers.css']],
    'stickers-order' => ['sheet' => 'soSheet', 'class' => 'so-preview', 'css' => ['shared/tabel.css', 'shared/rapport.css', 'shared/stickers.css', 'stickers/assets/art-sticker.css', 'stickers/assets/order-stickers.css']],
];

/** Labels die 90° gedraaid in de printer liggen, per sheet-class van de tegel: 'ccw' of 'cw'. */
const GEEVE_LABEL_DRAAI = ['sl-printsheet' => 'cw'];

/**
 * Medium en afdrukinstellingen van de Windows-driver (RTMLBSlanglabels01), per sheet-class: mm, mm/s als TSPL-SPEED (inch/s),
 * zwarting 0-15. Optioneel: 'supersample' => n (omzetten naar zwart-wit via n x n hogere resolutie, scherpere letters) met 'drempel' => 0-255 (hoger = vettere letters), 'gap' => mm tussenruimte tussen de labels (zoals in de driver), 'tear' => true (na elk label doorvoeren tot de afscheurrand, SET TEAR ON), 'offset' => mm extra
 * doorvoer na het label (TSPL OFFSET, voor fijnstellen van de afscheurrand), 'verschuifX' / 'verschuifY' => de afdruk in mm
 * naar rechts / omlaag schuiven op het medium (fijnstellen van het midden; negatief = andere kant op).
 */
// Steek 31 mm = hoogte 29 + gap 2 (opgemeten op de rol); het printvlak van 25 mm komt verticaal in het midden van die 29 mm.
const GEEVE_LABEL_MEDIUM = ['sl-printsheet' => ['breedte' => 90, 'hoogte' => 29, 'snelheid' => 1, 'zwarting' => 12, 'supersample' => 3, 'drempel' => 150, 'gap' => 2, 'tear' => true, 'offset' => 0, 'verschuifX' => 0, 'verschuifY' => 0]];

/**
 * Sheet-classes van tegels waarvan de labels als één opdracht gaan en de printer pas na het laatste label snijdt
 * (niet na elk label).
 */
const GEEVE_LABEL_SNIJD_EINDE = ['so-preview', 'ps-printsheet', 'sa-printsheet', 'ac-printsheet', 'pt-printsheet', 'km-printsheet'];

/**
 * Sheet-classes van tegels waarvan de Artikelsticker (70 x 25 mm) op een Altec/TSC-labelprinter met de eigen lettertypen en
 * streepjes van de printer wordt afgedrukt in plaats van als bitmap (geeveTsplNatiefArtikelsticker()). De netwerkmodule van
 * de TTP-343 Plus verwerkt een bitmap van ca. 10 KB per label zo langzaam dat de printer na elk label even stilstaat; deze
 * opdracht is ca. 1 KB per label. De letters zijn de ingebouwde lettertypen van de printer (niet Arial Vet) en de barcode
 * heeft een geheel aantal punten per streepje. Een lege lijst zet dit uit (dan gaat de sticker weer als bitmap).
 */
const GEEVE_TSPL_NATIEF_STICKERS = ['so-preview', 'sa-printsheet'];

/**
 * Artikelsticker op Altec/TSC: de tekst met de eigen lettertypen van de printer (true, minste data, blokkerige letters) of de
 * echte letters (Arial vet) als bitmap van alleen de tekst, met de barcode als streepjes (false, ca. 40-60% van de data van
 * een volledig label). Alleen van belang als GEEVE_TSPL_NATIEF_STICKERS de tegel bevat.
 */
const GEEVE_TSPL_NATIEF_TEKST = false;

/** Minimum aantal lege rijen (punten) tussen twee blokken die als aparte BITMAP gaan (300 dpi: 6 rijen = 0,5 mm). */
const GEEVE_TSPL_BAND_LEEG = 6;

/** Wel/niet omkeren van de bitmap voor TSPL: bij TSC is een 0-bit een zwarte punt. */
const GEEVE_TSPL_ZWART_IS_NUL = true;

/**
 * Tegels waar boven de printknop de velden "Snelheid" en "Zwarting" staan, met de soort labelprinter waarvoor ze gelden:
 * 'brother' (Brother QL: Zwarting = grijsdrempel in %, Snelheid = Standaard/Snel), 'tspl' (Altec/TSC: SPEED in inch/s, bereik per model, zie GEEVE_LABEL_MODEL_SNELHEID, en
 * DENSITY 0-15 als cijfers) of 'zpl' (Zebra: ^PR 2-6 en ~SD 0-30). Andere printers negeren ze. Leeg = standaard. Zie geevePrintAfdrukInstellingenHtml().
 */
const GEEVE_PRINT_TILE_AFDRUK_INSTELLING = ['stickers-ptouch', 'stickers-order', 'stickers-productie', 'stickers-slanglabels'];

/** Voorgeselecteerde printer per tegel (naam van de printerwachtrij), voor wie nog geen eigen keuze (cookie) heeft. */
const GEEVE_PRINT_TILE_STANDAARD_PRINTER = ['stickers-kabelmerkers' => 'RTMLBGeelKunstof01'];

/**
 * Printers die aan één tegel vastzitten (naam van de printerwachtrij, hoofdletterongevoelig => tegel-id). Zo'n printer komt
 * alleen in de printerlijst van die tegel voor (en geevePrinters() geeft voor die tegel alléén zulke printers), en alle andere
 * tegels laten hem weg. print-api.php valideert via geevePrinters(), dus ook de server weigert een andere combinatie.
 */
const GEEVE_PRINTER_ALLEEN_TEGEL = ['RTMLBGeelKunstof01' => 'stickers-kabelmerkers'];

/**
 * Printers waarvan de driver niet automatisch te herkennen is (geeveSmbPrinterType() geeft dan null en de printer zou
 * wegvallen): sharenaam (hoofdletterongevoelig) => type zoals geeveSmbPrinterType() dat teruggeeft. De share moet wel op de
 * printserver bestaan. RTMPROVE01 (10.209.180.132) is een Zebra ZD230 (203 dpi, ZPL; driver "ZDesigner ZD230-203dpi ZPL"). Het veld 'dpi' overschrijft de standaard van 300 dpi.
 */
const GEEVE_PRINTER_TYPE_VAST = ['rtmprove01' => ['soort' => 'label', 'taal' => 'zpl', 'color' => false, 'dpi' => 203]];

/**
 * Snelheid (inch/s) per printermodel, herkend aan de driver op de printserver (reguliere expressie => grenzen). 'standaard'
 * is de snelheid die de portal stuurt als in het scherm "Standaard" staat (of de tegel geen keuzelijst heeft) en de tegel
 * zelf geen vaste snelheid heeft (GEEVE_LABEL_MEDIUM). Hogere waarden dan 'max' worden teruggebracht tot 'max'.
 * Niet herkende labelprinters houden het volle bereik van hun taal en geen eigen standaard.
 * - Altec/TSC TTP-343 Plus: 300 dpi, maximaal 3 inch/s (76 mm/s, TSC-servicehandleiding TTP-245 Plus / TTP-343 Plus).
 * - Zebra ZD230: maximaal 6 inch/s (152 mm/s); geen eigen standaard, dan geldt de instelling van de printer zelf.
 */
const GEEVE_LABEL_MODEL_SNELHEID = [
    '/TTP-343/i' => ['min' => 1, 'max' => 3, 'standaard' => 3],
    '/ZD230/i' => ['min' => 2, 'max' => 6],
];

/** Volle snelheidsbereik per printertaal (zie GEEVE_PRINT_TILE_AFDRUK_INSTELLING). */
const GEEVE_LABEL_TAAL_SNELHEID = ['tspl' => ['min' => 1, 'max' => 12], 'zpl' => ['min' => 2, 'max' => 6]];

/**
 * Snelheidsgrenzen van een labelprinter: ['min', 'max', 'standaard'?], of null voor printers zonder instelbare snelheid in
 * inch/s (Brother QL, A4-printers).
 *
 * @param array<string, mixed> $printer een regel uit geevePrinterList()
 * @return array{min: int, max: int, standaard?: int}|null
 */
function geeveLabelSnelheid(array $printer): ?array
{
    $taal = (string) ($printer['taal'] ?? '');
    if (!isset(GEEVE_LABEL_TAAL_SNELHEID[$taal])) {
        return null;
    }
    $model = (string) ($printer['driver'] ?? '') . ' ' . (string) ($printer['name'] ?? '');
    foreach (GEEVE_LABEL_MODEL_SNELHEID as $patroon => $grenzen) {
        if (preg_match($patroon, $model) === 1) {
            return $grenzen;
        }
    }
    return GEEVE_LABEL_TAAL_SNELHEID[$taal];
}

/**
 * De snelheid die echt naar de printer gaat: de keuze uit het scherm (teruggebracht tot het bereik van het model), anders
 * de vaste snelheid van de tegel ($mediumSnelheid, alleen TSPL), anders de standaard van het model; null = niets sturen.
 */
function geeveLabelSnelheidVoorPrint(array $printer, ?int $gekozen, ?int $mediumSnelheid): ?int
{
    $grenzen = geeveLabelSnelheid($printer);
    if ($grenzen === null) {
        return $gekozen;
    }
    if ($gekozen !== null) {
        return max($grenzen['min'], min($grenzen['max'], $gekozen));
    }
    if ($mediumSnelheid !== null) {
        return max($grenzen['min'], min($grenzen['max'], $mediumSnelheid));
    }
    return $grenzen['standaard'] ?? null;
}

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
    return geeveSmbCheck(geeveRun(array_merge(['/usr/bin/' . $program, '-A', $cred], $args), $timeoutSeconds), $cred);
}

/**
 * Zelfde als geeveSmbRun(), maar meerdere aanroepen tegelijk ([sleutel => [programma, argumenten]]); de totale tijd is dan die
 * van de langzaamste i.p.v. de som. Geeft [sleutel => resultaat] terug en gooit bij de eerste fout een RuntimeException.
 *
 * @param array<string, array{0: string, 1: list<string>}> $jobs
 * @return array<string, array{code: int, out: string, err: string}>
 */
function geeveSmbRunParallel(array $jobs, int $timeoutSeconds): array
{
    $blocked = geeveSmbBlocked();
    if ($blocked !== null) {
        throw new RuntimeException($blocked);
    }
    $cred = (string) geeveSmbCredFile();
    $commands = [];
    foreach ($jobs as $key => [$program, $args]) {
        $commands[$key] = array_merge(['/usr/bin/' . $program, '-A', $cred], $args);
    }
    $results = [];
    foreach (geeveRunParallel($commands, $timeoutSeconds) as $key => $result) {
        $results[$key] = geeveSmbCheck($result, $cred);
    }
    return $results;
}

/**
 * Controleert het resultaat van smbclient/rpcclient: een inlogfout blokkeert verdere pogingen (zie geeveSmbBlocked()),
 * een andere fout geeft een RuntimeException met een leesbare melding.
 *
 * @param array{code: int, out: string, err: string} $result
 * @return array{code: int, out: string, err: string}
 */
function geeveSmbCheck(array $result, string $cred): array
{
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
    if ($list === null) {
        $list = geevePrinterListZonderMagema(geevePrinterListOphalen());
    }
    return $list;
}

/**
 * Haalt de Magema-printers (naam bevat "Magema", hoofdletterongevoelig) uit een printerlijst. Centraal, zodat het overal in de
 * portal geldt en ook voor een oude cache die nog Magema-printers bevat. Geen mb_*: stripos is voor deze namen genoeg.
 *
 * @param array<string, array<string, mixed>> $printers
 * @return array<string, array<string, mixed>>
 */
function geevePrinterListZonderMagema(array $printers): array
{
    foreach ($printers as $share => $printer) {
        if (stripos((string) $share, 'magema') !== false || stripos((string) ($printer['name'] ?? ''), 'magema') !== false) {
            unset($printers[$share]);
        }
    }
    return $printers;
}

/** Leest de printerlijst uit de cache of haalt hem op bij de printserver (zonder Magema-filter; dat doet geevePrinterList()). */
function geevePrinterListOphalen(): array
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
        // De twee opvragen tegelijk: de verversing (elke 30 minuten, door wie als eerste een printerkeuze opent) duurt dan zo
        // lang als de langzaamste i.p.v. de som.
        $both = geeveSmbRunParallel([
            'shares' => ['smbclient', ['-L', '//' . GEEVE_SMB_SERVER, '-g']],
            'drivers' => ['rpcclient', [GEEVE_SMB_SERVER, '-c', 'enumprinters 1']],
        ], 20);
        $shares = $both['shares'];
        $drivers = $both['drivers'];
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
        $vast = GEEVE_PRINTER_TYPE_VAST[strtolower($share)] ?? null;
        if (($driver === null && $vast === null) || stripos($share, GEEVE_SMB_PREFIX) !== 0 || preg_match('/[\x00-\x1f\/\\\\]/', $share) === 1) {
            continue;
        }
        $type = $vast ?? geeveSmbPrinterType($share, (string) $driver);
        if ($type !== null) {
            $found[$share] = ['name' => $share, 'driver' => (string) $driver, 'host' => trim($parts[2] ?? '')] + $type;
        }
    }
    $found = geevePrinterListZonderMagema($found);
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

/**
 * De printers die in een tegel te kiezen zijn (het soort past bij de tegel), als [share => naam]. Printers uit
 * GEEVE_PRINTER_ALLEEN_TEGEL horen bij precies één tegel: die tegel krijgt alleen die printer, de rest krijgt hem niet.
 */
function geevePrinters(string $tile = 'slangkaarten'): array
{
    $vast = array_change_key_case(GEEVE_PRINTER_ALLEEN_TEGEL, CASE_LOWER);
    $alleenVoorTegel = array_keys(array_filter($vast, static fn(string $t): bool => $t === $tile));
    $printers = [];
    foreach (geevePrinterList() as $share => $printer) {
        $eigenaar = $vast[strtolower((string) $share)] ?? null;
        if ($alleenVoorTegel !== []) {
            // Tegel met een eigen printer: alleen die printer(s).
            $toegestaan = in_array(strtolower((string) $share), $alleenVoorTegel, true);
        } else {
            // Gewone tegel: soort moet passen en een aan een andere tegel vastgezette printer valt weg.
            $toegestaan = $eigenaar === null && geevePrinterPastBijTegel($printer, $tile);
        }
        if ($toegestaan) {
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

    // Vangnet naast het saneren van ingestuurde HTML (shared/printsaneren.php): de pagina mag zelf niets van elders ophalen
    // of uitvoeren, alleen de eigen stylesheets (file:), afbeeldingen/lettertypen van schijf en het meetscript hieronder.
    $nonce = bin2hex(random_bytes(12));
    $links = '<meta http-equiv="Content-Security-Policy" content="default-src \'none\'; style-src \'unsafe-inline\' file:; '
        . 'img-src data: file:; font-src file:; script-src \'nonce-' . $nonce . '\'; base-uri \'none\'; form-action \'none\'">' . "\n";
    $printRegels = '';
    foreach (array_merge(['shared/tokens.css', 'shared/style.css'], $cssFiles) as $css) {
        $path = geevePortalDir() . '/' . ltrim($css, '/');
        $links .= '<link rel="stylesheet" href="file://' . htmlspecialchars($path, ENT_QUOTES) . '">' . "\n";
        $printRegels .= geeveMediaPrintBlocks((string) @file_get_contents($path));
    }
    $passendScript = '';
    if ($passend !== null) {
        // Meten kan alleen op "scherm"; daarom gelden de printregels hier ook op scherm, bij de printbreedte
        // (vaste breedte 186 mm = A4 min 2 x 12 mm marge). Het script zet daarna per te hoog element een zoom.
        $links .= "<style>@media screen {\n" . $printRegels . "\nhtml, body { margin: 0; padding: 0; background: none; }\n#printSheet { width: 186mm; }\n}</style>\n";
        $passendScript = '<script nonce="' . $nonce . '">(function () {'
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
function geevePrintHtml(string $bodyHtml, array $cssFiles, string $queue, string $title, ?array $passend = null, string $sheetClass = '', array $afdruk = []): string
{
    $printer = geevePrinterList()[$queue] ?? null;
    if ($printer === null) {
        throw new RuntimeException('Onbekende printer: ' . $queue);
    }
    if (geevePrinterSoort($printer) === 'label') {
        return geevePrintLabels($bodyHtml, $cssFiles, $queue, $title, $sheetClass, $afdruk);
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
/** Zoveel brother_ql-omzettingen tegelijk (de server heeft 20 kernen en 7,6 GB geheugen; één omzetting duurt ca. 0,06 s). */
const GEEVE_BROTHER_QL_TEGELIJK = 8;

/**
 * Print een PDF op een Brother QL-1100/1050: elke pagina wordt een bitmap van 1164 x 526 punten (300 dpi, het
 * printbare vlak van een 102 x 51 mm-label; de pagina wordt passend verkleind en gecentreerd), brother_ql zet die om
 * in QL-rasteropdrachten en die gaan kaal (raw) naar de share.
 */
function geeveBrotherPrint(string $pdfFile, array $printer, string $queue, string $title, string $workDir, bool $snijdEinde = false, array $afdruk = []): string
{
    $python = GEEVE_BROTHER_QL_DIR . '/bin/python3';
    $create = GEEVE_BROTHER_QL_DIR . '/bin/brother_ql_create';
    if (!is_executable($create) || !is_executable($python)) {
        throw new RuntimeException('brother_ql staat niet op de server (' . GEEVE_BROTHER_QL_DIR . '), Brother-labelprinters kunnen daardoor niet printen.');
    }
    $dots = GEEVE_BROTHER_QL_LABEL;
    $raster = geeveRun(['/usr/bin/pdftoppm', '-gray', '-r', '300', '-scale-to-y', (string) $dots['hoogte'], '-scale-to-x', '-1', $pdfFile, $workDir . '/ql'], 60);
    $pages = glob($workDir . '/ql-*.pgm') ?: [];
    sort($pages);
    if ($raster['code'] !== 0 || $pages === []) {
        throw new RuntimeException('De labels konden niet worden omgezet naar een afbeelding' . ($raster['code'] === 127 ? ' (pdftoppm ontbreekt: installeer poppler-utils).' : ': ' . geeveLastLine($raster['err'])));
    }
    // Eén Python-start voor alle pagina's (PIL laden kost per start merkbaar tijd), daarna de brother_ql-omzettingen
    // tegelijk i.p.v. na elkaar.
    $pad = 'import sys\nfrom PIL import Image\n'
        . 'w, h, grens = int(sys.argv[1]), int(sys.argv[2]), int(sys.argv[3])\n'
        . 'for pad in sys.argv[4:]:\n'
        . '    src = Image.open(pad).convert("L")\n'
        . '    if src.width > w or src.height > h:\n'
        . '        f = min(w / src.width, h / src.height)\n'
        . '        src = src.resize((max(1, int(src.width * f)), max(1, int(src.height * f))))\n'
        . '    src = src.point(lambda p: 0 if p < grens else 255).convert("1")\n'
        . '    canvas = Image.new("1", (w, h), 1)\n'
        . '    canvas.paste(src, ((w - src.width) // 2, (h - src.height) // 2))\n'
        . '    canvas.save(pad[:-4] + ".png")\n';
    // Zwarting: zwart worden de beeldpunten die donkerder zijn dan deze grijswaarde (50 % = 128 is de standaard).
    $grens = (int) round(($afdruk['zwarting'] ?? 50) / 100 * 255);
    $fit = geeveRun(array_merge([$python, '-c', str_replace('\\n', "\n", $pad), (string) $dots['breedte'], (string) $dots['hoogte'], (string) $grens], $pages), 60);
    if ($fit['code'] !== 0) {
        throw new RuntimeException('De labels konden niet worden klaargemaakt: ' . geeveLastLine($fit['err']));
    }
    $commands = [];
    foreach ($pages as $i => $page) {
        // Oudere brother_ql_create kent alleen --no-cut (snijden is de standaard); --cut geeft een fout.
        $opties = $snijdEinde && $i < count($pages) - 1 ? ['--no-cut'] : [];
        if (($afdruk['snelheid'] ?? 0) === 1) {
            $opties[] = '--lq'; // lagere afdrukkwaliteit, sneller
        }
        $commands[$i] = array_merge([$create, '--model', (string) ($printer['model'] ?? 'QL-1100'), '--label-size', $dots['maat']], $opties, [substr($page, 0, -4) . '.png', $workDir . '/label' . $i . '.bin']);
    }
    // Hooguit GEEVE_BROTHER_QL_TEGELIJK omzettingen tegelijk: bij honderden stickers alles tegelijk starten (één Python per
    // label) vult het geheugen van de server. Het resultaat gaat label voor label naar het bestand, niet eerst in het geheugen.
    $file = $workDir . '/labels.bin';
    $uit = fopen($file, 'wb');
    if ($uit === false) {
        throw new RuntimeException('Kon het printbestand niet wegschrijven.');
    }
    try {
        foreach (geeveRunParallel($commands, 60, GEEVE_BROTHER_QL_TEGELIJK) as $i => $make) {
            $out = $workDir . '/label' . $i . '.bin';
            if ($make['code'] !== 0 || !is_file($out)) {
                throw new RuntimeException('brother_ql kon het label niet omzetten: ' . geeveLastLine($make['err'] . $make['out']));
            }
            fwrite($uit, (string) file_get_contents($out));
            @unlink($out);
        }
    } finally {
        fclose($uit);
    }
    return geeveSmbPrint($queue, $file, $title);
}

/**
 * Print de labels in een HTML-fragment op een kale TSPL-labelprinter: PDF op labelformaat (de @page-regel van de app),
 * per pagina een zwart-wit bitmap op de resolutie van de printer, als BITMAP-opdracht naar de share.
 * Heeft pdftoppm (poppler-utils) nodig.
 */
function geevePrintLabels(string $bodyHtml, array $cssFiles, string $queue, string $title, string $sheetClass = '', array $afdruk = []): string
{
    $printer = geevePrinterList()[$queue] ?? null;
    if ($printer === null || geevePrinterSoort($printer) !== 'label') {
        throw new RuntimeException('Dit is geen labelprinter.');
    }
    // Voorkom dat een aangeleverd label lokale bestanden of andere pagina's binnenhaalt.
    if (preg_match('#(file:|<iframe|<object|<embed|<link|@import|<base)#i', $bodyHtml) === 1) {
        throw new RuntimeException('Het label bevat niet-toegestane inhoud.');
    }
    // Veel labels (honderden stickers, of Slanglabels met supersampling) kunnen in PHP langer rekenen dan max_execution_time
    // (30 s op de server); dan zou de print halverwege afbreken.
    @set_time_limit(180);
    $dpi = (int) ($printer['dpi'] ?? 300);
    $streepjes = [];
    // Snelheid volgens het printermodel (bijv. Altec TTP-343 Plus: 1-3, standaard 3). Bij TSPL gaat een vaste snelheid van
    // de tegel (GEEVE_LABEL_MEDIUM, Slanglabels) voor op de standaard van het model.
    if (($printer['taal'] ?? '') !== 'brother') {
        $mediumSnelheid = ($printer['taal'] ?? '') === 'tspl' && isset(GEEVE_LABEL_MEDIUM[$sheetClass]['snelheid']) ? (int) GEEVE_LABEL_MEDIUM[$sheetClass]['snelheid'] : null;
        $snelheid = geeveLabelSnelheidVoorPrint($printer, isset($afdruk['snelheid']) ? (int) $afdruk['snelheid'] : null, $mediumSnelheid);
        if ($snelheid !== null) {
            $afdruk['snelheid'] = $snelheid;
        }
    }
    $workDir = geeveWorkDir();
    try {
        if (($printer['taal'] ?? '') === 'tspl') {
            // Artikelsticker (70 x 25 mm): compacte opdracht met de eigen lettertypen van de printer, zonder Chromium en bitmap.
            if (GEEVE_TSPL_NATIEF_TEKST) {
                $natief = geeveTsplNatiefArtikelsticker($bodyHtml, $sheetClass, $afdruk);
                if ($natief !== null) {
                    $file = $workDir . '/labels.tspl';
                    file_put_contents($file, $natief);
                    return geeveSmbPrint($queue, $file, $title);
                }
            } elseif (in_array($sheetClass, GEEVE_TSPL_NATIEF_STICKERS, true)) {
                // Echte letters als bitmap van alleen de tekst; de barcode (het meeste van de data) gaat als streepjes.
                $stickers = geeveArtikelstickersUitHtml($bodyHtml);
                if ($stickers !== null) {
                    $streepjes = array_map('geeveTsplBarcodeStreepjes', $stickers);
                    $bodyHtml = '<style>.sticker--art .bc svg { visibility: hidden; }</style>' . $bodyHtml;
                }
            }
        }
        $pdfFile = geeveRenderPdf($bodyHtml, $cssFiles, $title, $workDir, null, $sheetClass);
        if (($printer['taal'] ?? '') === 'brother') {
            return geeveBrotherPrint($pdfFile, $printer, $queue, $title, $workDir, in_array($sheetClass, GEEVE_LABEL_SNIJD_EINDE, true), $afdruk);
        }
        if (($printer['taal'] ?? '') === 'zpl') {
            // Zebra (ZPL): per pagina een zwart-wit bitmap op de resolutie van de printer, als ^GFA-opdracht.
            $raster = geeveRun(['/usr/bin/pdftoppm', '-mono', '-r', (string) $dpi, $pdfFile, $workDir . '/pagina'], 60);
            $pages = glob($workDir . '/pagina-*.pbm') ?: [];
            sort($pages);
            if ($raster['code'] !== 0 || $pages === []) {
                throw new RuntimeException('De labels konden niet worden omgezet naar een afbeelding' . ($raster['code'] === 127 ? ' (pdftoppm ontbreekt: installeer poppler-utils).' : ': ' . geeveLastLine($raster['err'])));
            }
            $zpl = '';
            foreach ($pages as $i => $page) {
                $zpl .= geevePbmToZpl((string) file_get_contents($page), $afdruk, $i === 0, $i === count($pages) - 1);
            }
            $file = $workDir . '/labels.zpl';
            file_put_contents($file, $zpl);
            return geeveSmbPrint($queue, $file, $title);
        }
        $medium = GEEVE_LABEL_MEDIUM[$sheetClass] ?? [];
        $factor = (int) ($medium['supersample'] ?? 0);
        if ($factor > 1) {
            // Scherper dan -mono: eerst in grijstinten op een hoger raster, daarna per punt middelen en met een drempel zwart/wit maken.
            $raster = geeveRun(['/usr/bin/pdftoppm', '-gray', '-r', (string) ($dpi * $factor), $pdfFile, $workDir . '/pagina'], 60);
            $pages = glob($workDir . '/pagina-*.pgm') ?: [];
            sort($pages);
        } else {
            $raster = geeveRun(['/usr/bin/pdftoppm', '-mono', '-r', (string) $dpi, $pdfFile, $workDir . '/pagina'], 60);
            $pages = glob($workDir . '/pagina-*.pbm') ?: [];
            sort($pages);
        }
        if ($raster['code'] !== 0 || $pages === []) {
            throw new RuntimeException('De labels konden niet worden omgezet naar een afbeelding' . ($raster['code'] === 127 ? ' (pdftoppm ontbreekt: installeer poppler-utils).' : ': ' . geeveLastLine($raster['err'])));
        }
        $pbms = [];
        foreach ($pages as $page) {
            $pbms[] = $factor > 1 ? geevePgmNaarPbm((string) file_get_contents($page), $factor, (int) ($medium['drempel'] ?? 128)) : (string) file_get_contents($page);
        }
        $tspl = geeveTsplOpdracht($pbms, $dpi, $sheetClass, $afdruk, $streepjes);
        $file = $workDir . '/labels.tspl';
        file_put_contents($file, $tspl);
        return geeveSmbPrint($queue, $file, $title);
    } finally {
        geeveRemoveDir($workDir);
    }
}

/**
 * Maakt van de pagina's (PBM, zie geevePbmToTspl()) één TSPL-opdracht voor een tegel ($sheetClass).
 *
 * Snijder en afscheurrand (SET CUTTER / SET TEAR) worden één keer per opdracht gezet: bij het eerste label uit, bij het laatste
 * aan. Die instellingen bewaart de printer in zijn geheugen; bij elk label opnieuw zetten kostte tijd, en zonder SET TEAR OFF
 * bleef TEAR aan staan van de vorige opdracht, zodat de printer na elk label doorvoerde tot de afscheurrand en voor het
 * volgende label weer terugtrok (de pauze tussen de stickers bij grote aantallen).
 *
 * @param list<string> $pbms
 * @param list<string> $extra per label extra opdrachten (bijv. BAR) vlak voor PRINT
 */
function geeveTsplOpdracht(array $pbms, int $dpi, string $sheetClass, array $afdruk = [], array $extra = []): string
{
    $snijdEinde = in_array($sheetClass, GEEVE_LABEL_SNIJD_EINDE, true);
    $medium = GEEVE_LABEL_MEDIUM[$sheetClass] ?? [];
    $aantal = count($pbms);
    $tspl = '';
    foreach (array_values($pbms) as $i => $pbm) {
        $eerste = $i === 0;
        $laatste = $i === $aantal - 1;
        // Snijden na het laatste label: snijder en afscheuren uit bij het eerste label, aan (snijden na elk 1 label) bij het
        // laatste, en dat laatste label doorgevoerd tot de afscheurrand. De labels ertussen krijgen niets: de stand blijft gelden.
        $snijder = '';
        if ($snijdEinde) {
            $snijder = $laatste ? "SET CUTTER 1\r\nSET TEAR ON\r\n" : ($eerste ? "SET CUTTER OFF\r\nSET TEAR OFF\r\n" : '');
        }
        $tspl .= $snijder . geevePbmToTspl($pbm, $dpi, GEEVE_LABEL_DRAAI[$sheetClass] ?? '', $medium, $afdruk, $laatste, $eerste, (string) ($extra[$i] ?? ''));
    }
    return $tspl;
}

/**
 * Haalt uit het afdrukblok van de Artikelsticker (70 x 25 mm, zie stickers/assets/art-sticker.js) per sticker de omschrijving,
 * het artikelnummer en de streepjes van de barcode. Null als het blok iets anders bevat dan alleen zulke stickers (dan gaat het
 * als bitmap).
 *
 * @return list<array{desc: string, art: string, modules: int, bars: list<array{0: int, 1: int}>}>|null
 */
function geeveArtikelstickersUitHtml(string $bodyHtml): ?array
{
    if (!class_exists('DOMDocument') || strpos($bodyHtml, 'sticker--70x25') === false) {
        return null;
    }
    $dom = new DOMDocument();
    $vorige = libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="utf-8"?><body>' . $bodyHtml . '</body>', LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();
    libxml_use_internal_errors($vorige);
    $xp = new DOMXPath($dom);
    $klasse = static fn(string $naam): string => "contains(concat(' ', normalize-space(@class), ' '), ' " . $naam . " ')";
    $alle = $xp->query('//div[' . $klasse('sticker') . ']');
    $art = $xp->query('//div[' . $klasse('sticker') . ' and ' . $klasse('sticker--art') . ' and ' . $klasse('sticker--70x25') . ']');
    if ($alle === false || $art === false || $art->length === 0 || $art->length !== $alle->length) {
        return null;
    }
    $stickers = [];
    foreach ($art as $sticker) {
        $deel = static function (string $naam) use ($xp, $sticker, $klasse): string {
            $node = $xp->query('.//div[' . $klasse($naam) . ']', $sticker)->item(0);
            return $node === null ? '' : trim(preg_replace('/\s+/', ' ', $node->textContent) ?? '');
        };
        $modules = 0;
        $bars = [];
        $svg = $xp->query('.//svg', $sticker)->item(0);
        if ($svg instanceof DOMElement) {
            $viewBox = preg_split('/[\s,]+/', trim($svg->getAttribute('viewbox'))) ?: [];
            $modules = isset($viewBox[2]) ? (int) round((float) $viewBox[2]) : 0;
            // Alleen de zwarte streepjes (rect in de groep g); de eerste rect is de witte achtergrond.
            foreach ($xp->query('.//g//rect', $svg) as $rect) {
                $x = (float) $rect->getAttribute('x');
                $w = (float) $rect->getAttribute('width');
                if ($w > 0) {
                    $bars[] = [(int) round($x), max(1, (int) round($w))];
                }
            }
            // Aansluitende streepjes samenvoegen tot een.
            $samen = [];
            foreach ($bars as [$x, $w]) {
                $laatste = count($samen) - 1;
                if ($laatste >= 0 && $samen[$laatste][0] + $samen[$laatste][1] >= $x) {
                    $samen[$laatste][1] = max($samen[$laatste][1], $x + $w - $samen[$laatste][0]);
                } else {
                    $samen[] = [$x, $w];
                }
            }
            $bars = $samen;
            if ($modules <= 0 && $bars !== []) {
                $laatste = $bars[count($bars) - 1];
                $modules = $laatste[0] + $laatste[1];
            }
        }
        $stickers[] = ['desc' => $deel('desc'), 'art' => $deel('art'), 'modules' => $modules, 'bars' => $bars];
    }
    return $stickers;
}

/** Tekst voor een TEXT-opdracht van de printer: codetabel 850, zonder stuurtekens, aanhalingstekens als \["]. */
function geeveTsplTekst(string $tekst): string
{
    $tekst = (string) preg_replace('/[\x00-\x1f\x7f]+/', ' ', $tekst);
    $cp = function_exists('iconv') ? @iconv('UTF-8', 'CP850//TRANSLIT//IGNORE', $tekst) : false;
    if ($cp === false) {
        $cp = (string) preg_replace('/[^\x20-\x7e]/', '?', $tekst);
    }
    return str_replace(['\\', '"'], ['\\\\', '\\["]'], $cp);
}

/**
 * Verdeelt tekst over hooguit $maxRegels regels van hooguit $cap tekens (op woordgrenzen; een te lang woord wordt gebroken).
 * Geeft de regels, of null als het niet past.
 *
 * @return list<string>|null
 */
function geeveTekstRegels(string $tekst, int $cap, int $maxRegels): ?array
{
    $regels = [];
    $regel = '';
    foreach (preg_split('/ +/', trim($tekst)) ?: [] as $woord) {
        while (strlen($woord) > $cap) {
            if ($regel !== '') {
                $regels[] = $regel;
                $regel = '';
            }
            $regels[] = substr($woord, 0, $cap);
            $woord = substr($woord, $cap);
        }
        if ($woord === '') {
            continue;
        }
        if ($regel === '') {
            $regel = $woord;
        } elseif (strlen($regel) + 1 + strlen($woord) <= $cap) {
            $regel .= ' ' . $woord;
        } else {
            $regels[] = $regel;
            $regel = $woord;
        }
    }
    if ($regel !== '') {
        $regels[] = $regel;
    }
    return count($regels) <= $maxRegels ? $regels : null;
}

/**
 * De streepjes van de barcode van een Artikelsticker (70 x 25 mm) als BAR-opdrachten: dezelfde streepjes als in het voorbeeld,
 * elk een geheel aantal punten breed (300 dpi), gecentreerd, 7 mm hoog vanaf 9,2 mm.
 *
 * @param array{modules: int, bars: list<array{0: int, 1: int}>} $sticker
 */
function geeveTsplBarcodeStreepjes(array $sticker): string
{
    if ($sticker['modules'] <= 0 || $sticker['bars'] === []) {
        return '';
    }
    $doel = min($sticker['modules'] * 0.38 / 25.4 * 300, 65 / 25.4 * 300);
    $n = max(1, (int) floor($doel / $sticker['modules']));
    $x0 = intdiv(827 - $sticker['modules'] * $n, 2);
    $uit = '';
    foreach ($sticker['bars'] as [$bx, $bw]) {
        $uit .= 'BAR ' . ($x0 + $bx * $n) . ',109,' . ($bw * $n) . ",83\r\n";
    }
    return $uit;
}

/**
 * Maakt van de stickers uit geeveArtikelstickersUitHtml() een TSPL-opdracht voor het ALTEC-label 70 x 25 mm (300 dpi, 827 x 295
 * punten) met de ingebouwde lettertypen (TEXT, vet nagebootst met een tweede afdruk twee punten ernaast) en de streepjes van
 * de barcode als BAR (zelfde streepjes als in het voorbeeld, een geheel aantal punten breed). Indeling zoals art-sticker.css:
 * omschrijving (2 regels) 1,2-8,8 mm, barcode 9,2-16,2 mm, artikelnummer 17,2-23,8 mm, alles gecentreerd met 2,5 mm marge.
 *
 * @param list<array{desc: string, art: string, modules: int, bars: list<array{0: int, 1: int}>}> $stickers
 */
function geeveTsplNatiefOpdracht(array $stickers, string $sheetClass, array $afdruk = []): string
{
    // Ingebouwde lettertypen van de printer: lettertype => [breedte, hoogte] in punten.
    $lettertypen = ['2' => [12, 20], '3' => [16, 24], '4' => [24, 32], '5' => [32, 48]];
    $labelBreedte = 827;
    $beschikbaar = 767; // 70 mm min 2 x 2,5 mm marge
    $snijdEinde = in_array($sheetClass, GEEVE_LABEL_SNIJD_EINDE, true);
    $tekst = static function (int $x, int $y, string $font, int $mul, string $inhoud): string {
        $d = $mul >= 2 ? 2 : 1; // vet: dezelfde tekst nog eens een of twee punten verder
        return 'TEXT ' . $x . ',' . $y . ',"' . $font . '",0,' . $mul . ',' . $mul . ',"' . $inhoud . "\"\r\n"
            . 'TEXT ' . ($x + $d) . ',' . $y . ',"' . $font . '",0,' . $mul . ',' . $mul . ',"' . $inhoud . "\"\r\n";
    };
    $aantal = count($stickers);
    $uit = '';
    foreach ($stickers as $i => $sticker) {
        $eerste = $i === 0;
        $laatste = $i === $aantal - 1;
        if ($snijdEinde) {
            $uit .= $laatste ? "SET CUTTER 1\r\nSET TEAR ON\r\n" : ($eerste ? "SET CUTTER OFF\r\nSET TEAR OFF\r\n" : '');
        }
        if ($eerste) {
            $uit .= "SIZE 70 mm,25 mm\r\nGAP 3 mm,0 mm\r\n";
            if (isset($afdruk['snelheid'])) {
                $uit .= 'SPEED ' . (int) $afdruk['snelheid'] . "\r\n";
            }
            if (isset($afdruk['zwarting'])) {
                $uit .= 'DENSITY ' . (int) $afdruk['zwarting'] . "\r\n";
            }
            $uit .= "DIRECTION 0\r\nCODEPAGE 850\r\n";
        }
        $uit .= "CLS\r\n";

        // Omschrijving: gebied 14-104 (1,2-8,8 mm), hooguit 2 regels, grootste lettertype waarin het past.
        $omschrijving = (string) iconv('UTF-8', 'CP850//TRANSLIT//IGNORE', $sticker['desc']);
        if ($omschrijving === '' && $sticker['desc'] !== '') {
            $omschrijving = (string) preg_replace('/[^\x20-\x7e]/', '?', $sticker['desc']);
        }
        foreach (['4', '3', '2'] as $k => $font) {
            [$w, $h] = $lettertypen[$font];
            $regels = geeveTekstRegels($omschrijving, intdiv($beschikbaar, $w), 2);
            if ($regels !== null || $k === 2) {
                if ($regels === null) {
                    $regels = array_slice(geeveTekstRegels($omschrijving, intdiv($beschikbaar, $w), 99) ?? [], 0, 2);
                }
                break;
            }
        }
        $regels = $regels ?? [];
        $hoogteBlok = count($regels) * $h + max(0, count($regels) - 1) * 8;
        $y = 14 + intdiv(90 - $hoogteBlok, 2);
        foreach ($regels as $regel) {
            $x = intdiv($labelBreedte - strlen($regel) * $w, 2);
            $uit .= $tekst($x, $y, $font, 1, geeveTsplTekst(iconv('CP850', 'UTF-8', $regel) ?: $regel));
            $y += $h + 8;
        }

        $uit .= geeveTsplBarcodeStreepjes($sticker);

        // Artikelnummer: gebied 203-281 (17,2-23,8 mm), een regel, grootste lettertype waarin het past.
        $artikel = (string) iconv('UTF-8', 'CP850//TRANSLIT//IGNORE', $sticker['art']);
        if ($artikel === '' && $sticker['art'] !== '') {
            $artikel = (string) preg_replace('/[^\x20-\x7e]/', '?', $sticker['art']);
        }
        if ($artikel !== '') {
            $gekozen = ['2', 1];
            foreach ([['4', 2], ['5', 1], ['4', 1], ['3', 1], ['2', 1]] as [$font, $mul]) {
                $gekozen = [$font, $mul];
                if (strlen($artikel) * $lettertypen[$font][0] * $mul <= $beschikbaar) {
                    break;
                }
            }
            [$font, $mul] = $gekozen;
            [$w, $h] = $lettertypen[$font];
            $cap = intdiv($beschikbaar, $w * $mul);
            $artikel = substr($artikel, 0, $cap);
            $x = intdiv($labelBreedte - strlen($artikel) * $w * $mul, 2);
            $uit .= $tekst($x, 203 + intdiv(78 - $h * $mul, 2), $font, $mul, geeveTsplTekst(iconv('CP850', 'UTF-8', $artikel) ?: $artikel));
        }
        $uit .= "PRINT 1,1\r\n";
    }
    return $uit;
}

/**
 * Artikelsticker (70 x 25 mm) op een Altec/TSC-printer als compacte opdracht met de eigen lettertypen van de printer, of null
 * als het afdrukblok daar niet voor geschikt is (dan gaat het als bitmap, zie geevePrintLabels()).
 */
function geeveTsplNatiefArtikelsticker(string $bodyHtml, string $sheetClass, array $afdruk = []): ?string
{
    if (!in_array($sheetClass, GEEVE_TSPL_NATIEF_STICKERS, true)) {
        return null;
    }
    $stickers = geeveArtikelstickersUitHtml($bodyHtml);
    return $stickers === null ? null : geeveTsplNatiefOpdracht($stickers, $sheetClass, $afdruk);
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
function geevePbmCanvas(string $data, int $width, int $height, int $newW, int $newH, int $shiftX = 0, int $shiftY = 0): array
{
    $rowBytes = intdiv($width + 7, 8);
    $newRowBytes = intdiv($newW + 7, 8);
    $out = array_fill(0, $newRowBytes * $newH, 0);
    $dx = $newW - $width + $shiftX;
    $dy = intdiv($newH - $height, 2) + $shiftY;
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

/**
 * Zet een 8-bit grijs PGM (P5) op een $factor-voud van de printerresolutie om in een 1-bit PBM (P4, 1 = zwart) op de
 * printerresolutie: per punt het gemiddelde van $factor x $factor beeldpunten, zwart als dat onder $drempel (0-255) ligt.
 * Een drempel boven 128 maakt dunne lijntjes en kleine letters dikker.
 */
function geevePgmNaarPbm(string $pgm, int $factor, int $drempel): string
{
    if (preg_match('/^P5\s+(?:#[^\n]*\n\s*)*(\d+)\s+(\d+)\s+255\s/', $pgm, $m, PREG_OFFSET_CAPTURE) !== 1) {
        throw new RuntimeException('Onverwacht afbeeldingsformaat van pdftoppm.');
    }
    $bigW = (int) $m[1][0];
    $bigH = (int) $m[2][0];
    $data = substr($pgm, $m[0][1] + strlen($m[0][0]), $bigW * $bigH);
    if (strlen($data) !== $bigW * $bigH) {
        throw new RuntimeException('De afbeelding van het label is onvolledig.');
    }
    $width = intdiv($bigW, $factor);
    $height = intdiv($bigH, $factor);
    $rowBytes = intdiv($width + 7, 8);
    $grens = $drempel * $factor * $factor;
    $out = array_fill(0, $rowBytes * $height, 0);
    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            $som = 0;
            for ($dy = 0; $dy < $factor; $dy++) {
                $start = ($y * $factor + $dy) * $bigW + $x * $factor;
                for ($dx = 0; $dx < $factor; $dx++) {
                    $som += ord($data[$start + $dx]);
                }
            }
            if ($som < $grens) {
                $out[$y * $rowBytes + ($x >> 3)] |= 1 << (7 - ($x & 7));
            }
        }
    }
    return "P4\n" . $width . ' ' . $height . "\n" . pack('C*', ...$out);
}

/**
 * Zet één PBM-pagina (P4, 1 bit per punt, 1 = zwart) om in een ZPL-label voor een Zebra: ^PW/^LL in punten, de bitmap als
 * ^GFA (ASCII-hex, 1 = zwart, geen inversie nodig) en ^XZ. Rijen zijn intdiv(breedte+7,8) bytes, de opvulbits zijn 0 (wit).
 */
function geevePbmToZpl(string $pbm, array $afdruk = [], bool $eerste = true, bool $laatste = true): string
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
    // Alleen het bedrukte deel sturen (^FO met de plaats ervan); het label (^PW/^LL) blijft even groot. Kolommen op bytegrens.
    $x = 0;
    $y = 0;
    $hoogte = $height;
    $bytes = $rowBytes;
    $eersteRij = null;
    $laatsteRij = null;
    $minByte = $rowBytes;
    $maxByte = -1;
    for ($r = 0; $r < $height; $r++) {
        $rij = substr($data, $r * $rowBytes, $rowBytes);
        if (trim($rij, "\0") === '') {
            continue;
        }
        $eersteRij = $eersteRij ?? $r;
        $laatsteRij = $r;
        $minByte = min($minByte, strlen($rij) - strlen(ltrim($rij, "\0")));
        $maxByte = max($maxByte, strlen(rtrim($rij, "\0")) - 1);
    }
    if ($eersteRij !== null) {
        $y = $eersteRij;
        $hoogte = $laatsteRij - $eersteRij + 1;
        $x = $minByte * 8;
        $bytes = $maxByte - $minByte + 1;
        $uitsnede = '';
        for ($r = $eersteRij; $r <= $laatsteRij; $r++) {
            $uitsnede .= substr($data, $r * $rowBytes + $minByte, $bytes);
        }
        $data = $uitsnede;
    }
    $total = $bytes * $hoogte;
    // Zwarting (~SD 0-30) staat vóór ^XA; snelheid (^PR, inch/s) erin. Zonder keuze geldt de instelling van de printer zelf.
    // Zwarting, snelheid en labelformaat blijven in de Zebra staan: alleen bij het eerste label sturen i.p.v. bij elk label.
    $voor = $eerste && isset($afdruk['zwarting']) ? '~SD' . (int) $afdruk['zwarting'] . "\r\n" : '';
    $snelheid = $eerste && isset($afdruk['snelheid']) ? '^PR' . (int) $afdruk['snelheid'] . "\r\n" : '';
    $kop = $eerste ? "^CI28\r\n^PW" . $width . "\r\n^LL" . $height . "\r\n^LH0,0\r\n" : '';
    // ^XB (alle labels behalve het laatste): niet doorvoeren tot de afscheurrand, dus ook niet terugtrekken voor het volgende
    // label. Zo print een reeks labels achter elkaar door; alleen het laatste label gaat door tot de afscheurrand.
    $geenDoorvoer = $laatste ? '' : "^XB\r\n";
    return $voor . "^XA\r\n" . $snelheid . $kop . $geenDoorvoer . '^FO' . $x . ',' . $y . '^GFA,' . $total . ',' . $total . ',' . $bytes . ',' . strtoupper(bin2hex($data))
        . "^FS\r\n^PQ1\r\n^XZ\r\n";
}

/** Zet één PBM-pagina (P4, 1 bit per punt, 1 = zwart) om in een TSPL-label: SIZE, BITMAP, PRINT. */
function geevePbmToTspl(string $pbm, int $dpi, string $draai = '', array $medium = [], array $afdruk = [], bool $laatste = true, bool $eerste = true, string $extra = ''): string
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
        $shiftX = (int) round((float) ($medium['verschuifX'] ?? 0) / 25.4 * $dpi);
        $shiftY = (int) round((float) ($medium['verschuifY'] ?? 0) / 25.4 * $dpi);
        [$data, $width, $height] = geevePbmCanvas($data, $width, $height, $newW, $newH, $shiftX, $shiftY);
        $rowBytes = intdiv($width + 7, 8);
    }
    // Alleen het bedrukte deel als BITMAP sturen (SIZE blijft het hele label). De printer en zijn netwerkverbinding verwerken
    // de bitmapdata langzaam (gemeten: een bijna leeg label gaat vlot, een vol label geeft een pauze van een halve tot een
    // hele seconde). Daarom geen enkele bitmap met alle witruimte, maar per blok onder elkaar (gescheiden door minstens
    // GEEVE_TSPL_BAND_LEEG lege rijen) een eigen BITMAP, elk bijgesneden tot de bedrukte kolommen van dat blok (op bytegrens).
    // Het resultaat op het label blijft punt voor punt gelijk.
    $blokken = [];
    $huidig = null;
    $leeg = 0;
    for ($y = 0; $y < $height; $y++) {
        $rij = substr($data, $y * $rowBytes, $rowBytes);
        if (trim($rij, "\0") === '') {
            $leeg++;
            continue;
        }
        $links = strlen($rij) - strlen(ltrim($rij, "\0"));
        $rechts = strlen(rtrim($rij, "\0")) - 1;
        if ($huidig !== null && $leeg < GEEVE_TSPL_BAND_LEEG) {
            // Dezelfde band: de lege rijen ertussen horen erbij.
            $huidig['tot'] = $y;
            $huidig['min'] = min($huidig['min'], $links);
            $huidig['max'] = max($huidig['max'], $rechts);
        } else {
            if ($huidig !== null) {
                $blokken[] = $huidig;
            }
            $huidig = ['van' => $y, 'tot' => $y, 'min' => $links, 'max' => $rechts];
        }
        $leeg = 0;
    }
    if ($huidig !== null) {
        $blokken[] = $huidig;
    }
    $pad = intdiv($width + 7, 8) * 8 - $width;
    $bitmaps = '';
    foreach ($blokken as $blok) {
        $bytes = $blok['max'] - $blok['min'] + 1;
        $uitsnede = '';
        for ($y = $blok['van']; $y <= $blok['tot']; $y++) {
            $uitsnede .= substr($data, $y * $rowBytes + $blok['min'], $bytes);
        }
        $hoogte = $blok['tot'] - $blok['van'] + 1;
        if (GEEVE_TSPL_ZWART_IS_NUL) {
            $uitsnede = ~$uitsnede;
            // Opvulbits aan het eind van elke rij blijven wit (1); alleen als het blok tot de rechterbyte van het label loopt.
            if ($pad > 0 && $blok['max'] === $rowBytes - 1) {
                $mask = chr((1 << $pad) - 1);
                for ($y = 0; $y < $hoogte; $y++) {
                    $i = $y * $bytes + $bytes - 1;
                    $uitsnede[$i] = $uitsnede[$i] | $mask;
                }
            }
        }
        $bitmaps .= 'BITMAP ' . ($blok['min'] * 8) . ',' . $blok['van'] . ',' . $bytes . ',' . $hoogte . ',0,' . $uitsnede . "\r\n";
    }
    if ($bitmaps === '') {
        // Helemaal leeg label: geen bitmap sturen, alleen printen.
        $bitmaps = '';
    }
    // 25,1 mm (afronding van de pdf-maat) wordt 25 mm; echte halve maten blijven staan.
    $mm = static fn(int $dots): float => abs($dots / $dpi * 25.4 - round($dots / $dpi * 25.4)) < 0.3 ? round($dots / $dpi * 25.4) : round($dots / $dpi * 25.4, 1);
    $widthMm = $mm($width);
    $heightMm = $mm($height);
    // Snelheid (inch/s) en zwarting (0-15): de waarden uit het scherm gaan voor op die van het medium.
    $instelling = '';
    $snelheid = $afdruk['snelheid'] ?? $medium['snelheid'] ?? null;
    $zwarting = $afdruk['zwarting'] ?? $medium['zwarting'] ?? null;
    if ($snelheid !== null) {
        $instelling .= 'SPEED ' . $snelheid . "\r\n";
    }
    if ($zwarting !== null) {
        $instelling .= 'DENSITY ' . $zwarting . "\r\n";
    }
    // Met een vast medium zonder 'gap' geen GAP-regel: de printer gebruikt zijn eigen gemeten tussenruimte. Met 'gap' (mm) staat
    // die vast, zoals in de Windows-driver (Gap/Mark size 2 mm, offset 0).
    $gapRegel = $medium === [] ? "GAP 3 mm,0 mm\r\n" : (isset($medium['gap']) ? 'GAP ' . $medium['gap'] . " mm,0 mm\r\n" : '');
    $gap = '';
    // Zonder snijder: alleen na het laatste label doorvoeren tot de afscheurrand. Tussen de labels niet: bij elk label
    // doorvoeren en weer terugtrekken gaf een andere startpositie per label (tekst ging van label tot label heen en weer).
    // SET CUTTER en SET TEAR bewaart de printer: alleen bij het eerste en het laatste label sturen, niet bij elk label.
    if (!empty($medium['tear'])) {
        $gap .= ($eerste ? "SET CUTTER OFF\r\n" : '') . ($laatste ? "SET TEAR ON\r\n" : ($eerste ? "SET TEAR OFF\r\n" : ''));
        if ($laatste && ($medium['offset'] ?? 0) != 0) {
            $gap .= 'OFFSET ' . round((float) $medium['offset'], 1) . " mm\r\n";
        }
    }
    // Formaat, tussenruimte, snelheid en zwarting gelden voor de hele opdracht: alleen bij het eerste label sturen. Bij elk
    // label opnieuw instellen kostte de printer tijd tussen de labels (bij veel stickers merkbaar).
    $kop = $eerste ? 'SIZE ' . $widthMm . ' mm,' . $heightMm . " mm\r\n" . $gapRegel . $instelling . "DIRECTION 0\r\n" : '';
    return $kop . $gap . "CLS\r\n" . $bitmaps . $extra . "PRINT 1,1\r\n";
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

/**
 * Voert meerdere programma's tegelijk uit (zelfde sleutels als $commands); geeft per sleutel ['code', 'out', 'err'].
 * $maxTegelijk > 0: hooguit zoveel tegelijk, de rest wacht in de rij. De tijdslimiet geldt per programma, vanaf zijn start.
 */
function geeveRunParallel(array $commands, int $timeoutSeconds, int $maxTegelijk = 0): array
{
    $wachtrij = $commands;
    $running = [];
    $result = [];
    while ($wachtrij !== [] || $running !== []) {
        while ($wachtrij !== [] && ($maxTegelijk <= 0 || count($running) < $maxTegelijk)) {
            $key = array_key_first($wachtrij);
            $command = $wachtrij[$key];
            unset($wachtrij[$key]);
            $process = @proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, ['PATH' => '/usr/local/bin:/usr/bin:/bin']);
            if (!is_resource($process)) {
                $result[$key] = ['code' => 127, 'out' => '', 'err' => 'Programma niet gevonden: ' . $command[0]];
                continue;
            }
            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);
            $running[$key] = ['process' => $process, 'pipes' => $pipes, 'out' => '', 'err' => '', 'deadline' => microtime(true) + $timeoutSeconds];
        }
        foreach ($running as $key => &$job) {
            $job['out'] .= (string) stream_get_contents($job['pipes'][1]);
            $job['err'] .= (string) stream_get_contents($job['pipes'][2]);
            $status = proc_get_status($job['process']);
            $timeout = microtime(true) > $job['deadline'];
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
        if ($running !== [] && ($wachtrij === [] || ($maxTegelijk > 0 && count($running) >= $maxTegelijk))) {
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
    $standaard = GEEVE_PRINT_TILE_STANDAARD_PRINTER[$tileId] ?? '';
    foreach ($printers as $queue => $name) {
        if ($standaard !== '' && strcasecmp((string) $queue, $standaard) === 0) {
            $standaard = (string) $queue;
            break;
        }
    }
    $selected = $saved === 'browser' || isset($printers[$saved]) ? $saved : (isset($printers[$standaard]) ? $standaard : (string) array_key_first($printers));
    $options = '';
    foreach ($printers as $queue => $name) {
        $info = geevePrinterList()[$queue] ?? [];
        $taal = (string) ($info['taal'] ?? '');
        // Snelheidsbereik en standaard van dit printermodel, voor de keuzelijst Snelheid (direct-print.js).
        $snelheid = '';
        $grenzen = geeveLabelSnelheid($info);
        if ($grenzen !== null) {
            $standaard = geeveLabelSnelheidVoorPrint($info, null, $taal === 'tspl' && isset(GEEVE_LABEL_MEDIUM[GEEVE_PRINT_TILE_PAGE[$tileId]['class'] ?? '']['snelheid']) ? (int) GEEVE_LABEL_MEDIUM[GEEVE_PRINT_TILE_PAGE[$tileId]['class'] ?? '']['snelheid'] : null);
            $snelheid = ' data-snelheid-min="' . $grenzen['min'] . '" data-snelheid-max="' . $grenzen['max'] . '" data-snelheid-standaard="' . ($standaard ?? '') . '"';
        }
        $options .= '<option value="' . $h($queue) . '" data-taal="' . $h($taal) . '"' . $snelheid . ($selected === $queue ? ' selected' : '') . '>' . $h($name) . '</option>';
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
        . '<span>Printer</span><select id="directPrinter">' . $options . '</select>'
        . '<span class="direct-print-status" id="directPrintStatus"></span></label>';
}

/**
 * De velden "Snelheid" en "Zwarting" om boven de printerkeuze en de Print-knop te zetten; alleen voor tegels in
 * GEEVE_PRINT_TILE_AFDRUK_INSTELLING, anders leeg. Per soort labelprinter een eigen set; direct-print.js toont de set
 * die bij de gekozen printer hoort (data-taal van de optie) en verbergt de rest. Brother QL: Snelheid Standaard/Snel,
 * Zwarting 10-90 %. TSPL (Altec/TSC): Snelheid 1-12, Zwarting 0-15. ZPL (Zebra): Snelheid 2-6, Zwarting 0-30. Standaard = standaard van de printer.
 * Het bereik en de standaard van Snelheid hangen af van het printermodel (GEEVE_LABEL_MODEL_SNELHEID); direct-print.js past de lijst aan
 * de gekozen printer aan (bijv. Altec TTP-343 Plus: 1-3, standaard 3).
 */
function geevePrintAfdrukInstellingenHtml(string $tileId): string
{
    if (!in_array($tileId, GEEVE_PRINT_TILE_AFDRUK_INSTELLING, true)) {
        return '';
    }
    // $vet: de waarde die "Standaard" werkelijk is; die staat in de lijst vetgedrukt, ook als Standaard is gekozen.
    $sheet = GEEVE_PRINT_TILE_PAGE[$tileId]['class'] ?? '';
    $medium = GEEVE_LABEL_MEDIUM[$sheet] ?? [];
    $keuzes = static function (string $id, string $titel, array $waarden, string $standaard = '', ?int $vet = null): string {
        // Is de standaardwaarde van de printer bekend, dan heet de optie "Standaard" zo (bv. "12 (standaard)") en staat vet.
        $opties = '<option value=""' . ($standaard === '' ? ' selected' : '')
            . ($vet !== null ? ' class="is-standaard" style="font-weight:bold">' . ($waarden[$vet] ?? $vet) . ' (standaard)' : '>Standaard') . '</option>';
        foreach ($waarden as $waarde => $tekst) {
            $opties .= '<option value="' . $waarde . '"' . ((string) $waarde === $standaard ? ' selected' : '') . '>' . $tekst . '</option>';
        }
        return '<select id="' . $id . '" title="' . $titel . '">' . $opties . '</select>';
    };
    $procenten = [];
    foreach (range(10, 90, 10) as $procent) {
        $procenten[$procent] = $procent . ' %';
    }
    $set = static fn(string $taal, string $snelheid, string $zwarting): string => '<div class="direct-print-instelling" data-taal="' . $taal . '" hidden>'
        . '<label>Snelheid ' . $snelheid . '</label><label>Zwarting ' . $zwarting . '</label></div>';
    return '<div id="directPrintAfdruk">'
        . $set('brother',
            $keuzes('directSnelheid-brother', 'Snel = lagere afdrukkwaliteit (Brother QL); voorgeselecteerd', [1 => 'Snel'], '1'),
            $keuzes('directZwarting-brother', 'Zwarting in % (hoger = zwarter); Standaard = 50 %', $procenten, '', 50))
        . $set('zpl',
            $keuzes('directSnelheid-zpl', 'Printsnelheid in inch/s (^PR); het bereik hangt af van de printer, Standaard = standaard van de printer', array_combine(range(2, 6), range(2, 6))),
            $keuzes('directZwarting-zpl', 'Zwarting (0-30, ~SD, hoger = zwarter); Standaard = standaard van de printer', array_combine(range(0, 30), range(0, 30))))
        . $set('tspl',
            $keuzes('directSnelheid-tspl', 'Printsnelheid in inch/s; het bereik hangt af van de printer (Altec TTP-343 Plus: 1-3, standaard 3)', array_combine(range(1, 12), range(1, 12)), '', isset($medium['snelheid']) ? (int) $medium['snelheid'] : null),
            $keuzes('directZwarting-tspl', 'Zwarting (0-15, hoger = zwarter); Standaard = standaard van de printer', array_combine(range(0, 15), range(0, 15)), '', isset($medium['zwarting']) ? (int) $medium['zwarting'] : null))
        . '</div>';
}
