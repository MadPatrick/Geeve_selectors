<?php

declare(strict_types=1);

// Eén gedeeld versienummer voor het hoofdscherm en alle subapps (hoses,
// adapters, hose-configurator, stauff). Elke pagina laadt dit bestand i.p.v.
// een eigen losse APP_VERSION-constante te declareren, zodat een versie-
// ophoging nog maar op één plek hoeft te gebeuren. Bestaat dit bestand niet
// (bv. een subapp los buiten deze portal-map gedeployed), dan valt elke
// pagina terug op een eigen hardcoded waarde - zie de APP_VERSION-regel
// verderop in dat bestand.

return '0.3.0';
