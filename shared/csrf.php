<?php

declare(strict_types=1);

/**
 * Eenvoudige CSRF-bescherming voor formulieren die bestanden wijzigen (o.a. CSV-uploads).
 * Een formulier toont csrfToken() in een verborgen veld "csrf"; het ontvangende script
 * controleert dat met csrfValid(). Dit voorkomt dat een andere website zonder medeweten van
 * de gebruiker een formulier naar deze app laat sturen.
 */
function csrfStartSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Lax']);
    }
}

function csrfToken(): string
{
    csrfStartSession();
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf_token'];
}

function csrfValid(mixed $submitted): bool
{
    csrfStartSession();
    return is_string($submitted)
        && isset($_SESSION['csrf_token'])
        && is_string($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $submitted);
}
