<?php

declare(strict_types=1);

// Eén gedeeld versienummer voor het hoofdscherm en alle subapps (hoses,
// adapters, configurator, stauff, slkprinten, stickers). Elke pagina laadt dit bestand i.p.v.
// een eigen losse APP_VERSION-constante te declareren, zodat een versie-
// ophoging nog maar op één plek hoeft te gebeuren. Bestaat dit bestand niet
// (bv. een subapp los buiten deze portal-map gedeployed), dan valt elke
// pagina terug op een eigen hardcoded waarde - zie de APP_VERSION-regel
// verderop in dat bestand.
//
// Opbouw: <major>.<minor>.<patch>.<build>. Het laatste nummer (build) gaat bij elke push naar
// GitHub automatisch 1 omhoog; de eerste drie alleen als Patrick daarom vraagt.

$versie = '0.4.5.298';

// Extra: de korte hash van de commit die op deze server uitgecheckt staat,
// live gelezen uit .git (geen exec/shell nodig, geen mb_*). Staat er geen
// .git (bv. losse kopie), dan blijft alleen het versienummer over.
$commit = '';
$gitMap = __DIR__ . '/.git';
$head = is_file($gitMap . '/HEAD') ? trim((string) @file_get_contents($gitMap . '/HEAD')) : '';
if (strpos($head, 'ref: ') === 0) {
    $ref = trim(substr($head, 5));
    $refBestand = $gitMap . '/' . $ref;
    $hash = is_file($refBestand) ? trim((string) @file_get_contents($refBestand)) : '';
    if ($hash === '' && is_file($gitMap . '/packed-refs')) {
        foreach ((array) @file($gitMap . '/packed-refs', FILE_IGNORE_NEW_LINES) as $regel) {
            if (substr($regel, -strlen($ref) - 1) === ' ' . $ref) {
                $hash = trim(substr($regel, 0, 40));
                break;
            }
        }
    }
    $head = $hash;
}
if (preg_match('/^[0-9a-f]{40}$/', $head) === 1) {
    $commit = substr($head, 0, 7);
}

return $commit !== '' ? $versie . ' (' . $commit . ')' : $versie;
