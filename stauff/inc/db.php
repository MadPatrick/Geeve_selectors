<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../../shared/db.php';
require_once __DIR__ . '/../../shared/databases.php';

/**
 * Maakt (en hergebruikt) een leesverbinding met de Exact-database voor een tegel (standaard Stauff; Klant Artikelnummers en
 * Kortingstructuur gebruiken dezelfde functie met hun eigen tegel-id). Welke database (main/test) gebruikt wordt, staat in
 * Config > Tegels; de verbindingslogica staat in shared/db.php.
 */
function getPdoConnection(string $tegel = 'stauff'): PDO
{
    static $pdo = [];

    return $pdo[$tegel] ??= geeveDbVerbinding($tegel, 'exact');
}
