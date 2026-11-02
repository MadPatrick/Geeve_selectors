<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../../shared/db.php';
require_once __DIR__ . '/../../shared/databases.php';

/** Verbinding met de "Slangkaarten"-database (order-/slangkaartdata); de omgeving kiest Config > Tegels. */
function getPdoConnection(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    return $pdo = geeveDbVerbinding('slangkaarten', 'slangkaarten');
}

/**
 * Verbinding met de Exact-database "005" (zelfde server, ander doel: de artikellocatie opzoeken voor de picklijst, zie
 * findArtikelLocatie()).
 */
function getExactPdoConnection(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    // De Exact-gegevens (verzendwijze, locatie/voorraad op de picklijst) zijn aanvullend: een trage query mag het printen niet
    // laten hangen (8 seconden), dan blijft dat veld gewoon leeg. Een mislukte verbinding onthoudt shared/db.php, ook voor de
    // volgende slangkaarten in dezelfde aanvraag.
    return $pdo = geeveDbVerbinding('slangkaarten', 'exact', 'lezen', 8);
}
