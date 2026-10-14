<?php

declare(strict_types=1);

// Beveiligde tegel (Config > Tegels): zonder ontgrendelen geen gegevens.
require_once __DIR__ . '/../../../shared/tiles.php';
tileApiGuard('artikel-marge');

/**
 * Artikelmarges: per artikel de standaard verkoop- en inkoopprijs, toeslag en marge. Leest de view
 * [Exactrapportage].[dbo].[1000 artikelen + standaard verk_pr + standaard ink_pr], dezelfde bron als de dataverbinding in de
 * Excel "Artikel met verkoopprijs inkoopprijs en marge", met dezelfde kolommen in dezelfde volgorde
 * (zonder verkinink, ink_pr per verp, ink_eenh en ArtGrpnm_EN).
 * Beide filters zijn optioneel (leeg = alle artikelen, ca. 70.000 regels); het scherm toont alleen de eerste MARGE_MAX_SHOWN.
 */

require_once __DIR__ . '/../../inc.php';

const MARGE_MAX_SHOWN = 500;

/** Kolommen uit de view (volgorde van de Excel). */
const MARGE_COLUMNS = [
    'Artnr', 'artnm', 'artgrp', 'Vrk prijs', 'verk_eenh', 'ink_pr per st', 'toeslag % V/I', 'marge I/V', 'aant_lev',
    'ArtGrpnm_NL', 'parkergroepennr', 'kortingsgroep parker',
    'finartgrp', 'IE_i_VE', 'artnr_lev', 'crdnr',
];
/** Kolommen die als getal worden doorgegeven (ook als de view ze als tekst levert, zoals ink_pr per st). */
const MARGE_NUMERIC = ['Vrk prijs', 'ink_pr per st', 'toeslag % V/I', 'marge I/V', 'aant_lev', 'IE_i_VE'];

reportApi(function (array $in): array {
    $group = trim((string) ($in['groep'] ?? ''));
    if ($group !== '' && preg_match('/^[A-Za-z0-9._ -]{1,20}$/', $group) !== 1) {
        throw new RuntimeException('Vul een geldige artikelgroep in.');
    }
    $search = trim((string) ($in['zoek'] ?? ''));
    if (strlen($search) > 60) {
        throw new RuntimeException('De zoekterm is te lang (maximaal 60 tekens).');
    }

    $where = [];
    $params = [];
    if ($group !== '') {
        $where[] = 'LTRIM(RTRIM(CAST([artgrp] AS varchar(20)))) = ?';
        $params[] = $group;
    }
    if ($search !== '') {
        // % en _ in de zoekterm letterlijk nemen.
        $like = '%' . str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $search) . '%';
        $where[] = '([Artnr] LIKE ? OR [artnm] LIKE ?)';
        $params[] = $like;
        $params[] = $like;
    }

    $select = implode(', ', array_map(fn ($c) => '[' . $c . ']', MARGE_COLUMNS));
    $stmt = reportConnection()->prepare(
        'SET NOCOUNT ON; SELECT ' . $select . ' FROM [Exactrapportage].[dbo].[1000 artikelen + standaard verk_pr + standaard ink_pr] WITH (NOLOCK)' .
        ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY [Artnr]'
    );
    $stmt->execute($params);

    $numeric = array_map(fn ($c) => in_array($c, MARGE_NUMERIC, true), MARGE_COLUMNS);
    $rows = [];
    while ($r = $stmt->fetch(PDO::FETCH_NUM)) {
        foreach ($r as $i => $v) {
            if ($v === null) {
                continue;
            }
            $v = trim((string) $v);
            if ($numeric[$i]) {
                $v = is_numeric($v) ? $v + 0 : ($v === '' ? null : $v);
            }
            $r[$i] = $v;
        }
        $rows[] = $r;
    }

    $kop = [];
    if ($group !== '') {
        $kop[] = ['label' => 'Artikelgroep', 'value' => $group];
    }
    if ($search !== '') {
        $kop[] = ['label' => 'Bevat', 'value' => $search];
    }
    if (count($rows) > MARGE_MAX_SHOWN) {
        $kop[] = ['label' => 'Op het scherm', 'value' => 'eerste ' . MARGE_MAX_SHOWN . ' (download bevat alles)'];
    }
    $titles = ['Artikelen met verkoopprijs, inkoopprijs en marge'];
    $filter = trim(($group !== '' ? 'Artikelgroep ' . $group : '') . ($search !== '' ? ($group !== '' ? ', ' : '') . 'bevat "' . $search . '"' : ''));
    if ($filter !== '') {
        $titles[] = $filter;
    }
    $tag = trim(($group !== '' ? $group : 'alle') . ($search !== '' ? '_' . $search : ''));

    return [
        'columns' => MARGE_COLUMNS,
        'numeric' => $numeric,
        'dates' => array_fill(0, count(MARGE_COLUMNS), false),
        'rows' => $rows,
        'text' => ['Artnr', 'artgrp', 'artnr_lev', 'crdnr', 'parkergroepennr', 'finartgrp'],
        'currency' => ['Vrk prijs', 'ink_pr per st'],
        'percent' => ['toeslag % V/I', 'marge I/V'],
        'nowrap' => ['Artnr'],
        'maxShown' => MARGE_MAX_SHOWN,
        'kop' => $kop,
        'titles' => $titles,
        'fileTag' => preg_replace('/[^A-Za-z0-9._-]+/', '_', $tag),
    ];
}, false);
