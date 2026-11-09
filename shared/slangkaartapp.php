<?php

declare(strict_types=1);

/**
 * Leesverbinding met de slangkaart-app van Hans (Slangkaart.Web).
 *
 * Nieuwe slangkaarten staan niet meer in de oude database ("2500 Slangkaarten ..."), maar in de eigen opslag van die app.
 * De app geeft ze terug met dezelfde kolomnamen als de oude views. Deze bestanden voegen die rijen samen met de oude
 * (de app gaat voor). Alleen lezen; de adressen zijn alleen bereikbaar vanaf de server zelf (127.0.0.1).
 *
 * Adres: omgevingsvariabele GEEVE_SLANGKAART_APP_URL (bijv. voor de testversie
 * http://127.0.0.1:5299/slangkaarten/api/slangkaarten), standaard poort 5288. Is de app niet bereikbaar, dan geven alle
 * functies hier een lege lijst en werkt alles zoals voorheen met alleen de oude database.
 */

function slangkaartAppBasis(): string
{
    $url = trim((string) getenv('GEEVE_SLANGKAART_APP_URL'));

    return rtrim($url !== '' ? $url : 'http://127.0.0.1:5288/slangkaarten/api/slangkaarten', '/');
}

/**
 * GET naar de app. $params: naam => waarde, of naam => lijst waarden (herhaalde parameter, zoals nummer=a&nummer=b).
 * Geeft null bij elke fout (niet bereikbaar, geen 200, geen geldige JSON). Na een verbindingsfout wordt de app in dit
 * verzoek niet opnieuw geprobeerd, zodat een stilgevallen app de pagina niet telkens ophoudt.
 *
 * @param array<string, string|list<string>> $params
 * @return array<string, mixed>|null
 */
function slangkaartAppGet(string $pad, array $params): ?array
{
    static $onbereikbaar = false;
    if ($onbereikbaar) {
        return null;
    }

    $delen = [];
    foreach ($params as $naam => $waarde) {
        foreach ((array) $waarde as $enkel) {
            $delen[] = rawurlencode($naam) . '=' . rawurlencode((string) $enkel);
        }
    }
    $url = slangkaartAppBasis() . '/' . ltrim($pad, '/') . ($delen !== [] ? '?' . implode('&', $delen) : '');

    $context = stream_context_create(['http' => ['method' => 'GET', 'timeout' => 10, 'ignore_errors' => true, 'header' => "Accept: application/json\r\n"]]);
    $antwoord = @file_get_contents($url, false, $context);
    if ($antwoord === false) {
        $onbereikbaar = true;

        return null;
    }
    $status = 0;
    if (isset($http_response_header[0]) && preg_match('#^HTTP/\S+\s+(\d{3})#', (string) $http_response_header[0], $m) === 1) {
        $status = (int) $m[1];
    }
    if ($status !== 200) {
        return null;
    }
    $json = json_decode($antwoord, true);

    return is_array($json) ? $json : null;
}

/** Datums uit de app (ISO met T) in dezelfde tekstvorm als de oude database ("2026-11-09 00:00:00"). */
function slangkaartAppRij(array $rij): array
{
    foreach ($rij as $naam => $waarde) {
        if (is_string($waarde) && preg_match('/^(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2}:\d{2})/', $waarde, $m) === 1) {
            $rij[$naam] = $m[1] . ' ' . $m[2];
        } elseif ($waarde === null) {
            $rij[$naam] = '';
        }
    }

    return $rij;
}

/**
 * Orderregels (zoals "2500 Slangkaarten bij order") van kaarten uit de app.
 *
 * @param array{ordernr?: string, nummer?: string, klant?: string} $zoek
 * @return list<array<string, mixed>>
 */
function slangkaartAppRegels(array $zoek): array
{
    $ordernr = trim((string) ($zoek['ordernr'] ?? ''));
    if ($ordernr !== '') {
        $json = slangkaartAppGet('order-regels', ['ordernr' => $ordernr]);
    } else {
        $params = array_filter(['nummer' => trim((string) ($zoek['nummer'] ?? '')), 'klant' => trim((string) ($zoek['klant'] ?? ''))], static fn(string $v): bool => $v !== '');
        $json = $params === [] ? null : slangkaartAppGet('zoek-regels', $params);
    }

    return slangkaartAppRijen($json['regels'] ?? null);
}

/**
 * Kaarten (zoals "2500 Slangkaarten" + sleutel Details) per slangnummer.
 *
 * @param list<string> $nummers
 * @return array<string, array<string, mixed>> per slangnummer
 */
function slangkaartAppKaarten(array $nummers): array
{
    $nummers = array_values(array_unique(array_filter(array_map('trim', $nummers), static fn(string $n): bool => $n !== '')));
    $resultaat = [];
    foreach (array_chunk($nummers, 150) as $deel) {
        $json = slangkaartAppGet('kaarten', ['nummer' => $deel]);
        foreach (slangkaartAppRijen($json['kaarten'] ?? null) as $kaart) {
            $nummer = trim((string) ($kaart['Nummer'] ?? ''));
            if ($nummer !== '') {
                $resultaat[$nummer] = $kaart + ['GHnr' => $nummer];
            }
        }
    }

    return $resultaat;
}

/**
 * Slangnummers uit de app die bij de zoekterm passen.
 *
 * @return list<string>
 */
function slangkaartAppZoekNummers(string $term, bool $exact): array
{
    $json = slangkaartAppGet('zoek', ['term' => $term, 'exact' => $exact ? 'true' : 'false']);
    $nummers = is_array($json['nummers'] ?? null) ? $json['nummers'] : [];

    return array_values(array_filter(array_map(static fn($n): string => trim((string) $n), $nummers), static fn(string $n): bool => $n !== ''));
}

/** @return list<array<string, mixed>> */
function slangkaartAppRijen($lijst): array
{
    if (!is_array($lijst)) {
        return [];
    }
    $rijen = [];
    foreach ($lijst as $rij) {
        if (is_array($rij)) {
            $rijen[] = slangkaartAppRij($rij);
        }
    }

    return $rijen;
}

/**
 * Voegt oude (view) en nieuwe (app) orderregels samen op ordernr + rgl; bij dezelfde regel gaat de app voor. Regels
 * die alleen in de oude database staan blijven staan.
 *
 * @param list<array<string, mixed>> $oud
 * @param list<array<string, mixed>> $app
 * @return list<array<string, mixed>>
 */
function slangkaartAppSamenvoegen(array $oud, array $app): array
{
    if ($app === []) {
        return $oud;
    }
    $sleutel = static fn(array $r): string => strtoupper(trim((string) ($r['ordernr'] ?? ''))) . '|' . trim((string) ($r['rgl'] ?? ''));
    $inApp = [];
    foreach ($app as $r) {
        $inApp[$sleutel($r)] = true;
    }
    $samen = $app;
    foreach ($oud as $r) {
        if (!isset($inApp[$sleutel($r)])) {
            $samen[] = $r;
        }
    }

    return $samen;
}

/**
 * Koppelonderdelen uit de sleutel Details van een app-kaart, in de vorm van findCouplingRowsFromDetails()
 * (Zijde AA/BB = artikelnummer, aantal 1).
 *
 * @return array{A: list<array<string, string>>, B: list<array<string, string>>}
 */
function slangkaartAppKoppelingen(array $kaart): array
{
    $zijden = ['A' => [], 'B' => []];
    $details = is_array($kaart['Details'] ?? null) ? $kaart['Details'] : [];
    usort($details, static fn($a, $b): int => (int) ($a['Regel'] ?? 0) <=> (int) ($b['Regel'] ?? 0));
    foreach ($details as $d) {
        $zijde = strtoupper(trim((string) ($d['Type'] ?? '')));
        $artikel = trim((string) ($d['artnr'] ?? ''));
        if ($artikel === '' || ($zijde !== 'A' && $zijde !== 'B')) {
            continue;
        }
        $zijden[$zijde][] = [$zijde === 'A' ? 'Zijde AA' : 'Zijde BB' => $artikel, $zijde === 'A' ? 'Aantal_A' : 'Aantal_B' => '1'];
    }

    return $zijden;
}

/**
 * Voor een controlepagina: één aanroep met adres, status en het begin van het antwoord, ook bij een fout.
 *
 * @param array<string, string|list<string>> $params
 * @return array{url: string, status: int, antwoord: string}
 */
function slangkaartAppDiagnose(string $pad, array $params): array
{
    $delen = [];
    foreach ($params as $naam => $waarde) {
        foreach ((array) $waarde as $enkel) {
            $delen[] = rawurlencode($naam) . '=' . rawurlencode((string) $enkel);
        }
    }
    $url = slangkaartAppBasis() . '/' . ltrim($pad, '/') . ($delen !== [] ? '?' . implode('&', $delen) : '');
    $context = stream_context_create(['http' => ['method' => 'GET', 'timeout' => 10, 'ignore_errors' => true]]);
    $antwoord = @file_get_contents($url, false, $context);
    if ($antwoord === false) {
        $fout = error_get_last();

        return ['url' => $url, 'status' => 0, 'antwoord' => 'Geen verbinding: ' . ($fout['message'] ?? 'onbekende fout')];
    }
    $status = 0;
    if (isset($http_response_header[0]) && preg_match('#^HTTP/\S+\s+(\d{3})#', (string) $http_response_header[0], $m) === 1) {
        $status = (int) $m[1];
    }

    return ['url' => $url, 'status' => $status, 'antwoord' => substr($antwoord, 0, 1500)];
}
