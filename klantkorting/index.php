<?php

declare(strict_types=1);

// Verhuisd naar templates/klantkorting/ (bladwijzers blijven werken).
header('Location: ../templates/klantkorting/' . (isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== '' ? '?' . $_SERVER['QUERY_STRING'] : ''), true, 301);
exit;
