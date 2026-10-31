<?php

declare(strict_types=1);

/**
 * Foutmeldingen voor de gebruiker. Meldingen die we zelf in de code schrijven (RuntimeException e.d.) zijn bedoeld voor het
 * scherm en blijven staan. Een databasefout (PDOException, ook als die in een andere uitzondering is verpakt) bevat
 * servernamen, inlognamen en SQL-tekst: die gaat naar het PHP-serverlog en de gebruiker krijgt alleen de SQLSTATE-code.
 */
function geeveFoutTekst(Throwable $fout): string
{
    // DatabaseConfigException (shared/db.php) bevat al een opgeschoonde melding; de PDOException erin is alleen voor het log.
    if ($fout instanceof DatabaseConfigException) {
        return $fout->getMessage();
    }
    $oorzaak = $fout;
    while (!($oorzaak instanceof PDOException) && $oorzaak->getPrevious() !== null) {
        $oorzaak = $oorzaak->getPrevious();
    }
    if (!($oorzaak instanceof PDOException)) {
        return $fout->getMessage();
    }

    error_log('[geeve] ' . get_class($fout) . ': ' . $fout->getMessage());
    $code = preg_match('/^\w{5}$/', (string) $oorzaak->getCode()) === 1 ? ' (SQLSTATE ' . $oorzaak->getCode() . ')' : '';
    return 'De database gaf een foutmelding' . $code . '. De details staan in het PHP-serverlog van de server.';
}
