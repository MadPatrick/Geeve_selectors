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

return '0.4.5.220';
