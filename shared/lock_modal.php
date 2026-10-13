<?php

declare(strict_types=1);

/**
 * Popup voor de toegangscode van beveiligde tegels (zie shared/tiles.php) met het script dat de klik op een beveiligde tegel
 * afhandelt. Vóór het includen: $csrf (token), $lockRoot (pad naar de portal-root, bijv. '' of '../') en de functie h().
 * Opent ook meteen als de pagina met ?unlock=<tegel> is geopend (doorverwijzing vanaf een beveiligde pagina).
 */
?>
<div class="lock-modal" id="lockModal" hidden role="dialog" aria-modal="true" aria-labelledby="lockTitle">
    <form class="lock-dialog" id="lockForm" data-csrf="<?= h($csrf) ?>" data-unlock-url="<?= h($lockRoot) ?>unlock.php" autocomplete="off">
        <h2 id="lockTitle">Toegangscode</h2>
        <p>Dit onderdeel is beveiligd. Vul de code in om het te openen.</p>
        <input type="password" id="lockCode" class="lock-input" inputmode="numeric" autocomplete="off" aria-label="Toegangscode" required>
        <div class="lock-error" id="lockError" hidden></div>
        <div class="lock-actions">
            <button type="button" id="lockCancel" class="lock-button lock-button--secondary">Annuleren</button>
            <button type="submit" class="lock-button">Openen</button>
        </div>
    </form>
</div>
<script src="<?= h($lockRoot) ?>assets/lock.js?v=<?= h((string) (@filemtime(__DIR__ . '/../assets/lock.js') ?: '1')) ?>"></script>
