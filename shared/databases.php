<?php

declare(strict_types=1);

/**
 * Welke database gebruikt een tegel, en uit welke omgeving (main of test)? Eén plek voor de bronnen, de keuze per tegel (Config >
 * Databases per tegel) en het maken van de verbinding. De verbindingslogica zelf (driver, DSN, foutafhandeling) blijft in
 * shared/db.php (geeveSqlServerConnect()).
 *
 * Twee bronnen:
 *  - exact: de Exact-database (de databasenaam en server staan in portal.json of Config). Hierin kan geschreven worden; naast de gewone ("main") database
 *    kan er een test-database komen (EXACT_DB_NAME_TEST in Config).
 *  - slangkaarten: de eigen database "Slangkaarten". Alleen lezen.
 *
 * Tegels die nu al schrijven (Stauff, Import Offerte: "Naar Exact" via Slangkaart.Web) staan in GEEVE_TILE_SCHRIJVEN_VIA_WEB.
 *
 * Per tegel en per bron die de tegel gebruikt (GEEVE_TILE_DB) staan twee keuzes in de instelling DB_KEUZE (JSON, versleuteld in
 * .settings.enc zoals de overige tegelinstellingen):
 *  - lezen:    main (standaard) of test;
 *  - schrijven: geen (standaard), test of main. Alleen voor bronnen die schrijfbaar zijn (exact).
 *
 * Zonder instelling verandert er niets: alles leest uit de main-database en schrijven staat uit. De PHP-apps schrijven op dit
 * moment ook nergens naar Exact (dat gebeurt alleen in Slangkaart.Web); geeveDbVerbinding($tegel, $bron, 'schrijven') is de plek
 * waar een app straks zijn schrijfverbinding vandaan haalt, en weigert zolang schrijven voor die tegel op "geen" staat.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/secure_settings.php';

/** bron => [titel, schrijfbaar, omgevingen]. */
const GEEVE_DB_BRONNEN = [
    'exact' => ['Exact-database', true, ['main', 'test']],
    'slangkaarten' => ['Slangkaarten-database', false, ['main']],
];

const GEEVE_DB_OMGEVING_TITEL = ['main' => 'Main', 'test' => 'Test'];

/**
 * tegel-id => bronnen die de tegel gebruikt (id's uit TILE_REGISTRY). Tegels die niet in de lijst staan gebruiken geen database
 * (alleen CSV's of een vaste pagina). Een nieuwe tegel met database: hier toevoegen en geeveDbVerbinding() gebruiken.
 */
const GEEVE_TILE_DB = [
    'slangkaarten' => ['slangkaarten', 'exact'],
    'stauff' => ['exact'],
    'msp' => ['exact'],
    'inkoop-groep' => ['exact'],
    'productiviteit' => ['exact'],
    'inkoopprijzen-gr14-gr55' => ['exact'],
    'negatieve-voorraad' => ['exact'],
    'artikel-marge' => ['exact'],
    // Leest de tabel [Slangkaarten].[dbo].[Slangkaarten] via de Exact-login (3-delige naam), niet via de Slangkaarten-login.
    'slangkaart-netto' => ['exact'],
    'klantartikel' => ['exact'],
    'klantkorting' => ['exact'],
    'inkooporder' => ['exact'],
    'order-offerte' => ['exact'],
    'offerte-import' => ['exact'],
    'artikel-import' => ['exact'],
    'export-info' => ['exact'],
    'orderhistorie' => ['exact'],
    'productieorders' => ['exact'],
    'barcode' => ['exact'],
    'stickers-order' => ['exact'],
    'stickers-productie' => ['exact'],
    'stickers-artikel' => ['exact'],
    'stickers-slanglabels' => ['exact'],
];

/**
 * Tegels die al naar Exact schrijven, en wel via de slangkaart-app (Slangkaart.Web, "Naar Exact"). Dat doel (server) staat in de
 * configuratie van Slangkaart.Web (EntityService:Schrijfservers, nu alleen een testserver; productie is daar in de code
 * geblokkeerd). Voor deze tegels geldt dus: schrijven staat standaard op "test" (zoals het nu werkt), "main" is niet te kiezen
 * en "geen" zet de knop "Naar Exact" uit. De portal zelf maakt voor deze tegels geen schrijfverbinding.
 */
const GEEVE_TILE_SCHRIJVEN_VIA_WEB = ['stauff', 'offerte-import'];

/** True als de tegel via Slangkaart.Web naar Exact schrijft. */
function geeveDbSchrijftViaWeb(string $tegel): bool
{
    return in_array($tegel, GEEVE_TILE_SCHRIJVEN_VIA_WEB, true);
}

/** Standaardkeuze zonder instelling: lezen uit main; schrijven uit, behalve voor tegels die via Slangkaart.Web schrijven (test). */
function geeveDbStandaard(string $tegel, string $bron): array
{
    return ['lezen' => 'main', 'schrijven' => $bron === 'exact' && geeveDbSchrijftViaWeb($tegel) ? 'test' : 'geen'];
}

/** Instelling uit een omgevingsvariabele of uit Config (.settings.enc / serverbestand); leeg = null. */
function geeveDbWaarde(string $sleutel): ?string
{
    $env = getenv($sleutel);
    if ($env !== false && trim($env) !== '') {
        return trim($env);
    }
    $instellingen = secureSettingsRead();
    return isset($instellingen[$sleutel]) && trim($instellingen[$sleutel]) !== '' ? trim($instellingen[$sleutel]) : null;
}

/**
 * Databaseconfig (host, port, name, user, password, trustServerCertificate) van één bron in één omgeving, in het formaat van
 * geeveSqlServerConnect(). Gooit DatabaseConfigException als de omgeving niet bestaat of nog niet is ingesteld.
 */
function geeveDbConfig(string $bron, string $omgeving = 'main'): array
{
    if (!isset(GEEVE_DB_BRONNEN[$bron]) || !in_array($omgeving, GEEVE_DB_BRONNEN[$bron][2], true)) {
        throw new DatabaseConfigException('Onbekende database: ' . $bron . ' (' . $omgeving . ').');
    }
    // Geen standaardserver in de code: de server staat in /etc/geeve/portal.json (EXACT_DB_HOST) of anders in Config.
    $exactHost = geeveDbWaarde('EXACT_DB_HOST');
    if ($exactHost === null && ($bron === 'exact' || geeveDbWaarde('DB_HOST') === null)) {
        throw new DatabaseConfigException('De databaseserver is niet ingesteld. Zet EXACT_DB_HOST in ' . secureSettingsFilePath() . ' (of vul de server in via Config > Database-instellingen).');
    }
    $exactPort = geeveDbWaarde('EXACT_DB_PORT');
    $exactTrust = geeveDbWaarde('EXACT_DB_TRUST_SERVER_CERT') ?? 'yes';

    if ($bron === 'slangkaarten') {
        // Server, poort en certificaat volgen de Exact-instellingen (tenzij DB_HOST/DB_PORT/DB_TRUST_SERVER_CERT zijn gezet).
        return [
            'host' => geeveDbWaarde('DB_HOST') ?? $exactHost,
            'port' => geeveDbWaarde('DB_PORT') ?? $exactPort,
            'name' => geeveDbWaarde('DB_NAME') ?? 'Slangkaarten',
            'user' => geeveDbWaarde('DB_USER'),
            'password' => geeveDbWaarde('DB_PASSWORD'),
            'trustServerCertificate' => geeveDbWaarde('DB_TRUST_SERVER_CERT') ?? $exactTrust,
        ];
    }

    $mainNaam = geeveDbWaarde('EXACT_DB_NAME') ?? '005';
    if ($omgeving === 'test') {
        // Test: een andere databasenaam en/of een andere server (EXACT_DB_HOST_TEST/EXACT_DB_PORT_TEST); wat niet is ingesteld volgt Main.
        if (!geeveDbOmgevingBeschikbaar('exact', 'test')) {
            throw new DatabaseConfigException('De Exact test-database is nog niet ingesteld. Vul de naam en/of server in via Config > Database-instellingen.');
        }
        $naam = geeveDbTestNaam() ?? $mainNaam;
        $exactHost = geeveDbWaarde('EXACT_DB_HOST_TEST') ?? $exactHost;
        $exactPort = geeveDbWaarde('EXACT_DB_HOST_TEST') !== null ? geeveDbWaarde('EXACT_DB_PORT_TEST') : $exactPort;
    } else {
        $naam = $mainNaam;
    }
    return [
        'host' => $exactHost,
        'port' => $exactPort,
        'name' => $naam,
        'user' => geeveDbWaarde('EXACT_DB_USER'),
        'password' => geeveDbWaarde('EXACT_DB_PASSWORD'),
        'trustServerCertificate' => $exactTrust,
    ];
}

/**
 * Naam van een databaseonderdeel, tussen blokhaken, voor in een 3-delige naam in een query ([naam].[dbo].[object]). De namen
 * staan in Config > Database-instellingen; zonder instelling de standaardnaam:
 *  - slangkaarten: Slangkaarten (DB_NAME);
 *  - rapportage:   Exactrapportage, de rapportageviews en -procedures (EXACT_DB_RAPPORTAGE);
 *  - data:         005_Data_Geeve, de data-views en -procedures van Geeve (EXACT_DB_DATA).
 */
function geeveDbNaamSql(string $soort): string
{
    $sleutels = ['slangkaarten' => ['DB_NAME', 'Slangkaarten'], 'rapportage' => ['EXACT_DB_RAPPORTAGE', 'Exactrapportage'], 'data' => ['EXACT_DB_DATA', '005_Data_Geeve']];
    if (!isset($sleutels[$soort])) {
        throw new InvalidArgumentException('Onbekende database: ' . $soort . '.');
    }
    return geeveSqlNaam(geeveDbWaarde($sleutels[$soort][0]) ?? $sleutels[$soort][1]);
}

/** Naam van de Exact test-database als die is ingesteld en een geldige databasenaam is (geen overgebleven voorbeeldtekst als "<naam ...>"); anders null. */
function geeveDbTestNaam(): ?string
{
    $naam = geeveDbWaarde('EXACT_DB_NAME_TEST');
    return $naam !== null && preg_match('/^[A-Za-z0-9_.-]{1,128}$/', $naam) === 1 ? $naam : null;
}

/** True als de omgeving van de bron is ingesteld (voor main altijd; voor test als er een databasenaam of een testserver is). */
function geeveDbOmgevingBeschikbaar(string $bron, string $omgeving): bool
{
    if (!isset(GEEVE_DB_BRONNEN[$bron]) || !in_array($omgeving, GEEVE_DB_BRONNEN[$bron][2], true)) {
        return false;
    }
    return $omgeving !== 'test' || geeveDbTestNaam() !== null || geeveDbWaarde('EXACT_DB_HOST_TEST') !== null;
}

/** Opgeslagen keuzes als [tegel][bron] => ['lezen' => ..., 'schrijven' => ...]; alleen geldige waarden. */
function geeveDbKeuzes(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = [];
    $json = secureSettingsRead()['DB_KEUZE'] ?? '';
    $opgeslagen = $json === '' ? null : json_decode($json, true);
    if (!is_array($opgeslagen)) {
        return $cache;
    }
    foreach ($opgeslagen as $tegel => $bronnen) {
        if (!is_string($tegel) || !is_array($bronnen)) {
            continue;
        }
        foreach ($bronnen as $bron => $keuze) {
            if (!is_string($bron) || !isset(GEEVE_DB_BRONNEN[$bron]) || !is_array($keuze)) {
                continue;
            }
            $std = geeveDbStandaard($tegel, $bron);
            $lezen = $keuze['lezen'] ?? $std['lezen'];
            $schrijven = $keuze['schrijven'] ?? $std['schrijven'];
            $schrijfOk = GEEVE_DB_BRONNEN[$bron][1] && in_array($schrijven, GEEVE_DB_BRONNEN[$bron][2], true)
                && !($schrijven === 'main' && geeveDbSchrijftViaWeb($tegel)); // main is via Slangkaart.Web (nog) geblokkeerd
            $cache[$tegel][$bron] = [
                'lezen' => in_array($lezen, GEEVE_DB_BRONNEN[$bron][2], true) ? $lezen : 'main',
                'schrijven' => $schrijfOk ? $schrijven : 'geen',
            ];
        }
    }
    return $cache;
}

/** De keuze voor één tegel en bron; zonder instelling de standaard (geeveDbStandaard). @return array{lezen:string,schrijven:string} */
function geeveDbKeuze(string $tegel, string $bron): array
{
    return geeveDbKeuzes()[$tegel][$bron] ?? geeveDbStandaard($tegel, $bron);
}

/** Mag deze tegel naar Exact schrijven (keuze is niet "geen")? Gebruik dit voor de knop "Naar Exact" van een tegel. */
function geeveDbSchrijvenAan(string $tegel): bool
{
    return geeveDbKeuze($tegel, 'exact')['schrijven'] !== 'geen';
}

/** Tekst voor als de inloggegevens van een bron ontbreken. */
function geeveDbOntbrekendHint(string $bron): string
{
    return $bron === 'slangkaarten'
        ? 'Database-inloggegevens ontbreken. Vul de gebruiker en het wachtwoord van de Slangkaarten-database in via Config in het hoofdmenu (zie slkprinten/README.md voor de read-only SQL-login van deze webapp).'
        : 'Exact-database-inloggegevens ontbreken. Vul EXACT_DB_USER en EXACT_DB_PASSWORD in via Config in het hoofdmenu.';
}

/**
 * Verbinding voor een tegel met een bron, in de omgeving die in Config voor die tegel is gekozen.
 * $doel 'lezen': de leesomgeving (standaard main). $doel 'schrijven': de schrijfomgeving; staat die op "geen" (standaard), of is de
 * bron niet schrijfbaar, dan een DatabaseConfigException. Een niet-lege $tegel die niet in GEEVE_TILE_DB staat krijgt de standaard.
 * Gooit DatabaseConfigException met een melding die veilig op het scherm kan.
 */
function geeveDbVerbinding(string $tegel, string $bron, string $doel = 'lezen', ?int $queryTimeout = null): PDO
{
    if (!isset(GEEVE_DB_BRONNEN[$bron])) {
        throw new DatabaseConfigException('Onbekende database: ' . $bron . '.');
    }
    $keuze = geeveDbKeuze($tegel, $bron);
    if ($doel === 'schrijven') {
        if (!GEEVE_DB_BRONNEN[$bron][1]) {
            throw new DatabaseConfigException('De ' . GEEVE_DB_BRONNEN[$bron][0] . ' is alleen-lezen: hier kan niet in geschreven worden.');
        }
        if (geeveDbSchrijftViaWeb($tegel)) {
            throw new DatabaseConfigException('Deze tegel schrijft via de slangkaart-app (Slangkaart.Web), niet via een eigen databaseverbinding.');
        }
        if ($keuze['schrijven'] === 'geen') {
            throw new DatabaseConfigException('Schrijven naar de ' . GEEVE_DB_BRONNEN[$bron][0] . ' staat uit voor deze tegel. Kies een test- of main-database in Config > Tegels.');
        }
        $omgeving = $keuze['schrijven'];
    } else {
        $omgeving = $keuze['lezen'];
    }
    return geeveSqlServerConnect(geeveDbConfig($bron, $omgeving), geeveDbOntbrekendHint($bron), $queryTimeout);
}
