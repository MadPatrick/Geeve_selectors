<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../../shared/db.php';

/**
 * Maakt (en hergebruikt) een PDO-verbinding met de Exact-database "005" (zie inc/config.php), niet met de Slangkaarten-database
 * van /slkprinten - andere database, zelfde server. De verbindingslogica staat in shared/db.php.
 */
function getPdoConnection(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    return $pdo = geeveSqlServerConnect(
        appConfig()['db'],
        'Database-inloggegevens ontbreken. Zet EXACT_DB_USER en EXACT_DB_PASSWORD via Config in het hoofdmenu ' .
        'voor de SQL-login die database "005" mag lezen.'
    );
}
