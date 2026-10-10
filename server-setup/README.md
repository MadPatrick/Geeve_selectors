# Inloggen op Config met een serveraccount

De Config-pagina (`update.php`: database-instellingen en update) vraagt een gebruikersnaam en
wachtwoord van een account op de server dat **lid is van de groep `sudo`**. De webapp kent zelf
geen wachtwoorden: een klein hulpscript controleert ze als root.

## Installeren (eenmalig, op de Debian-server)
```
cd /pad/naar/portal/server-setup
sudo sh install.sh
```
Dit zet `geeve-auth` in `/usr/local/sbin` (alleen root en de groep root) en voegt een sudoers-regel toe
waarmee `www-data` **alleen dat ene script, zonder argumenten** als root mag starten. Draait PHP/Apache
onder een andere gebruiker dan `www-data`, pas die naam dan aan in `geeve-auth.sudoers`.
Vereist: `php-cli`.

## Werking
- Zolang `/usr/local/sbin/geeve-auth` niet bestaat, is de pagina **open** en toont ze een waarschuwing
  (zo kun je jezelf niet buitensluiten).
- Na installatie is inloggen verplicht. Een sessie verloopt na 30 minuten inactiviteit.
- Na 8 mislukte pogingen binnen 5 minuten wordt inloggen tijdelijk geblokkeerd; elke mislukte poging
  duurt bovendien 1 seconde.
- Wachtwoord en gebruikersnaam gaan via stdin naar het script (niet als argument) en worden nergens opgeslagen.
- Geblokkeerde accounts (`!`/`*` in `/etc/shadow`) en accounts zonder `sudo`-lidmaatschap worden geweigerd.

## Testen
```
printf 'jouwgebruiker\nhetwachtwoord' | sudo -u www-data sudo -n /usr/local/sbin/geeve-auth; echo $?
```
`0` = geslaagd, `1` = geweigerd.

## Verwijderen
```
sudo rm /usr/local/sbin/geeve-auth /etc/sudoers.d/geeve-auth
```
De Config-pagina is daarna weer open voor iedereen.
