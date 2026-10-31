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
    // Na csrfReleaseSession() opnieuw openen kan alleen zolang er nog geen uitvoer is verstuurd.
    if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
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

/**
 * Geeft het sessiebestand vrij (session_write_close). PHP sluit de sessie anders pas aan het eind van het script af en
 * blokkeert zolang elke andere aanvraag van dezelfde browser: een trage API-aanroep (Exact/SQL, soms minuten) liet een
 * klik op een tegel dan wachten tot die klaar was. Aanroepen zodra de sessie niet meer nodig is; csrfStartSession()
 * opent hem zo nodig weer.
 */
function csrfReleaseSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
}
