<?php

declare(strict_types=1);

/**
 * Instellingen van /stauff (ook gebruikt door de rapporten en templates die dezelfde Exact-verbinding delen).
 *
 * Laadt de instellingen uit Config / /etc/geeve/portal.json als omgevingsvariabelen (echte omgevingsvariabelen op de webserver
 * hebben voorrang). De databasegegevens zelf (server, naam, login, test of main) komen uit shared/databases.php; hier staat
 * geen databaseconfig of standaardwaarde meer. Er worden geen .env-bestanden gelezen.
 */
if (is_file(__DIR__ . '/../../shared/secure_settings.php')) {
    require_once __DIR__ . '/../../shared/secure_settings.php';
    loadSecureSettings();
}
