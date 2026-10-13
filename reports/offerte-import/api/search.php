<?php

declare(strict_types=1);

/**
 * Zoekt debiteuren, crediteuren of medewerkers (soort medewerker, nummer = res_id) op naam (of nummer) met een tolerante (fuzzy) vergelijking, voor het invulveld in de
 * offerte- en inkooporder-import. POST met csrf, q en soort (debiteur of crediteur); antwoord:
 * {"ok":true,"results":[{"nummer":"4001","naam":"..."}]} (maximaal 8, beste eerst). Alleen lezen.
 */

require_once __DIR__ . '/../../inc.php';
require_once __DIR__ . '/../../../shared/csrf.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !csrfValid($_POST['csrf'] ?? null)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Ongeldig verzoek (sessie verlopen). Laad de pagina opnieuw.']);
    exit;
}

/** Kleine letters, zonder accenten en leestekens, als losse woorden. */
function searchWords(string $text): array
{
    $text = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
    // Veelvoorkomende letters met accent zonder iconv (die werkt niet overal).
    $text = strtr($text, ['é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e', 'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'í' => 'i', 'ï' => 'i', 'ó' => 'o', 'ö' => 'o', 'ô' => 'o', 'ú' => 'u', 'ü' => 'u', 'û' => 'u', 'ç' => 'c', 'ñ' => 'n', 'ß' => 'ss', 'É' => 'e', 'Ë' => 'e', 'Ö' => 'o', 'Ü' => 'u']);
    $text = preg_replace('/[^a-z0-9]+/', ' ', $text) ?? '';
    return array_values(array_filter(explode(' ', trim($text)), static fn ($w) => $w !== ''));
}

/** Score 0..1 van één zoekwoord tegen de woorden van een naam. */
function tokenScore(string $token, array $words): float
{
    $best = 0.0;
    $len = strlen($token);
    foreach ($words as $word) {
        if ($word === $token) {
            return 1.0;
        }
        if (str_starts_with($word, $token)) {
            $best = max($best, 0.9);
        } elseif ($len >= 3 && str_contains($word, $token)) {
            $best = max($best, 0.7);
        } elseif ($len >= 4) {
            $head = substr($word, 0, $len);
            $dist = min(levenshtein($token, $word), levenshtein($token, $head));
            $max = max($len, 1);
            if ($dist <= 2 && $dist < $max / 2) {
                $best = max($best, 0.8 - 0.2 * $dist);
            }
        }
    }
    return $best;
}

$flags = JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE;
try {
    $query = trim((string) ($_POST['q'] ?? ''));
    $kind = (string) ($_POST['soort'] ?? 'debiteur');
    $creditor = $kind === 'crediteur';
    $employee = $kind === 'medewerker';
    $tokens = searchWords($query);
    if (strlen($query) < 2 || strlen($query) > 60 || $tokens === []) {
        echo json_encode(['ok' => true, 'results' => []], $flags);
        exit;
    }

    $pdo = reportConnection();
    $sql = $employee
        ? "SET NOCOUNT ON; SELECT CAST(res_id AS varchar(20)) AS nr, '' AS code, RTRIM(fullname) AS naam FROM humres WHERE res_id IS NOT NULL"
        : ($creditor
        ? "SET NOCOUNT ON; SELECT RTRIM(crdnr) AS nr, RTRIM(crdcode) AS code, RTRIM(cmp_name) AS naam FROM cicmpy WHERE LTRIM(RTRIM(ISNULL(crdnr, ''))) <> '' OR LTRIM(RTRIM(ISNULL(crdcode, ''))) <> ''"
        : "SET NOCOUNT ON; SELECT RTRIM(debnr) AS nr, RTRIM(debcode) AS code, RTRIM(cmp_name) AS naam FROM cicmpy WHERE LTRIM(RTRIM(ISNULL(debnr, ''))) <> '' OR LTRIM(RTRIM(ISNULL(debcode, ''))) <> ''");
    $scored = [];
    foreach ($pdo->query($sql)->fetchAll() as $row) {
        $number = trim((string) $row['nr']) !== '' ? trim((string) $row['nr']) : trim((string) $row['code']);
        $name = trim((string) $row['naam']);
        if ($number === '') {
            continue;
        }
        $words = searchWords($name);
        $sum = 0.0;
        $ok = true;
        foreach ($tokens as $token) {
            $s = tokenScore($token, $words);
            if (ctype_digit($token) && strncmp(strtolower($number), $token, strlen($token)) === 0) {
                $s = max($s, $token === strtolower($number) ? 1.0 : 0.9);
            }
            if ($s <= 0.0) {
                $ok = false;
                break;
            }
            $sum += $s;
        }
        if ($ok) {
            $scored[] = ['score' => $sum / count($tokens), 'nummer' => $number, 'naam' => $name];
        }
    }
    usort($scored, static fn ($a, $b) => [$b['score'], $a['naam']] <=> [$a['score'], $b['naam']]);
    $results = array_map(static fn ($r) => ['nummer' => $r['nummer'], 'naam' => $r['naam']], array_slice($scored, 0, 8));
    echo json_encode(['ok' => true, 'results' => $results], $flags);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], $flags);
}
