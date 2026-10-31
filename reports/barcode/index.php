<?php

declare(strict_types=1);

// Deze app staat sinds 0.4.5.283 in templates/ (zelfde mapnaam). Oude adressen en bladwijzers sturen we door.
$pad = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
$query = (string) ($_SERVER['QUERY_STRING'] ?? '');
$nieuw = preg_replace('#/reports/(?=[^/]+)#', '/templates/', $pad, 1);
header('Location: ' . $nieuw . ($query !== '' ? '?' . $query : ''), true, 301);
exit;
