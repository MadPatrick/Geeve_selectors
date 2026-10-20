<?php

declare(strict_types=1);

/**
 * Sneeuw in de topbalk rond de kerst (Config > Topbalk). Instellingen (secureSettings):
 *   SNEEUW_AAN   'yes' = sneeuw gebruiken, anders altijd de glow
 *   SNEEUW_VAN   'MM-DD' eerste dag van de periode (standaard 12-15)
 *   SNEEUW_TOT   'MM-DD' laatste dag van de periode (standaard 01-06)
 * De periode geldt elk jaar en mag over de jaarwisseling lopen (van > tot).
 *
 * Pasen (Config > Topbalk): PASEN_AAN 'yes' = paaseieren in de topbalk van 7 dagen voor eerste paasdag t/m
 * tweede paasdag (paasdatum berekend, geen extensie nodig); buiten die periode geen eieren.
 */

require_once __DIR__ . '/secure_settings.php';

const SNEEUW_STANDAARD_VAN = '12-15';
const SNEEUW_STANDAARD_TOT = '01-06';

/** Geeft 'MM-DD' terug, of $standaard als de waarde geen geldige dag is. */
function sneeuwDag(string $waarde, string $standaard): string
{
    if (preg_match('/^(\d{2})-(\d{2})$/', $waarde, $m) === 1 && checkdate((int) $m[1], (int) $m[2], 2024)) {
        return $waarde;
    }
    return $standaard;
}

/** De ingestelde periode: ['aan' => bool, 'van' => 'MM-DD', 'tot' => 'MM-DD']. */
function sneeuwInstellingen(): array
{
    $s = secureSettingsRead();
    return [
        'aan' => ($s['SNEEUW_AAN'] ?? '') === 'yes',
        'van' => sneeuwDag((string) ($s['SNEEUW_VAN'] ?? ''), SNEEUW_STANDAARD_VAN),
        'tot' => sneeuwDag((string) ($s['SNEEUW_TOT'] ?? ''), SNEEUW_STANDAARD_TOT),
    ];
}

/** True als de sneeuw aan staat en vandaag (of $vandaag, 'MM-DD') in de periode valt. */
function sneeuwActief(?string $vandaag = null): bool
{
    $i = sneeuwInstellingen();
    if (!$i['aan']) {
        return false;
    }
    $nu = $vandaag ?? date('m-d');
    return $i['van'] <= $i['tot']
        ? ($nu >= $i['van'] && $nu <= $i['tot'])
        : ($nu >= $i['van'] || $nu <= $i['tot']);
}

/** De sneeuwvlokken als HTML (vaste, pseudo-willekeurige verdeling: bij elke paginalaad hetzelfde). */
function sneeuwVlokkenHtml(int $aantal = 36): string
{
    $html = '<div class="snow" aria-hidden="true">';
    for ($n = 0; $n < $aantal; $n++) {
        $links = ($n * 37 + 11) % 100;
        $grootte = 3 + ($n * 5) % 5;            // 3-7 px
        $duur = 6 + ($n * 7) % 7;               // 6-12 s
        $vertraging = -(($n * 13) % 12);        // negatief: de vlokken vallen al bij het laden
        $drift = (($n % 2 === 0) ? 1 : -1) * (8 + ($n * 3) % 14); // zijwaartse afwijking in px
        $html .= '<i style="--l:' . $links . '%;--s:' . $grootte . 'px;--d:' . $duur . 's;--w:' . $vertraging . 's;--x:' . $drift . 'px"></i>';
    }
    return $html . '</div>';
}

/** Datum van eerste paasdag (Gregoriaanse kalender, algoritme van Meeus/Jones/Butcher). */
function paasDatum(int $jaar): DateTimeImmutable
{
    $a = $jaar % 19;
    $b = intdiv($jaar, 100);
    $c = $jaar % 100;
    $d = intdiv($b, 4);
    $e = $b % 4;
    $f = intdiv($b + 8, 25);
    $g = intdiv($b - $f + 1, 3);
    $h = (19 * $a + $b - $d - $g + 15) % 30;
    $i = intdiv($c, 4);
    $k = $c % 4;
    $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
    $m = intdiv($a + 11 * $h + 22 * $l, 451);
    $maand = intdiv($h + $l - 7 * $m + 114, 31);
    $dag = (($h + $l - 7 * $m + 114) % 31) + 1;
    return new DateTimeImmutable(sprintf('%04d-%02d-%02d', $jaar, $maand, $dag));
}

/** True als paaseieren aan staan en vandaag (of $vandaag, 'JJJJ-MM-DD') tussen 7 dagen voor en tweede paasdag valt. */
function paasActief(?string $vandaag = null): bool
{
    if ((secureSettingsRead()['PASEN_AAN'] ?? '') !== 'yes') {
        return false;
    }
    $nu = new DateTimeImmutable($vandaag ?? date('Y-m-d'));
    $pasen = paasDatum((int) $nu->format('Y'));
    return $nu >= $pasen->modify('-7 days') && $nu <= $pasen->modify('+1 day');
}

/** Een paar paaseieren langs de onderkant van de topbalk. */
function paasEierenHtml(): string
{
    $html = '<div class="eggs" aria-hidden="true">';
    foreach ([[38, 1], [47, 2], [55, 3], [63, 4], [71, 5]] as $n => [$links, $kleur]) {
        $html .= '<svg class="egg egg-' . $kleur . '" style="--l:' . $links . '%;--r:' . (($n % 2 === 0) ? -12 : 10) . 'deg;--w:' . ($n * -0.6) . 's" viewBox="0 0 24 30">'
            . '<path class="egg-body" d="M12 1C6 1 2 12 2 19a10 10 0 0 0 20 0C22 12 18 1 12 1z"/>'
            . '<path class="egg-stripe" d="M3 17q4.5 3.5 9 0t9 0M4.5 22.5q3.8 3 7.500 0t7.500 0" fill="none"/></svg>';
    }
    return $html . '</div>';
}
