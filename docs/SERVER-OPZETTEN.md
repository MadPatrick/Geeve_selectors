# Een nieuwe Geeve-server opzetten

Handleiding om de Geeve-intranetserver (portal + Slangkaart-app + printen) opnieuw op te bouwen, bijvoorbeeld bij een
nieuwe VM. Het grootste deel doet [`tools/install-server.sh`](../tools/install-server.sh); de rest is handwerk
(wachtwoorden, data, app uitrollen). Opgesteld naar de huidige server (Debian 13, PHP 8.4, Apache, prefork;
hostnaam PCNL23-0010). **Het script is alleen met `--dry-run` en op syntax gecontroleerd, niet op een schone VM:
probeer het eerst op een testserver.**

**Nooit wachtwoorden of tokens in git of in deze handleiding.** Ze staan in `/etc/geeve/` en `/etc/slangkaart/`.

## Overzicht: wat draait waar

| Onderdeel | Waar | Opmerking |
|---|---|---|
| Portal + apps (PHP) | `/var/www/html`, Apache poort 80 | `AllowOverride All`, `mod_php`, prefork |
| Slangkaart-app (.NET) | `/var/www/html/slangkaarten`, service `slangkaart`, `127.0.0.1:5288` | Apache stuurt `/slangkaarten/` door (proxy); uitrollen doet Hans |
| CSV's (artikelnummers) | `/var/www/html/data/<app>/` | **niet in git**; de serverversie is leidend |
| Back-ups CSV's | `/var/www/html/data/backups/<app>/` | ook niet in git |
| Instellingen (inlog) | `/etc/geeve/` (root:www-data) | `portal.json`, `smbprint.cred`, `config.cred` |
| Versleutelde Config-instellingen | `/var/www/html/.settings.enc` + `.settings.key` | niet in git, niet via de browser |
| Slangkaart-instellingen en data | `/etc/slangkaart/`, `/var/lib/slangkaart/` | eigen gebruiker `slangkaart` |
| Printserver | `SVNL-VWP-PS01` (SMB) | via `smbclient` met `/etc/geeve/smbprint.cred` |
| SQL Server (Exact) | `geeve-sql-2019` (catalog 005), poort 1433 | via sqlsrv/ODBC 18 |
| Testkopie (optioneel) | `/var/www/test`, Apache poort 8080 | niet nodig voor productie |

## Pakketten

Apt (Debian 13): `apache2`, `libapache2-mod-php8.4`, `php8.4`, `php8.4-cli`, `php8.4-xml`, `git`, `curl`, `openssl`,
`smbclient` (printen; bevat `rpcclient`), `ghostscript` (PDF naar PCL XL voor A4-printers), `poppler-utils` (pdftoppm voor
labels), `chromium` (HTML naar PDF), `fonts-liberation` (zonder dit valt de print terug op het bredere DejaVu en wordt rechts
tekst afgesneden), `python3`, `python3-venv`, `cups-client`, en om sqlsrv te bouwen: `php8.4-dev`, `php-pear`,
`build-essential`, `unixodbc-dev`. Uit de Microsoft-repo (`packages.microsoft.com/debian/13/prod`): `msodbcsql18` en
`aspnetcore-runtime-10.0` (voor de Slangkaart-app). Via PECL: `sqlsrv` en `pdo_sqlsrv`. Via pip in `/opt/brother_ql`:
`brother_ql_next`.

PHP-extensies die de code nodig heeft: `sqlsrv`, `pdo_sqlsrv`, `xml`/`xsl`/`simplexml`/`dom`, `fileinfo`, `sockets`,
`openssl` (versleutelde instellingen), `sodium`, `zlib`, `iconv`, `session`, `json`. **Geen `mbstring` nodig: de code
gebruikt geen `mb_*` functies** (de server heeft het ook niet).

Apache-modules: `mpm_prefork`, `php8.4`, `rewrite`, `headers`, `deflate`, `proxy`, `proxy_http`, `proxy_wstunnel`.
`headers` is nodig voor de `.htaccess` in de root (`Cache-Control: no-cache` op css/js/afbeeldingen, zodat de browser na een
update de nieuwe versie laadt).

PHP-instellingen: standaardwaarden (memory_limit 128M, max_execution_time 30, upload 2M, display_errors Off), plus
`date.timezone = Europe/Amsterdam`. Tijdzone van de server: Europe/Amsterdam (CEST).

## Stappen

### 1. Basis
Installeer Debian 13 (minimaal), zet hostnaam, tijdzone (`timedatectl set-timezone Europe/Amsterdam`), netwerk en
DNS (de server moet `SVNL-VWP-PS01`, `geeve-sql-2019...` en de EntityService-host kunnen bereiken; poort 22 naar
GitHub is op het Geeve-netwerk geblokkeerd, zie stap 6).

### 2. Script draaien
```bash
# kopieer dit script naar de server (de repo is privé en nog niet opgehaald)
sudo bash install-server.sh --dry-run    # eerst kijken
sudo bash install-server.sh              # pakketten, Apache, PHP, deploy key
```
Het script installeert de pakketten, bouwt sqlsrv, zet de Apache-configuratie (`geeve-htaccess.conf`,
`slangkaart-app.conf`), maakt de deploy key (stap 6) en toont de publieke sleutel, maakt de mappen met de juiste rechten, de
gebruiker `slangkaart` en de systemd-service, en maakt lege configuratiebestanden aan. Bestaande bestanden worden **niet**
overschreven. Opnieuw draaien is veilig. Het script schrijft geen wachtwoorden.

### 3. Portal-code uit git
Zet eerst de deploy key uit stap 6 in GitHub, dan:
```bash
sudo bash install-server.sh --clone      # git clone naar /var/www/html (hernoemt alleen de standaard index.html van Apache)
```
Of handmatig: `git clone git@github-geeve:MadPatrick/Geeve_selectors.git /var/www/html`. Het script zet daarna de
rechten (`www-data`) en maakt `data/...` en `tmp/` aan.

### 4. Instellingen invullen (niet in git)
- `/etc/geeve/portal.json`: SQL-inlog (`EXACT_DB_USER`, `EXACT_DB_PASSWORD`, `DB_USER`, `DB_PASSWORD`,
  `EXACT_DB_TRUST_SERVER_CERT`; ook `EXACT_DB_HOST`/`PORT`/`NAME` kunnen erin). Rechten `root:www-data 0640`. Apache geeft het
  pad door via `SetEnv GEEVE_INSTELLINGEN`. De huidige SQL-login is `GeeveSlangkaart` (db_datareader op `Exactrapportage`).
- `/etc/geeve/smbprint.cred`: account voor printen (`username =`, `password =`, `domain =`), `root:www-data 0640`.
- `/etc/geeve/config.cred`: toegangscode voor Config, één regel met `password_hash()` van de code (zie `shared/access.php`).
  Neem over van de oude server of stel opnieuw in; ontbreekt het bestand, dan geldt de toegangscode van de beveiligde tegels.
- `/etc/slangkaart/appsettings.Production.json`: connection strings (Exact, OudeSlangkaarten), EntityService.
- `.settings.enc` en `.settings.key` in de portal-root (eigendom `www-data`, `0600`): neem ze mee van de oude server als
  Config-instellingen (tegels, toegangscode, sneeuw) behouden moeten blijven, anders opnieuw invullen via Config (de
  sleutel maakt PHP dan zelf aan, mits `www-data` in de portal-map mag schrijven).

### 5. Data (CSV's) terugzetten
De CSV's staan niet in git. Kopieer van de oude server (of een back-up) naar:
`data/adapters/artikelnummers_adapters.csv`, `data/configurator/artikelnummers.csv`,
`data/configurator/slangkoppelingen.csv`, `data/hoses/artikelnummers_{accessoires,rvs,staal}.csv`,
`data/printers/smb-printers.json` (die laatste bouwt de portal zelf op als hij ontbreekt). Eigenaar `www-data`, map `0775`.
Controleer met `sha256sum` dat ze gelijk zijn aan het origineel. Zonder deze bestanden zijn de Selectors leeg. Zie ook
[`UITROL-CENTRALE-DATA.md`](UITROL-CENTRALE-DATA.md).

### 6. GitHub-toegang (read-only deploy key)
Het script doet dit al (idempotent); handmatig kan het zo:
```bash
sudo ssh-keygen -t ed25519 -N '' -C 'geeve-server-selectors-deploy' -f /root/.ssh/geeve_selectors_deploy
sudo cat /root/.ssh/geeve_selectors_deploy.pub     # alleen de .pub tonen/delen
```
Voeg de publieke sleutel toe in GitHub: repo `Geeve_selectors` > Settings > Deploy keys, **zonder** write access.
Poort 22 is geblokkeerd, dus SSH via 443 in `/root/.ssh/config`:
```
Host github-geeve
    HostName ssh.github.com
    Port 443
    User git
    IdentityFile /root/.ssh/geeve_selectors_deploy
    IdentitiesOnly yes
```
Test: `ssh -T git@github-geeve`; de remote van de checkout is `git@github-geeve:MadPatrick/Geeve_selectors.git`
(`git remote set-url origin ...` als hij anders staat).

### 7. Slangkaart-app (.NET) uitrollen
De gebouwde bestanden (`Slangkaart.Web.dll` e.d.) staan niet in git. **Hans rolt deze uit** vanuit `master` (zie de README
van de repo Rubix-BNL/slangkaart-web en `deploy-debian.sh`): publish-uitvoer in `/var/www/html/slangkaarten/`, daarna:
```bash
sudo systemctl enable --now slangkaart
sudo systemctl status slangkaart
```
De service draait als gebruiker `slangkaart`, luistert op `127.0.0.1:5288` en schrijft alleen naar `/var/lib/slangkaart`.
Zonder deze app werkt de rest van de portal wel; alleen de tegel Slangkaarten en "Naar Exact" in Stauff doen dan niets.

### 8. Printers
Printers komen uit de printserver (`SVNL-VWP-PS01`) via `smbclient`; de lijst staat 30 minuten in
`data/printers/smb-printers.json`. Test: `smbclient -A /etc/geeve/smbprint.cred -L //SVNL-VWP-PS01`. Brother-labelprinters
gebruiken `/opt/brother_ql` (`brother_ql_create`), kale TSPL-labelprinters gebruiken Chromium + `pdftoppm`.

### 9. Updaten daarna
Voortaan uitrollen met `cd /var/www/html && bash tools/update.sh --dry-run` en daarna `bash tools/update.sh`.

## Controle na afloop

- `php -m | grep -E 'sqlsrv|pdo_sqlsrv|sodium|xsl'` toont de extensies.
- `apache2ctl -M | grep -E 'rewrite|headers|proxy|php'` toont de modules.
- `systemctl is-active apache2 slangkaart` geeft twee keer `active` (slangkaart pas na Hans' uitrol).
- Open `http://<server>/`: hoofdscherm met tegels en het versienummer (`version.php`).
- `/.settings.key` en `/data/hoses/artikelnummers_rvs.csv` moeten **403** geven (alleen via de knop van de app te downloaden).
- Open `/slangkaarten/`, `/hoses/` en `/adapters/`; print een proefpagina.
- `bash tools/update.sh --dry-run` meldt "Al up-to-date".
- `php tools/check-browsers.php` en `node tools/browsertest.js` (zie `CLAUDE.md`) zijn voor ontwikkelaars, niet nodig op de server.

## Valkuilen

- `AllowOverride All` voor `/var/www/html`, anders werken de `.htaccess`-beveiligingen niet en zijn dotfiles en `data/`
  bereikbaar (op 19-10-2026 is dit gebeurd en opgelost met `geeve-htaccess.conf`).
- Rechten op `data/` en `data/backups/*`: `www-data` moet er schrijven (back-ups bij opslaan in de apps). Mappen die door
  `root` zijn aangemaakt (bijv. door `adapters/onderhoud/lage-druk-toevoegen.php` als root, of een script) geven dan
  fouten: op de huidige server is `data/backups/adapters` `root:root`. Herstel met `chown -R www-data:www-data data`;
  `install-server.sh` zet ze goed.
- `git pull` weigert bij lokale wijzigingen in gevolgde bestanden (zo ook `tools/update.sh`): wijzig niets rechtstreeks op de
  server, werk via git.
- De Slangkaart-app is een ander proces: na een nieuwe publish `systemctl restart slangkaart` (Hans).
- Poort 22 naar GitHub is geblokkeerd: gebruik SSH via `ssh.github.com:443`.
- Zonder `fonts-liberation` wordt rechts tekst afgesneden bij het printen.
