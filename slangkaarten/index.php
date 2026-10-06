<?php

declare(strict_types=1);

// Voorkomt dat een browser/proxy een eerder opgehaalde pagina hergebruikt
// i.p.v. de resultaten van een nieuwe zoekopdracht op te halen.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// Eén gedeeld versienummer voor hoofdscherm + alle subapps (version.php op
// rootniveau) - valt terug op deze waarde als dat bestand ontbreekt (bv.
// deze map los buiten de portal gedeployed).
define('APP_VERSION', is_file(__DIR__ . '/../version.php') ? (string) require __DIR__ . '/../version.php' : '0.2.1');

require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/queries.php';

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

/**
 * Zorgt dat een decimaal getal kleiner dan 1 altijd met een voorloop-0
 * begint (bv. ",34" of ".34" -> "0,34"/"0.34") - SQL Server/ODBC laat de
 * voorloop-0 soms weg bij het omzetten van een float/decimal naar tekst,
 * en niet elk getal op de kaart loopt via formatQuantity() (bv. Lengte,
 * Krimpmaten - die komen rechtstreeks uit de database/CSV). Laat
 * niet-numerieke/lege waarden en getallen die al met een cijfer beginnen
 * ongewijzigd.
 */
function ensureLeadingZero(string $value): string
{
    return preg_replace('/^(-?)([.,])/', '${1}0${2}', $value) ?? $value;
}

/**
 * "Aantal" (aantal slangen) staat als decimal(18,3) in de database
 * ("1.000", "4.000", ...), maar is altijd een geheel aantal - toont dit
 * zonder decimalen. Niet-numerieke/lege waarden blijven ongewijzigd.
 */
function formatWholeNumber(string $value): string
{
    $normalized = str_replace(',', '.', trim($value));

    return is_numeric($normalized) ? (string) (int) round((float) $normalized) : $value;
}

/**
 * Toont een decimaal aantal in Nederlandse notatie, overbodige nullen
 * weggelaten (bv. "1.300" -> "1,3", "2.000" -> "2") - in tegenstelling
 * tot "Aantal slangen" is het aantal van een extra artikel niet per se
 * een geheel getal. Niet-numerieke/lege waarden blijven ongewijzigd.
 * $decimals bepaalt op hoeveel decimalen afgerond wordt (Voorraad op de
 * picklijst gebruikt hiervoor 1 i.p.v. de standaard 3).
 */
function formatQuantity(string $value, int $decimals = 3): string
{
    $normalized = str_replace(',', '.', trim($value));
    if (!is_numeric($normalized)) {
        return $value;
    }

    $formatted = rtrim(rtrim(number_format((float) $normalized, $decimals, '.', ''), '0'), '.');

    return ensureLeadingZero(str_replace('.', ',', $formatted));
}

/**
 * Toont datum + tijd zonder milliseconden (bv. "2026-09-24 14:03:00"
 * i.p.v. "2026-09-24 14:03:00.000" - orddat/syscreated/sysmodified komen
 * met een millisecondencomponent uit de database die nooit relevant is).
 * Onherkenbare/lege waarden blijven ongewijzigd (zie parseDutchDateTime()
 * in inc/queries.php).
 */
/**
 * Alleen de datum ("2026-10-06"), zonder tijd - voor de orderdatum (orddat),
 * dat een datum zonder tijdcomponent is (altijd 00:00:00 in de database).
 */
function formatDate(string $value): string
{
    if ($value === '') {
        return '';
    }

    $date = parseDutchDateTime($value);

    return $date !== null ? $date->format('Y-m-d') : $value;
}

function formatDateTime(string $value): string
{
    if ($value === '') {
        return '';
    }

    $date = parseDutchDateTime($value);

    return $date !== null ? $date->format('Y-m-d H:i:s') : $value;
}

/** Vertaalt de ord_soort-code naar leesbare tekst. Onbekende codes blijven ongewijzigd. */
function formatOrderType(string $value): string
{
    return match ($value) {
        'V' => 'Order',
        'Q' => 'Quote',
        default => $value,
    };
}

/**
 * Vertaalt een bit-vinkje naar "Ja"/"Nee". De meeste CARD_FLAG_SLOTS-
 * kolommen zijn NOT NULL en komen al als "Ja"/"Nee"-tekst uit de database,
 * maar sommige (bv. DNVCertificaat) zijn nullable - een lege/NULL-waarde
 * gaf daardoor een streepje i.p.v. "Nee". Behandelt leeg/0/false ook als
 * "Nee", zodat alle vinkjes hetzelfde ogen.
 */
function formatFlag(string $value): string
{
    $normalized = strtolower(trim($value));

    if ($normalized === '' || in_array($normalized, ['0', 'false', 'nee', 'no', 'n'], true)) {
        return 'Nee';
    }
    if (in_array($normalized, ['1', 'true', 'ja', 'yes', 'y'], true)) {
        return 'Ja';
    }
    if (is_numeric($normalized)) {
        return (float) $normalized !== 0.0 ? 'Ja' : 'Nee';
    }

    return $value;
}

/**
 * Veldlabels + kandidaat-kolomnamen voor de kaart. Zie inc/queries.php
 * voor uitleg over pick() en waarom dit met kandidaat-lijsten werkt i.p.v.
 * vaste kolomnamen (schema nog niet geverifieerd).
 */
const UW_REFERENTIE_CANDIDATES = ['Uw_referentie', 'Uw Referentie', 'UwReferentie'];

// "ord_soort" ("order soort") is de beste kandidaat uit het bevestigde
// schema voor Order/Offerte - de daadwerkelijke waarde (tekst "Order"/
// "Offerte" of een code) is nog niet met echte data geverifieerd.
const ORDER_TYPE_CANDIDATES = ['ord_soort', 'Ordersoort', 'Order soort', 'Soort'];

const SLANGTYPE_CANDIDATES = ['SlangType', 'Slang type', 'Slangtype', 'Type'];
const LENGTE_CANDIDATES = ['Lengte', 'Lengte / Prijs', 'Lengte/Prijs'];

const CARD_DETAIL_FIELDS = [
    ['label' => 'Referentie',      'candidates' => ['Referentie']],
    ['label' => 'Uw Referentie',   'candidates' => UW_REFERENTIE_CANDIDATES],
    ['label' => 'Omschrijving',    'candidates' => ['Omschrijving']],
    ['label' => 'Slang type',      'candidates' => SLANGTYPE_CANDIDATES],
    ['label' => 'Lengte',          'candidates' => LENGTE_CANDIDATES, 'format' => 'leadingzero'],
];

// Bevestigd tegen INFORMATION_SCHEMA.COLUMNS van "2500 Slangkaarten bij
// order" (definitieve schema-dump, geen enkele kolom heeft een prefix).
// "Proppen" heeft zowel Proppen als ProppenJN - de JN-variant (Ja/Nee)
// staat vooraan, met het andere veld als fallback.
const CARD_FLAG_SLOTS = [
    ['Labelen', ['Labelen']], ['Testen/spoelen', ['TestenSpoelen', 'Testen/spoelen', 'Testen spoelen']], ['DNV Certificaat', ['DNVCertificaat', 'DNV Certificaat']],
    ['Graveren', ['Graveren']], ['Pin prikken', ['PinPrikken', 'Pin prikken']], ['Snijlengte', ['SnijlengteJN', 'Snijlengte']],
    ['Testen', ['Testen']], ['Proppen', ['ProppenJN', 'Proppen']], null,
];

const AANTAL_CANDIDATES = ['Aantal', 'Aantal slangen', 'AantalSlangen'];
const NOTITIE_CANDIDATES = ['Notitie', 'Notite', 'Opmerking', 'Opmerkingen'];
const HOEK_CANDIDATES = ['Hoek', 'Draaihoek'];

// Losse "extra artikelen" op de order zelf (max. 2 slots) - géén
// koppelonderdeel van zijde A/B, dus een eigen kader op de kaart. Let op
// de kolomnaam-typo "AantaExtraArtikel" (zonder "l") voor slot 1 - staat
// zo echt in de database (bevestigd via INFORMATION_SCHEMA), slot 2 heet
// wel "AantalExtraArtikel2".
const EXTRA_ARTIKEL_SLOTS = [
    ['artikel' => ['ExtraArtikel'], 'aantal' => ['AantaExtraArtikel', 'AantalExtraArtikel']],
    ['artikel' => ['ExtraArtikel2'], 'aantal' => ['AantalExtraArtikel2']],
];

// Ordercrediteur/Afleveradres zijn geen losse tekstvelden, maar worden
// opgebouwd uit meerdere kolommen (bevestigd tegen het echte schema).
const ORDERCREDITEUR_CODE_CANDIDATES = ['debnr'];
const ORDERCREDITEUR_ADRES_CANDIDATES = ['ord_adres'];
const ORDERCREDITEUR_POSTCODE_CANDIDATES = ['ord_pc'];
const ORDERCREDITEUR_PLAATS_CANDIDATES = ['ord_pl'];

const AFLEVERADRES_NAAM_CANDIDATES = ['del_debnm'];
const AFLEVERADRES_ADRES_CANDIDATES = ['del_adres'];
const AFLEVERADRES_POSTCODE_CANDIDATES = ['del_pc'];
const AFLEVERADRES_PLAATS_CANDIDATES = ['del_pl'];

const ORDERDATUM_CANDIDATES = ['orddat', 'Orderdatum'];

// Aangemaakt/Laatste gewijzigd zijn ook opgebouwd (datum + naam apart).
const AANGEMAAKT_DATUM_CANDIDATES = ['syscreated'];
const AANGEMAAKT_NAAM_CANDIDATES = ['SCnm'];
const AANGEMAAKT_CANDIDATES = ['Aangemaakt'];
const GEWIJZIGD_DATUM_CANDIDATES = ['sysmodified'];
const GEWIJZIGD_NAAM_CANDIDATES = ['SMnm'];
const GEWIJZIGD_CANDIDATES = ['Laatste gewijzigd', 'Laatst gewijzigd'];

// Bevestigd via een echte SELECT * op beide tabellen: het artikel/
// onderdeelnummer staat in "Zijde AA" (tabel Zijde A) resp. "Zijde BB"
// (tabel Zijde B) - bijv. "10217-48-48RVS". "Coupling A"/"Coupling B"
// bleken in de praktijk altijd leeg, en "koppeling" bevat een letterlijke
// "0"-placeholder (geen echt artikelnummer) - allebei laten staan als
// fallback voor het geval een andere order ze wel gebruikt.
const ARTIKELNUMMER_CANDIDATES = ['Zijde AA', 'Zijde BB', 'Coupling A', 'Coupling B', 'koppeling', 'Artikelnummer', 'ArtikelNr', 'Artikel'];
const QTY_CANDIDATES = ['Aantal_A', 'Aantal_B', 'Qty', 'Aantal'];

/**
 * Kolommen van het selecteerbare regel-overzicht (stap voor het printen).
 * Volgorde en keuze op verzoek vastgesteld; Ordernummer/Klant staan in de
 * koptekst van stap 2 i.p.v. als kolom (zie index.php, sectie "hoseLines").
 * Koppeling A/B staan hier niet bij - die komen niet uit deze tabel zelf
 * maar worden per regel bijgehaald (zie enrichHoseLinesWithCouplings()),
 * en krijgen elk artikel een eigen kolom (zie renderHoseLinesForm()),
 * omdat een zijde meerdere koppelonderdelen kan hebben.
 */
const REGELNUMMER_CANDIDATES = ['rgl', 'Regel', 'RegelNr'];

const LINE_OVERVIEW_FIELDS = [
    ['label' => 'Regel',         'candidates' => REGELNUMMER_CANDIDATES],
    ['label' => 'Aantal',        'candidates' => ['Aantal', 'Aantal slangen', 'AantalSlangen'], 'format' => 'whole'],
    ['label' => 'Slangnummer',   'candidates' => ['GHnr', 'Slangnummer', 'SlangNr', 'Slang nr']],
    ['label' => 'GHnm',          'candidates' => ['GHnm', 'Omschrijving slang']],
    ['label' => 'Slang type',    'candidates' => SLANGTYPE_CANDIDATES],
    ['label' => 'Lengte',        'candidates' => LENGTE_CANDIDATES, 'format' => 'leadingzero'],
];

/** Simpele SVG-weergave van de draaihoek tussen de twee koppelzijden. */
function renderAngleSvg(?float $degrees): string
{
    if ($degrees === null) {
        return '';
    }

    $degrees = max(0.0, min(360.0, $degrees));
    $radius = 42;
    $cx = 50;
    $cy = 50;
    $startAngle = -90.0;
    $endAngle = $startAngle + $degrees;
    $largeArc = $degrees > 180 ? 1 : 0;

    $toXY = static function (float $angleDeg) use ($cx, $cy, $radius): array {
        $rad = deg2rad($angleDeg);
        return [$cx + $radius * cos($rad), $cy + $radius * sin($rad)];
    };

    [$startX, $startY] = $toXY($startAngle);
    [$endX, $endY] = $toXY($endAngle);

    $wedge = $degrees > 0
        ? sprintf(
            'M %1$s %2$s L %3$s %4$s A %5$s %5$s 0 %6$s 1 %7$s %8$s Z',
            $cx,
            $cy,
            round($startX, 2),
            round($startY, 2),
            $radius,
            $largeArc,
            round($endX, 2),
            round($endY, 2)
        )
        : '';

    $degreesLabel = floor($degrees) === $degrees
        ? number_format($degrees, 0, ',', '')
        : number_format($degrees, 1, ',', '');

    // De 2 radiuslijnen die de hoek begrenzen apart en vet getekend
    // (i.p.v. als rand van het gevulde vlak), zodat ze duidelijk
    // opvallen terwijl het vlak zelf grijs blijft.
    $angleLines = $wedge !== ''
        ? sprintf(
            '<line x1="%1$s" y1="%2$s" x2="%3$s" y2="%4$s" stroke="#111" stroke-width="2.2"></line>'
            . '<line x1="%1$s" y1="%2$s" x2="%5$s" y2="%6$s" stroke="#111" stroke-width="2.2"></line>',
            $cx,
            $cy,
            round($startX, 2),
            round($startY, 2),
            round($endX, 2),
            round($endY, 2)
        )
        : '';

    return '<svg class="angle-diagram" viewBox="0 0 100 116" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">'
        . '<circle cx="50" cy="50" r="' . $radius . '" fill="none" stroke="#999" stroke-width="1.2"></circle>'
        . ($wedge !== '' ? '<path d="' . $wedge . '" fill="#999"></path>' : '')
        . '<line x1="8" y1="50" x2="92" y2="50" stroke="#ccc" stroke-width="0.8"></line>'
        . '<line x1="50" y1="8" x2="50" y2="92" stroke="#ccc" stroke-width="0.8"></line>'
        . $angleLines
        . '<text x="50" y="108" text-anchor="middle" font-size="11" fill="#111">Hoek ' . h($degreesLabel) . '</text>'
        . '</svg>';
}

/** Rendert de key/value-blokjes bovenaan een koppelzijde-tabel (A of B). */
function renderCouplingTable(string $label, array $rows): string
{
    $html = '<div class="coupling-block"><h4>' . h($label) . '</h4>';

    if ($rows === []) {
        $html .= '<p class="coupling-empty">Geen onderdelen</p>';
    } else {
        $html .= '<div class="coupling-table-wrap"><table class="coupling-table"><thead><tr><th>Artikelnummer</th><th>Qty</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            $html .= '<tr><td>' . h(pick($row, ARTIKELNUMMER_CANDIDATES)) . '</td><td>' . h(pick($row, QTY_CANDIDATES)) . '</td></tr>';
        }
        $html .= '</tbody></table></div>';
    }

    return $html . '</div>';
}

/**
 * Rendert het "Extra artikelen"-kader: losse extra artikelen op de order
 * zelf (max. 2, zie EXTRA_ARTIKEL_SLOTS) - géén koppelonderdeel van zijde
 * A/B, dus bewust een eigen kader i.p.v. bij die tabellen.
 */
function renderExtraArtikelenTable(array $row): string
{
    $rows = [];
    foreach (EXTRA_ARTIKEL_SLOTS as $slot) {
        $artikel = pick($row, $slot['artikel']);
        if ($artikel === '') {
            continue;
        }
        $rows[] = [$artikel, formatQuantity(pick($row, $slot['aantal']))];
    }

    $html = '<div class="coupling-block extra-artikelen-block"><h4>Extra artikelen</h4>';
    $html .= '<div class="coupling-table-wrap"><table class="coupling-table"><thead><tr><th>Artikelnummer</th><th>Aantal</th></tr></thead><tbody>';

    if ($rows === []) {
        $html .= '<tr><td class="coupling-empty-cell" colspan="2">Geen extra artikelen</td></tr>';
    } else {
        foreach ($rows as [$artikel, $aantal]) {
            $html .= '<tr><td>' . h($artikel) . '</td><td>' . h($aantal) . '</td></tr>';
        }
    }

    $html .= '</tbody></table></div>';

    return $html . '</div>';
}

/**
 * Krimpmaten (Persmaat) opzoeken voor een koppel-/hulsartikel, op basis
 * van slangtype + het koppel-/hulsartikelnummer. Leest
 * /hoses/data/artikelnummers_staal.csv en artikelnummers_rvs.csv -
 * dezelfde CSV's als de Slangen fitting Selector (madpatrick/Geeve_hose,
 * hier gekopieerd naar /hoses in de Geeve_selectors-portal). Deze
 * bestanden bestaan alleen binnen die portal (buurmap van deze app) -
 * bij een standalone-deploy van deze app op zich ontbreken ze gewoon en
 * wordt het krimpmaten-kader leeg getoond, geen foutmelding.
 *
 * Structuur per CSV-rij (kolom "artnr" = slangtype): 2 "2delig_N"-varianten
 * (apart huls + pilaar, elk met eigen Persmaat/Schilmaat) en 3
 * "1delig_N"-varianten (1 geïntegreerd koppelartikel, eigen Persmaat/
 * Schilmaat/Insteekdiepte). We doorzoeken beide soorten varianten op het
 * gevraagde koppelartikelnummer.
 */
function csvCleanValue(?string $value): string
{
    if ($value === null) {
        return '';
    }

    $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
    $value = str_replace("\xC2\xA0", ' ', $value);
    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

    return trim($value);
}

function csvGetColumn(array $row, string $columnName): string
{
    $target = strtolower($columnName);
    foreach ($row as $column => $value) {
        if (strtolower(csvCleanValue((string) $column)) === $target) {
            return csvCleanValue((string) $value);
        }
    }

    return '';
}

/**
 * Vergelijkt het volledige artikelnummer van de order (bv. "10171-12-12" of
 * "48-12S-20S") met een kort koppelingtype uit de CSV (bv. "10", "48",
 * "V4", "V6" - géén volledig artikelnummer maar een serie-aanduiding). Het
 * type staat óf vooraan het artikelnummer, óf als los token midden in de
 * code (zie "V4"/"V6").
 */
function matchesKoppelingType(string $artikelnummer, string $type): bool
{
    if ($type === '') {
        return false;
    }

    if (stripos($artikelnummer, $type) === 0) {
        return true;
    }

    return preg_match('/\b' . preg_quote($type, '/') . '\b/i', $artikelnummer) === 1;
}

/**
 * Vergelijkt een CSV-koppelveld (Huls, Pilaar of 1delig_N) met het
 * werkelijke order-artikelnummer ($needleKoppeling, al uppercased). Deze
 * velden bevatten 2 heel verschillende soorten waarden (geverifieerd over
 * alle rijen in beide CSV's: lengte 1-3 of 7+, nooit ertussenin):
 * - Korte seriecodes ("10", "48", "VS", "V4", max 3 tekens) - matchen los
 *   via matchesKoppelingType() (prefix/los-woord), want het order-
 *   artikelnummer is dan een langere, specifiekere code die ermee begint
 *   of 'm als los token bevat (zie de docblock daarboven).
 * - Volledige, specifieke artikelnummers ("1300P9-04RVS", "13002-04MM",
 *   7+ tekens) - moeten exact overeenkomen. Los matchen zou hier per
 *   ongeluk "13002-04RVS" laten matchen op het kortere "13002-04" (ander
 *   materiaal, toevallig dezelfde eerste tekens).
 */
function matchesKoppelingField(string $needleKoppeling, string $fieldValue): bool
{
    if ($fieldValue === '') {
        return false;
    }

    if (strlen($fieldValue) <= 3) {
        return matchesKoppelingType($needleKoppeling, strtoupper($fieldValue));
    }

    return strtoupper($fieldValue) === $needleKoppeling;
}

/**
 * Leest en indexeert 1 krimpmaten-CSV op "artnr" (slangtype) - 1x per
 * requestproces, statisch gecached per bestandsnaam. findKrimpmaatInCsv()
 * las dit bestand voorheen bij ELKE aanroep opnieuw van voor naar achter
 * uit (tot ~940 regels), voor elk uniek (slangtype, koppelartikel)-paar
 * op elke kaart - zonder enige memoization, ook niet voor exact dezelfde
 * combinatie die vaker voorkomt in dezelfde printopdracht. Bij N
 * slangkaarten met in totaal M van die combinaties liep de totale tijd op
 * tot M volledige bestandsscans, en viel die tijd niet te voorspellen uit
 * het aantal kaarten alleen - een kleine, uiteenlopende printopdracht kan
 * trager zijn dan een grote met veel identieke/vroeg-in-het-bestand-
 * staande slangtypes. Alleen de EERSTE rij per artnr wordt bewaard - zelfde
 * gedrag als de oude code, die ook altijd stopte bij de eerste artnr-match
 * (zie de onvoorwaardelijke "break" die hieronder is vervallen). Geeft een
 * echte index terug (sleutel = artnr), niet enkel een gecachte rijenlijst -
 * dat laatste zou findKrimpmaatInCsv() nog steeds lineair laten zoeken per
 * aanroep.
 */
function loadKrimpmatenCsv(string $csvFile): array
{
    static $cache = [];
    if (array_key_exists($csvFile, $cache)) {
        return $cache[$csvFile];
    }

    $index = [];
    $handle = @fopen($csvFile, 'rb');
    if ($handle === false) {
        return $cache[$csvFile] = $index;
    }

    $headers = fgetcsv($handle, 0, ',');
    if ($headers === false) {
        fclose($handle);
        return $cache[$csvFile] = $index;
    }
    $headers = array_map('csvCleanValue', $headers);

    while (($data = fgetcsv($handle, 0, ',')) !== false) {
        if (count($data) !== count($headers)) {
            continue;
        }
        $row = array_combine($headers, $data);
        if ($row === false) {
            continue;
        }
        $artnr = strtoupper(csvGetColumn($row, 'artnr'));
        if ($artnr === '' || isset($index[$artnr])) {
            continue;
        }
        $index[$artnr] = $row;
    }
    fclose($handle);

    return $cache[$csvFile] = $index;
}

/** Doorzoekt 1 CSV-bestand (via de index, zie loadKrimpmatenCsv()) op slangtype + koppelartikel, zie findKrimpmaat(). */
function findKrimpmaatInCsv(string $csvFile, string $slangType, string $koppelingArtikel): ?array
{
    $index = loadKrimpmatenCsv($csvFile);
    $needleSlang = strtoupper($slangType);
    if (!isset($index[$needleSlang])) {
        return null;
    }
    $row = $index[$needleSlang];
    $needleKoppeling = strtoupper($koppelingArtikel);

    foreach ([1, 2] as $number) {
        $prefix = "2delig_{$number}";
        $huls = csvGetColumn($row, "{$prefix} - Huls");
        $pilaar = csvGetColumn($row, "{$prefix} - Pilaar");
        if (matchesKoppelingField($needleKoppeling, $huls) || matchesKoppelingField($needleKoppeling, $pilaar)) {
            return [
                'persmaat'    => csvGetColumn($row, "{$prefix} - Persmaat (mm)"),
                'schilIntern' => csvGetColumn($row, "{$prefix} - Schilmaat intern (mm)"),
                'schilExtern' => csvGetColumn($row, "{$prefix} - Schilmaat extern (mm)"),
            ];
        }
    }

    foreach ([1, 2, 3] as $number) {
        $prefix = "1delig_{$number}";
        $koppelArtikel = csvGetColumn($row, $prefix);
        if (matchesKoppelingField($needleKoppeling, $koppelArtikel)) {
            return [
                'persmaat'    => csvGetColumn($row, "{$prefix} - Persmaat (mm)"),
                'schilIntern' => csvGetColumn($row, "{$prefix} - Schilmaat intern (mm)"),
                'schilExtern' => csvGetColumn($row, "{$prefix} - Schilmaat extern (mm)"),
            ];
        }
    }

    return null;
}

/** Zoekt de krimpmaat (Persmaat) op in beide materiaal-CSV's, zie boven. */
function findKrimpmaat(string $slangType, string $koppelingArtikel): ?array
{
    if ($slangType === '' || $koppelingArtikel === '') {
        return null;
    }

    static $cache = [];
    $cacheKey = $slangType . "\0" . $koppelingArtikel;
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    $result = null;
    foreach (['artikelnummers_staal.csv', 'artikelnummers_rvs.csv'] as $filename) {
        $result = findKrimpmaatInCsv(__DIR__ . '/../hoses/data/' . $filename, $slangType, $koppelingArtikel);
        if ($result !== null) {
            break;
        }
    }

    return $cache[$cacheKey] = $result;
}

/**
 * Rendert het "Krimpmaten"-kader: voor elk koppel-/hulsartikel op zijde
 * A/B de bijbehorende Persmaat, opgezocht via findKrimpmaat(). Toont
 * "Geen krimpmaten gevonden" als er geen CSV-data beschikbaar is (bv.
 * standalone-deploy zonder /hoses-buurmap) of geen van de artikelen een
 * match opleverde.
 */
function renderKrimpmatenTable(array $card, string $slangType): string
{
    $sideLabels = ['sideA' => 'A', 'sideB' => 'B'];
    $rows = [];

    foreach ($sideLabels as $side => $label) {
        $seen = [];
        $persmaten = [];
        $schilInterns = [];
        $schilExterns = [];

        foreach ($card[$side] as $componentRow) {
            $artikel = pick($componentRow, ARTIKELNUMMER_CANDIDATES);
            if ($artikel === '' || isset($seen[$artikel])) {
                continue;
            }
            $seen[$artikel] = true;

            $krimpmaat = findKrimpmaat($slangType, $artikel);
            if ($krimpmaat === null) {
                continue;
            }
            if ($krimpmaat['persmaat'] !== '' && !in_array($krimpmaat['persmaat'], $persmaten, true)) {
                $persmaten[] = ensureLeadingZero($krimpmaat['persmaat']);
            }
            if ($krimpmaat['schilIntern'] !== '' && !in_array($krimpmaat['schilIntern'], $schilInterns, true)) {
                $schilInterns[] = ensureLeadingZero($krimpmaat['schilIntern']);
            }
            if ($krimpmaat['schilExtern'] !== '' && !in_array($krimpmaat['schilExtern'], $schilExterns, true)) {
                $schilExterns[] = ensureLeadingZero($krimpmaat['schilExtern']);
            }
        }

        $rows[] = [$label, implode(', ', $persmaten), implode(', ', $schilInterns), implode(', ', $schilExterns)];
    }

    $html = '<div class="coupling-block krimpmaten-block"><h4>Krimpmaten</h4>';
    $html .= '<div class="coupling-table-wrap"><table class="coupling-table"><thead><tr>'
        . '<th>Zijde</th><th>Krimpmaat (mm)</th><th>Schilmaat intern (mm)</th><th>Schilmaat extern (mm)</th>'
        . '</tr></thead><tbody>';
    foreach ($rows as [$label, $persmaat, $schilIntern, $schilExtern]) {
        $html .= '<tr><td>' . h($label) . '</td>'
            . '<td>' . ($persmaat !== '' ? h($persmaat) : '&mdash;') . '</td>'
            . '<td>' . ($schilIntern !== '' ? h($schilIntern) : '&mdash;') . '</td>'
            . '<td>' . ($schilExtern !== '' ? h($schilExtern) : '&mdash;') . '</td></tr>';
    }
    $html .= '</tbody></table></div>';

    return $html . '</div>';
}

/**
 * Zoekt locatie + vrije voorraad op voor een lijst artikelen in 2
 * databaserondes (1 voor locatie, 1 voor voorraad - niet 1 per artikel),
 * in de Exact-database "005" - zelfde database als /stauff gebruikt voor
 * artikelgroep 67, maar een eigen verbinding (zie
 * getExactPdoConnection()/EXACT_DB_* in .env).
 *
 * Locatie: tabel GRV_StockpositionsPerDay (kolom "Warehouse Location").
 * CSPickITItemLocations (de oorspronkelijke kandidaat) bleek leeg te
 * zijn. ROW_NUMBER() OVER (PARTITION BY ItemCode ...) pakt de meest
 * recente rij per artikel.
 *
 * Voorraad: eerst geprobeerd met GRV_StockpositionsPerDay's "Free Stock"
 * (bleek voor geen enkel artikel te kloppen) en StockBalances' laatste
 * rij (zelfde probleem). De echte rekenlogica is achterhaald door de
 * definitie van view "3000 vrd nu loc" te bekijken (dezelfde die Exact's
 * eigen "huidige voorraad"-rapport gebruikt): StockBalances is een
 * mutatielog, "huidige voorraad" is de SOM van Quantity/FreeStock over
 * alle rijen t/m vandaag (niet de laatste rij!), en de vrije voorraad is
 * daarna het laagste van FreeStock-som en Quantity-som, met een vloer op
 * 0 (geen negatieve voorraad tonen) - exact die formule wordt hieronder
 * hergebruikt, maar dan gericht op alleen de gevraagde artikelen i.p.v.
 * de zware joins (Items/ItemAssortment/prijslijst) die dat volledige
 * rapport erbij doet.
 *
 * Geeft voor elk artikel dat niet gevonden wordt (of bij een
 * connectiefout/lege .env) lege strings terug (streepjes op de
 * picklijst), nooit een foutmelding op de kaart/pagina.
 *
 * @param string[] $artikelen
 * @return array<string, array{locatie: string, voorraad: string}>
 */
function findArtikelExactDataBatch(array $artikelen): array
{
    $artikelen = array_values(array_unique(array_filter(
        $artikelen,
        static fn(string $artikel): bool => $artikel !== ''
    )));
    if ($artikelen === []) {
        return [];
    }

    try {
        $pdo = getExactPdoConnection();
    } catch (Throwable $exception) {
        return [];
    }

    $placeholders = [];
    $params = [];
    foreach ($artikelen as $index => $artikel) {
        $placeholders[] = ":code{$index}";
        $params["code{$index}"] = $artikel;
    }
    $inClause = implode(', ', $placeholders);

    $result = [];
    foreach ($artikelen as $artikel) {
        $result[$artikel] = ['locatie' => '', 'voorraad' => ''];
    }

    try {
        $stmt = $pdo->prepare(
            'WITH ranked AS (' .
            'SELECT ItemCode, [Warehouse Location], ' .
            'ROW_NUMBER() OVER (PARTITION BY ItemCode ORDER BY [Transaction Date] DESC) AS rn ' .
            'FROM GRV_StockpositionsPerDay ' .
            "WHERE ItemCode IN ({$inClause})" .
            ') SELECT ItemCode, [Warehouse Location] FROM ranked WHERE rn = 1'
        );
        $stmt->execute($params);
        while (($row = $stmt->fetch()) !== false) {
            $itemCode = (string) $row['ItemCode'];
            if (isset($result[$itemCode])) {
                $result[$itemCode]['locatie'] = trim((string) ($row['Warehouse Location'] ?? ''));
            }
        }
    } catch (Throwable $exception) {
        // Locatie blijft leeg voor deze batch.
    }

    try {
        $stmt = $pdo->prepare(
            'WITH agg AS (' .
            'SELECT ItemCode, ' .
            'SUM(CASE WHEN [Date] <= GETDATE() THEN Quantity END) AS CurrentQuantity, ' .
            'SUM(CASE WHEN [Date] <= GETDATE() THEN FreeStock END) AS FreeQuantity ' .
            'FROM StockBalances WITH (NOLOCK) ' .
            "WHERE ItemCode IN ({$inClause}) " .
            'GROUP BY ItemCode' .
            ') SELECT ItemCode, ' .
            'CASE WHEN FreeQuantity > CurrentQuantity ' .
            'THEN (CASE WHEN CurrentQuantity < 0 THEN 0 ELSE CurrentQuantity END) ' .
            'ELSE (CASE WHEN FreeQuantity < 0 THEN 0 ELSE FreeQuantity END) END AS VrijeVoorraad ' .
            'FROM agg'
        );
        $stmt->execute($params);
        while (($row = $stmt->fetch()) !== false) {
            $itemCode = (string) $row['ItemCode'];
            if (isset($result[$itemCode])) {
                $result[$itemCode]['voorraad'] = $row['VrijeVoorraad'] !== null ? trim((string) $row['VrijeVoorraad']) : '';
            }
        }
    } catch (Throwable $exception) {
        // Voorraad blijft leeg voor deze batch.
    }

    return $result;
}

/**
 * Leveringswijze (verzendwijze) opzoeken in Exact via het ordernummer, in
 * dezelfde Exact-database "005" die findArtikelExactDataBatch() hierboven
 * ook gebruikt (getExactPdoConnection()). Staat niet in "2500 Slangkaarten
 * bij order" (bevestigd via de volledige INFORMATION_SCHEMA-dump bovenaan
 * queries.php) - het is een eigenschap van de order zelf in Exact.
 *
 * 2 stappen, beide bevestigd (via /stauff/db-test.php's kolomnaam-
 * zoekoptie): "ordlev" bleek zelf GEEN ordernummer te bevatten - het is
 * een vertaaltabel van levwijze-code naar omschrijving (kolom
 * "levwijze" = code, "oms40_0" = omschrijving zoals op de kaart). De
 * code per order staat in "orkrg" (kolom "ordernr" voor het ordernummer,
 * "levwijze" voor dezelfde code) - deze tabel heeft de code al vanaf het
 * aanmaken van de order (i.t.t. "orhkrg", dat pas een rij krijgt zodra er
 * een pakbon is, en dus leeg blijft voor nog niet geleverde orders).
 */
function findLeveringswijze(string $ordernummer): string
{
    static $cache = [];

    if ($ordernummer === '') {
        return '';
    }
    if (array_key_exists($ordernummer, $cache)) {
        return $cache[$ordernummer];
    }

    $result = '';

    try {
        $pdo = getExactPdoConnection();

        $stmt = $pdo->prepare('SELECT TOP 1 [levwijze] FROM [dbo].[orkrg] WHERE [ordernr] = :value');
        $stmt->execute(['value' => $ordernummer]);
        $code = trim((string) ($stmt->fetchColumn() ?: ''));

        if ($code !== '') {
            $stmt = $pdo->prepare('SELECT TOP 1 [oms40_0] FROM [dbo].[ordlev] WHERE [levwijze] = :code');
            $stmt->execute(['code' => $code]);
            $result = trim((string) ($stmt->fetchColumn() ?: ''));
            // "oms40_0" begint soms met een vaste "Delivery by "-prefix
            // (bv. "Delivery by Starintex Innight today") - die voegt
            // niets toe op de kaart, alleen de vervoerder/dienst zelf.
            $result = preg_replace('/^delivery\s+by\s+/i', '', $result) ?? $result;
        }
    } catch (Throwable $exception) {
        // Leveringswijze blijft leeg (geen .env, connectiefout, o.i.d.).
    }

    $cache[$ordernummer] = $result;
    return $result;
}

/**
 * Bouwt de picklijst op: alle losse artikelen (koppelonderdelen zijde
 * A/B + extra artikelen) van alle geprinte slangkaarten samen, gegroepeerd
 * per artikelnummer met de aantallen opgeteld en locatie/voorraad
 * opgezocht via findArtikelExactDataBatch() (1 query voor alle artikelen
 * samen, niet per artikel apart).
 */
function buildPicklist(array $hoseCards): array
{
    $items = [];

    $addItem = static function (array &$items, string $artikel, string $aantalRaw, float $aantalSlangen): void {
        if ($artikel === '') {
            return;
        }

        $normalized = str_replace(',', '.', trim($aantalRaw));
        $aantal = is_numeric($normalized) ? (float) $normalized : 0.0;

        if (!isset($items[$artikel])) {
            $items[$artikel] = ['artikel' => $artikel, 'aantal' => 0.0, 'locatie' => '', 'voorraad' => ''];
        }
        $items[$artikel]['aantal'] += $aantal * $aantalSlangen;
    };

    foreach ($hoseCards as $card) {
        // Qty/Aantal per koppelonderdeel en extra artikel staat per 1 slang -
        // voor de picklijst (totaal benodigd voor de hele orderregel) moet
        // dit vermenigvuldigd worden met "Aantal slangen" van die kaart.
        $aantalSlangenRaw = str_replace(',', '.', trim(pick($card['row'], AANTAL_CANDIDATES)));
        $aantalSlangen = is_numeric($aantalSlangenRaw) ? (float) $aantalSlangenRaw : 1.0;

        // De slang zelf staat niet bij de koppelonderdelen (die komen uit
        // sideA/sideB) - "aantal" hiervoor is Lengte (per 1 slang) x
        // aantal slangen, net als de andere regels hieronder.
        $addItem($items, pick($card['row'], SLANGTYPE_CANDIDATES), pick($card['row'], LENGTE_CANDIDATES), $aantalSlangen);

        foreach (['sideA', 'sideB'] as $side) {
            foreach ($card[$side] as $componentRow) {
                $addItem($items, pick($componentRow, ARTIKELNUMMER_CANDIDATES), pick($componentRow, QTY_CANDIDATES), $aantalSlangen);
            }
        }

        foreach (EXTRA_ARTIKEL_SLOTS as $slot) {
            $addItem($items, pick($card['row'], $slot['artikel']), pick($card['row'], $slot['aantal']), $aantalSlangen);
        }
    }

    // Locatie + voorraad voor alle artikelen in 1 keer opzoeken (zie
    // findArtikelExactDataBatch()) i.p.v. tijdens het optellen hierboven
    // per artikel apart - dat laatste was de trage stap bij het printen.
    $exactData = findArtikelExactDataBatch(array_keys($items));
    foreach ($items as $artikel => &$item) {
        $item['locatie'] = $exactData[$artikel]['locatie'] ?? '';
        $item['voorraad'] = $exactData[$artikel]['voorraad'] ?? '';
    }
    unset($item);

    $list = array_values($items);
    usort($list, static fn(array $a, array $b): int => strnatcasecmp($a['artikel'], $b['artikel']));

    return $list;
}

/** Rendert de picklijst (zie buildPicklist()) als een eigen printpagina. */
function renderPicklist(array $items, string $orderNumber, string $klant): string
{
    ob_start();
    ?>
    <div class="picklist-sheet">
        <div class="picklist-header">
            <div class="card-brand">GEEVE <span>HYDRAULICS</span><small class="card-subbrand">Picklijst</small></div>
            <div class="card-order-meta">
                <div><span>Ordernummer</span><strong><?= h($orderNumber) ?></strong></div>
                <?php if ($klant !== ''): ?><div class="card-klant"><?= h($klant) ?></div><?php endif; ?>
            </div>
        </div>
        <?php if ($items === []): ?>
            <p class="coupling-empty">Geen losse artikelen gevonden.</p>
        <?php else: ?>
            <table class="picklist-table">
                <thead>
                    <tr>
                        <th>Artikelnummer</th>
                        <th>Locatie</th>
                        <th>Aantal</th>
                        <th>Voorraad</th>
                        <th>Besteld</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?= h($item['artikel']) ?></td>
                            <td><?= $item['locatie'] !== '' ? h($item['locatie']) : '&mdash;' ?></td>
                            <td><?= h(formatQuantity((string) $item['aantal'])) ?></td>
                            <td><?= $item['voorraad'] !== '' ? h(formatQuantity($item['voorraad'], 1)) : '&mdash;' ?></td>
                            <td></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
    return (string) ob_get_clean();
}

/**
 * Bouwt een adresblok van losse velden op (code+naam op de 1e regel,
 * straat op de 2e, postcode+plaats op de 3e), als newline-tekst - klaar
 * om met nl2br() te tonen. Lege regels worden overgeslagen.
 */
function composeAddressBlock(string $naam, string $code, string $adres, string $postcode, string $plaats): string
{
    $lines = [];

    $naamLine = trim($code !== '' ? "{$code}  {$naam}" : $naam);
    if ($naamLine !== '') {
        $lines[] = $naamLine;
    }
    if ($adres !== '') {
        $lines[] = $adres;
    }

    $plaatsLine = trim("{$postcode}  {$plaats}");
    if ($plaatsLine !== '') {
        $lines[] = $plaatsLine;
    }

    return implode("\n", $lines);
}

/** Combineert een datum en een naam tot 1 weergavetekst ("datum naam"). */
function composeDatumNaam(string $datum, string $naam): string
{
    return trim("{$datum} {$naam}");
}

/** Rendert 1 volledige slangkaart (gebruikt zowel op scherm als in print). */
function renderHoseCard(array $card): string
{
    $row = $card['row'];

    $orderNumber = pick($row, ORDER_NUMBER_COLUMNS);
    $slangnummer = pick($row, HOSE_KEY_COLUMNS);
    $klant = pick($row, KLANT_CANDIDATES);
    $aantal = formatWholeNumber(pick($row, AANTAL_CANDIDATES));
    $notitie = pick($row, NOTITIE_CANDIDATES);
    $hoekRaw = pick($row, HOEK_CANDIDATES);
    $hoek = $hoekRaw !== '' ? (float) str_replace(',', '.', $hoekRaw) : null;
    $slangType = pick($row, SLANGTYPE_CANDIDATES);
    $leveringswijze = findLeveringswijze($orderNumber);

    $ordercrediteur = composeAddressBlock(
        $klant,
        pick($row, ORDERCREDITEUR_CODE_CANDIDATES),
        pick($row, ORDERCREDITEUR_ADRES_CANDIDATES),
        pick($row, ORDERCREDITEUR_POSTCODE_CANDIDATES),
        pick($row, ORDERCREDITEUR_PLAATS_CANDIDATES)
    );
    $afleveradres = composeAddressBlock(
        pick($row, AFLEVERADRES_NAAM_CANDIDATES),
        '',
        pick($row, AFLEVERADRES_ADRES_CANDIDATES),
        pick($row, AFLEVERADRES_POSTCODE_CANDIDATES),
        pick($row, AFLEVERADRES_PLAATS_CANDIDATES)
    );

    $orderdatum = formatDate(pick($row, ORDERDATUM_CANDIDATES));

    $aangemaakt = composeDatumNaam(
        formatDateTime(pick($row, AANGEMAAKT_DATUM_CANDIDATES)),
        pick($row, AANGEMAAKT_NAAM_CANDIDATES)
    ) ?: pick($row, AANGEMAAKT_CANDIDATES);

    $gewijzigd = composeDatumNaam(
        formatDateTime(pick($row, GEWIJZIGD_DATUM_CANDIDATES)),
        pick($row, GEWIJZIGD_NAAM_CANDIDATES)
    ) ?: pick($row, GEWIJZIGD_CANDIDATES);

    ob_start();
    ?>
    <div class="hose-card">
        <div class="card-top">
            <div class="card-brand">GEEVE <span>HYDRAULICS</span><small class="card-subbrand">Slangenkaart</small></div>
            <div class="card-order-meta">
                <div><span>Ordernummer</span><strong><?= h($orderNumber) ?></strong></div>
                <?php if ($klant !== ''): ?><div class="card-klant"><?= h($klant) ?></div><?php endif; ?>
            </div>
        </div>

        <div class="card-body">
            <div class="card-details">
                <div class="card-detail-row card-slangnummer">
                    <span>Slangnummer</span>
                    <strong><?= h($slangnummer) ?: '&mdash;' ?></strong>
                </div>
                <?php foreach (CARD_DETAIL_FIELDS as $field): ?>
                    <?php $fieldValue = pick($row, $field['candidates']); ?>
                    <?php if ($fieldValue !== '' && ($field['format'] ?? '') === 'leadingzero'): ?>
                        <?php $fieldValue = ensureLeadingZero($fieldValue); ?>
                    <?php endif; ?>
                    <div class="card-detail-row">
                        <span><?= h($field['label']) ?></span>
                        <strong><?= $fieldValue !== '' ? h($fieldValue) : '&mdash;' ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="card-qty-box">
                <span>Aantal slangen</span>
                <strong><?= h($aantal) ?: '&mdash;' ?></strong>
            </div>
        </div>

        <div class="card-couplings">
            <?= renderCouplingTable('A', $card['sideA']) ?>
            <?= renderCouplingTable('B', $card['sideB']) ?>
            <div class="angle-block"><?= renderAngleSvg($hoek) ?></div>
        </div>

        <div class="card-note">
            <span>Notitie</span>
            <div><?= $notitie !== '' ? nl2br(h($notitie)) : '' ?></div>
        </div>

        <div class="card-bewerkingen">
            <div class="coupling-block">
                <h4>Bewerkingen</h4>
                <div class="card-flags">
                    <?php foreach (CARD_FLAG_SLOTS as $slot): ?>
                        <?php if ($slot === null): ?>
                            <div class="card-flag card-flag-empty"></div>
                        <?php else: ?>
                            <div class="card-flag"><span><?= h($slot[0]) ?></span><strong><?= h(formatFlag(pick($row, $slot[1]))) ?></strong></div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="card-flags-row">
            <div class="card-extra-artikelen">
                <?= renderExtraArtikelenTable($row) ?>
            </div>
            <div class="card-krimpmaten">
                <?= renderKrimpmatenTable($card, $slangType) ?>
            </div>
        </div>

        <div class="card-footer">
            <div class="card-footer-addresses">
                <div class="card-address-block">
                    <span>Ordercrediteur</span>
                    <div><?= $ordercrediteur !== '' ? nl2br(h($ordercrediteur)) : '&mdash;' ?></div>
                </div>
                <div class="card-address-block">
                    <span>Afleveradres</span>
                    <div><?= $afleveradres !== '' ? nl2br(h($afleveradres)) : '&mdash;' ?></div>
                </div>
                <div class="card-address-block card-verzendwijze">
                    <span>Verzendwijze</span>
                    <div><?= $leveringswijze !== '' ? h($leveringswijze) : '&mdash;' ?></div>
                </div>
            </div>
            <div class="card-footer-meta">
                <div class="card-meta-block">
                    <span>Orderdatum</span>
                    <div><?= h($orderdatum) ?: '&mdash;' ?></div>
                </div>
                <div class="card-meta-block">
                    <span>Aangemaakt</span>
                    <div><?= h($aangemaakt) ?: '&mdash;' ?></div>
                </div>
                <div class="card-meta-block">
                    <span>Laatste gewijzigd</span>
                    <div><?= h($gewijzigd) ?: '&mdash;' ?></div>
                </div>
            </div>
        </div>
    </div>
    <?php
    return (string) ob_get_clean();
}

/**
 * Rendert het selecteerbare regel-overzicht (stap 2): alle slangregels die
 * bij de zoekopdracht horen, elk met een aangevinkte checkbox, plus een
 * knop om alleen de aangevinkte regels als slangkaart te printen.
 */
function renderHoseLinesForm(array $hoseLines, string $orderNumber, string $customerName): string
{
    // Koppeling A/B krijgen elk artikel een eigen kolom i.p.v. ze in 1 cel
    // te stapelen - het aantal kolommen hangt af van de regel met de
    // meeste koppelonderdelen (meestal 1, soms meer). De tabel scrollt
    // horizontaal (zie .lines-table-wrap) als dat te breed wordt.
    $maxKoppelingA = 0;
    $maxKoppelingB = 0;
    foreach ($hoseLines as $line) {
        $maxKoppelingA = max($maxKoppelingA, count($line['_KoppelingAList'] ?? []));
        $maxKoppelingB = max($maxKoppelingB, count($line['_KoppelingBList'] ?? []));
    }
    $maxKoppelingA = max($maxKoppelingA, 1);
    $maxKoppelingB = max($maxKoppelingB, 1);

    ob_start();
    ?>
    <form method="post" action="index.php" class="lines-form" id="hoseLinesForm">
        <input type="hidden" name="ordernummer" value="<?= h($orderNumber) ?>">
        <input type="hidden" name="klant" value="<?= h($customerName) ?>">
        <div class="lines-table-wrap">
            <table class="lines-table">
                <thead>
                    <tr>
                        <th class="lines-check-col">
                            <input type="checkbox" id="checkAllLines" checked title="Alles (de)selecteren">
                        </th>
                        <?php foreach (LINE_OVERVIEW_FIELDS as $field): ?>
                            <th><?= h($field['label']) ?></th>
                        <?php endforeach; ?>
                        <?php for ($i = 1; $i <= $maxKoppelingA; $i++): ?>
                            <th><?= $maxKoppelingA > 1 ? 'Koppeling A ' . $i : 'Koppeling A' ?></th>
                        <?php endfor; ?>
                        <?php for ($i = 1; $i <= $maxKoppelingB; $i++): ?>
                            <th><?= $maxKoppelingB > 1 ? 'Koppeling B ' . $i : 'Koppeling B' ?></th>
                        <?php endfor; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($hoseLines as $line): ?>
                        <?php $hoseKey = pick($line, HOSE_KEY_COLUMNS); ?>
                        <?php $koppelingA = $line['_KoppelingAList'] ?? []; ?>
                        <?php $koppelingB = $line['_KoppelingBList'] ?? []; ?>
                        <tr>
                            <td class="lines-check-col">
                                <input
                                    type="checkbox"
                                    name="slangnummers[]"
                                    value="<?= h($hoseKey) ?>"
                                    class="line-checkbox"
                                    <?= $hoseKey === '' ? 'disabled' : 'checked' ?>
                                >
                            </td>
                            <?php foreach (LINE_OVERVIEW_FIELDS as $field): ?>
                                <?php $fieldValue = pick($line, $field['candidates']); ?>
                                <?php if ($fieldValue !== '' && ($field['format'] ?? '') === 'whole'): ?>
                                    <?php $fieldValue = formatWholeNumber($fieldValue); ?>
                                <?php elseif ($fieldValue !== '' && ($field['format'] ?? '') === 'leadingzero'): ?>
                                    <?php $fieldValue = ensureLeadingZero($fieldValue); ?>
                                <?php endif; ?>
                                <td><?= $fieldValue !== '' ? h($fieldValue) : '&mdash;' ?></td>
                            <?php endforeach; ?>
                            <?php for ($i = 0; $i < $maxKoppelingA; $i++): ?>
                                <td><?= isset($koppelingA[$i]) ? h($koppelingA[$i]) : '&mdash;' ?></td>
                            <?php endfor; ?>
                            <?php for ($i = 0; $i < $maxKoppelingB; $i++): ?>
                                <td><?= isset($koppelingB[$i]) ? h($koppelingB[$i]) : '&mdash;' ?></td>
                            <?php endfor; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </form>
    <script>
        (function () {
            var checkAll = document.getElementById('checkAllLines');
            if (!checkAll) { return; }
            checkAll.addEventListener('change', function () {
                document.querySelectorAll('.line-checkbox').forEach(function (checkbox) {
                    checkbox.checked = checkAll.checked;
                });
            });
        })();
    </script>
    <?php
    return (string) ob_get_clean();
}

/**
 * Rendert de orderkeuze (tussenstap na zoeken op klantnaam): een lijst
 * van orders van deze klant, nieuwste eerst, elk met een "Kiezen"-knop
 * die verder gaat als een gewone ordernummer-zoekopdracht.
 */
function renderCustomerOrdersForm(array $customerOrders, bool $showCreated = false): string
{
    ob_start();
    ?>
    <div class="lines-table-wrap">
        <table class="lines-table">
            <thead>
                <tr>
                    <th>Ordernummer</th>
                    <th>Type order</th>
                    <th>Klant</th>
                    <th>Uw referentie</th>
                    <th>Orderdatum</th>
                    <?php if ($showCreated): ?><th>Order aangemaakt</th><?php endif; ?>
                    <th>Aantal slangregels</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($customerOrders as $order): ?>
                    <?php $row = $order['row']; ?>
                    <?php $orderNumberValue = pick($row, ORDER_NUMBER_COLUMNS); ?>
                    <tr>
                        <td><?= h($orderNumberValue) ?: '&mdash;' ?></td>
                        <td><?= h(formatOrderType(pick($row, ORDER_TYPE_CANDIDATES))) ?: '&mdash;' ?></td>
                        <td><?= h(pick($row, KLANT_CANDIDATES)) ?: '&mdash;' ?></td>
                        <td><?= h(pick($row, UW_REFERENTIE_CANDIDATES)) ?: '&mdash;' ?></td>
                        <td><?= h(formatDate(pick($row, ORDERDATUM_CANDIDATES))) ?: '&mdash;' ?></td>
                        
                        <?php if ($showCreated): ?><td><?= h(formatDateTime(pick($row, AANGEMAAKT_DATUM_CANDIDATES))) ?: '&mdash;' ?></td><?php endif; ?><td><?= (int) $order['count'] ?></td>
                        <td>
                            <a class="link-button" href="index.php?ordernummer=<?= h(rawurlencode($orderNumberValue)) ?>">Kiezen</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
    return (string) ob_get_clean();
}

/**
 * Rendert de gevonden regels bij zoeken op slangnummer (artikelnummer,
 * zie findLinesByHoseNumber()) - géén order-unieke sleutel, dus dit kan
 * regels uit meerdere orders/klanten tonen. Elke rij heeft een
 * "Kiezen"-knop die verder gaat als een gewone ordernummer-zoekopdracht
 * (naar stap 2 van die order).
 */
function renderHoseNumberResultsForm(array $hoseNumberResults): string
{
    ob_start();
    ?>
    <div class="lines-table-wrap">
        <table class="lines-table">
            <thead>
                <tr>
                    <th>Slangnummer</th>
                    <th>Slang type</th>
                    <th>Ordernummer</th>
                    <th>Klant</th>
                    <th>Orderdatum</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($hoseNumberResults as $row): ?>
                    <?php $orderNumberValue = pick($row, ORDER_NUMBER_COLUMNS); ?>
                    <tr>
                        <td><?= h(pick($row, HOSE_KEY_COLUMNS)) ?: '&mdash;' ?></td>
                        <td><?= h(pick($row, SLANGTYPE_CANDIDATES)) ?: '&mdash;' ?></td>
                        <td><?= h($orderNumberValue) ?: '&mdash;' ?></td>
                        <td><?= h(pick($row, KLANT_CANDIDATES)) ?: '&mdash;' ?></td>
                        <td><?= h(formatDate(pick($row, ORDERDATUM_CANDIDATES))) ?: '&mdash;' ?></td>
                        <td>
                            <a class="link-button" href="index.php?ordernummer=<?= h(rawurlencode($orderNumberValue)) ?>">Kiezen</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
    return (string) ob_get_clean();
}

/**
 * De laatste 10 orders/offertes, met een korte bestandscache (zie
 * RECENT_ORDERS_CACHE_SECONDS): de opvraging (Exact + slangkaart-tabel) kan
 * een paar seconden duren, dus die gebeurt NIET tijdens het openen van de
 * pagina maar via ?recent=1 (zie het script onderaan) - de rest van de
 * pagina staat dan meteen er. ?fresh=1 slaat de cache over, ?debug=1 toont
 * hoe lang elke stap duurde.
 */
const RECENT_ORDERS_CACHE_SECONDS = 120;
// Een oudere lijst dan dit wordt niet meer getoond; tussen de 2 minuten en dit
// wordt hij nog WEL meteen getoond, terwijl de pagina op de achtergrond een
// verse opvraagt (stale-while-revalidate) - zodat niemand op de trage
// opvraging hoeft te wachten behalve de allereerste keer.
const RECENT_ORDERS_MAX_STALE_SECONDS = 86400;

function renderRecentOrdersFragment(bool $fresh, bool $debug): string
{
    $cacheFile = sys_get_temp_dir() . '/geeve_slangkaarten_recent_orders.json';
    $source = 'database';
    $startedAt = microtime(true);
    $orders = null;

    $cacheAge = is_file($cacheFile) ? time() - (int) @filemtime($cacheFile) : PHP_INT_MAX;
    if (!$fresh && $cacheAge < RECENT_ORDERS_MAX_STALE_SECONDS) {
        $cached = json_decode((string) @file_get_contents($cacheFile), true);
        if (is_array($cached) && $cached !== []) {
            $orders = $cached;
            $source = 'cache';
            if ($cacheAge >= RECENT_ORDERS_CACHE_SECONDS) {
                $source = 'cache (verouderd, wordt ververst)';
                header('X-Recent-Stale: 1');
            }
        }
    }

    if ($orders === null) {
        try {
            $orders = findRecentOrders(getPdoConnection(), 10);
        } catch (Throwable $exception) {
            $orders = [];
        }
        if ($orders !== []) {
            @file_put_contents($cacheFile, json_encode($orders, JSON_UNESCAPED_UNICODE), LOCK_EX);
        }
    }

    if ($orders === []) {
        return '<p class="print-status">De laatste orders konden niet worden geladen - zoek hierboven op een order, klant of slangnummer.</p>';
    }

    $html = renderCustomerOrdersForm($orders, pick($orders[0]['row'], AANGEMAAKT_DATUM_CANDIDATES) !== '');

    if ($debug) {
        $timings = $GLOBALS['recentOrdersTimings'] ?? [];
        $parts = ['totaal ' . round((microtime(true) - $startedAt) * 1000) . ' ms', 'bron: ' . $source];
        foreach ($timings as $name => $ms) {
            $parts[] = $name . ' ' . $ms . ' ms';
        }
        $html .= '<p class="print-status">' . h(implode(' | ', $parts)) . '</p>';
    }

    return $html;
}

if (isset($_GET['recent'])) {
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    echo renderRecentOrdersFragment(isset($_GET['fresh']), isset($_GET['debug']));
    exit;
}

$orderNumber = trim((string) ($_GET['ordernummer'] ?? $_POST['ordernummer'] ?? ''));
$customerName = trim((string) ($_GET['klant'] ?? $_POST['klant'] ?? ''));
$hoseNumberSearch = trim((string) ($_GET['slangnummer'] ?? $_POST['slangnummer'] ?? ''));
$selectedKeys = array_values(array_filter(array_map(
    static fn($value): string => trim((string) $value),
    $_POST['slangnummers'] ?? []
), static fn(string $value): bool => $value !== ''));

$hoseLines = [];
$hoseCards = [];
$customerOrders = [];
$hoseNumberResults = [];
$showRecentOrders = false;
$errorMessage = null;

if ($selectedKeys !== []) {
    try {
        $pdo = getPdoConnection();
        $hoseCards = findHoseCardsByKeys($pdo, $selectedKeys, $orderNumber);
    } catch (DatabaseConfigException $exception) {
        $errorMessage = $exception->getMessage();
    }
} elseif ($orderNumber !== '') {
    try {
        $pdo = getPdoConnection();
        $hoseLines = enrichHoseLinesWithCouplings($pdo, findHoseLinesByOrder($pdo, $orderNumber));
        // Stap 2 toont de regels op regelnummer i.p.v. de (ongesorteerde)
        // volgorde die de database teruggeeft.
        usort(
            $hoseLines,
            static fn(array $a, array $b): int =>
                (int) pick($a, REGELNUMMER_CANDIDATES) <=> (int) pick($b, REGELNUMMER_CANDIDATES)
        );
    } catch (DatabaseConfigException $exception) {
        $errorMessage = $exception->getMessage();
    }
} elseif ($customerName !== '') {
    // Zoeken op klant gaat via een tussenstap: eerst een orderkeuze
    // (nieuwste eerst), pas na het kiezen van een order volgt het
    // regel-overzicht - net als bij een rechtstreekse ordernummer-
    // zoekopdracht.
    try {
        $pdo = getPdoConnection();
        $customerOrders = findOrdersByCustomer($pdo, $customerName);
    } catch (DatabaseConfigException $exception) {
        $errorMessage = $exception->getMessage();
    }
} elseif ($hoseNumberSearch !== '') {
    // Zoeken op slangnummer (artikelnummer) is geen order-unieke sleutel
    // (zie findLinesByHoseNumber()) - toont daarom ook een tussenstap met
    // de gevonden regels (mogelijk uit meerdere orders/klanten), waarna
    // de gebruiker een order kiest om verder te gaan naar stap 2.
    try {
        $pdo = getPdoConnection();
        $hoseNumberResults = findLinesByHoseNumber($pdo, $hoseNumberSearch);
    } catch (DatabaseConfigException $exception) {
        $errorMessage = $exception->getMessage();
    }
} else {
    // Nog niet gezocht: de laatste 10 orders/offertes worden na het laden van
    // de pagina opgehaald (zie renderRecentOrdersFragment()), niet hier.
    $showRecentOrders = true;
}
?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Slangkaarten | Geeve Hydraulics</title>
    <link rel="icon" href="../favicon.ico?v=<?= h(assetVersion('../favicon.ico')) ?>" type="image/x-icon">
    <link rel="stylesheet" href="../shared/style.css?v=<?= h(assetVersion('../shared/style.css')) ?>">
    <link rel="stylesheet" href="assets/style.css?v=<?= h(assetVersion('assets/style.css')) ?>">
</head>
<body>
<main class="page-shell page-shell--wide">
    <?php
    $headerTitle = 'Slangkaarten bij order';
    $headerTopline = '<span class="version-inline">Versie ' . h(APP_VERSION) . '</span>';
    require __DIR__ . '/../shared/header.php';
    ?>

    <section class="panel search-panel">
        <div class="section-heading">
            <div>
                <span class="step">Stap 1</span>
                <h2>Order, klant of slangnummer opzoeken</h2>
            </div>
            <a class="link-button" href="index.php">Wissen</a>
        </div>
        <form class="search-row" method="get" action="index.php">
            <label class="field" for="ordernummer">
                <span>Ordernummer</span>
                <input
                    id="ordernummer"
                    name="ordernummer"
                    type="text"
                    inputmode="numeric"
                    placeholder="bijv. 36019177"
                    autocomplete="off"
                    autofocus
                    value="<?= h($orderNumber) ?>"
                >
            </label>
            <label class="field" for="slangnummer">
                <span>Slangnummer</span>
                <input
                    id="slangnummer"
                    name="slangnummer"
                    type="text"
                    placeholder="bijv. artikelnummer"
                    autocomplete="off"
                    value="<?= h($hoseNumberSearch) ?>"
                >
            </label>
            <label class="field" for="klant">
                <span>Klant</span>
                <input
                    id="klant"
                    name="klant"
                    type="text"
                    placeholder="bijv. Palfinger"
                    autocomplete="off"
                    value="<?= h($customerName) ?>"
                >
            </label>
            <button type="submit" class="submit-button" id="searchSubmitButton">
                <span class="button-fill" aria-hidden="true"></span>
                <span class="button-label">Opzoeken</span>
            </button>
        </form>
        <small>Vul een ordernummer, (een deel van) de klantnaam, óf een slangnummer in.</small>
    </section>

    <script>
        (function () {
            var form = document.querySelector('.search-row');
            var button = document.getElementById('searchSubmitButton');
            var label = button ? button.querySelector('.button-label') : null;
            if (!form || !button || !label) { return; }

            form.addEventListener('submit', function () {
                button.classList.add('loading');
                button.disabled = true;
                label.textContent = 'Bezig met zoeken…';
            });
        })();
    </script>

    <?php if ($errorMessage !== null): ?>
        <section class="warning-box"><?= h($errorMessage) ?></section>
    <?php elseif ($hoseCards !== []): ?>
        <section class="panel result-panel">
            <div class="section-heading">
                <div>
                    <span class="step">Stap 3</span>
                    <h2><?= count($hoseCards) ?> slangkaart<?= count($hoseCards) === 1 ? '' : 'en' ?> wordt geprint&hellip;</h2>
                </div>
                <button type="button" class="print-button" onclick="window.print()">Opnieuw printen</button>
            </div>
            <p class="print-status">Het printvenster wordt automatisch geopend. Zie je het niet, klik dan op "Opnieuw printen".</p>
        </section>
        <script>window.addEventListener('load', function () { window.print(); });</script>
    <?php elseif ($selectedKeys !== []): ?>
        <section class="empty-result">Geen slangkaarten gevonden voor de geselecteerde regels.</section>
    <?php elseif ($hoseLines !== []): ?>
        <?php $hoseLinesOrderNumber = pick($hoseLines[0], ORDER_NUMBER_COLUMNS); ?>
        <?php $hoseLinesKlant = pick($hoseLines[0], KLANT_CANDIDATES); ?>
        <section class="panel result-panel">
            <div class="section-heading">
                <div>
                    <span class="step">Stap 2</span>
                    <div class="stap2-badges">
                        <?php if ($hoseLinesKlant !== ''): ?>
                            <span class="klant-badge"><?= h($hoseLinesKlant) ?></span>
                        <?php endif; ?>
                        <span class="order-badge">Order <?= h($hoseLinesOrderNumber) ?></span>
                    </div>
                    <h2><?= count($hoseLines) ?> slangregel<?= count($hoseLines) === 1 ? '' : 'en' ?> gevonden</h2>
                </div>
                <button type="submit" form="hoseLinesForm" class="submit-button" id="printSelectedButton">
                    <span class="button-fill" aria-hidden="true"></span>
                    <span class="button-label">Print geselecteerde slangkaarten</span>
                </button>
            </div>
            <?= renderHoseLinesForm($hoseLines, $orderNumber, $customerName) ?>
        </section>
        <script>
            (function () {
                var form = document.getElementById('hoseLinesForm');
                var button = document.getElementById('printSelectedButton');
                var label = button ? button.querySelector('.button-label') : null;
                if (!form || !button || !label) { return; }

                form.addEventListener('submit', function () {
                    button.classList.add('loading');
                    button.disabled = true;
                    label.textContent = 'Slangkaarten worden opgehaald…';
                });
            })();
        </script>
    <?php elseif ($customerOrders !== []): ?>
        <section class="panel result-panel">
            <div class="section-heading">
                <div>
                    <span class="step">Stap 1b</span>
                    <h2><?= count($customerOrders) ?> order<?= count($customerOrders) === 1 ? '' : 's' ?> gevonden - kies een order</h2>
                </div>
            </div>
            <?= renderCustomerOrdersForm($customerOrders) ?>
        </section>
    <?php elseif ($hoseNumberResults !== []): ?>
        <section class="panel result-panel">
            <div class="section-heading">
                <div>
                    <span class="step">Stap 1b</span>
                    <h2><?= count($hoseNumberResults) ?> slangregel<?= count($hoseNumberResults) === 1 ? '' : 'en' ?> gevonden voor dit slangnummer - kies een order</h2>
                </div>
            </div>
            <?= renderHoseNumberResultsForm($hoseNumberResults) ?>
        </section>
    <?php elseif ($showRecentOrders): ?>
        <section class="panel result-panel">
            <div class="section-heading">
                <div>
                    <span class="step">Recent</span>
                    <h2>Laatste 10 orders / offertes - of zoek hierboven</h2>
                </div>
            </div>
            <div id="recentOrdersBody"><p class="print-status">Laatste orders laden&hellip;</p></div>
        </section>
        <script>
            (function () {
                var body = document.getElementById('recentOrdersBody');
                if (!body || !window.fetch) { return; }
                var url = 'index.php?recent=1' + (location.search.indexOf('debug') !== -1 ? '&debug=1' : '');
                fetch(url, { cache: 'no-store' })
                    .then(function (response) {
                        var stale = response.headers.get('X-Recent-Stale') === '1';
                        return response.text().then(function (html) {
                            body.innerHTML = html;
                            if (stale) {
                                // Oude lijst staat er al; vervang door een verse zodra die binnen is.
                                fetch(url + '&fresh=1', { cache: 'no-store' })
                                    .then(function (r) { return r.text(); })
                                    .then(function (fresh) { body.innerHTML = fresh; })
                                    .catch(function () {});
                            }
                        });
                    })
                    .catch(function () {
                        body.innerHTML = '<p class="print-status">De laatste orders konden niet worden geladen.</p>';
                    });
            })();
        </script>
    <?php elseif ($orderNumber !== '' || $customerName !== '' || $hoseNumberSearch !== ''): ?>
        <section class="empty-result">Geen slangregels gevonden voor deze zoekopdracht.</section>
    <?php endif; ?>
</main>

<?php if ($hoseCards !== []): ?>
<div id="printSheet" aria-hidden="true">
    <?php foreach ($hoseCards as $card): ?>
        <?= renderHoseCard($card) ?>
    <?php endforeach; ?>
    <?= renderPicklist(buildPicklist($hoseCards), $orderNumber, pick($hoseCards[0]['row'], KLANT_CANDIDATES)) ?>
</div>
<?php endif; ?>
</body>
</html>
