<?php

declare(strict_types=1);

/**
 * Sneeuw in de topbalk rond de kerst (Config > Topbalk). Instellingen (secureSettings):
 *   SNEEUW_AAN   'yes' = sneeuw gebruiken, anders altijd de glow
 *   SNEEUW_VAN   'MM-DD' eerste dag van de periode (standaard 12-15)
 *   SNEEUW_TOT   'MM-DD' laatste dag van de periode (standaard 01-06)
 * De periode geldt elk jaar en mag over de jaarwisseling lopen (van > tot).
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
