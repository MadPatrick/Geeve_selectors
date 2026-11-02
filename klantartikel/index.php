<?php

declare(strict_types=1);

// Verhuisd naar templates/klantartikel/ (bladwijzers blijven werken).
header('Location: ../templates/klantartikel/' . (isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== '' ? '?' . $_SERVER['QUERY_STRING'] : ''), true, 301);
exit;
