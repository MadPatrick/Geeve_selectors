<?php

declare(strict_types=1);

/**
 * Sneeuw (kerst) en paaseieren (Pasen) in de topbalk, in plaats van de glow. Zie ook shared/header.php.
 */

/*
 * Topbalk-animaties (Config > Topbalk), elk met: AAN ('yes'), DATUM ('MM-DD', elk jaar), VOOR en NA (aantal dagen
 * rond die datum waarin het actief is). Sneeuw: SNEEUW_* (standaard 25 december, 10 dagen ervoor, 12 erna).
 * Pasen: PASEN_* (standaard de berekende eerste paasdag, 7 dagen ervoor, 1 erna; een lege DATUM = automatisch
 * eerste paasdag). Nieuwjaar: NIEUWJAAR_* (standaard 1 januari, 1 dag ervoor, 1 erna) met vuurwerk. Buiten de periode
 * (of uit) staat de glow. Voorrang bij overlap: Nieuwjaar, dan sneeuw, dan paaseieren.
 */

require_once __DIR__ . '/secure_settings.php';

const TOPBALK_STANDAARD = [
    'SNEEUW' => ['datum' => '12-25', 'voor' => 10, 'na' => 12],
    'PASEN' => ['datum' => '', 'voor' => 7, 'na' => 1],
    'NIEUWJAAR' => ['datum' => '01-01', 'voor' => 1, 'na' => 1],
];

/** Geeft 'MM-DD' terug, of $standaard als de waarde geen geldige dag is. */
function sneeuwDag(string $waarde, string $standaard): string
{
    if (preg_match('/^(\d{2})-(\d{2})$/', $waarde, $m) === 1 && checkdate((int) $m[1], (int) $m[2], 2024)) {
        return $waarde;
    }
    return $standaard;
}

/** Aantal dagen 0-60. */
function topbalkDagen($waarde, int $standaard): int
{
    return is_numeric($waarde) ? max(0, min(60, (int) $waarde)) : $standaard;
}

/** De instellingen van $naam ('SNEEUW' of 'PASEN'): ['aan' => bool, 'datum' => 'MM-DD' of '' (= Pasen), 'voor' => int, 'na' => int]. */
function topbalkInstellingen(string $naam): array
{
    $s = secureSettingsRead();
    $std = TOPBALK_STANDAARD[$naam];
    return [
        'aan' => ($s[$naam . '_AAN'] ?? '') === 'yes',
        'datum' => sneeuwDag((string) ($s[$naam . '_DATUM'] ?? ''), $std['datum']),
        'voor' => topbalkDagen($s[$naam . '_VOOR'] ?? null, $std['voor']),
        'na' => topbalkDagen($s[$naam . '_NA'] ?? null, $std['na']),
    ];
}

/** True als $naam aan staat en vandaag (of $vandaag, 'JJJJ-MM-DD') binnen de periode rond de datum valt. */
function topbalkActief(string $naam, ?string $vandaag = null): bool
{
    $i = topbalkInstellingen($naam);
    if (!$i['aan']) {
        return false;
    }
    $nu = new DateTimeImmutable($vandaag ?? date('Y-m-d'));
    $jaar = (int) $nu->format('Y');
    foreach ([$jaar - 1, $jaar, $jaar + 1] as $j) { // ook de datum van vorig/volgend jaar: de periode kan over de jaarwisseling lopen
        $midden = $i['datum'] === '' ? paasDatum($j) : new DateTimeImmutable($j . '-' . $i['datum']);
        if ($nu >= $midden->modify('-' . $i['voor'] . ' days') && $nu <= $midden->modify('+' . $i['na'] . ' days')) {
            return true;
        }
    }
    return false;
}

function sneeuwActief(?string $vandaag = null): bool
{
    return topbalkActief('SNEEUW', $vandaag);
}

function nieuwjaarActief(?string $vandaag = null): bool
{
    return topbalkActief('NIEUWJAAR', $vandaag);
}

function paasActief(?string $vandaag = null): bool
{
    return topbalkActief('PASEN', $vandaag);
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

/** Een paar vuurwerkbursts verspreid over de topbalk. */
function vuurwerkHtml(): string
{
    $html = '<div class="fireworks" aria-hidden="true">';
    foreach ([[52, 38, 1, 0], [64, 62, 2, 1.1], [76, 34, 3, 2.3], [88, 58, 4, 0.6], [40, 60, 3, 1.8], [96, 40, 1, 2.9]] as [$links, $boven, $kleur, $vertraging]) {
        $html .= '<i class="fw fw-' . $kleur . '" style="--l:' . $links . '%;--t:' . $boven . '%;--w:' . $vertraging . 's"></i>';
    }
    return $html . '</div>';
}
