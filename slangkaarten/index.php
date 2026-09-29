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
 */
function formatQuantity(string $value): string
{
    $normalized = str_replace(',', '.', trim($value));
    if (!is_numeric($normalized)) {
        return $value;
    }

    $formatted = rtrim(rtrim(number_format((float) $normalized, 3, '.', ''), '0'), '.');

    return str_replace('.', ',', $formatted);
}

/**
 * Toont datum + tijd zonder milliseconden (bv. "2026-09-24 14:03:00"
 * i.p.v. "2026-09-24 14:03:00.000" - orddat/syscreated/sysmodified komen
 * met een millisecondencomponent uit de database die nooit relevant is).
 * Onherkenbare/lege waarden blijven ongewijzigd (zie parseDutchDateTime()
 * in inc/queries.php).
 */
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

const CARD_DETAIL_FIELDS = [
    ['label' => 'Referentie',      'candidates' => ['Referentie']],
    ['label' => 'Uw Referentie',   'candidates' => UW_REFERENTIE_CANDIDATES],
    ['label' => 'Omschrijving',    'candidates' => ['Omschrijving']],
    ['label' => 'Slang type',      'candidates' => SLANGTYPE_CANDIDATES],
    ['label' => 'Lengte',          'candidates' => ['Lengte', 'Lengte / Prijs', 'Lengte/Prijs']],
    ['label' => 'Snijlengte',      'candidates' => ['SnijlengteJN', 'Snijlengte']],
];

// Bevestigd tegen INFORMATION_SCHEMA.COLUMNS van "2500 Slangkaarten bij
// order" (definitieve schema-dump, geen enkele kolom heeft een prefix).
// "Proppen" heeft zowel Proppen als ProppenJN - de JN-variant (Ja/Nee)
// staat vooraan, met het andere veld als fallback.
const CARD_FLAG_SLOTS = [
    ['Labelen', ['Labelen']], ['Testen/spoelen', ['TestenSpoelen', 'Testen/spoelen', 'Testen spoelen']], ['DNV Certificaat', ['DNVCertificaat', 'DNV Certificaat']],
    ['Graveren', ['Graveren']], ['Pin prikken', ['PinPrikken', 'Pin prikken']], null,
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
const LINE_OVERVIEW_FIELDS = [
    ['label' => 'Regel',         'candidates' => ['rgl', 'Regel', 'RegelNr']],
    ['label' => 'Aantal',        'candidates' => ['Aantal', 'Aantal slangen', 'AantalSlangen'], 'format' => 'whole'],
    ['label' => 'Slangnummer',   'candidates' => ['GHnr', 'Slangnummer', 'SlangNr', 'Slang nr']],
    ['label' => 'GHnm',          'candidates' => ['GHnm', 'Omschrijving slang']],
    ['label' => 'Slang type',    'candidates' => SLANGTYPE_CANDIDATES],
    ['label' => 'Lengte',        'candidates' => ['Lengte']],
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

/** Doorzoekt 1 CSV-bestand op slangtype + koppelartikel, zie findKrimpmaat(). */
function findKrimpmaatInCsv(string $csvFile, string $slangType, string $koppelingArtikel): ?array
{
    $handle = @fopen($csvFile, 'rb');
    if ($handle === false) {
        return null;
    }

    $headers = fgetcsv($handle, 0, ',');
    if ($headers === false) {
        fclose($handle);
        return null;
    }
    $headers = array_map('csvCleanValue', $headers);

    $result = null;
    $needleSlang = strtoupper($slangType);
    $needleKoppeling = strtoupper($koppelingArtikel);

    while (($data = fgetcsv($handle, 0, ',')) !== false) {
        if (count($data) !== count($headers)) {
            continue;
        }
        $row = array_combine($headers, $data);
        if ($row === false || strtoupper(csvGetColumn($row, 'artnr')) !== $needleSlang) {
            continue;
        }

        foreach ([1, 2] as $number) {
            $prefix = "2delig_{$number}";
            $huls = csvGetColumn($row, "{$prefix} - Huls");
            $pilaar = csvGetColumn($row, "{$prefix} - Pilaar");
            if (matchesKoppelingType($needleKoppeling, strtoupper($huls)) || matchesKoppelingType($needleKoppeling, strtoupper($pilaar))) {
                $result = [
                    'persmaat'    => csvGetColumn($row, "{$prefix} - Persmaat (mm)"),
                    'schilIntern' => csvGetColumn($row, "{$prefix} - Schilmaat intern (mm)"),
                    'schilExtern' => csvGetColumn($row, "{$prefix} - Schilmaat extern (mm)"),
                ];
                break 2;
            }
        }

        foreach ([1, 2, 3] as $number) {
            $prefix = "1delig_{$number}";
            if (matchesKoppelingType($needleKoppeling, strtoupper(csvGetColumn($row, $prefix)))) {
                $result = [
                    'persmaat'    => csvGetColumn($row, "{$prefix} - Persmaat (mm)"),
                    'schilIntern' => csvGetColumn($row, "{$prefix} - Schilmaat intern (mm)"),
                    'schilExtern' => csvGetColumn($row, "{$prefix} - Schilmaat extern (mm)"),
                ];
                break 2;
            }
        }

        break;
    }

    fclose($handle);
    return $result;
}

/** Zoekt de krimpmaat (Persmaat) op in beide materiaal-CSV's, zie boven. */
function findKrimpmaat(string $slangType, string $koppelingArtikel): ?array
{
    if ($slangType === '' || $koppelingArtikel === '') {
        return null;
    }

    foreach (['artikelnummers_staal.csv', 'artikelnummers_rvs.csv'] as $filename) {
        $result = findKrimpmaatInCsv(__DIR__ . '/../hoses/data/' . $filename, $slangType, $koppelingArtikel);
        if ($result !== null) {
            return $result;
        }
    }

    return null;
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
                $persmaten[] = $krimpmaat['persmaat'];
            }
            if ($krimpmaat['schilIntern'] !== '' && !in_array($krimpmaat['schilIntern'], $schilInterns, true)) {
                $schilInterns[] = $krimpmaat['schilIntern'];
            }
            if ($krimpmaat['schilExtern'] !== '' && !in_array($krimpmaat['schilExtern'], $schilExterns, true)) {
                $schilExterns[] = $krimpmaat['schilExtern'];
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
 * Bouwt de picklijst op: alle losse artikelen (koppelonderdelen zijde
 * A/B + extra artikelen) van alle geprinte slangkaarten samen, gegroepeerd
 * per artikelnummer met de aantallen opgeteld. "Locatie" moet nog uit de
 * database opgehaald worden (nog niet bekend uit welke tabel/kolom - net
 * als destijds bij Slangkaarten/Stauff verkennen we dat later samen) -
 * toont voorlopig altijd een streepje.
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
            $items[$artikel] = ['artikel' => $artikel, 'aantal' => 0.0, 'locatie' => ''];
        }
        $items[$artikel]['aantal'] += $aantal * $aantalSlangen;
    };

    foreach ($hoseCards as $card) {
        // Qty/Aantal per koppelonderdeel en extra artikel staat per 1 slang -
        // voor de picklijst (totaal benodigd voor de hele orderregel) moet
        // dit vermenigvuldigd worden met "Aantal slangen" van die kaart.
        $aantalSlangenRaw = str_replace(',', '.', trim(pick($card['row'], AANTAL_CANDIDATES)));
        $aantalSlangen = is_numeric($aantalSlangenRaw) ? (float) $aantalSlangenRaw : 1.0;

        foreach (['sideA', 'sideB'] as $side) {
            foreach ($card[$side] as $componentRow) {
                $addItem($items, pick($componentRow, ARTIKELNUMMER_CANDIDATES), pick($componentRow, QTY_CANDIDATES), $aantalSlangen);
            }
        }

        foreach (EXTRA_ARTIKEL_SLOTS as $slot) {
            $addItem($items, pick($card['row'], $slot['artikel']), pick($card['row'], $slot['aantal']), $aantalSlangen);
        }
    }

    $list = array_values($items);
    usort($list, static fn(array $a, array $b): int => strnatcasecmp($a['artikel'], $b['artikel']));

    return $list;
}

/** Rendert de picklijst (zie buildPicklist()) als een eigen printpagina. */
function renderPicklist(array $items, string $orderNumber): string
{
    ob_start();
    ?>
    <div class="picklist-sheet">
        <div class="picklist-header">
            <div class="card-brand">GEEVE <span>HYDRAULICS</span><small class="card-subbrand">Picklijst</small></div>
            <div class="card-order-meta">
                <div><span>Ordernummer</span><strong><?= h($orderNumber) ?></strong></div>
            </div>
        </div>
        <?php if ($items === []): ?>
            <p class="coupling-empty">Geen losse artikelen gevonden.</p>
        <?php else: ?>
            <table class="picklist-table">
                <thead><tr><th>Aantal</th><th>Artikelnummer</th><th>Locatie</th></tr></thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?= h(formatQuantity((string) $item['aantal'])) ?></td>
                            <td><?= h($item['artikel']) ?></td>
                            <td><?= $item['locatie'] !== '' ? h($item['locatie']) : '&mdash;' ?></td>
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

    $orderdatum = formatDateTime(pick($row, ORDERDATUM_CANDIDATES));

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
                    <div class="card-detail-row">
                        <span><?= h($field['label']) ?></span>
                        <strong><?= h(pick($row, $field['candidates'])) ?: '&mdash;' ?></strong>
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
    <form method="post" action="index.php" class="lines-form">
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
        <div class="lines-actions">
            <button type="submit" class="submit-button">Print geselecteerde slangkaarten</button>
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
function renderCustomerOrdersForm(array $customerOrders): string
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
                        <td><?= h(formatDateTime(pick($row, ORDERDATUM_CANDIDATES))) ?: '&mdash;' ?></td>
                        <td><?= (int) $order['count'] ?></td>
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

$orderNumber = trim((string) ($_GET['ordernummer'] ?? $_POST['ordernummer'] ?? ''));
$customerName = trim((string) ($_GET['klant'] ?? $_POST['klant'] ?? ''));
$selectedKeys = array_values(array_filter(array_map(
    static fn($value): string => trim((string) $value),
    $_POST['slangnummers'] ?? []
), static fn(string $value): bool => $value !== ''));

$hoseLines = [];
$hoseCards = [];
$customerOrders = [];
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
    <link rel="stylesheet" href="assets/style.css?v=<?= h(assetVersion('assets/style.css')) ?>">
</head>
<body>
<main class="page-shell">
    <header class="page-header">
        <div class="brand-panel">
            <div class="brand-copy">
                <div class="brand-logo-row">
                    <img src="../images/geeve.jpg" alt="Geeve Hydraulics - know how in hydraulics" class="brand-logo-img">
                    <img src="../images/rubix.jpg" alt="Powered by Rubix" class="brand-rubix-img">
                </div>
            </div>
            <a href="../index.php" class="header-home-button" title="Terug naar hoofdmenu" aria-label="Terug naar hoofdmenu">
                <svg viewBox="0 0 24 24" width="36" height="36" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M3 11.5 12 4l9 7.5"></path>
                    <path d="M5.5 9.5V20a1 1 0 0 0 1 1H10v-5a2 2 0 1 1 4 0v5h3.5a1 1 0 0 0 1-1V9.5"></path>
                </svg>
            </a>
            <div class="header-content">
                <div class="header-topline">
                    <span class="version-inline">Versie <?= h(APP_VERSION) ?></span>
                </div>
                <h1>Slangkaarten bij order</h1>
            </div>
        </div>
    </header>

    <section class="panel search-panel">
        <div class="section-heading">
            <div>
                <span class="step">Stap 1</span>
                <h2>Order of klant opzoeken</h2>
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
        <small>Vul een ordernummer óf (een deel van) de klantnaam in.</small>
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
                    <h2>Order <?= h($hoseLinesOrderNumber) ?> &middot; <?= count($hoseLines) ?> slangregel<?= count($hoseLines) === 1 ? '' : 'en' ?> gevonden</h2>
                </div>
                <?php if ($hoseLinesKlant !== ''): ?>
                    <span class="klant-badge"><?= h($hoseLinesKlant) ?></span>
                <?php endif; ?>
            </div>
            <?= renderHoseLinesForm($hoseLines, $orderNumber, $customerName) ?>
        </section>
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
    <?php elseif ($orderNumber !== '' || $customerName !== ''): ?>
        <section class="empty-result">Geen slangregels gevonden voor deze zoekopdracht.</section>
    <?php endif; ?>
</main>

<?php if ($hoseCards !== []): ?>
<div id="printSheet" aria-hidden="true">
    <?php foreach ($hoseCards as $card): ?>
        <?= renderHoseCard($card) ?>
    <?php endforeach; ?>
    <?= renderPicklist(buildPicklist($hoseCards), $orderNumber) ?>
</div>
<?php endif; ?>
</body>
</html>
